<?php
defined('ABSPATH') || exit;

function mv_guides_url(): string {
    $page = (int) get_option('page_for_posts');
    return $page ? get_permalink($page) : home_url('/blog/');
}

function mv_guide_image(int $id, string $size, string $sizes, bool $primary = false): void {
    $alt = get_post_meta($id, '_mv_editorial_image_alt', true);
    $options = ['sizes' => $sizes, 'loading' => $primary ? 'eager' : 'lazy', 'decoding' => 'async'];
    if ($alt !== '') {
        $options['alt'] = $alt;
    }
    if ($primary) {
        $options['fetchpriority'] = 'high';
    }
    echo get_the_post_thumbnail($id, $size, $options);
}

add_action('pre_get_posts', static function (WP_Query $query): void {
    if (!is_admin() && $query->is_main_query() && ($query->is_home() || $query->is_category())) {
        $query->set('posts_per_page', 12);
        $query->set('orderby', ['date' => 'DESC', 'ID' => 'DESC']);
    }
});

add_action('template_redirect', static function (): void {
    if (!is_tax('product_cat')) {
        return;
    }
    $target = (int) get_term_meta(get_queried_object_id(), '_mv_category_redirect', true);
    $url = $target ? get_term_link($target, 'product_cat') : false;
    if ($url && !is_wp_error($url)) {
        wp_safe_redirect($url, 301);
        exit;
    }
});

add_action('wp_head', static function (): void {
    if (!is_singular('post') || mv_external_seo()) {
        return;
    }
    $id = get_queried_object_id();
    $url = get_permalink($id);
    $article = [
        '@context' => 'https://schema.org', '@type' => 'BlogPosting',
        'mainEntityOfPage' => $url, 'headline' => wp_strip_all_tags(get_the_title($id)),
        'description' => wp_strip_all_tags(mv_meta_description()),
        'datePublished' => get_post_time(DATE_W3C, true, $id), 'dateModified' => get_post_modified_time(DATE_W3C, true, $id),
        'author' => ['@type' => 'Organization', 'name' => 'تحریریهٔ معلم ورزش', 'url' => mv_guides_url()],
        'publisher' => ['@type' => 'Organization', 'name' => get_bloginfo('name'), 'url' => home_url('/')],
        'inLanguage' => 'fa-IR',
    ];
    if ($image = get_the_post_thumbnail_url($id, 'full')) {
        $article['image'] = [$image];
    }
    echo '<script type="application/ld+json">' . wp_json_encode($article, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . "</script>\n";
}, 6);

function mv_schema_iran_currency(array $data): array {
    if (($data['priceCurrency'] ?? '') === 'IRT') {
        $data['priceCurrency'] = 'IRR';
        foreach (['price', 'lowPrice', 'highPrice', 'minPrice', 'maxPrice'] as $field) {
            if (isset($data[$field]) && is_numeric($data[$field])) {
                $data[$field] = (string) ((float) $data[$field] * 10);
            }
        }
    }
    foreach ($data as &$value) {
        if (is_array($value)) {
            $value = mv_schema_iran_currency($value);
        }
    }
    unset($value);
    return $data;
}
add_filter('woocommerce_structured_data_product', 'mv_schema_iran_currency', 20);

add_action('woocommerce_after_single_product_summary', static function (): void {
    $guides = array_filter((array) get_post_meta(get_queried_object_id(), '_mv_product_guides', true));
    $posts = [];
    foreach ($guides as $id) {
        if (get_post_status($id) === 'publish') {
            $posts[] = $id;
        }
    }
    if (!$posts) {
        return;
    }
    echo '<section class="mv-related-guides" aria-labelledby="mv-related-guides-title"><h2 id="mv-related-guides-title">راهنمای انتخاب و نگهداری</h2><ul>';
    foreach ($posts as $id) {
        printf('<li><a href="%s">%s</a></li>', esc_url(get_permalink($id)), esc_html(get_the_title($id)));
    }
    echo '</ul></section>';
}, 14);
