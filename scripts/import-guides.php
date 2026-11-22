<?php
defined('WP_CLI') && WP_CLI || exit;

$catalog = dirname(__DIR__) . '/catalog';
$topics = json_decode(file_get_contents($catalog . '/guide-topics.json'), true, 512, JSON_THROW_ON_ERROR);
$image_overrides = json_decode(file_get_contents($catalog . '/cutout-overrides.json'), true, 512, JSON_THROW_ON_ERROR);
$guides = [];
$slugs = [];
foreach (glob($catalog . '/guides/*.txt') as $file) {
    $records = preg_split('/(?=^@)/mu', trim(file_get_contents($file)), -1, PREG_SPLIT_NO_EMPTY);
    foreach ($records as $record) {
        $rows = preg_split('/\R/u', trim($record));
        $header = array_shift($rows);
        $title = trim(array_shift($rows) ?? '');
        $excerpt = trim(array_shift($rows) ?? '');
        $fields = explode('|', substr($header, 1));
        if (count($fields) !== 3) {
            WP_CLI::error('Invalid guide header in ' . basename($file));
        }
        [$slug, $topic, $product_ids] = $fields;
        $source_ids = array_values(array_unique(array_map('intval', explode(',', $product_ids))));
        $body = trim(implode("\n", $rows));
        $words = preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\x{200C}]*/u', $body);
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) || isset($slugs[$slug]) || !isset($topics[$topic])
            || !$title || !$excerpt || in_array(0, $source_ids, true) || count($source_ids) < 2
            || $words < 200 || substr_count($body, '## ') < 3) {
            WP_CLI::error('Incomplete or duplicate guide ' . $slug);
        }
        $slugs[$slug] = true;
        $guides[] = compact('slug', 'topic', 'title', 'excerpt', 'source_ids', 'body');
    }
}
if (count($guides) < 100 && !in_array('preview', $args ?? [], true)) {
    WP_CLI::error('Complete the 100-guide collection before publishing.');
}

global $wpdb;
$products = [];
foreach ($wpdb->get_results("SELECT p.ID, m.meta_value FROM $wpdb->posts p INNER JOIN $wpdb->postmeta m ON p.ID=m.post_id
    WHERE p.post_type='product' AND p.post_status='publish' AND m.meta_key='_mv_source_id'") as $row) {
    $products[(int) $row->meta_value] = (int) $row->ID;
}
$used_images = [];
foreach ($guides as &$guide) {
    foreach ($guide['source_ids'] as $source) {
        if (!isset($products[$source])) {
            WP_CLI::error('Guide references an unavailable product ' . $source . ' in ' . $guide['slug']);
        }
    }
    $guide['products'] = array_map(static fn(int $source): int => $products[$source], $guide['source_ids']);
    $candidates = [];
    $image_titles = [];
    foreach ($guide['products'] as $product) {
        $item = wc_get_product($product);
        foreach (array_merge([$item->get_image_id()], $item->get_gallery_image_ids()) as $position => $image) {
            $file = $image ? get_attached_file($image) : false;
            if ($file && wp_attachment_is_image($image) && is_file($file)) {
                $cutout = pathinfo($file, PATHINFO_FILENAME);
                $detail = ($image_overrides[$cutout] ?? '') === 'preserve-detail';
                $candidates[$image] = 10 * ($used_images[$image] ?? 0) + ($detail ? 40 : 0) + ($position > 0 ? 1 : 0);
                $image_titles[$image] = $item->get_name();
            }
        }
    }
    if (!$candidates) {
        WP_CLI::error('No local editorial photo available for ' . $guide['slug']);
    }
    asort($candidates, SORT_NUMERIC);
    $guide['image'] = array_key_first($candidates);
    $guide['image_alt'] = 'تصویر ' . $image_titles[$guide['image']];
    $used_images[$guide['image']] = ($used_images[$guide['image']] ?? 0) + 1;
    $existing = get_page_by_path($guide['slug'], OBJECT, 'post');
    if ($existing && get_post_meta($existing->ID, '_mv_guide_key', true) !== $guide['slug']) {
        WP_CLI::error('An existing editorial article owns the URL ' . $guide['slug']);
    }
    $guide['post_id'] = $existing ? $existing->ID : 0;
}
unset($guide);
if (in_array('verify', $args ?? [], true)) {
    WP_CLI::success(sprintf('Validated %d distinct editorial records and %d local images.', count($guides), count($used_images)));
    return;
}
$category_ids = [];
foreach ($topics as $key => [$name, $description]) {
    $slug = 'guide-' . $key;
    $term = get_term_by('slug', $slug, 'category');
    if (!$term) {
        $created = wp_insert_term($name, 'category', ['slug' => $slug, 'description' => $description]);
        if (is_wp_error($created)) {
            WP_CLI::error($created->get_error_message());
        }
        $category_ids[$key] = (int) $created['term_id'];
    } else {
        $category_ids[$key] = (int) $term->term_id;
    }
}

function mv_import_guide_body(string $body, array $products): string {
    $html = [];
    $section = 0;
    $list = false;
    foreach (preg_split('/\R/u', $body) as $line) {
        $line = trim($line);
        if ($list && !str_starts_with($line, '- ')) {
            $html[] = '</ul>';
            $list = false;
        }
        if ($line === '') {
            continue;
        }
        if (str_starts_with($line, '## ')) {
            $html[] = '<h2 id="step-' . ++$section . '">' . esc_html(substr($line, 3)) . '</h2>';
        } elseif (str_starts_with($line, '- ')) {
            if (!$list) {
                $html[] = '<ul>';
                $list = true;
            }
            $html[] = '<li>' . esc_html(substr($line, 2)) . '</li>';
        } elseif (str_starts_with($line, '?') && str_contains($line, '|')) {
            [$question, $answer] = explode('|', substr($line, 1), 2);
            $html[] = '<h2 id="step-' . ++$section . '">' . esc_html($question) . '</h2>';
            $html[] = '<p>' . esc_html($answer) . '</p>';
        } else {
            $html[] = '<p>' . esc_html($line) . '</p>';
        }
    }
    if ($list) {
        $html[] = '</ul>';
    }
    $html[] = '<aside class="guide-product-links"><h2 id="guide-products">کالاهای مرتبط</h2><ul>';
    foreach ($products as $id) {
        $product = wc_get_product($id);
        $summary = wp_trim_words(trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($product->get_short_description()))), 38, '…');
        $html[] = sprintf('<li><a href="%s">%s</a>%s</li>', esc_url(wp_make_link_relative(get_permalink($id))),
            esc_html($product->get_name()), $summary ? '<p>' . esc_html($summary) . '</p>' : '');
    }
    $html[] = '</ul></aside>';
    return implode("\n", $html);
}

