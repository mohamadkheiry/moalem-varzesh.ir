<?php
defined('WP_CLI') && WP_CLI && wp_get_environment_type() === 'local' || exit;
$phone = getenv('MV_BROWSER_QA_PHONE');
$tag = getenv('MV_BROWSER_QA_TAG');
$email = getenv('MV_BROWSER_QA_EMAIL');
if (!preg_match('/^0900[0-9]{7}$/D', $phone ?: '') || !preg_match('/^[a-f0-9]{32}$/D', $tag ?: '')
    || $email !== "checkout-$tag@example.test") {
    WP_CLI::error('Missing local test identifiers.');
}
$fixture_path = getenv('MV_QA_SMS_FIXTURE');
$fixture = $fixture_path && is_readable($fixture_path) ? json_decode(file_get_contents($fixture_path), true) : [];
$test_phones = ($fixture['tag'] ?? '') === $tag ? array_values($fixture['purchasePhones'] ?? []) : [];
global $wpdb;
foreach (wc_get_orders(['billing_email' => $email, 'limit' => 10]) as $order) {
    if ($order->get_billing_phone() !== $phone) {
        continue;
    }
    foreach (['received', 'completed', 'cancelled', 'manager-received'] as $event) {
        $wpdb->delete(mv_commerce_table(), ['state_key' => hash('sha256', 'order-sms|' . $order->get_id() . '|' . $event)]);
    }
    wc_maybe_increase_stock_levels($order->get_id());
    $order->delete(true);
}
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach (array_merge([$phone], $test_phones) as $test_phone) {
    if (!preg_match('/^0900[0-9]{7}$/D', $test_phone)) {
        continue;
    }
    foreach (['_mv_verified_mobile', '_mv_unverified_mobile'] as $meta) {
        $id = mv_commerce_customer_for_mobile($test_phone, $meta);
        if ($id && get_user_meta($id, '_mv_browser_qa_tag', true) === $tag) {
            wp_delete_user($id);
        }
    }
    foreach (['otp', 'otp-send-phone', 'password-phone', 'register-phone'] as $scope) {
        $wpdb->delete(mv_commerce_table(), ['state_key' => mv_commerce_key($scope, $test_phone)]);
    }
}
foreach (['otp-send-ip', 'otp-verify-ip', 'password-ip', 'register-ip'] as $scope) {
    $wpdb->delete(mv_commerce_table(), ['state_key' => mv_commerce_key($scope, '127.0.0.2')]);
}
