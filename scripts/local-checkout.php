<?php
defined('WP_CLI') && WP_CLI || exit;

if (wp_get_environment_type() !== 'local' || !class_exists('WooCommerce')) {
    WP_CLI::error('The checkout fixture is available only in a local WooCommerce installation.');
}
$zone_name = 'ارسال آزمایشی ایران';
$zone = null;
foreach (WC_Shipping_Zones::get_zones() as $existing) {
    if ($existing['zone_name'] === $zone_name) {
        $zone = new WC_Shipping_Zone($existing['zone_id']);
        break;
    }
}
if (!$zone) {
    $zone = new WC_Shipping_Zone();
    $zone->set_zone_name($zone_name);
    $zone->add_location('IR', 'country');
    $zone->save();
}
if (!$zone->get_shipping_methods()) {
    $instance = $zone->add_shipping_method('flat_rate');
    update_option("woocommerce_flat_rate_{$instance}_settings", [
        'title' => 'ارسال آزمایشی',
        'tax_status' => 'none',
        'cost' => '0',
    ]);
}
update_option('woocommerce_cod_settings', [
    'enabled' => 'yes',
    'title' => 'پرداخت آزمایشی',
    'description' => 'سفارش محلی برای بررسی فرایند خرید. هیچ مبلغی دریافت نمی‌شود.',
    'instructions' => 'این سفارش فقط در فروشگاه محلی ثبت شده است.',
    'enable_for_virtual' => 'yes',
]);
WC_Cache_Helper::get_transient_version('shipping', true);
WP_CLI::success('Installed the local sandbox shipping and payment methods.');
