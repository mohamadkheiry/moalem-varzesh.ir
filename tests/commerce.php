<?php
defined('WP_CLI') && WP_CLI || exit;
if (wp_get_environment_type() !== 'local' || !function_exists('mv_commerce_otp_request')) {
    WP_CLI::error('Run these tests on a local WooCommerce installation with Moalem Commerce active.');
}

if (!defined('MV_ONESMS_KEY')) {
    define('MV_ONESMS_KEY', 'local-only-mock-key');
}
if (!defined('MV_ONESMS_FROM')) {
    define('MV_ONESMS_FROM', '9890000875');
}

$requests = [];
$provider = 'accept';
$mock = static function ($pre, array $options, string $url) use (&$requests, &$provider) {
    if ($url !== MV_ONESMS_URL) {
        return $pre;
    }
    $requests[] = [
        'method' => $options['method'],
        'headers_ok' => !empty($options['headers']['X-API-Key'])
            && str_starts_with($options['headers']['Content-Type'], 'application/json'),
        'body' => json_decode($options['body'], true, 512, JSON_THROW_ON_ERROR),
    ];
    return match ($provider) {
        'fail' => new WP_Error('http_request_failed', 'simulated network failure'),
        'reject' => ['response' => ['code' => 200], 'body' => '{"success":false}'],
        'bad_status' => ['response' => ['code' => 429], 'body' => '{"success":false}'],
        default => ['response' => ['code' => 200], 'body' => '{"success":true}'],
    };
};
add_filter('pre_http_request', $mock, 10, 3);
$manager_phone = '';
$manager_setting = static function () use (&$manager_phone): string {
    return $manager_phone;
};
add_filter('pre_option_mv_commerce_manager_phone', $manager_setting);
$public_contact_phone = '';
$public_contact_setting = static function () use (&$public_contact_phone): string {
    return $public_contact_phone;
};
add_filter('pre_option_mv_commerce_public_contact_phone', $public_contact_setting);

global $wpdb;
$table = mv_commerce_table();
$phone = '0900' . random_int(1000000, 9999999);
$other = '0901' . substr($phone, 4);
$ip = 'local-test-' . $phone;
$order = null;
$manager_orders = [];
$user_id = 0;
$keys = [
    mv_commerce_key('otp', $phone), mv_commerce_key('otp', $other),
    mv_commerce_key('otp-send-ip', $ip), mv_commerce_key('password-ip', $ip),
    mv_commerce_key('password-phone', $phone), mv_commerce_key('basic-test', $phone),
];
$count = 0;
$error = null;
$check = static function (bool $result, string $description) use (&$count): void {
    if (!$result) {
        throw new RuntimeException($description);
    }
    ++$count;
    WP_CLI::log("PASS $description");
};
$clear = static function (string $key) use ($wpdb, $table): void {
    $wpdb->delete($table, ['state_key' => $key]);
};
$sent_code = static function () use (&$requests): string {
    preg_match('/[0-9]{6}/', end($requests)['body']['body'], $match);
    return $match[0] ?? '';
};

