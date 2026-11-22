<?php
defined('ABSPATH') || exit;

function mv_bale_delivery_key(int $id): string {
    return mv_commerce_key('bale-channel-delivery', (string) $id);
}

function mv_bale_delivery(int $id): array {
    $row = mv_commerce_read(mv_bale_delivery_key($id));
    return $row ? (json_decode($row['payload'], true) ?: []) : [];
}

function mv_bale_public_post(?WP_Post $post): bool {
    if (!$post || $post->post_status !== 'publish' || $post->post_password !== ''
        || !in_array($post->post_type, ['post', 'product'], true)) {
        return false;
    }
    if ($post->post_type === 'product') {
        $product = function_exists('wc_get_product') ? wc_get_product($post->ID) : null;
        return $product && $product->get_catalog_visibility() !== 'hidden';
    }
    return true;
}

function mv_bale_record(int $id, ?array $previous, array $data): bool {
    $data['updated'] = time();
    if (!mv_commerce_write(mv_bale_delivery_key($id), $previous, 0, $data)) {
        return false;
    }
    update_post_meta($id, '_mv_bale_delivery_updated', $data['updated']);
    return true;
}

function mv_bale_schedule(int $id, int $when): bool {
    if (function_exists('as_schedule_single_action') && did_action('action_scheduler_init')) {
        return (bool) as_schedule_single_action($when, 'mv_bale_send', [$id], 'mv-bale');
    }
    return wp_schedule_single_event($when, 'mv_bale_send', [$id]);
}

function mv_bale_queue(int $id): void {
    $post = get_post($id);
    $settings = mv_bale_settings();
    if (!mv_bale_public_post($post) || empty($settings[$post->post_type]) || !$settings['channel']
        || !mv_bale_verified(mv_bale_token(), $settings['channel']) || !mv_bale_post_url($post, $settings['public_base'])) {
        return;
    }
    $data = ['status' => 'queued', 'channel' => $settings['channel'], 'attempts' => 0, 'due' => time()];
    if (!mv_bale_record($id, null, $data)) {
        return;
    }
    if (wp_get_environment_type() === 'local' && defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
        mv_bale_send($id);
    } else {
        mv_bale_schedule($id, time());
    }
}

add_action('transition_post_status', static function (string $new, string $old, WP_Post $post): void {
    if ($new !== 'publish' || $old === 'publish' || $post->post_password !== ''
        || !in_array($post->post_type, ['post', 'product'], true)) {
        return;
    }
    $settings = mv_bale_settings();
    if (!empty($settings[$post->post_type]) && mv_bale_verified(mv_bale_token(), $settings['channel'])) {
        // WooCommerce and the editor finish persisting prices, variations and media before the job reads them.
        add_action('shutdown', static fn() => mv_bale_queue($post->ID), 30);
    }
}, 10, 3);

function mv_bale_send(int $id): void {
    $key = mv_bale_delivery_key($id);
    $row = mv_commerce_read($key);
    $data = json_decode($row['payload'] ?? '{}', true);
    if (!$row || !in_array($data['status'] ?? '', ['queued', 'retry'], true) || $data['due'] > time()) {
        return;
    }
    $post = get_post($id);
    $settings = mv_bale_settings();
    $token = mv_bale_token();
    $message = mv_bale_public_post($post) ? mv_bale_message($post, $settings) : null;
    if (!$message || !mv_bale_verified($token, $settings['channel']) || empty($settings[$post->post_type]) || $settings['channel'] !== $data['channel']) {
        $data['status'] = 'paused';
        mv_bale_record($id, $row, $data);
        return;
    }
    $data['status'] = 'sending';
    ++$data['attempts'];
    if (!mv_bale_record($id, $row, $data) || !($claim = mv_commerce_read($key))) {
        return;
    }
    $payload = ['chat_id' => $data['channel'], 'reply_markup' => $message['reply_markup']];
    $photo = mv_bale_photo($message['photo']);
    $result = $photo ? mv_bale_request($token, 'sendPhoto', $payload + [
        'from_chat_id' => $data['channel'], 'caption' => $message['text'],
    ], $photo) : null;
    if ($result === null || (is_wp_error($result) && $result->get_error_code() === 'bale_rejected' && $result->get_error_data() === 400)) {
        $result = mv_bale_request($token, 'sendMessage', $payload + ['text' => $message['text']]);
    }
    if (!is_wp_error($result) && !empty($result['message_id'])) {
        $data['status'] = 'sent';
        $data['message'] = (string) $result['message_id'];
    } elseif (is_wp_error($result) && $result->get_error_code() === 'bale_rate' && $data['attempts'] < 4) {
        $data['status'] = 'retry';
        $data['due'] = time() + $result->get_error_data();
    } elseif (is_wp_error($result) && in_array($result->get_error_code(), ['bale_rate', 'bale_rejected', 'bale_settings'], true)) {
        $data['status'] = 'rejected';
        $data['http'] = $result->get_error_code() === 'bale_rejected' ? $result->get_error_data() : 0;
    } else {
        // A timeout or invalid receipt may hide an accepted send. Automatic replay would risk duplicates.
        $data['status'] = 'uncertain';
    }
    if (mv_bale_record($id, $claim, $data) && $data['status'] === 'retry') {
        mv_bale_schedule($id, $data['due']);
    }
}
add_action('mv_bale_send', 'mv_bale_send');

function mv_bale_retry(int $id): void {
    $row = mv_commerce_read(mv_bale_delivery_key($id));
    $data = json_decode($row['payload'] ?? '{}', true);
    if (!$row || !in_array($data['status'] ?? '', ['rejected', 'paused'], true)) {
        return;
    }
    $data['status'] = 'queued';
    $data['attempts'] = 0;
    $data['due'] = time();
    unset($data['http']);
    mv_bale_record($id, $row, $data);
}
