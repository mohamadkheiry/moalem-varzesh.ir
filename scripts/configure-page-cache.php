<?php
defined('WP_CLI') && WP_CLI && wp_get_environment_type() === 'local' || exit;

if (!function_exists('wp_cache_setting') || !function_exists('wp_cache_enable') || !defined('WP_CACHE') || !WP_CACHE) {
    WP_CLI::error('Activate WP Super Cache and enable the WP_CACHE constant first.');
}

$excluded = ['^/wp-json/', '^/wp-sitemap(?:[.-]|$)', '^/robots\.txt$'];
if (function_exists('wc_get_page_permalink')) {
    foreach (['cart', 'checkout', 'myaccount'] as $page) {
        $path = rtrim((string) wp_parse_url(wc_get_page_permalink($page), PHP_URL_PATH), '/');
        if ($path !== '') {
            $excluded[] = '^' . preg_quote($path, '~') . '(?:/|\?|$)';
        }
    }
}

$cookies = [
    '^woocommerce_(?:items_in_cart|cart_hash|recently_viewed)$',
    '^wp_woocommerce_session_',
    '^mv_account_session$',
    '^mv_store_design$',
];
$settings = [
    'wp_cache_mod_rewrite' => 0,
    'cache_compression' => 1,
    'cache_max_time' => 600,
    'cache_rebuild_files' => 0,
    'wp_cache_not_logged_in' => 2,
    'wp_cache_no_cache_for_get' => 1,
    'wp_cache_slash_check' => (int) str_ends_with((string) get_option('permalink_structure'), '/'),
    'wpsc_served_header' => 1,
    'cache_rejected_uri' => array_values(array_unique(array_merge($GLOBALS['cache_rejected_uri'] ?? [], $excluded))),
    'wpsc_rejected_cookies' => array_values(array_unique(array_merge($GLOBALS['wpsc_rejected_cookies'] ?? [], $cookies))),
];
foreach ($settings as $key => $value) {
    if (!wp_cache_setting($key, $value)) {
        WP_CLI::error('Could not save WP Super Cache setting: ' . $key);
    }
}
wp_super_cache_enable();
wp_cache_enable();
wp_cache_clear_cache();
WP_CLI::success('Enabled the local public-page cache, gzip, and session/commerce exclusions.');
