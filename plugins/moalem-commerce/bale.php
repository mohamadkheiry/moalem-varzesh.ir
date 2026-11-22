<?php
defined('ABSPATH') || exit;

const MV_BALE_API = 'https://tapi.bale.ai/bot';

function mv_bale_settings(): array {
    return wp_parse_args(get_option('mv_bale_settings', []), [
        'channel' => '', 'channel_url' => '', 'public_base' => '', 'footer' => '',
        'product_button' => 'مشاهده و خرید از فروشگاه', 'post_button' => 'خواندن مقاله',
        'product' => false, 'post' => false,
    ]);
}

function mv_bale_channel(string $value): string {
    $value = mv_commerce_digits($value);
    return preg_match('/^(?:@[a-zA-Z][a-zA-Z0-9_]{3,31}|-?[1-9][0-9]{2,18})$/D', $value) ? $value : '';
}

function mv_bale_channel_url(array $settings): string {
    if ($settings['channel_url']) {
        return $settings['channel_url'];
    }
    return str_starts_with($settings['channel'], '@') ? 'https://ble.ir/' . substr($settings['channel'], 1) : '';
}

function mv_bale_public_base(string $url): string {
    $parts = wp_parse_url($url);
    $host = strtolower($parts['host'] ?? '');
    if (($parts['scheme'] ?? '') !== 'https' || !preg_match('/^[a-z0-9-]+(?:\.[a-z0-9-]+)+$/D', $host)
        || filter_var($host, FILTER_VALIDATE_IP) || preg_match('/\.(?:local|localhost|test|example|invalid|internal)$/D', $host)
        || array_intersect_key($parts ?: [], array_flip(['user', 'pass', 'port', 'query', 'fragment']))) {
        return '';
    }
    return untrailingslashit(esc_url_raw($url, ['https']));
}

function mv_bale_token_valid(string $token): bool {
    return (bool) preg_match('/^[0-9]{3,20}:[A-Za-z0-9_-]{16,180}$/D', $token);
}

function mv_bale_token_key(): string {
    return hash_hmac('sha256', 'moalem-commerce-bale-token', wp_salt('auth'), true);
}

function mv_bale_connection_signature(string $token, string $channel): string {
    return hash_hmac('sha256', $channel . '|' . $token, mv_bale_token_key());
}

function mv_bale_verified(string $token, string $channel): bool {
    return $token !== '' && $channel !== '' && hash_equals((string) get_option('mv_bale_verified', ''),
        mv_bale_connection_signature($token, $channel));
}

function mv_bale_token(): string {
    $external = defined('MV_BALE_BOT_TOKEN') ? (string) MV_BALE_BOT_TOKEN : (string) getenv('MV_BALE_BOT_TOKEN');
    if ($external !== '') {
        return mv_bale_token_valid($external) ? $external : '';
    }
    $stored = base64_decode((string) get_option('mv_bale_token', ''), true);
    if (!$stored || strlen($stored) <= 28 || !function_exists('openssl_decrypt')) {
        return '';
    }
    $token = openssl_decrypt(substr($stored, 28), 'aes-256-gcm', mv_bale_token_key(), OPENSSL_RAW_DATA,
        substr($stored, 0, 12), substr($stored, 12, 16));
    return is_string($token) && mv_bale_token_valid($token) ? $token : '';
}

function mv_bale_store_token(string $token): bool {
    if (!mv_bale_token_valid($token) || !function_exists('openssl_encrypt')) {
        return false;
    }
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($token, 'aes-256-gcm', mv_bale_token_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return is_string($cipher) && update_option('mv_bale_token', base64_encode($iv . $tag . $cipher), false);
}

function mv_bale_text(string $value): string {
    return trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?? '');
}

function mv_bale_limit_text(string $value, int $length): string {
    return mb_strlen($value) > $length ? rtrim(mb_substr($value, 0, $length - 1)) . '…' : $value;
}

function mv_bale_price(WC_Product $product): string {
    if ($product->is_type('variable')) {
        $min = $product->get_variation_price('min', true);
        $max = $product->get_variation_price('max', true);
        if (!is_numeric($min) || !is_numeric($max)) {
            return '';
        }
        $low = mv_bale_text(wc_price($min));
        return (float) $min === (float) $max ? $low : $low . ' تا ' . mv_bale_text(wc_price($max));
    }
    return is_numeric($product->get_price()) ? mv_bale_text(wc_price(wc_get_price_to_display($product))) : '';
}

function mv_bale_post_url(WP_Post $post, string $base): string {
    $base = mv_bale_public_base($base ?: home_url('/'));
    if (!$base) {
        return '';
    }
    $path = wp_make_link_relative(get_permalink($post));
    $home_path = rtrim((string) wp_parse_url(home_url('/'), PHP_URL_PATH), '/');
    if ($home_path !== '' && str_starts_with($path, $home_path . '/')) {
        $path = substr($path, strlen($home_path));
    }
    return $base . '/' . ltrim($path, '/');
}

