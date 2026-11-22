<?php
/**
 * Plugin Name: Local Moalem OTP Browser Fixture
 */
defined('ABSPATH') || exit;
if (wp_get_environment_type() !== 'local') {
    return;
}
$fixture_file = dirname(ABSPATH) . '/onesms-browser-fixture.json';
if (!is_readable($fixture_file)) {
    return;
}
$fixture = json_decode(file_get_contents($fixture_file), true);
if (!is_array($fixture) || (int) ($fixture['expires'] ?? 0) <= time()) {
    return;
}
if (!defined('MV_ONESMS_KEY')) {
    define('MV_ONESMS_KEY', 'browser-mock-key');
}
if (!defined('MV_ONESMS_FROM')) {
    define('MV_ONESMS_FROM', '9890000875');
}
add_filter('pre_http_request', static function ($pre, array $config, string $url) use ($fixture) {
    if ($url !== 'https://connect.onesms.ir/v1/send/quick') {
        return $pre;
    }
    $payload = json_decode($config['body'], true);
    $target = $payload['to'] ?? [];
    if ($target !== [$fixture['phone']] && $target !== [$fixture['purchasePhones']['25'] ?? '']) {
        return new WP_Error('local_mock_blocked');
    }
    preg_match('/[0-9]{6}/', $payload['body'] ?? '', $code);
    if (isset($code[0]) && (str_contains($payload['body'], 'کد ورود') || str_contains($payload['body'], 'کد تأیید شمارهٔ ورود مدیر'))) {
        if ($target === [$fixture['phone']] && ($_SERVER['HTTP_X_MV_QA_MODE'] ?? '') === $fixture['tag']
            && !file_exists($fixture['capture'] . '.failed')) {
            file_put_contents($fixture['capture'] . '.failed', '', LOCK_EX);
            return ['response' => ['code' => (int) ($fixture['failureStatus'] ?? 503)], 'body' => '{"success":false}'];
        }
        file_put_contents($fixture['capture'], $code[0], LOCK_EX);
    }
    if (!empty($fixture['orderCapture']) && str_contains($payload['body'] ?? '', 'سفارش')) {
        file_put_contents($fixture['orderCapture'], wp_json_encode($payload), LOCK_EX);
    }
    return ['response' => ['code' => 200], 'body' => '{"success":true}'];
}, 10, 3);
if (($_SERVER['HTTP_X_MV_QA_MODE'] ?? '') === $fixture['tag']) {
    header('X-MV-QA-Mode: mocked');
    add_filter('pre_option_mv_commerce_manager_phone', '__return_empty_string');
    // A separate limit scope for the fixture avoids changing localhost login counters.
    $_SERVER['REMOTE_ADDR'] = '127.0.0.2';
    add_action('user_register', static function (int $id) use ($fixture): void {
        $phone = get_user_meta($id, '_mv_verified_mobile', true) ?: get_user_meta($id, '_mv_unverified_mobile', true);
        if (in_array($phone, array_merge([$fixture['phone']], array_values($fixture['purchasePhones'] ?? [])), true)) {
            add_user_meta($id, '_mv_browser_qa_tag', $fixture['tag'], true);
        }
    });
    add_action('template_redirect', static function () use ($fixture): void {
        $user = get_current_user_id();
        if (($_GET['mv_history_seed'] ?? '') !== '1' || !is_account_page() || !is_wc_endpoint_url('orders')
            || get_user_meta($user, '_mv_browser_qa_tag', true) !== $fixture['tag']
            || get_user_meta($user, '_mv_unverified_mobile', true) !== ($fixture['purchasePhones']['27'] ?? null)) {
            return;
        }
        $source = 'mv-qa-history-' . $fixture['tag'];
        if (wc_get_orders(['created_via' => $source, 'limit' => 1])) {
            return;
        }
        $products = wc_get_products(['type' => 'simple', 'stock_status' => 'instock', 'limit' => 1]);
        if (!$products) {
            return;
        }
        add_filter('woocommerce_can_reduce_order_stock', '__return_false');
        $shipping = [
            'first_name' => 'گیرنده', 'last_name' => 'آزمایشی', 'country' => 'IR',
            'state' => 'THR', 'city' => 'تهران', 'address_1' => 'نشانی ثبت‌شده روی سفارش',
            'postcode' => '1234567890',
        ];
        foreach (array_merge(array_fill(0, 9, 'processing'), ['completed', 'cancelled', 'refunded', 'foreign']) as $status) {
            $order = new WC_Order();
            $order->set_created_via($source);
            $order->set_customer_id($status === 'foreign' ? 0 : $user);
            $order->set_status($status === 'foreign' ? 'on-hold' : $status);
            $order->set_address($shipping, 'shipping');
            $order->save();
            $order->add_product($products[0], 1);
            $delivery = new WC_Order_Item_Shipping();
            $delivery->set_method_id('flat_rate');
            $delivery->set_method_title('ارسال آزمایشی');
            $order->add_item($delivery);
            $order->calculate_totals();
            if ($status === 'completed') {
                $order->add_order_note('به‌روزرسانی آزمایشی قابل مشاهده برای مشتری', true);
            } elseif ($status === 'foreign') {
                header('X-MV-QA-Foreign-Order: ' . $order->get_id());
            }
        }
        remove_filter('woocommerce_can_reduce_order_stock', '__return_false');
    }, 13);
}
