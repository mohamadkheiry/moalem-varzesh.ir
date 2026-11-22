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
    if (($payload['to'] ?? []) !== [$fixture['phone']]) {
        return new WP_Error('local_mock_blocked');
    }
    preg_match('/[0-9]{6}/', $payload['body'] ?? '', $code);
    if (isset($code[0]) && (str_contains($payload['body'], 'کد ورود') || str_contains($payload['body'], 'کد تأیید شمارهٔ ورود مدیر'))) {
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
        if (get_user_meta($id, '_mv_verified_mobile', true) === $fixture['phone']) {
            add_user_meta($id, '_mv_browser_qa_tag', $fixture['tag'], true);
        }
    });
}
