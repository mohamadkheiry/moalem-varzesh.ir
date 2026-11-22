<?php
defined('WP_CLI') && WP_CLI && wp_get_environment_type() === 'local' || exit;
if (!function_exists('mv_bale_queue')) {
    WP_CLI::error('Activate Moalem Commerce before testing Bale.');
}

$token = '123456:local-fixture-bale-token-2665';
$stored_token = get_option('mv_bale_token', false);
$options = array_replace(mv_bale_settings(), [
    'channel' => '@fixture_channel', 'public_base' => 'https://moalem-varzesh.ir',
    'footer' => 'از فروشگاه معلم ورزش', 'product' => true, 'post' => true,
]);
$verified = '';
$config_filter = static function () use (&$options): array { return $options; };
$verified_filter = static function () use (&$verified): string { return $verified; };
add_filter('pre_option_mv_bale_settings', $config_filter);
add_filter('pre_option_mv_bale_verified', $verified_filter);
$requests = [];
$mode = 'accept';
$mock = static function ($previous, array $args, string $url) use (&$requests, &$mode) {
    if (!str_starts_with($url, MV_BALE_API)) {
        return $previous;
    }
    $method = basename($url);
    $requests[] = [$method, $args['headers']['Content-Type'], $args['body']];
    if ($mode === 'timeout') {
        return new WP_Error('http_request_failed', 'simulated timeout');
    }
    if ($mode === 'rate') {
        return ['response' => ['code' => 429], 'body' => '{"ok":false,"error_code":429,"parameters":{"retry_after":21}}'];
    }
    if ($mode === 'reject' || ($mode === 'bad-photo' && $method === 'sendPhoto')) {
        $status = $mode === 'reject' ? 403 : 400;
        return ['response' => ['code' => $status], 'body' => wp_json_encode(['ok' => false, 'error_code' => $status])];
    }
    $data = match ($method) {
        'getMe' => ['id' => 123456, 'is_bot' => true],
        'getChat' => ['type' => $mode === 'group' ? 'group' : 'channel'],
        'getChatMember' => ['status' => 'administrator', 'can_post_messages' => $mode !== 'no-rights'],
        default => ['message_id' => 165],
    };
    return ['response' => ['code' => 200], 'body' => wp_json_encode(['ok' => true, 'result' => $data])];
};
add_filter('pre_http_request', $mock, 10, 3);
$created = [];
$checks = 0;
$error = '';
$assert = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    ++$checks;
    WP_CLI::log('PASS ' . $message);
};
$article = static function (array $data = []) use (&$created): int {
    $id = wp_insert_post($data + [
        'post_title' => 'راهنمای آزمایشی بله ' . wp_generate_password(6, false, false),
        'post_content' => 'متن نمونهٔ تمرین', 'post_excerpt' => 'خلاصهٔ راهنمای تمرین',
        'post_status' => 'draft', 'post_type' => 'post',
    ], true);
    if (is_wp_error($id)) {
        throw new RuntimeException('Could not create the local article fixture.');
    }
    $created[] = $id;
    return $id;
};
$publish = static function (int $id): void {
    wp_update_post(['ID' => $id, 'post_status' => 'publish']);
    mv_bale_queue($id);
    mv_bale_send($id);
};

