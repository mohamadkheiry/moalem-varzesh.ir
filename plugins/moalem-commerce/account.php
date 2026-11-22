<?php
defined('ABSPATH') || exit;

function mv_commerce_otp_digest(string $phone, string $challenge, string $code, string $scope = 'otp'): string {
    return mv_commerce_key($scope . '-code', $phone . '|' . $challenge . '|' . $code);
}

function mv_commerce_otp_request(string $phone, string $ip, string $scope = 'otp', string $label = 'کد ورود') {
    if (!mv_commerce_limit(mv_commerce_key('otp-send-ip', $ip), 8, 15 * MINUTE_IN_SECONDS)) {
        return new WP_Error('otp_limit');
    }

    $key = mv_commerce_key($scope, $phone);
    $now = time();
    $previous = mv_commerce_read($key);
    $previous_data = $previous ? json_decode($previous['payload'], true) : [];
    if (($previous_data['resend'] ?? 0) > $now) {
        return new WP_Error('otp_limit');
    }

    $code = (string) random_int(100000, 999999);
    $challenge = bin2hex(random_bytes(16));
    $data = [
        'id' => $challenge,
        'digest' => mv_commerce_otp_digest($phone, $challenge, $code, $scope),
        'attempts' => 0,
        'resend' => $now + MINUTE_IN_SECONDS,
    ];
    $expires = $now + 5 * MINUTE_IN_SECONDS;
    if (!mv_commerce_write($key, $previous, $expires, $data)) {
        return new WP_Error('otp_limit');
    }
    $result = mv_commerce_sms($phone, "معلم ورزش | $label: $code\nاعتبار کد: ۵ دقیقه");
    if (is_wp_error($result)) {
        $current = mv_commerce_read($key);
        $current_data = $current ? json_decode($current['payload'], true) : [];
        if (($current_data['id'] ?? '') === $challenge) {
            $current_data['digest'] = '';
            mv_commerce_write($key, $current, $expires, $current_data);
        }
    }
    return $result;
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

function mv_commerce_find_customer(string $phone): int {
    $users = get_users([
        'meta_key' => '_mv_verified_mobile',
        'meta_value' => $phone,
        'role' => 'customer',
        'fields' => 'ID',
        'number' => 2,
    ]);
    return count($users) === 1 && !user_can($users[0], 'edit_users')
        && !mv_commerce_is_manager((int) $users[0]) ? (int) $users[0] : 0;
}

function mv_commerce_user_for_verified_phone(string $phone, string $password) {
    $id = mv_commerce_find_customer($phone);
    if ($id) {
        if ($password !== '') {
            wp_set_password($password, $id);
        }
        return $id;
    }
    $id = wp_insert_user([
        'user_login' => 'mv-' . substr(mv_commerce_key('customer', $phone), 0, 36),
        'user_pass' => $password !== '' ? $password : wp_generate_password(32, true, true),
        'display_name' => 'کاربر فروشگاه',
        'role' => 'customer',
        'meta_input' => ['_mv_verified_mobile' => $phone, 'billing_phone' => $phone],
    ]);
    return $id;
}

function mv_commerce_password_login(string $phone, string $password, string $ip) {
    if (!mv_commerce_limit(mv_commerce_key('password-ip', $ip), 30, 15 * MINUTE_IN_SECONDS)
        || !mv_commerce_limit(mv_commerce_key('password-phone', $phone), 8, 15 * MINUTE_IN_SECONDS)) {
        return new WP_Error('login_limit');
    }
    $user = get_userdata(mv_commerce_find_customer($phone));
    if (!$user || $password === '' || strlen($password) > 4096) {
        return new WP_Error('invalid_login');
    }
    return wp_signon(['user_login' => $user->user_login, 'user_password' => $password, 'remember' => true], is_ssl());
}

function mv_commerce_account_mode(): string {
    $mode = isset($_GET['mode']) && is_string($_GET['mode']) ? sanitize_key($_GET['mode']) : '';
    return in_array($mode, ['otp', 'verify'], true) ? $mode : 'login';
}

function mv_commerce_account_url(string $mode = 'login'): string {
    $args = $mode === 'login' ? [] : ['mode' => $mode];
    if (($_GET['return'] ?? '') === 'checkout') {
        $args['return'] = 'checkout';
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
    $target = ($_GET['return'] ?? '') === 'checkout' ? wc_get_checkout_url() : wc_get_page_permalink('myaccount');
    wp_safe_redirect($target, 303);
    exit;
}

add_action('template_redirect', static function (): void {
    if (!function_exists('is_account_page') || !is_account_page() || is_user_logged_in()) {
        return;
    }
    mv_commerce_account_session();
    nocache_headers();
    if (is_wc_endpoint_url('lost-password')) {
        wp_safe_redirect(mv_commerce_account_url('otp'), 302);
        exit;
    }
    $action = mv_commerce_field('mv_action');
    if (!in_array($action, ['login', 'otp', 'verify'], true)) {
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
    if ($action === 'login') {
        $result = mv_commerce_password_login($phone, mv_commerce_field('mv_password'), $ip);
        if (is_wp_error($result)) {
            mv_commerce_account_error('شماره موبایل یا رمز عبور درست نیست. می‌توانید با رمز یک‌بارمصرف وارد شوید.');
            return;
        }
        mv_commerce_account_redirect();
    }
    if ($action === 'otp') {
        if (random_int(1, 30) === 1) {
            mv_commerce_prune();
        }
        $result = mv_commerce_otp_request($phone, $ip);
        if (is_wp_error($result)) {
            mv_commerce_account_error($result->get_error_code() === 'otp_limit'
                ? 'تعداد درخواست‌ها بیش از حد مجاز است. کمی بعد دوباره تلاش کنید.'
                : 'درخواست پیامک تأیید نشد. کمی بعد دوباره تلاش کنید.');
            return;
        }
        WC()->session->set('mv_otp_phone', $phone);
        WC()->session->set_customer_session_cookie(true);
        wp_safe_redirect(mv_commerce_account_url('verify'), 303);
        exit;
    }

    $code = mv_commerce_digits(mv_commerce_field('mv_code'));
    $password = mv_commerce_field('mv_password');
    if ($password !== '' && (strlen($password) < 8 || strlen($password) > 4096 || trim($password) !== $password)) {
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
    $user = wp_set_current_user($id);
    wp_set_auth_cookie($id, true, is_ssl());
    do_action('wp_login', $user->user_login, $user);
    mv_commerce_account_redirect();
}, 9);

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
