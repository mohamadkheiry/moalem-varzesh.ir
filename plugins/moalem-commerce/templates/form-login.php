<?php
defined('ABSPATH') || exit;
$mode = mv_commerce_account_mode();
$phone = mv_commerce_phone(mv_commerce_field('mv_phone'));
$session_phone = WC()->session ? (string) WC()->session->get('mv_otp_phone', '') : '';
if ($mode !== 'login' && !$phone) {
    $phone = $session_phone;
}
$titles = ['login' => 'ورود به حساب', 'otp' => 'ورود یا عضویت با پیامک', 'verify' => 'تأیید شماره موبایل'];
$descriptions = [
    'login' => 'با شماره موبایل و رمز عبور وارد حساب‌تان شوید.',
    'otp' => 'شماره موبایل‌تان را وارد کنید تا کد ورود را برایتان پیامک کنیم.',
    'verify' => $session_phone ? 'کد شش‌رقمی ارسال‌شده به شمارهٔ زیر را وارد کنید. این کد ۵ دقیقه اعتبار دارد.' : 'اگر کد دارید، شماره موبایل و کد شش‌رقمی را وارد کنید.',
];
$actions = ['login' => 'ورود', 'otp' => 'ارسال کد ورود', 'verify' => 'تأیید و ورود'];
$image = 'assets/hero-boots-780.webp';
?>
<div class="mv-account">
    <div class="mv-account-panel">
        <h1><?php echo esc_html($titles[$mode]); ?></h1>
        <p class="mv-account-intro" id="mv-account-intro"><?php echo esc_html($descriptions[$mode]); ?></p>
        <?php if ($mode !== 'verify') : ?>
            <nav class="mv-account-method" aria-label="روش ورود">
                <a href="<?php echo esc_url(mv_commerce_account_url()); ?>"<?php if ($mode === 'login') echo ' aria-current="page"'; ?>>رمز عبور</a>
                <a href="<?php echo esc_url(mv_commerce_account_url('otp')); ?>"<?php if ($mode === 'otp') echo ' aria-current="page"'; ?> aria-label="ورود یا عضویت با رمز یک‌بارمصرف">رمز یک‌بارمصرف</a>
            </nav>
        <?php endif; ?>
        <?php wc_print_notices(); ?>
        <form class="woocommerce-form woocommerce-form-login login" method="post" action="<?php echo esc_url(mv_commerce_account_url($mode)); ?>" aria-describedby="mv-account-intro">
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
            <?php endif; ?>
            <?php if ($mode !== 'otp') : ?>
                <p class="form-row form-row-wide">
                    <label for="mv-password"><?php echo $mode === 'login' ? 'رمز عبور' : 'رمز عبور جدید (اختیاری)'; ?></label>
                    <span class="mv-password-field">
                        <input id="mv-password" name="mv_password" type="password" dir="ltr" maxlength="4096"
                            autocomplete="<?php echo $mode === 'login' ? 'current-password' : 'new-password'; ?>"
                            <?php echo $mode === 'login' ? 'required' : 'minlength="8" aria-describedby="mv-password-hint"'; ?>>
                    </span>
                    <?php if ($mode === 'verify') : ?><small id="mv-password-hint">برای ورودهای بعدی می‌توانید یک رمز با دست‌کم ۸ نویسه انتخاب کنید.</small><?php endif; ?>
                </p>
            <?php endif; ?>
            <?php wp_nonce_field(mv_commerce_account_nonce(), '_mv_nonce', false); ?>
            <input type="hidden" name="mv_action" value="<?php echo esc_attr($mode); ?>">
            <button class="button mv-account-submit" type="submit"><?php echo esc_html($actions[$mode]); ?></button>
        </form>
        <?php if ($mode === 'verify') : ?>
            <div class="mv-account-links">
                <a href="<?php echo esc_url(mv_commerce_account_url('otp')); ?>">درخواست کد جدید یا تغییر شماره</a>
                <a href="<?php echo esc_url(mv_commerce_account_url()); ?>">ورود با رمز عبور</a>
            </div>
        <?php endif; ?>
        <?php if (($_GET['return'] ?? '') === 'checkout' && WC()->cart && !WC()->cart->is_empty()) : ?>
            <div class="mv-account-links"><a href="<?php echo esc_url(wc_get_checkout_url()); ?>">بازگشت به تسویه‌حساب</a></div>
        <?php endif; ?>
    </div>
    <?php if (is_readable(get_theme_file_path($image))) : ?>
        <div class="mv-account-visual" aria-hidden="true">
            <img src="<?php echo esc_url(get_theme_file_uri($image)); ?>" width="780" height="622" alt="" loading="lazy">
        </div>
    <?php endif; ?>
</div>
