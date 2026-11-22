<?php
defined('WP_CLI') && WP_CLI && wp_get_environment_type() === 'local' || exit;
$phone = getenv('MV_BROWSER_QA_PHONE');
$tag = getenv('MV_BROWSER_QA_TAG');
$email = getenv('MV_BROWSER_QA_EMAIL');
if (!preg_match('/^0900[0-9]{7}$/D', $phone ?: '') || !preg_match('/^[a-f0-9]{32}$/D', $tag ?: '')
    || $email !== "checkout-$tag@example.test") {
    WP_CLI::error('Missing local test identifiers.');
}
$id = mv_commerce_find_customer($phone);
if ($id && get_user_meta($id, '_mv_browser_qa_tag', true) === $tag) {
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($id);
}
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
foreach (['otp', 'otp-send-phone', 'password-phone'] as $scope) {
    $wpdb->delete(mv_commerce_table(), ['state_key' => mv_commerce_key($scope, $phone)]);
}
foreach (['otp-send-ip', 'otp-verify-ip', 'password-ip'] as $scope) {
    $wpdb->delete(mv_commerce_table(), ['state_key' => mv_commerce_key($scope, '127.0.0.2')]);
}