try {
    foreach (['09123456789', '+98 (912) 345-67-89', '۰۰۹۸۹۱۲۳۴۵۶۷۸۹', '٠٩١٢٣٤٥٦٧٨٩'] as $input) {
        $check(mv_commerce_phone($input) === '09123456789', 'normalize ' . $input);
    }
    foreach (['', '+979123456789', '03123456789', '0912abc6789', '091234567890'] as $input) {
        $check(mv_commerce_phone($input) === '', 'reject malformed phone ' . $input);
    }
    $check(mv_commerce_sms($phone, 'پیام آزمایشی') === true, 'mock transport acceptance');
    $check(end($requests) === [
        'method' => 'POST', 'headers_ok' => true,
        'body' => ['from' => (string) MV_ONESMS_FROM, 'to' => [$phone], 'body' => 'پیام آزمایشی'],
    ], 'provider JSON contract and header presence');

    $provider = 'reject';
    $check(mv_commerce_sms($phone, 'آزمون')->get_error_code() === 'sms_rejected', 'provider application rejection');
    $provider = 'bad_status';
    $check(mv_commerce_sms($phone, 'آزمون')->get_error_code() === 'sms_rejected', 'provider HTTP 429 rejection');
    $provider = 'fail';
    $check(mv_commerce_otp_request($phone, $ip)->get_error_code() === 'sms_transport', 'failed transport leaves no usable OTP');
    $check(!mv_commerce_otp_verify($phone, $sent_code()), 'failed delivery code cannot authorize a session');
    $otp_key = mv_commerce_key('otp', $phone);
    $clear($otp_key);

    $provider = 'accept';
    $check(mv_commerce_otp_request($phone, $ip) === true, 'issue OTP');
    $code = $sent_code();
    $wrong = $code === '111111' ? '111112' : '111111';
    $stored = mv_commerce_read($otp_key);
    $data = json_decode($stored['payload'], true);
    $check($code !== '' && $data['digest'] === mv_commerce_otp_digest($phone, $data['id'], $code)
        && !isset($data['code']), 'challenge stores a digest');
    $check(mv_commerce_otp_request($phone, $ip)->get_error_code() === 'otp_limit', 'resend cooldown');
    $check(!mv_commerce_otp_verify($other, $code), 'code bound to its phone');
    $check(!mv_commerce_otp_verify($phone, $wrong), 'wrong OTP');
    $check(mv_commerce_otp_verify($phone, $code), 'correct OTP after an incorrect attempt');
    $check(!mv_commerce_otp_verify($phone, $code), 'OTP is single-use');

    $clear($otp_key);
    $check(mv_commerce_otp_request($phone, $ip) === true, 'issue OTP for expiry case');
    $row = mv_commerce_read($otp_key);
    $check(mv_commerce_write($otp_key, $row, time() - 1, json_decode($row['payload'], true))
        && !mv_commerce_otp_verify($phone, $sent_code()), 'expired OTP rejected');
    $clear($otp_key);
    $check(mv_commerce_otp_request($phone, $ip) === true, 'issue OTP for retry case');
    $code = $sent_code();
    $wrong = $code === '222222' ? '222223' : '222222';
    for ($attempt = 1; $attempt <= 5; ++$attempt) {
        $check(!mv_commerce_otp_verify($phone, $wrong), 'reject incorrect verification attempt ' . $attempt);
    }
    $check(!mv_commerce_otp_verify($phone, $code), 'lock challenge after five attempts');

    $basic_key = mv_commerce_key('basic-test', $phone);
    $check(mv_commerce_limit($basic_key, 2, 120) && mv_commerce_limit($basic_key, 2, 120)
        && !mv_commerce_limit($basic_key, 2, 120), 'rate limit reservation');
    $row = mv_commerce_read($basic_key);
    $check(mv_commerce_write($basic_key, $row, time() + 120, ['count' => 1])
        && !mv_commerce_write($basic_key, $row, time() + 120, ['count' => 2]), 'concurrent stale update rejected');

    $id = mv_commerce_user_for_verified_phone($phone, 'Store QA password 9518');
    $check(is_int($id) && $id > 0, 'create a customer from a verified phone');
    $user_id = $id;
    $check(mv_commerce_find_customer($phone) === $id && get_user_meta($id, 'billing_phone', true) === $phone, 'persist verified identity and billing phone');
    $check(is_wp_error(mv_commerce_password_login($phone, 'bad password', $ip)), 'password rejection');
    $account = mv_commerce_password_login($phone, 'Store QA password 9518', $ip);
    $check(!is_wp_error($account) && $account->ID === $id, 'WordPress password authentication by phone');
    $check(mv_commerce_user_for_verified_phone($phone, 'Store reset password 2747') === $id
        && wp_check_password('Store reset password 2747', get_userdata($id)->user_pass, $id), 'OTP can set a replacement password');

    $order = wc_create_order();
    if (is_wp_error($order)) {
        throw new RuntimeException('Could not create local test order.');
    }
    $order->set_billing_phone($phone);
    $order->set_created_via('mv-automated-qa');
    $order->save();
    foreach (['received', 'completed', 'cancelled'] as $event) {
        $keys[] = hash('sha256', 'order-sms|' . $order->get_id() . '|' . $event);
    }
    $before = count($requests);
    $order->update_status('on-hold');
    $order->update_status('processing');
    $check(count($requests) === $before + 1 && str_contains(end($requests)['body']['body'], $order->get_order_number()), 'order status hook sends one confirmation');
    $order->update_status('completed');
    mv_commerce_order_sms($order->get_id(), 'processing', 'completed', $order);
    $check(count($requests) === $before + 2, 'order completion receipt prevents duplicates');
    $provider = 'fail';
    $order->update_status('cancelled');
    mv_commerce_order_sms($order->get_id(), 'completed', 'cancelled', $order);
    $receipt = mv_commerce_read(end($keys));
    $check(count($requests) === $before + 3 && json_decode($receipt['payload'], true)['result'] === 'sms_transport', 'ambiguous transport failure is recorded without replay');

    require_once ABSPATH . 'wp-admin/includes/template.php';
    $manager_phone = '0902' . substr($phone, 4);
    $new_manager = '0903' . substr($phone, 4);
    $check(mv_commerce_sanitize_manager_phone(' +98 ' . substr($new_manager, 1)) === $new_manager, 'normalize replacement manager mobile');
    $check(mv_commerce_sanitize_manager_phone('bad phone') === $manager_phone, 'invalid replacement keeps the existing manager mobile');
    $check(mv_commerce_sanitize_manager_phone('  ') === '', 'a blank manager setting disables alerts');

    $create_order = static function (string $billing_phone) use (&$manager_orders, &$keys): WC_Order {
        $order = wc_create_order(['created_via' => 'mv-automated-qa']);
        if (is_wp_error($order)) {
            throw new RuntimeException('Could not create a manager alert test order.');
        }
        $manager_orders[] = $order;
        foreach (['received', 'completed', 'manager-received'] as $event) {
            $keys[] = hash('sha256', 'order-sms|' . $order->get_id() . '|' . $event);
        }
        $order->set_billing_phone($billing_phone);
        $order->save();
        return $order;
    };
    $provider = 'accept';
    $before = count($requests);
    $first = $create_order('');
    $first->update_status('on-hold');
    $notice = end($requests)['body'];
    $check(count($requests) === $before + 1 && $notice['to'] === [$manager_phone]
        && str_contains($notice['body'], $first->get_order_number()), 'manager receives a new-order alert without a buyer phone');
    $manager_phone = $new_manager;
    $first->update_status('processing');
    $check(count($requests) === $before + 1, 'changing manager does not replay an existing order alert');
    $second = $create_order($phone);
    $second->update_status('completed');
    $check(count($requests) === $before + 3 && $requests[$before + 1]['body']['to'] === [$phone]
        && end($requests)['body']['to'] === [$new_manager], 'new manager and buyer get independent alerts on the next order');
    mv_commerce_order_sms($second->get_id(), 'pending', 'completed', $second);
    $check(count($requests) === $before + 3, 'manager alert is idempotent on a direct completion');

    $public_contact_phone = $other;
    $contact_body = static function (): string {
        ob_start();
        get_template_part('template-parts/social-links');
        return ob_get_clean();
    };
    $link = 'href="tel:+98' . substr($other, 1) . '"';
    $html = $contact_body();
    $check(str_contains($html, $link) && !str_contains($html, $new_manager), 'only the public contact number appears in the floating menu');
    $public_contact_phone = $phone;
    $html = $contact_body();
    $check(!str_contains($html, $link) && str_contains($html, 'href="tel:+98' . substr($phone, 1) . '"'),
        'floating menu immediately uses the updated public contact');
    $check(mv_commerce_sanitize_phone_option('invalid number', 'mv_commerce_public_contact_phone', 'Invalid number') === $phone,
        'invalid public number retains the current value');
    $public_contact_phone = '';
    $check(!str_contains($contact_body(), 'href="tel:') && mv_commerce_manager_phone() === $new_manager,
        'blank public contact hides call link while retaining the SMS recipient');
} catch (Throwable $exception) {
    $error = $exception->getMessage();
} finally {
    if ($order instanceof WC_Order) {
        $order->delete(true);
    }
    foreach ($manager_orders as $manager_order) {
        $manager_order->delete(true);
    }
    if ($user_id) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($user_id);
    }
    foreach ($keys as $key) {
        $clear($key);
    }
    remove_filter('pre_http_request', $mock);
    remove_filter('pre_option_mv_commerce_manager_phone', $manager_setting);
    remove_filter('pre_option_mv_commerce_public_contact_phone', $public_contact_setting);
}
if ($error !== null) {
    WP_CLI::error($error);
}
WP_CLI::success("$count commerce checks, using a mocked OneSMS transport.");
