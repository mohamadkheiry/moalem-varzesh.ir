<?php
defined('WP_CLI') && WP_CLI || exit;

$posts = get_posts(['post_type' => 'post', 'post_status' => 'publish', 'numberposts' => -1]);
$products = get_posts(['post_type' => 'product', 'post_status' => 'publish', 'numberposts' => -1]);
$errors = [];
$entry = [];
$article_titles = [];
$product_titles = [];
$product_descriptions = [];
$photo_ids = [];
$topic_counts = [];
$words = [];
$guides = 0;
$legacy_links = [];
$origin = wp_parse_url(home_url(), PHP_URL_HOST);
foreach ($posts as $article) {
    $id = $article->ID;
    $url = get_permalink($id);
    $entry[] = ['kind' => 'post', 'url' => $url];
    $title = get_post_meta($id, '_mv_seo_title', true);
    $description = get_post_meta($id, '_mv_seo_description', true);
    $featured = (int) get_post_thumbnail_id($id);
    if (!$title || !$description || isset($article_titles[$title]) || !$featured || !is_file(get_attached_file($featured))) {
        $errors[] = 'Editorial metadata, duplicate title or missing local hero ' . $id;
    }
    $article_titles[$title] = true;
    $related = (array) get_post_meta($id, '_mv_guide_product_ids', true);
    foreach ($related as $product) {
        if (get_post_type($product) !== 'product' || get_post_status($product) !== 'publish') {
            $errors[] = 'Invalid referenced product for ' . $id;
        }
        if (!str_contains($article->post_content, wp_make_link_relative(get_permalink($product)))) {
            $errors[] = 'Product not linked in ' . $id;
        }
    }
    $guide = get_post_meta($id, '_mv_guide_key', true);
    if ($guide) {
        ++$guides;
        $photo_ids[$featured] = true;
        $categories = get_the_category($id);
        $topic = $categories[0]->slug ?? '';
        $topic_counts[$topic] = ($topic_counts[$topic] ?? 0) + 1;
        $plain = html_entity_decode(wp_strip_all_tags($article->post_content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $count = preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\x{200C}]*/u', $plain);
        $words[] = $count;
        if ($count < 250 || count($related) < 2 || count($categories) !== 1 || !str_starts_with($topic, 'guide-')
            || substr_count($article->post_content, '<h2 id=') < 4) {
            $errors[] = 'Insufficient guide structure ' . $guide . ' (' . $count . ' words)';
        }
    } else {
        preg_match_all('~\b(?:href|src)=["\x27]([^"\x27]+)~i', $article->post_content, $matches);
        foreach ($matches[1] as $raw) {
            $link = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $host = wp_parse_url($link, PHP_URL_HOST);
            if (in_array($host, ['moalem-varzesh.ir', 'www.moalem-varzesh.ir'], true)) {
                $errors[] = 'Old domain dependency in article ' . $id;
            } elseif ($host === $origin || str_starts_with($link, '/')) {
                $legacy_links[wp_make_link_relative($link)] = home_url(wp_make_link_relative($link));
            }
        }
    }
}
foreach ($products as $product) {
    $id = $product->ID;
    $entry[] = ['kind' => 'product', 'url' => get_permalink($id)];
    $title = get_post_meta($id, '_mv_seo_title', true);
    $description = get_post_meta($id, '_mv_seo_description', true);
    if (!$title || !$description || isset($product_titles[$title]) || isset($product_descriptions[$description])
        || mb_strlen($description) > 160 || !trim(wp_strip_all_tags($product->post_excerpt))
        || !get_post_meta($id, '_mv_product_guides', true)) {
        $errors[] = 'Incomplete product SEO or editorial relationship ' . $id;
    }
    if (str_contains($product->post_content, '[MESSI]')) {
        $errors[] = 'An editing placeholder is visible in product ' . $id;
    }
    $product_titles[$title] = true;
    $product_descriptions[$description] = true;
}

if ($guides !== 100 || count($posts) !== 107 || count($topic_counts) !== 10 || array_sum($topic_counts) !== 100
    || min($topic_counts) !== 10 || count($products) !== 116) {
    $errors[] = 'Unexpected guide, topic or product coverage.';
}
if ($errors) {
    WP_CLI::error(implode("\n", array_slice($errors, 0, 26)));
}
WP_CLI::success(sprintf('100 new guides, 7 repaired guides, 10 topics, %d distinct new guide images, %d–%d words per new guide, %d optimized products.',
    count($photo_ids), min($words), max($words), count($products)));
if (!empty($args[0])) {
    file_put_contents($args[0], wp_json_encode(['pages' => $entry, 'legacy_links' => array_values($legacy_links)], JSON_UNESCAPED_UNICODE));
}
