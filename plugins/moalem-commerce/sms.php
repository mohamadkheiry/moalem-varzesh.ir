<?php
defined('ABSPATH') || exit;

const MV_ONESMS_URL = 'https://connect.onesms.ir/v1/send/quick';

function mv_commerce_digits(string $input): string {
    return strtr(trim($input), array_combine(
        preg_split('//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY),
        str_split('01234567890123456789')
    ));
}

function mv_commerce_phone(string $input): string {
    $digits = preg_replace('/[\s\x{200e}\x{200f}\x{200c}()-]/u', '', mv_commerce_digits($input)) ?? '';
    if (preg_match('/^(?:0098|\+98|98)(9[0-9]{9})$/D', $digits, $international)) {
        return '0' . $international[1];
    }
    return preg_match('/^09[0-9]{9}$/D', $digits) ? $digits : '';
}

function mv_commerce_sms_failure(string $code, int $status = 0): WP_Error {
    if (function_exists('wc_get_logger')) {
        $message = 'OneSMS request failed: ' . $code;
        if ($status) {
            $message .= ' (HTTP ' . $status . ')';
        }
        wc_get_logger()->warning($message, ['source' => 'moalem-sms']);
    }
    return new WP_Error($code);
}

function mv_commerce_sms(string $phone, string $body) {
    $key = defined('MV_ONESMS_KEY') ? MV_ONESMS_KEY : getenv('MV_ONESMS_KEY');
    $sender = defined('MV_ONESMS_FROM') ? MV_ONESMS_FROM : getenv('MV_ONESMS_FROM');
    $phone = mv_commerce_phone($phone);
    if (!$key || !$sender || !$phone) {
        return mv_commerce_sms_failure('sms_unavailable');
    }
    $response = wp_safe_remote_post(MV_ONESMS_URL, [
        'timeout' => 7,
        'redirection' => 0,
        'headers' => [
            'X-API-Key' => $key,
            'Content-Type' => 'application/json; charset=utf-8',
        ],
        'body' => wp_json_encode(['from' => (string) $sender, 'to' => [$phone], 'body' => $body], JSON_UNESCAPED_UNICODE),
    ]);
    if (is_wp_error($response)) {
        return mv_commerce_sms_failure('sms_transport');
    }
    $status = (int) wp_remote_retrieve_response_code($response);
    $reply = json_decode(wp_remote_retrieve_body($response), true);
    if ($status < 200 || $status >= 300 || ($reply['success'] ?? null) === false || !empty($reply['error'])) {
        return mv_commerce_sms_failure('sms_rejected', $status);
    }
    return true;
}

add_action('woocommerce_order_status_changed', 'mv_commerce_order_sms', 20, 4);

function mv_commerce_order_sms(int $_order_id, string $from, string $to, WC_Order $order): void {
    $messages = [
        'processing' => ['received', 'سفارش %s ثبت شد و در حال آماده‌سازی است.'],
        'on-hold' => ['received', 'سفارش %s ثبت شد و در انتظار تأیید است.'],
        'completed' => ['completed', 'پردازش سفارش %s به پایان رسید.'],
        'cancelled' => ['cancelled', 'سفارش %s لغو شد.'],
    ];
    $phone = mv_commerce_phone($order->get_billing_phone());
    if ($phone && isset($messages[$to])) {
        [$event, $message] = $messages[$to];
        mv_commerce_order_notice($order, $event, $phone, $message, 'مشتری');
    }
    $new_order = in_array($to, ['processing', 'on-hold'], true)
        || ($to === 'completed' && in_array($from, ['pending', 'checkout-draft', 'draft', 'failed'], true));
    if ($new_order && ($manager = mv_commerce_manager_phone())) {
        mv_commerce_order_notice($order, 'manager-received', $manager, 'سفارش جدید %s ثبت شد.', 'مدیر');
    }
}

function mv_commerce_order_notice(WC_Order $order, string $event, string $phone, string $message, string $recipient): void {
    $key = hash('sha256', 'order-sms|' . $order->get_id() . '|' . $event);
    if (!mv_commerce_write($key, null, 0, ['result' => 'started'])) {
        return;
    }
    $receipt = mv_commerce_read($key);
    $result = mv_commerce_sms($phone, 'معلم ورزش | ' . sprintf($message, $order->get_order_number()));
    $accepted = !is_wp_error($result);
    mv_commerce_write($key, $receipt, 0, ['result' => $accepted ? 'accepted' : $result->get_error_code()]);
    $order->add_order_note($accepted
        ? sprintf('درخواست پیامک وضعیت سفارش برای %s توسط سرویس پیامکی پذیرفته شد.', $recipient)
        : sprintf('ارسال پیامک وضعیت سفارش برای %s تأیید نشد.', $recipient));
}
