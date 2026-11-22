<?php
defined('ABSPATH') || exit;

function mv_commerce_otp_digest(string $phone, string $challenge, string $code, string $scope = 'otp'): string {
    return mv_commerce_key($scope . '-code', $phone . '|' . $challenge . '|' . $code);
}

function mv_commerce_otp_request(string $phone, string $ip, string $scope = 'otp', string $label = 'کد ورود') {
    $key = mv_commerce_key($scope, $phone);
    $now = time();
    $previous = mv_commerce_read($key);
    $previous_data = json_decode($previous['payload'] ?? '{}', true) ?: [];
    if (($previous_data['resend'] ?? 0) > $now) {
        return new WP_Error('otp_cooldown');
    }
    $window = 15 * MINUTE_IN_SECONDS;
    if (!mv_commerce_limit(mv_commerce_key('otp-send-ip', $ip), 8, $window)
        || !mv_commerce_limit(mv_commerce_key($scope . '-send-phone', $phone), 8, $window)) {
        return new WP_Error('otp_limit');
    }

    $code = (string) random_int(100000, 999999);
    $challenge = bin2hex(random_bytes(16));
    // Reserve the send while the provider responds. The new code cannot be used yet.
    $data = [
        'id' => $challenge,
        'digest' => '',
        'attempts' => 0,
        'resend' => $now + MINUTE_IN_SECONDS,
    ];
    if (!mv_commerce_write($key, $previous, $data['resend'], $data)) {
        return new WP_Error('otp_unavailable');
    }
    $reservation = mv_commerce_read($key);
    if (!$reservation || (json_decode($reservation['payload'], true)['id'] ?? '') !== $challenge) {
        return new WP_Error('otp_unavailable');
    }
    $result = mv_commerce_sms($phone, "معلم ورزش | $label: $code\nاعتبار کد: ۵ دقیقه");
    if (is_wp_error($result)) {
        mv_commerce_write($key, $reservation, (int) ($previous['expires'] ?? $now), $previous_data);
        return $result;
    }
    $accepted_at = time();
    $data['digest'] = mv_commerce_otp_digest($phone, $challenge, $code, $scope);
    $data['resend'] = $accepted_at + MINUTE_IN_SECONDS;
    return mv_commerce_write($key, $reservation, $accepted_at + 5 * MINUTE_IN_SECONDS, $data)
        ? true
        : new WP_Error('otp_unavailable');
}

function mv_commerce_otp_verify(string $phone, string $code, string $scope = 'otp'): bool {
    $key = mv_commerce_key($scope, $phone);
    for ($attempt = 0; $attempt < 4; ++$attempt) {
        $row = mv_commerce_read($key);
        if (!$row || (int) $row['expires'] <= time()) {
            return false;
        }
        $data = json_decode($row['payload'], true);
        if (empty($data['digest']) || $data['attempts'] >= 5) {
            return false;
        }
        $matches = hash_equals($data['digest'], mv_commerce_otp_digest($phone, $data['id'], $code, $scope));
        ++$data['attempts'];
        if ($matches) {
            $data['digest'] = '';
        }
        if (mv_commerce_write($key, $row, (int) $row['expires'], $data)) {
            return $matches;
        }
    }
    return false;
}

function mv_commerce_customer_for_mobile(string $phone, string $meta): int {
    $users = get_users([
        'meta_key' => $meta,
        'meta_value' => $phone,
        'role' => 'customer',
        'fields' => 'ID',
        'number' => 2,
    ]);
    return count($users) === 1 && !user_can($users[0], 'edit_users')
        && !mv_commerce_is_manager((int) $users[0]) ? (int) $users[0] : 0;
}

function mv_commerce_find_customer(string $phone): int {
    return mv_commerce_customer_for_mobile($phone, '_mv_verified_mobile');
}

function mv_commerce_with_mobile_lock(string $phone, callable $action) {
    global $wpdb;
    $lock = mv_commerce_key('customer-mobile-lock', $phone);
    if ((string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 3)', $lock)) !== '1') {
        return new WP_Error('account_busy');
    }
    try {
        return $action();
    } finally {
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
    }
}

function mv_commerce_create_customer(string $phone, string $password, bool $verified) {
    return wp_insert_user([
        'user_login' => 'mv-' . substr(mv_commerce_key($verified ? 'customer' : 'temporary-customer', $phone), 0, 36),
        'user_pass' => $password !== '' ? $password : wp_generate_password(32, true, true),
        'display_name' => 'کاربر فروشگاه',
        'role' => 'customer',
        'meta_input' => [($verified ? '_mv_verified_mobile' : '_mv_unverified_mobile') => $phone, 'billing_phone' => $phone],
    ]);
}

