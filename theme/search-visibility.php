<?php
defined('ABSPATH') || exit;

function mv_unlisted_page_ids(): array {
    static $ids = null;
    if ($ids === null) {
        $ids = function_exists('wc_get_page_id')
            ? array_filter(array_map('wc_get_page_id', ['cart', 'checkout', 'myaccount']), static fn(int $id): bool => $id > 0)
            : [];
        $sample = get_page_by_path('sample-page');
        if ($sample && str_contains($sample->post_content, 'This is an example page')) {
            $ids[] = $sample->ID;
        }
    }
    return $ids;
}

add_filter('wp_robots', static function (array $robots): array {
    $commerce_private = function_exists('is_cart') && (is_cart() || is_checkout() || is_account_page());
    $duplicate_query = false;
    if (function_exists('is_shop') && (is_shop() || is_product_taxonomy())) {
        foreach (array_keys($_GET) as $parameter) {
            if (preg_match('/^(?:orderby$|min_price$|max_price$|rating_filter$|filter_|query_type_)/', (string) $parameter)) {
                $duplicate_query = true;
                break;
            }
        }
    }
    if ($commerce_private || $duplicate_query || is_author() || is_search()
        || (is_page() && in_array(get_queried_object_id(), mv_unlisted_page_ids(), true))) {
        unset($robots['index']);
        $robots['noindex'] = true;
    }
    return $robots;
});

add_filter('wp_sitemaps_add_provider', static fn($provider, string $name) => $name === 'users' ? false : $provider, 10, 2);

add_filter('wp_sitemaps_posts_query_args', static function (array $query, string $type): array {
    if ($type === 'page') {
        $query['post__not_in'] = array_merge($query['post__not_in'] ?? [], mv_unlisted_page_ids());
    } elseif ($type === 'product' && taxonomy_exists('product_visibility')) {
        $query['tax_query'][] = [
            'taxonomy' => 'product_visibility', 'field' => 'slug', 'operator' => 'NOT IN',
            'terms' => ['exclude-from-catalog', 'exclude-from-search'],
        ];
    }
    return $query;
}, 10, 2);

add_filter('wp_sitemaps_taxonomies_query_args', static function (array $query, string $taxonomy): array {
    if ($taxonomy === 'product_cat') {
        $query['hide_empty'] = true;
        $query['meta_query'][] = ['key' => '_mv_category_redirect', 'compare' => 'NOT EXISTS'];
    }
    return $query;
}, 10, 2);

add_filter('wp_sitemaps_posts_entry', static function (array $entry, WP_Post $post): array {
    $modified = get_post_modified_time(DATE_W3C, true, $post);
    if (empty($entry['lastmod']) && $modified) {
        $entry['lastmod'] = $modified;
    }
    return $entry;
}, 10, 2);

function mv_share_image(): string {
    $id = get_queried_object_id();
    if (is_front_page()) {
        return get_theme_file_uri('assets/support/about-1200.webp');
    }
    $support = is_home() ? 'guides' : (is_page() ? mv_support_page_key($id) : '');
    if ($support) {
        $image = (int) mv_support_settings()['pages'][$support]['image'];
        return wp_get_attachment_image_url($image, 'large')
            ?: get_the_post_thumbnail_url($id, 'large')
            ?: get_theme_file_uri('assets/support/' . mv_support_pages()[$support][4] . '-1200.webp');
    }
    if (is_singular()) {
        return get_the_post_thumbnail_url($id, 'large') ?: '';
    }
    if (is_tax('product_cat')) {
        return wp_get_attachment_image_url((int) get_term_meta($id, 'thumbnail_id', true), 'large') ?: '';
    }
    return '';
}

add_action('wp_head', static function (): void {
    if (!is_front_page() || mv_external_seo()) {
        return;
    }
    $website = [
        '@context' => 'https://schema.org', '@type' => 'WebSite',
        'name' => get_bloginfo('name'), 'url' => home_url('/'), 'inLanguage' => get_bloginfo('language'),
    ];
    echo '<script type="application/ld+json">' . wp_json_encode($website, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>' . "\n";
}, 6);
