<?php
defined('WP_CLI') && WP_CLI || exit;
$local_indexing = get_option('blog_public');
$server = wp_sitemaps_get_server();
$server->register_sitemaps();
$posts = $server->registry->get_provider('posts');
$taxonomies = $server->registry->get_provider('taxonomies');
if (!$posts || !$taxonomies || $server->registry->get_provider('users')) {
    WP_CLI::error('Unexpected sitemap providers.');
}

$pages = $posts->get_url_list(1, 'page');
$shop = wc_get_page_permalink('shop');
$page_urls = array_column($pages, 'loc');
if (!in_array($shop, $page_urls, true)) {
    WP_CLI::error('The shop page is missing from the sitemap.');
}
foreach (mv_unlisted_page_ids() as $id) {
    if (in_array(get_permalink($id), $page_urls, true)) {
        WP_CLI::error('An unlisted page is in the sitemap.');
    }
}

$products = $posts->get_url_list(1, 'product');
$articles = $posts->get_url_list(1, 'post');
if (count($products) !== 116 || count($articles) !== 107) {
    WP_CLI::error('The imported catalog or editorial library is missing from the sitemap.');
}
foreach (array_merge($pages, $products, $articles) as $entry) {
    if (empty($entry['lastmod']) || !strtotime($entry['lastmod'])) {
        WP_CLI::error('A sitemap entry is missing its WordPress modification date.');
    }
}
$xml = $server->renderer->get_sitemap_xml($products);
if (!simplexml_load_string($xml)) {
    WP_CLI::error('The product sitemap is not valid XML.');
}
$category_urls = array_column($taxonomies->get_url_list(1, 'product_cat'), 'loc');
$redirected = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'meta_key' => '_mv_category_redirect']);
foreach ($redirected as $term) {
    if (in_array(get_term_link($term), $category_urls, true)) {
        WP_CLI::error('A redirected category is in the sitemap.');
    }
}
if (get_option('blog_public') !== $local_indexing) {
    WP_CLI::error('The test changed the site indexing setting.');
}
WP_CLI::success(sprintf('Valid sitemap data for %d products, %d articles, %d pages, and %d product categories.',
    count($products), count($articles), count($pages), count($category_urls)));