function mv_commerce_mobile_in_use(string $phone, array $keys): bool {
    $query = ['relation' => 'OR'];
    foreach ($keys as $key) {
        $query[] = ['key' => $key, 'value' => $phone];
    }
    return (bool) get_users(['fields' => 'ID', 'number' => 1, 'meta_query' => $query]);
}

function mv_commerce_valid_password(string $password): bool {
    return mb_strlen($password) >= 8 && strlen($password) <= 4096 && trim($password) === $password && !str_contains($password, "\0");
}

function mv_commerce_register_customer(string $phone, string $password, string $ip) {
    if (!mv_commerce_valid_password($password)) {
        return new WP_Error('weak_password');
    }
    if (!mv_commerce_limit(mv_commerce_key('register-ip', $ip), 10, 15 * MINUTE_IN_SECONDS)
        || !mv_commerce_limit(mv_commerce_key('register-phone', $phone), 4, 15 * MINUTE_IN_SECONDS)) {
        return new WP_Error('register_limit');
    }
    return mv_commerce_with_mobile_lock($phone, static function () use ($phone, $password) {
        if (mv_commerce_mobile_in_use($phone, ['_mv_verified_mobile', '_mv_unverified_mobile'])) {
            return new WP_Error('mobile_in_use');
        }
        return mv_commerce_create_customer($phone, $password, false);
    });
}

function mv_commerce_user_for_verified_phone(string $phone, string $password) {
    return mv_commerce_with_mobile_lock($phone, static function () use ($phone, $password) {
        if ($id = mv_commerce_find_customer($phone)) {
            if ($password !== '') {
                wp_set_password($password, $id);
            }
            return $id;
        }
        if (mv_commerce_mobile_in_use($phone, ['_mv_verified_mobile'])) {
            return new WP_Error('mobile_in_use');
        }
        // A code proves ownership of the phone. Retaining the password-only account also requires its password.
        $pending = get_userdata(mv_commerce_customer_for_mobile($phone, '_mv_unverified_mobile'));
        if ($pending && $password !== '' && wp_check_password($password, $pending->user_pass, $pending->ID)) {
            if (!update_user_meta($pending->ID, '_mv_verified_mobile', $phone)) {
                return new WP_Error('account_save_failed');
            }
            delete_user_meta($pending->ID, '_mv_unverified_mobile', $phone);
            return $pending->ID;
        }
        return mv_commerce_create_customer($phone, $password, true);
    });
}

function mv_commerce_password_login(string $phone, string $password, string $ip) {
    if (!mv_commerce_limit(mv_commerce_key('password-ip', $ip), 30, 15 * MINUTE_IN_SECONDS)
        || !mv_commerce_limit(mv_commerce_key('password-phone', $phone), 8, 15 * MINUTE_IN_SECONDS)) {
        return new WP_Error('login_limit');
    }
    if ($password === '' || strlen($password) > 4096) {
        return new WP_Error('invalid_login');
    }
    $user = get_userdata(mv_commerce_find_customer($phone));
    $pending = get_userdata(mv_commerce_customer_for_mobile($phone, '_mv_unverified_mobile'));
    if ($pending && (!$user || (!wp_check_password($password, $user->user_pass, $user->ID)
        && wp_check_password($password, $pending->user_pass, $pending->ID)))) {
        $user = $pending;
    }
    if (!$user) {
        return new WP_Error('invalid_login');
    }
    return wp_signon(['user_login' => $user->user_login, 'user_password' => $password, 'remember' => true], is_ssl());
}

function mv_commerce_account_mode(): string {
    $mode = isset($_GET['mode']) && is_string($_GET['mode']) ? sanitize_key($_GET['mode']) : '';
    return in_array($mode, ['otp', 'verify', 'register'], true) ? $mode : 'login';
}

function mv_commerce_modal_request(): bool {
    return ($_GET['mv_modal'] ?? '') === '1';
}

function mv_commerce_account_url(string $mode = 'login'): string {
    $args = $mode === 'login' ? [] : ['mode' => $mode];
    $return = $_GET['return'] ?? '';
    if (in_array($return, ['cart', 'checkout', 'product'], true)) {
        $args['return'] = $return;
        if ($return === 'product' && is_scalar($_GET['mv_item'] ?? null)) {
            $args['mv_item'] = absint($_GET['mv_item']);
        }
    }
    if (mv_commerce_modal_request()) {
        $args['mv_modal'] = '1';
    }
    return add_query_arg($args, wc_get_page_permalink('myaccount'));
}

function mv_commerce_account_nonce(): string {
    return 'mv-account-' . ($GLOBALS['mv_account_session'] ?? '');
}

