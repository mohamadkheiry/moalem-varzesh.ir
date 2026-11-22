<?php
defined('ABSPATH') || exit;

add_filter('should_load_separate_core_block_assets', '__return_true');

add_action('wp_enqueue_scripts', static function (): void {
    if (!(is_home() || is_category() || is_singular('post'))) {
        return;
    }
    if (is_singular()) {
        $content = get_post_field('post_content', get_queried_object_id());
        if (str_contains($content, '<!-- wp:') || str_contains($content, '[')) {
            return;
        }
    }
    wp_dequeue_script('wc-add-to-cart');
    wp_dequeue_script('woocommerce');
    foreach (['woocommerce-layout', 'woocommerce-smallscreen', 'woocommerce-general', 'woocommerce-inline'] as $style) {
        wp_dequeue_style($style);
    }
}, 90);

function mv_loop_image_sizes(array $attributes, WP_Post $attachment, $size): array {
    if ($size !== 'woocommerce_thumbnail' || !isset($attributes['srcset'])) {
        return $attributes;
    }
    $attributes['sizes'] = '(min-width: 96rem) 335px, (min-width: 64rem) 22vw, (min-width: 48rem) 29vw, 44vw';
    if (is_front_page()) {
        $attributes['loading'] = 'lazy';
        unset($attributes['fetchpriority']);
        $attributes['sizes'] = 'auto, ' . $attributes['sizes'];
    }
    return $attributes;
}
add_action('woocommerce_before_shop_loop_item_title', static function (): void {
    add_filter('wp_get_attachment_image_attributes', 'mv_loop_image_sizes', 20, 3);
}, 9);
add_action('woocommerce_before_shop_loop_item_title', static function (): void {
    remove_filter('wp_get_attachment_image_attributes', 'mv_loop_image_sizes', 20);
}, 11);

add_filter('wp_preload_resources', static function (array $resources): array {
    $resources[] = [
        'href' => mv_licensed_store_font_url() ?: get_theme_file_uri('assets/vazirmatn.woff2'),
        'as' => 'font', 'type' => 'font/woff2', 'crossorigin' => 'anonymous',
    ];
    return $resources;
});

function mv_refresh_public_page_cache(): void {
    static $scheduled = false;
    if (!$scheduled && function_exists('wp_cache_clear_cache')) {
        $scheduled = true;
        add_action('shutdown', static fn() => wp_cache_clear_cache(is_multisite() ? get_current_blog_id() : 0));
    }
}
foreach ([
    'woocommerce_update_product', 'woocommerce_update_product_variation',
    'woocommerce_product_set_stock', 'woocommerce_variation_set_stock',
    'woocommerce_product_set_stock_status', 'woocommerce_variation_set_stock_status',
    'update_option_mv_support_design', 'add_option_mv_support_design', 'delete_option_mv_support_design',
    'update_option_mv_commerce_public_contact_phone', 'add_option_mv_commerce_public_contact_phone',
    'update_option_mv_bale_settings', 'add_option_mv_bale_settings', 'delete_option_mv_bale_settings',
    'update_option_mv_enamad_enabled', 'update_option_blog_public', 'wp_update_nav_menu',
    'created_product_cat', 'edited_product_cat', 'delete_product_cat',
] as $change) {
    add_action($change, 'mv_refresh_public_page_cache');
}