$links = [];
$topic_posts = [];
foreach ($guides as $guide) {
    $content = mv_import_guide_body($guide['body'], $guide['products']);
    $fields = [
        'ID' => $guide['post_id'], 'post_type' => 'post', 'post_status' => 'publish',
        'post_name' => $guide['slug'], 'post_title' => $guide['title'], 'post_content' => $content,
        'post_excerpt' => $guide['excerpt'], 'post_category' => [$category_ids[$guide['topic']]],
        'comment_status' => 'closed', 'ping_status' => 'closed',
    ];
    $old = $guide['post_id'] ? get_post($guide['post_id']) : null;
    $changed = !$old || $old->post_title !== $fields['post_title'] || $old->post_content !== $content
        || $old->post_excerpt !== $fields['post_excerpt'] || $old->post_status !== 'publish'
        || wp_get_post_categories($old->ID) !== $fields['post_category'];
    $post_id = $changed ? wp_insert_post(wp_slash($fields), true) : $old->ID;
    if (is_wp_error($post_id)) {
        WP_CLI::error($post_id->get_error_message());
    }
    update_post_meta($post_id, '_mv_guide_key', $guide['slug']);
    update_post_meta($post_id, '_mv_seo_title', $guide['title']);
    update_post_meta($post_id, '_mv_seo_description', $guide['excerpt']);
    update_post_meta($post_id, '_mv_guide_product_ids', $guide['products']);
    if (!get_post_meta(get_post_thumbnail_id($post_id), '_mv_guide_visual_key', true)) {
        update_post_meta($post_id, '_mv_editorial_image_alt', $guide['image_alt']);
        set_post_thumbnail($post_id, $guide['image']);
    }
    $topic_posts[$guide['topic']][] = $post_id;
    foreach ($guide['products'] as $product) {
        $links[$product][] = $post_id;
    }
}

foreach ($products as $product) {
    if (isset($links[$product])) {
        $related = $links[$product];
    } else {
        $topic = get_post_meta($product, '_mv_seo_topic', true);
        $related = $topic_posts[$topic] ?? $topic_posts['buying'] ?? [];
    }
    update_post_meta($product, '_mv_product_guides', array_slice($related, 0, 3));
}
WP_CLI::success(sprintf('Published %d distinct guides in %d topics with %d distinct local photos.', count($guides), count($category_ids), count($used_images)));