function mv_commerce_account_session(): void {
    $cookie = $_COOKIE['mv_account_session'] ?? '';
    if (is_string($cookie) && preg_match('/^[a-f0-9]{48}$/D', $cookie)) {
        $GLOBALS['mv_account_session'] = $cookie;
        return;
    }
    $cookie = bin2hex(random_bytes(24));
    $GLOBALS['mv_account_session'] = $cookie;
    setcookie('mv_account_session', $cookie, [
        'path' => COOKIEPATH ?: '/',
        'secure' => is_ssl(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function mv_commerce_field(string $key): string {
    return isset($_POST[$key]) && is_string($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
}

function mv_commerce_account_error(string $message): void {
    wc_add_notice($message, 'error');
}

function mv_commerce_account_redirect(): void {
    $return = $_GET['return'] ?? '';
    $target = match ($return) {
        'checkout' => wc_get_checkout_url(),
        'cart' => wc_get_cart_url(),
        'product' => mv_commerce_purchase_return_url(),
        default => wc_get_page_permalink('myaccount'),
    };
    if (mv_commerce_modal_request()) {
        $target = add_query_arg('mv_modal', '1', wc_get_page_permalink('myaccount'));
    }
    wp_safe_redirect($target, 303);
    exit;
}

function mv_commerce_start_customer_session(int $id): void {
    $user = wp_set_current_user($id);
    wp_set_auth_cookie($id, true, is_ssl());
    do_action('wp_login', $user->user_login, $user);
}

function mv_commerce_account_otp_timing(string $phone): ?array {
    $session = WC()->session;
    if (!$phone || !$session || $phone !== $session->get('mv_otp_phone', '')) {
        return null;
    }
    $row = mv_commerce_read(mv_commerce_key('otp', $phone));
    $data = json_decode($row['payload'] ?? '{}', true);
    $usable = !empty($data['digest']) && (int) ($data['attempts'] ?? 5) < 5;
    return [
        'expires' => $usable ? (int) $row['expires'] : 0,
        'resend' => (int) ($data['resend'] ?? 0),
    ];
}

add_action('wp_enqueue_scripts', static function (): void {
    if (function_exists('is_account_page') && is_account_page() && !is_user_logged_in() && mv_commerce_account_mode() === 'verify') {
        wp_enqueue_script('mv-account-otp', plugins_url('assets/account-otp.js', __FILE__), [], '1.5.0', ['in_footer' => true, 'strategy' => 'defer']);
    }
});

add_action('template_redirect', static function (): void {
    if (!function_exists('is_account_page') || !is_account_page() || is_user_logged_in()) {
        return;
    }
    nocache_headers();
    $purpose = strtolower((string) ($_SERVER['HTTP_SEC_PURPOSE'] ?? $_SERVER['HTTP_PURPOSE'] ?? ''));
    if (str_contains($purpose, 'prefetch')) {
        status_header(204);
        exit;
    }
    mv_commerce_account_session();
    if (is_wc_endpoint_url('lost-password')) {
        wp_safe_redirect(mv_commerce_account_url('otp'), 302);
        exit;
    }
    $action = mv_commerce_field('mv_action');
    if (!in_array($action, ['login', 'otp', 'verify', 'register'], true)) {
        return;
    }
    if (!wp_verify_nonce(mv_commerce_field('_mv_nonce'), mv_commerce_account_nonce())) {
        mv_commerce_account_error('فرم منقضی شده است. صفحه را دوباره باز کنید.');
        return;
    }
    $phone = mv_commerce_phone(mv_commerce_field('mv_phone'));
    if (!$phone) {
        mv_commerce_account_error('شماره موبایل را به شکل ۰۹۱۲۳۴۵۶۷۸۹ وارد کنید.');
        return;
    }
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    if ($action === 'register') {
        $password = mv_commerce_field('mv_password');
        if ($password !== mv_commerce_field('mv_password_confirmation')) {
            mv_commerce_account_error('تکرار رمز عبور را دوباره بررسی کنید.');
            return;
        }
        $result = mv_commerce_register_customer($phone, $password, $ip);
        if (is_wp_error($result)) {
            mv_commerce_account_error(match ($result->get_error_code()) {
                'weak_password' => 'رمز عبور باید دست‌کم ۸ نویسه داشته باشد و با فاصله شروع یا تمام نشود.',
                'register_limit' => 'تعداد درخواست‌ها بیش از حد مجاز است. کمی بعد دوباره تلاش کنید.',
                default => 'ساخت حساب انجام نشد. اگر حساب دارید، از یکی از روش‌های ورود استفاده کنید.',
            });
            return;
        }
        mv_commerce_start_customer_session($result);
        mv_commerce_account_redirect();
    }
    if ($action === 'login') {
        $result = mv_commerce_password_login($phone, mv_commerce_field('mv_password'), $ip);
        if (is_wp_error($result)) {
            mv_commerce_account_error('شماره موبایل یا رمز عبور درست نیست. می‌توانید با رمز یک‌بارمصرف وارد شوید.');
            return;
        }
        mv_commerce_account_redirect();
    }
    $resend = $action === 'verify' && mv_commerce_field('mv_resend') === '1';
    if ($action === 'otp' || $resend) {
        $timing = mv_commerce_account_otp_timing($phone);
        $now = time();
        if ($action === 'otp' && ($timing['expires'] ?? 0) > $now) {
            WC()->session->__unset('mv_otp_retry_phone');
            wp_safe_redirect(mv_commerce_account_url('verify'), 303);
            exit;
        }
        if ($resend && (!$timing || $timing['resend'] > $now)) {
            mv_commerce_account_error($timing
                ? 'برای ارسال دوباره، تا پایان شمارش معکوس صبر کنید.'
                : 'برای درخواست کد، شماره موبایل را دوباره وارد کنید.');
            return;
        }
        if (random_int(1, 30) === 1) {
            mv_commerce_prune();
        }
        $result = mv_commerce_otp_request($phone, $ip);
        if (is_wp_error($result)) {
            mv_commerce_account_error(match ($result->get_error_code()) {
                'otp_limit' => 'تعداد درخواست‌ها بیش از حد مجاز است. کمی بعد دوباره تلاش کنید.',
                'otp_cooldown' => 'برای درخواست دوبارهٔ کد، یک دقیقه صبر کنید.',
                'sms_billing' => 'ارسال کد پیامکی فعلاً در دسترس نیست. اگر رمز عبور دارید، از گزینهٔ «رمز عبور» وارد شوید.',
                default => 'ارسال پیامک تأیید نشد. می‌توانید دوباره درخواست کد بدهید.',
            });
            if (!$resend) {
                WC()->session->set('mv_otp_retry_phone', $phone);
            }
            WC()->session->set_customer_session_cookie(true);
            wp_safe_redirect(mv_commerce_account_url($resend ? 'verify' : 'otp'), 303);
            exit;
        }
        WC()->session->__unset('mv_otp_retry_phone');
        WC()->session->set('mv_otp_phone', $phone);
        WC()->session->set_customer_session_cookie(true);
        wp_safe_redirect(mv_commerce_account_url('verify'), 303);
        exit;
    }

    $code = mv_commerce_digits(mv_commerce_field('mv_code'));
    $password = mv_commerce_field('mv_password');
    if ($password !== '' && !mv_commerce_valid_password($password)) {
        mv_commerce_account_error('رمز جدید باید دست‌کم ۸ نویسه داشته باشد و با فاصله شروع یا تمام نشود.');
        return;
    }
    if (!mv_commerce_limit(mv_commerce_key('otp-verify-ip', $ip), 40, 15 * MINUTE_IN_SECONDS)
        || !preg_match('/^[0-9]{6}$/D', $code)
        || !mv_commerce_otp_verify($phone, $code)) {
        mv_commerce_account_error('کد ورود درست نیست یا اعتبار آن تمام شده است.');
        return;
    }
    $id = mv_commerce_user_for_verified_phone($phone, $password);
    if (is_wp_error($id)) {
        mv_commerce_account_error('ورود انجام نشد. دوباره کد ورود درخواست کنید.');
        return;
    }
    WC()->session->__unset('mv_otp_phone');
    mv_commerce_start_customer_session($id);
    mv_commerce_account_redirect();
}, 9);

add_action('template_redirect', static function (): void {
    if (!function_exists('is_account_page') || !is_account_page() || !mv_commerce_modal_request()) {
        return;
    }
    nocache_headers();
    header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
    if (is_user_logged_in()) {
        echo '<span data-mv-authenticated></span>';
    } else {
        require __DIR__ . '/templates/form-login.php';
    }
    exit;
}, 11);

add_filter('woocommerce_locate_template', static function (string $path, string $name): string {
    return $name === 'myaccount/form-login.php' ? __DIR__ . '/templates/form-login.php' : $path;
}, 20, 2);

add_action('wp', static function (): void {
    if (!function_exists('is_checkout') || !is_checkout() || is_user_logged_in()) {
        return;
    }
    remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10);
    if (get_option('woocommerce_enable_checkout_login_reminder') !== 'yes') {
        return;
    }
    add_action('woocommerce_before_checkout_form', static function (): void {
        $url = add_query_arg('return', 'checkout', wc_get_page_permalink('myaccount'));
        printf('<p class="mv-checkout-login"><span>از قبل حساب دارید؟</span><a href="%s">ورود با شماره موبایل</a></p>', esc_url($url));
    }, 9);
});
