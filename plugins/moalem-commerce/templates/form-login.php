<?php
defined('ABSPATH') || exit;
$mode = mv_commerce_account_mode();
$is_modal = mv_commerce_modal_request();
$phone = mv_commerce_phone(mv_commerce_field('mv_phone'));
$session_phone = WC()->session ? (string) WC()->session->get('mv_otp_phone', '') : '';
if ($mode !== 'login' && !$phone) {
    $retry_phone = $mode === 'otp' && WC()->session ? (string) WC()->session->get('mv_otp_retry_phone', '') : '';
    $phone = $retry_phone ?: $session_phone;
}
$timing = $mode === 'verify' ? mv_commerce_account_otp_timing($phone) : null;
$now = time();
$valid_for = max(0, ($timing['expires'] ?? 0) - $now);
$resend_in = max(0, ($timing['resend'] ?? 0) - $now);
$timer_label = static fn(int $seconds): string => strtr(
    sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60),
    array_combine(str_split('0123456789'), ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'])
);
$titles = ['login' => 'ورود به حساب', 'otp' => 'ورود یا عضویت با پیامک', 'verify' => 'تأیید شماره موبایل', 'register' => 'ساخت حساب'];
$descriptions = [
    'login' => 'با شماره موبایل و رمز عبور وارد حساب‌تان شوید.',
    'otp' => 'شماره موبایل‌تان را وارد کنید تا کد ورود را برایتان پیامک کنیم.',
    'register' => 'شماره موبایل و رمز دلخواه‌تان را وارد کنید تا حساب شما ساخته شود.',
    'verify' => $timing ? 'کد شش‌رقمی پیامک‌شده به شمارهٔ زیر را وارد کنید.' : 'اگر کد دارید، شماره موبایل و کد شش‌رقمی را وارد کنید.',
];
$actions = ['login' => 'ورود', 'otp' => 'ارسال کد ورود', 'verify' => 'تأیید و ورود', 'register' => 'ساخت حساب و ادامه'];
$image = 'assets/hero-boots-780.webp';
?>
<div class="mv-account">
    <div class="mv-account-panel">
        <?php if ($is_modal) : ?><h2 id="mv-account-title"><?php echo esc_html($titles[$mode]); ?></h2>
        <?php else : ?><h1><?php echo esc_html($titles[$mode]); ?></h1><?php endif; ?>
        <p class="mv-account-intro" id="mv-account-intro"><?php echo esc_html($descriptions[$mode]); ?></p>
        <?php if ($mode !== 'verify') : ?>
            <nav class="mv-account-method" aria-label="روش ورود">
                <a href="<?php echo esc_url(mv_commerce_account_url()); ?>"<?php if ($mode === 'login') echo ' aria-current="page"'; ?>>رمز عبور</a>
                <a href="<?php echo esc_url(mv_commerce_account_url('otp')); ?>"<?php if ($mode === 'otp') echo ' aria-current="page"'; ?> aria-label="ورود یا عضویت با رمز یک‌بارمصرف">رمز یک‌بارمصرف</a>
                <a href="<?php echo esc_url(mv_commerce_account_url('register')); ?>"<?php if ($mode === 'register') echo ' aria-current="page"'; ?>>ثبت‌نام</a>
            </nav>
        <?php endif; ?>
        <?php wc_print_notices(); ?>
        <form class="woocommerce-form woocommerce-form-login login" method="post" action="<?php echo esc_url(mv_commerce_account_url($mode)); ?>" aria-describedby="mv-account-intro"
            <?php if ($timing) : ?>data-mv-otp-timing data-valid-for="<?php echo esc_attr($valid_for); ?>" data-resend-in="<?php echo esc_attr($resend_in); ?>"<?php endif; ?>>
            <p class="form-row form-row-wide">
                <label for="mv-phone">شماره موبایل</label>
                <input id="mv-phone" name="mv_phone" type="tel" inputmode="tel" autocomplete="<?php echo $mode === 'login' ? 'username' : 'tel'; ?>"
                    dir="ltr" placeholder="09123456789" value="<?php echo esc_attr($phone); ?>" maxlength="24"
                    <?php if ($mode === 'verify' && $session_phone && $phone === $session_phone) echo 'readonly'; ?> required>
            </p>
            <?php if ($mode === 'verify') : ?>
                <p class="form-row form-row-wide">
                    <label for="mv-code">کد یک‌بارمصرف</label>
                    <input id="mv-code" name="mv_code" type="text" inputmode="numeric" autocomplete="one-time-code" dir="ltr" maxlength="6" required>
                </p>
                <?php if ($timing) : ?>
                    <p class="mv-otp-validity" data-mv-otp-active <?php if (!$valid_for) echo 'hidden'; ?>>
                        <span>زمان باقی‌مانده برای ورود کد</span>
                        <bdi dir="ltr" role="timer" data-mv-otp-count><?php echo esc_html($timer_label($valid_for)); ?></bdi>
                    </p>
                    <p class="mv-otp-validity mv-otp-expired" data-mv-otp-expired role="status" <?php if ($valid_for) echo 'hidden'; ?>>
                        کد قبلی دیگر معتبر نیست. برای ادامه یک کد جدید بگیرید.
                    </p>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($mode !== 'otp') : ?>
                <p class="form-row form-row-wide">
                    <label for="mv-password"><?php echo $mode === 'verify' ? 'رمز عبور (اختیاری)' : 'رمز عبور'; ?></label>
                    <span class="mv-password-field">
                        <input id="mv-password" name="mv_password" type="password" dir="ltr" maxlength="4096"
                            autocomplete="<?php echo $mode === 'login' ? 'current-password' : 'new-password'; ?>"
                            <?php if ($mode !== 'verify') echo 'required'; ?><?php if ($mode !== 'login') echo ' minlength="8" aria-describedby="mv-password-hint"'; ?>>
                    </span>
                    <?php if ($mode === 'verify') : ?><small id="mv-password-hint">اگر بدون پیامک ثبت‌نام کرده‌اید، رمز فعلی را برای اتصال به همان حساب وارد کنید. برای حساب تازه می‌توانید رمز دلخواه تعیین کنید.</small>
                    <?php elseif ($mode === 'register') : ?><small id="mv-password-hint">دست‌کم ۸ نویسه</small><?php endif; ?>
                </p>
            <?php endif; ?>
            <?php if ($mode === 'register') : ?>
                <p class="form-row form-row-wide">
                    <label for="mv-password-confirmation">تکرار رمز عبور</label>
                    <input id="mv-password-confirmation" name="mv_password_confirmation" type="password" dir="ltr" maxlength="4096" autocomplete="new-password" required>
                </p>
            <?php endif; ?>
            <?php wp_nonce_field(mv_commerce_account_nonce(), '_mv_nonce', false); ?>
            <input type="hidden" name="mv_action" value="<?php echo esc_attr($mode); ?>">
            <button class="button mv-account-submit" type="submit" <?php if ($timing && !$valid_for) echo 'disabled'; ?>><?php echo esc_html($actions[$mode]); ?></button>
            <?php if ($timing) : ?>
                <button class="mv-otp-resend" type="submit" name="mv_resend" value="1" formnovalidate>
                    ارسال دوبارهٔ کد
                    <?php if ($resend_in) : ?><bdi dir="ltr" data-mv-otp-resend-count><?php echo esc_html($timer_label($resend_in)); ?></bdi><?php endif; ?>
                </button>
            <?php endif; ?>
        </form>
        <?php if ($mode === 'verify') : ?>
            <div class="mv-account-links">
                <a href="<?php echo esc_url(mv_commerce_account_url('otp')); ?>"><?php echo $timing ? 'تغییر شماره' : 'درخواست کد یا تغییر شماره'; ?></a>
                <a href="<?php echo esc_url(mv_commerce_account_url()); ?>">ورود با رمز عبور</a>
            </div>
        <?php endif; ?>
        <?php if (($_GET['return'] ?? '') === 'checkout' && WC()->cart && !WC()->cart->is_empty()) : ?>
            <div class="mv-account-links"><a href="<?php echo esc_url(wc_get_checkout_url()); ?>">بازگشت به تسویه‌حساب</a></div>
        <?php endif; ?>
    </div>
    <?php if (!$is_modal && is_readable(get_theme_file_path($image))) : ?>
        <div class="mv-account-visual" aria-hidden="true">
            <img src="<?php echo esc_url(get_theme_file_uri($image)); ?>" width="780" height="622" alt="" loading="lazy">
        </div>
    <?php endif; ?>
</div>
