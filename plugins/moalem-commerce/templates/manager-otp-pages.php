<?php
defined('ABSPATH') || exit;

add_action('admin_menu', static function (): void {
    if (mv_commerce_is_manager(get_current_user_id())) {
        add_submenu_page(current_user_can('list_users') ? 'users.php' : 'profile.php', 'ورود پیامکی مدیر',
            'ورود پیامکی مدیر', 'read', 'mv-manager-otp', 'mv_commerce_manager_enrollment_page');
    }
});

function mv_commerce_manager_enrollment_page(): void {
    $user = wp_get_current_user();
    $session = wp_get_session_token();
    if (!mv_commerce_is_manager($user->ID) || $session === '') {
        wp_die('دسترسی به تنظیمات ورود مدیر مجاز نیست.', '', ['response' => 403]);
    }

    $action = mv_commerce_field('mv_manager_action');
    $phone = mv_commerce_phone(mv_commerce_field('mv_manager_phone'));
    $verify = $action === 'verify';
    $notice = '';
    $error = '';
    if (in_array($action, ['send', 'verify', 'remove'], true)) {
        check_admin_referer('mv-manager-enrollment');
        if ($verify) {
            $code = mv_commerce_digits(mv_commerce_field('mv_manager_code'));
            if (preg_match('/^[0-9]{6}$/D', $code) && mv_commerce_verify_manager_enrollment($user->ID, $session, $phone, $code)) {
                $verify = false;
                $notice = 'شماره تأیید شد. ورود پیامکی مدیر فعال است.';
            } else {
                $error = 'تأیید شماره انجام نشد. کد را بررسی کنید یا دوباره کد بگیرید.';
            }
        } else {
            $password = mv_commerce_field('mv_manager_password');
            if (!mv_commerce_limit(mv_commerce_key('manager-enroll-password', (string) $user->ID), 8, 15 * MINUTE_IN_SECONDS)) {
                $error = 'تعداد تلاش‌ها بیش از حد مجاز است. کمی بعد دوباره تلاش کنید.';
            } elseif ($password === '' || strlen($password) > 4096 || !wp_check_password($password, $user->user_pass, $user->ID)) {
                $error = 'رمز عبور فعلی درست نیست.';
            } elseif ($action === 'remove') {
                if (mv_commerce_set_manager_identity($user->ID, '')) {
                    $notice = 'ورود پیامکی این حساب غیرفعال شد.';
                } else {
                    $error = 'تغییر ذخیره نشد. دوباره تلاش کنید.';
                }
            } elseif (!$phone) {
                $error = 'شماره موبایل معتبر وارد کنید.';
            } else {
                $owner = mv_commerce_manager_for_phone($phone);
                if ($owner && $owner['user_id'] !== $user->ID) {
                    $error = 'این شماره برای ورود یک مدیر دیگر ثبت شده است.';
                } else {
                    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
                    $result = mv_commerce_otp_request(
                        $phone, $ip, mv_commerce_manager_enrollment_scope($user->ID, $session), 'کد تأیید شمارهٔ ورود مدیر'
                    );
                    if ($result === true) {
                        $verify = true;
                        $notice = 'کد به شمارهٔ واردشده ارسال شد. کد را برای تأیید شماره وارد کنید.';
                    } else {
                        $error = 'ارسال کد تأیید نشد. کمی بعد دوباره تلاش کنید.';
                    }
                }
            }
        }
    }
    $identity = mv_commerce_manager_identity($user->ID);
    ?>
    <div class="wrap">
        <h1>ورود پیامکی مدیر</h1>
        <?php if ($notice !== '') : ?>
            <div class="notice notice-success"><p><?php echo esc_html($notice); ?></p></div>
        <?php endif; ?>
        <?php if ($error !== '') : ?>
            <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
        <?php endif; ?>
        <?php if ($identity) : ?>
            <p>شمارهٔ تأییدشدهٔ این حساب <bdi dir="ltr"><?php echo esc_html($identity['phone']); ?></bdi></p>
        <?php else : ?>
            <p>برای فعال کردن ورود پیامکی، شمارهٔ موبایل خود را تأیید کنید.</p>
        <?php endif; ?>
        <form method="post">
            <?php wp_nonce_field('mv-manager-enrollment'); ?>
            <?php if ($verify) : ?>
                <p>تأیید شمارهٔ <bdi dir="ltr"><?php echo esc_html($phone); ?></bdi></p>
                <input type="hidden" name="mv_manager_phone" value="<?php echo esc_attr($phone); ?>">
                <p>
                    <label for="mv-manager-enroll-code">کد تأیید شش‌رقمی</label><br>
                    <input id="mv-manager-enroll-code" name="mv_manager_code" inputmode="numeric" autocomplete="one-time-code"
                        dir="ltr" maxlength="6" required>
                </p>
                <button class="button button-primary" name="mv_manager_action" value="verify">تأیید شماره</button>
                <a class="button" href="<?php echo esc_url(menu_page_url('mv-manager-otp', false)); ?>">درخواست کد جدید</a>
            <?php else : ?>
                <p>
                    <label for="mv-manager-enroll-phone">شماره موبایل ورود</label><br>
                    <input id="mv-manager-enroll-phone" class="regular-text" name="mv_manager_phone" type="tel" inputmode="tel"
                        autocomplete="tel" dir="ltr" maxlength="24" required>
                </p>
                <p>
                    <label for="mv-manager-enroll-password">رمز عبور فعلی</label><br>
                    <input id="mv-manager-enroll-password" class="regular-text" name="mv_manager_password" type="password"
                        autocomplete="current-password" dir="ltr" maxlength="4096" required>
                </p>
                <button class="button button-primary" name="mv_manager_action" value="send">ارسال کد تأیید</button>
                <?php if ($identity) : ?>
                    <button class="button" name="mv_manager_action" value="remove" formnovalidate>غیرفعال کردن ورود پیامکی</button>
                <?php endif; ?>
            <?php endif; ?>
        </form>
    </div>
    <?php
}