function mv_bale_message(WP_Post $post, array $settings, bool $preview = false): ?array {
    $url = mv_bale_post_url($post, $settings['public_base']) ?: ($preview ? get_permalink($post) : '');
    if (!$url) {
        return null;
    }
    $product = $post->post_type === 'product' && function_exists('wc_get_product') ? wc_get_product($post->ID) : null;
    $blocks = [($product ? '🛍 ' : '📖 ') . mv_bale_limit_text(mv_bale_text(get_the_title($post)), 170)];
    if ($product) {
        $terms = get_the_terms($post->ID, 'product_cat');
        if (is_array($terms)) {
            $blocks[] = '🏷 ' . mv_bale_limit_text(implode('، ', wp_list_pluck(array_slice($terms, 0, 2), 'name')), 120);
        }
        if ($price = mv_bale_price($product)) {
            $blocks[] = '💳 قیمت ' . $price;
        }
        if (!$product->is_in_stock()) {
            $blocks[] = 'موجودی این کالا تمام شده است.';
        }
    }
    $excerpt = mv_bale_text($product ? $product->get_short_description() : $post->post_excerpt);
    if ($excerpt === '') {
        $excerpt = mv_bale_text(strip_shortcodes($post->post_content));
    }
    if ($excerpt !== '') {
        $blocks[] = mv_bale_limit_text($excerpt, 340);
    }
    $note = (string) get_post_meta($post->ID, '_mv_bale_note', true);
    foreach ([$note, $settings['footer']] as $line) {
        if ($line !== '') {
            $blocks[] = mv_bale_limit_text(sanitize_textarea_field($line), 180);
        }
    }
    $button = $product ? $settings['product_button'] : $settings['post_button'];
    return [
        'text' => implode("\n\n", $blocks),
        'photo' => (int) get_post_thumbnail_id($post),
        'reply_markup' => ['inline_keyboard' => [[['text' => $button, 'url' => $url]]]],
    ];
}

function mv_bale_photo(int $attachment): ?array {
    $file = $attachment ? get_attached_file($attachment) : false;
    $path = $file ? realpath($file) : false;
    $uploads = realpath(wp_get_upload_dir()['basedir']);
    if (!$path || !$uploads || !str_starts_with(wp_normalize_path($path), trailingslashit(wp_normalize_path($uploads)))
        || !is_readable($path) || filesize($path) > 8 * MB_IN_BYTES) {
        return null;
    }
    $mime = wp_get_image_mime($path);
    if (in_array($mime, ['image/jpeg', 'image/png'], true) && filesize($path) <= 5 * MB_IN_BYTES) {
        $bytes = file_get_contents($path);
        return $bytes ? [$mime, $bytes] : null;
    }
    if (!in_array($mime, ['image/webp', 'image/avif', 'image/jpeg', 'image/png'], true)) {
        return null;
    }
    $editor = wp_get_image_editor($path);
    if (is_wp_error($editor)) {
        return null;
    }
    require_once ABSPATH . 'wp-admin/includes/file.php';
    $temporary = wp_tempnam('mv-bale-photo.png');
    if (!$temporary) {
        return null;
    }
    $editor->resize(1000, 1000, false);
    $saved = $editor->save($temporary, 'image/png');
    $photo = null;
    if (!is_wp_error($saved)) {
        $file = $saved['path'];
        if (is_readable($file) && filesize($file) <= 5 * MB_IN_BYTES && ($bytes = file_get_contents($file))) {
            $photo = ['image/png', $bytes];
        }
        if ($file !== $temporary) {
            wp_delete_file($file);
        }
    }
    wp_delete_file($temporary);
    return $photo;
}

function mv_bale_request(string $token, string $method, array $payload = [], ?array $photo = null) {
    if (!mv_bale_token_valid($token)) {
        return new WP_Error('bale_settings');
    }
    $type = 'application/json; charset=UTF-8';
    $body = wp_json_encode((object) $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($photo) {
        $boundary = 'mv-' . bin2hex(random_bytes(24));
        $body = '';
        foreach ($payload as $key => $value) {
            $part = is_array($value) ? wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $value;
            $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$key\"\r\n\r\n$part\r\n";
        }
        [$mime, $content] = $photo;
        $filename = $mime === 'image/jpeg' ? 'product.jpg' : 'product.png';
        $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"photo\"; filename=\"$filename\"\r\n";
        $body .= "Content-Type: $mime\r\n\r\n$content\r\n--$boundary--\r\n";
        $type = 'multipart/form-data; boundary=' . $boundary;
    }
    $response = wp_safe_remote_post(MV_BALE_API . $token . '/' . $method, [
        'timeout' => 10, 'redirection' => 0, 'limit_response_size' => 65536,
        'headers' => ['Content-Type' => $type], 'body' => $body,
    ]);
    if (is_wp_error($response)) {
        return new WP_Error('bale_uncertain');
    }
    $http = (int) wp_remote_retrieve_response_code($response);
    $reply = json_decode(wp_remote_retrieve_body($response), true, 512, JSON_BIGINT_AS_STRING);
    if ($http >= 200 && $http < 300 && ($reply['ok'] ?? false) === true && is_array($reply['result'] ?? null)) {
        return $reply['result'];
    }
    if (($reply['ok'] ?? null) === false) {
        $status = (int) ($reply['error_code'] ?? $http);
        if ($status === 429) {
            $delay = (int) ($reply['parameters']['retry_after'] ?? 60);
            return new WP_Error('bale_rate', '', max(5, min(3600, $delay)));
        }
        if ($status >= 400 && $status < 500) {
            return new WP_Error('bale_rejected', '', $status);
        }
    }
    return new WP_Error('bale_uncertain');
}

require_once __DIR__ . '/bale-queue.php';
require_once __DIR__ . '/bale-admin.php';
