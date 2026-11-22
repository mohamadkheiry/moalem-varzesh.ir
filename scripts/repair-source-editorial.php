<?php
defined('WP_CLI') && WP_CLI || exit;

$cache = isset($args[0]) ? realpath($args[0]) : false;
if (!$cache || !is_file($cache . '/manifest.json')) {
    WP_CLI::error('Fetch the source article media before repairing the seven imported articles.');
}
$manifest = json_decode(file_get_contents($cache . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$source = json_decode(file_get_contents(dirname(__DIR__) . '/catalog/editorial.json'), true, 512, JSON_THROW_ON_ERROR);
$examples = [
    12678 => [10368, 11957, 12024], 12676 => [11142, 12297], 12680 => [11464, 11889, 10300],
    12633 => [12297, 12276, 12554], 12628 => [10712, 11115, 11209], 12623 => [12208, 11889, 11704],
    12377 => [10590, 10619, 12080],
];
global $wpdb;
$products = [];
foreach ($wpdb->get_results("SELECT p.ID, m.meta_value FROM $wpdb->posts p INNER JOIN $wpdb->postmeta m ON p.ID=m.post_id
    WHERE p.post_type='product' AND p.post_status='publish' AND m.meta_key='_mv_source_id'") as $row) {
    $products[(int) $row->meta_value] = (int) $row->ID;
}

function mv_legacy_path(string $url): string {
    return rtrim(rawurldecode((string) wp_parse_url($url, PHP_URL_PATH)), '/') . '/';
}

$routes = [];
foreach (get_posts(['post_type' => ['page', 'post', 'product'], 'post_status' => 'publish', 'numberposts' => -1]) as $post) {
    $url = get_permalink($post);
    $routes[mv_legacy_path($url)] = wp_make_link_relative($url);
}
foreach (get_terms(['taxonomy' => ['category', 'product_cat'], 'hide_empty' => false]) as $term) {
    $target = ($term->taxonomy === 'product_cat') ? (int) get_term_meta($term->term_id, '_mv_category_redirect', true) : 0;
    $url = get_term_link($term);
    $desired = $target ? get_term_link($target, 'product_cat') : $url;
    if (!is_wp_error($url) && !is_wp_error($desired)) {
        $routes[mv_legacy_path($url)] = wp_make_link_relative($desired);
    }
}

$uploads = wp_upload_dir();
$storage = $uploads['basedir'] . '/mv-editorial';
if ($uploads['error'] || !wp_mkdir_p($storage)) {
    WP_CLI::error('Could not prepare local article uploads.');
}
$media = [];
foreach ($manifest as $url => $entry) {
    if (!$entry) {
        continue;
    }
    $name = $entry['file'];
    if (!preg_match('/^[0-9a-f]{24}\.webp$/D', $name) || !is_file($cache . '/' . $name)) {
        WP_CLI::error('Missing cached article photo.');
    }
    $file = "$storage/$name";
    $attachment = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_mv_editorial_media_source' AND meta_value = %s LIMIT 1", $url));
    if (!is_file($file) && !copy($cache . '/' . $name, $file)) {
        WP_CLI::error('Could not store an editorial image.');
    }
    if (!$attachment) {
        $attachment = wp_insert_attachment([
            'post_mime_type' => 'image/webp', 'post_title' => 'تصویر راهنمای معلم ورزش', 'post_status' => 'inherit',
            'meta_input' => ['_mv_editorial_media_source' => $url],
        ], $file, 0, true);
        if (is_wp_error($attachment)) {
            WP_CLI::error($attachment->get_error_message());
        }
    }
    wp_update_attachment_metadata($attachment, [
        'file' => 'mv-editorial/' . $name, 'width' => (int) $entry['width'], 'height' => (int) $entry['height'],
        'filesize' => filesize($file), 'sizes' => [],
    ]);
    $media[$url] = (int) $attachment;
}

$removed_links = 0;
$updated_links = 0;
$replaced_photos = 0;
$removed_photos = 0;
foreach ($source['posts'] as $row) {
    $post = get_page_by_path($row['slug'], OBJECT, 'post');
    if (!$post) {
        WP_CLI::error('Source article has not been imported: ' . $row['id']);
    }
    $content = $post->post_content;
    $example_ids = array_values(array_filter(array_map(static fn(int $id): int => $products[$id] ?? 0, $examples[$row['id']])));
    $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
    $content = preg_replace_callback('~<a\b[^>]*>(.*?)</a>~is',
        static function (array $m) use ($routes, $site_host, &$updated_links, &$removed_links): string {
            $tag = new WP_HTML_Tag_Processor($m[0]);
            $tag->next_tag('A');
            $url = $tag->get_attribute('href');
            if (!is_string($url) || $url === '') {
                return $m[0];
            }
            $host = wp_parse_url($url, PHP_URL_HOST);
            if (str_starts_with($url, '#') || (!$host && !str_starts_with($url, '/'))
                || ($host && !in_array(strtolower($host), [$site_host, 'moalem-varzesh.ir', 'www.moalem-varzesh.ir'], true))) {
                return $m[0];
            }
            $target = $routes[mv_legacy_path($url)] ?? '';
            if (!$target) {
                ++$removed_links;
                return $m[1];
            }
            if ($target === $url) {
                return $m[0];
            }
            ++$updated_links;
            $tag->set_attribute('href', $target);
            return $tag->get_updated_html();
        }, $content);
    $cover = 0;
    $content = preg_replace_callback('~<img\b[^>]*?\bsrc=(["\x27])([^"\x27]+)\1[^>]*>~i',
        static function (array $m) use ($media, $manifest, &$cover, &$replaced_photos, &$removed_photos): string {
            $url = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (!array_key_exists($url, $manifest)) {
                return $m[0];
            }
            if (!isset($media[$url])) {
                ++$removed_photos;
                return '';
            }
            $image = $media[$url];
            if (!$cover && str_contains(basename($url), 'hero')) {
                $cover = $image;
                ++$replaced_photos;
                return '';
            }
            $options = ['loading' => 'lazy', 'decoding' => 'async'];
            if (preg_match('~\balt=(["\x27])(.*?)\1~i', $m[0], $alt)) {
                $options['alt'] = html_entity_decode($alt[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            ++$replaced_photos;
            return wp_get_attachment_image($image, 'full', false, $options);
        }, $content);
    $content = preg_replace('~<figure\b[^>]*>\s*</figure>~i', '', $content);
    if ($example_ids && !str_contains($content, 'class="guide-product-links"')) {
        $content .= '<aside class="guide-product-links"><h2>کالاهای مرتبط</h2><ul>';
        foreach ($example_ids as $id) {
            $content .= sprintf('<li><a href="%s">%s</a></li>', esc_url(wp_make_link_relative(get_permalink($id))), esc_html(get_the_title($id)));
        }
        $content .= '</ul></aside>';
    }
    if ($content !== $post->post_content) {
        $saved = wp_update_post(wp_slash(['ID' => $post->ID, 'post_content' => $content]), true);
        if (is_wp_error($saved)) {
            WP_CLI::error($saved->get_error_message());
        }
    }
    if (!$cover && !has_post_thumbnail($post->ID) && $example_ids) {
        $cover = wc_get_product($example_ids[0])->get_image_id();
    }
    if (!get_post_meta(get_post_thumbnail_id($post->ID), '_mv_guide_visual_key', true)) {
        if ($cover) {
            set_post_thumbnail($post->ID, $cover);
        }
        update_post_meta($post->ID, '_mv_editorial_image_alt', 'تصویر مرتبط با ' . wp_strip_all_tags($post->post_title));
    }
    update_post_meta($post->ID, '_mv_guide_product_ids', $example_ids);
    update_post_meta($post->ID, '_mv_seo_title', wp_strip_all_tags($post->post_title));
    $original_excerpt = trim(wp_strip_all_tags($row['excerpt']));
    if ($original_excerpt !== '') {
        update_post_meta($post->ID, '_mv_seo_description', $original_excerpt);
    }
}
WP_CLI::success(sprintf('Repaired 7 source guides: %d links normalized, %d unavailable links unwrapped, %d local image replacements, %d unavailable images removed.',
    $updated_links, $removed_links, $replaced_photos, $removed_photos));