function mv_commerce_manager_login_url(): string {
    return add_query_arg('action', 'mv_manager_otp', site_url('wp-login.php', 'login'));
}

add_action('login_form', static function (): void {
    printf('<p><a href="%s">ورود مدیر با رمز یک‌بارمصرف</a></p>', esc_url(mv_commerce_manager_login_url()));
});

add_action('login_form_mv_manager_otp', static function (): void {
    mv_commerce_account_session();
    $nonce_action = 'mv-manager-' . mv_commerce_account_nonce();
    $action = mv_commerce_field('mv_manager_action');
    $phone = mv_commerce_phone(mv_commerce_field('mv_manager_phone'));
    $verify = false;
    $notice = '';
    $errors = new WP_Error();

    if (in_array($action, ['send', 'verify'], true)) {
        if (!wp_verify_nonce(mv_commerce_field('_mv_manager_nonce'), $nonce_action)) {
            $errors->add('nonce', 'فرم منقضی شده است. دوباره تلاش کنید.');
        } elseif (!$phone) {
            $errors->add('phone', 'شماره موبایل معتبر وارد کنید.');
        } elseif ($action === 'send') {
            $verify = true;
            mv_commerce_request_manager_otp($phone, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
            $notice = 'اگر شماره برای ورود مدیر تأیید شده باشد، کد ورود به آن ارسال می‌شود.';
        } else {
            $verify = true;
            $code = mv_commerce_digits(mv_commerce_field('mv_manager_code'));
            $id = mv_commerce_verify_manager_otp($phone, $code, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
            if ($id) {
                $user = apply_filters('wp_authenticate_user', get_userdata($id), '');
                if ($user instanceof WP_User && $user->ID === $id && mv_commerce_is_manager($id)) {
                    wp_set_current_user($id);
                    wp_set_auth_cookie($id, false, is_ssl());
                    do_action('wp_login', $user->user_login, $user);
                    wp_safe_redirect(admin_url(), 303);
                    exit;
                }
            }
            $errors->add('otp', 'کد ورود درست نیست یا اعتبار آن تمام شده است.');
        }
    }

    login_header('ورود مدیر با رمز یک‌بارمصرف', $notice ? '<p class="message">' . esc_html($notice) . '</p>' : '', $errors);
    ?>
    <form name="mv-manager-login" method="post" action="<?php echo esc_url(mv_commerce_manager_login_url()); ?>">
        <p>
            <label for="mv-manager-phone">شماره موبایل مدیر</label>
            <input id="mv-manager-phone" class="input" name="mv_manager_phone" type="tel" inputmode="tel" autocomplete="tel"
                dir="ltr" maxlength="24" value="<?php echo esc_attr($phone); ?>" required>
        </p>
        <?php if ($verify) : ?>
            <p>
                <label for="mv-manager-code">رمز یک‌بارمصرف شش‌رقمی</label>
                <input id="mv-manager-code" class="input" name="mv_manager_code" inputmode="numeric" autocomplete="one-time-code"
                    dir="ltr" maxlength="6" required>
            </p>
        <?php endif; ?>
        <?php wp_nonce_field($nonce_action, '_mv_manager_nonce', false); ?>
        <p class="submit">
            <button class="button button-primary button-large" name="mv_manager_action" value="<?php echo $verify ? 'verify' : 'send'; ?>">
                <?php echo $verify ? 'ورود به پیشخوان' : 'ارسال کد ورود'; ?>
            </button>
        </p>
    </form>
    <p id="nav">
        <a href="<?php echo esc_url(wp_login_url()); ?>">ورود با رمز عبور</a>
        <?php if ($verify) : ?>
            | <a href="<?php echo esc_url(mv_commerce_manager_login_url()); ?>">درخواست کد جدید</a>
        <?php endif; ?>
    </p>
    <?php
    login_footer($verify ? 'mv-manager-code' : 'mv-manager-phone');
    exit;
});