try {
    $assert(mv_bale_channel('@fixture_channel') === '@fixture_channel' && mv_bale_channel('-۱۲۳۴۵۶۷۸') === '-12345678'
        && mv_bale_channel('https://ble.ir/x') === '', 'channel input normalizes usernames and Persian numeric IDs');
    $assert(mv_bale_public_base('http://127.0.0.1:8087') === '' && mv_bale_public_base('https://localhost') === ''
        && mv_bale_public_base('https://moalem-varzesh.ir/') === 'https://moalem-varzesh.ir', 'public button URLs require an HTTPS domain');
    $assert(mv_bale_store_token($token) && !str_contains((string) get_option('mv_bale_token'), $token), 'the token is encrypted in the option');
    $token = mv_bale_token();
    $assert(mv_bale_token_valid($token), 'the token is available to the server after saving');
    $assert(mv_bale_check_connection($token, $options['channel']) === 'connected'
        && array_column($requests, 0) === ['getMe', 'getChat', 'getChatMember'], 'connection validation sends no message');
    $mode = 'group';
    $assert(mv_bale_check_connection($token, $options['channel']) === 'invalid_channel', 'a group cannot be configured as the publication channel');
    $mode = 'no-rights';
    $assert(mv_bale_check_connection($token, $options['channel']) === 'channel_permission', 'a bot without posting rights is rejected');
    $mode = 'accept';

    $protected = $article(['post_status' => 'publish', 'post_password' => 'fixture']);
    $draft = $article();
    $disabled = $article();
    $before = count($requests);
    mv_bale_queue($protected);
    mv_bale_queue($draft);
    $publish($disabled);
    $assert(count($requests) === $before && !mv_bale_delivery($disabled) && !mv_bale_delivery($protected), 'draft, protected and unverified publishing are excluded');
    $verified = mv_bale_connection_signature($token, $options['channel']);
    $id = $article();
    update_post_meta($id, '_mv_bale_note', 'یادداشت مخصوص کانال');
    wp_update_post(['ID' => $id, 'post_status' => 'publish']);
    do_action('shutdown');
    mv_bale_send($id);
    $delivery = mv_bale_delivery($id);
    $payload = json_decode(end($requests)[2], true);
    $assert($delivery['status'] === 'sent' && $delivery['message'] === '165' && end($requests)[0] === 'sendMessage', 'native first publication sends an article and saves its receipt');
    $assert(str_contains($payload['text'], 'خلاصهٔ راهنما') && str_contains($payload['text'], 'یادداشت مخصوص کانال')
        && $payload['chat_id'] === $options['channel'] && str_starts_with($payload['reply_markup']['inline_keyboard'][0][0]['url'], $options['public_base']),
        'article payload uses the published excerpt, channel note, and existing permalink');
    $count = count($requests);
    wp_update_post(['ID' => $id, 'post_title' => 'عنوان ویرایش‌شده']);
    mv_bale_queue($id);
    mv_bale_send($id);
    $assert(count($requests) === $count, 'editing or duplicate queue processing does not repost a sent article');

    $sample = wc_get_products(['type' => 'simple', 'status' => 'publish', 'limit' => 1])[0];
    $product = new WC_Product_Simple();
    $product->set_name('کالای آزمایشی بله');
    $product->set_status('draft');
    $product->set_regular_price('172000');
    $product->set_sale_price('159000');
    $product->set_short_description('توضیح اصلی کالا');
    $product->set_image_id($sample->get_image_id());
    $created[] = $item = $product->save();
    $publish($item);
    $entry = end($requests);
    $assert($entry[0] === 'sendPhoto' && str_starts_with($entry[1], 'multipart/form-data; boundary=')
        && str_contains($entry[2], 'name="photo"') && str_contains($entry[2], 'name="from_chat_id"'), 'product featured image is uploaded as a multipart photo');
    $assert(str_contains($entry[2], mv_bale_text(wc_price(159000))) && !str_contains($entry[2], mv_bale_text(wc_price(172000)))
        && str_contains($entry[2], 'توضیح اصلی کالا'), 'message reads the current WooCommerce sale price and description');
    $variable = wc_get_products(['type' => 'variable', 'status' => 'publish', 'limit' => 1])[0];
    $assert(str_contains(mv_bale_price($variable), mv_bale_text(wc_price($variable->get_variation_price('min', true))))
        && str_contains(mv_bale_price($variable), mv_bale_text(wc_price($variable->get_variation_price('max', true)))), 'variable product uses its displayed WooCommerce price endpoints');

    $mode = 'bad-photo';
    $photo_article = $article();
    set_post_thumbnail($photo_article, $sample->get_image_id());
    $before = count($requests);
    $publish($photo_article);
    $assert(array_column(array_slice($requests, $before), 0) === ['sendPhoto', 'sendMessage']
        && mv_bale_delivery($photo_article)['status'] === 'sent', 'explicit rejected photo can fall back to a single text message');

    $mode = 'rate';
    $limited = $article();
    $publish($limited);
    $rate = mv_bale_delivery($limited);
    $assert($rate['status'] === 'retry' && $rate['due'] >= time() + 19, 'provider 429 queues a bounded retry using retry_after');
    $count = count($requests);
    mv_bale_send($limited);
    $assert(count($requests) === $count, 'queued retry cannot run before its due time');

    $mode = 'timeout';
    $timeout = $article();
    $publish($timeout);
    $count = count($requests);
    mv_bale_retry($timeout);
    mv_bale_send($timeout);
    $assert(mv_bale_delivery($timeout)['status'] === 'uncertain' && count($requests) === $count, 'ambiguous transport is recorded without a replay');
    $mode = 'reject';
    $rejected = $article();
    $publish($rejected);
    $assert(mv_bale_delivery($rejected)['http'] === 403, 'explicit authorization rejection is recorded as a safe status code');
    $mode = 'accept';
    mv_bale_retry($rejected);
    mv_bale_send($rejected);
    $assert(mv_bale_delivery($rejected)['status'] === 'sent', 'a manager can reprocess an explicit rejected delivery');
} catch (Throwable $exception) {
    $error = $exception->getMessage();
} finally {
    $options['product'] = $options['post'] = false;
    global $wpdb;
    foreach ($created as $id) {
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions('mv_bale_send', [$id], 'mv-bale');
        }
        wp_clear_scheduled_hook('mv_bale_send', [$id]);
        $wpdb->delete(mv_commerce_table(), ['state_key' => mv_bale_delivery_key($id)]);
        wp_delete_post($id, true);
    }
    if ($stored_token === false) {
        delete_option('mv_bale_token');
    } else {
        update_option('mv_bale_token', $stored_token, false);
    }
    remove_filter('pre_option_mv_bale_settings', $config_filter);
    remove_filter('pre_option_mv_bale_verified', $verified_filter);
    remove_filter('pre_http_request', $mock, 10);
}
if ($error) {
    WP_CLI::error($error);
}
WP_CLI::success("$checks Bale checks with a mocked provider and isolated WordPress data.");
