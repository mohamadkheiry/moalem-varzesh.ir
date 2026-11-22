<?php
defined('WP_CLI') && WP_CLI || exit;

function mv_page(string $slug, string $title, string $content): int {
    $page = get_page_by_path($slug);
    $id = wp_insert_post([
        'ID' => $page ? $page->ID : 0,
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_name' => $slug,
        'post_title' => $title,
        'post_content' => $content,
    ], true);
    if (is_wp_error($id)) {
        WP_CLI::error($id->get_error_message());
    }
    return $id;
}

if (!class_exists('WooCommerce')) {
    WP_CLI::error('Activate WooCommerce first.');
}

update_option('blogname', 'معلم ورزش');
update_option('blogdescription', 'فروشگاه پوشاک، کفش و تجهیزات ورزشی');
update_option('WPLANG', 'fa_IR');
update_option('timezone_string', 'Asia/Tehran');
update_option('woocommerce_currency', 'IRT');
update_option('woocommerce_price_num_decimals', 0);
update_option('woocommerce_currency_pos', 'right_space');
update_option('woocommerce_price_thousand_sep', '٬');
update_option('woocommerce_default_country', 'IR');
update_option('woocommerce_allowed_countries', 'specific');
update_option('woocommerce_specific_allowed_countries', ['IR']);
update_option('woocommerce_ship_to_countries', 'specific');
update_option('woocommerce_specific_ship_to_countries', ['IR']);
update_option('woocommerce_coming_soon', 'no');
update_option('woocommerce_cart_redirect_after_add', 'no');
update_option('woocommerce_enable_guest_checkout', 'yes');
update_option('woocommerce_enable_checkout_login_reminder', 'yes');
update_option('woocommerce_enable_myaccount_registration', 'yes');
update_option('mv_enamad_enabled', 1);
update_option('permalink_structure', '/%postname%/');
update_option('woocommerce_permalinks', [
    'product_base' => '/product',
    'category_base' => 'product-category',
    'tag_base' => 'product-tag',
    'attribute_base' => '',
    'use_verbose_page_rules' => false,
]);

$pages = [
    'shop' => ['shop', 'فروشگاه', ''],
    'cart' => ['cart', 'سبد خرید', '[woocommerce_cart]'],
    'checkout' => ['checkout', 'تسویه حساب', '[woocommerce_checkout]'],
    'myaccount' => ['my-account', 'حساب کاربری', '[woocommerce_my_account]'],
];
foreach ($pages as $option => [$slug, $name, $content]) {
    update_option('woocommerce_' . $option . '_page_id', mv_page($slug, $name, $content));
}

$catalog = getenv('MV_CATALOG_DIR') ?: dirname(__DIR__) . '/catalog';
$pages = json_decode(file_get_contents($catalog . '/pages.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ($pages as $page) {
    $id = mv_page($page['slug'], $page['title'], wp_kses_post($page['content']));
    if ($page['id'] === 12756) {
        update_option('wp_page_for_privacy_policy', $id);
    } elseif ($page['id'] === 5026) {
        update_option('woocommerce_terms_page_id', $id);
    }
}

if (wp_get_environment_type() === 'local') {
    update_option('blog_public', 0);
    update_option('woocommerce_email_enabled', 'no');
}
WP_CLI::success('Configured the Persian storefront, native commerce pages, and trust seal.');
