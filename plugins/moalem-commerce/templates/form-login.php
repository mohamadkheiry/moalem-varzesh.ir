<?php
defined('ABSPATH') || exit;
$mode = mv_commerce_account_mode();
$phone = mv_commerce_phone(mv_commerce_field('mv_phone'));
if ($mode === 'verify' && !$phone && WC()->session) {
    $phone = (string) WC()->session->get('mv_otp_phone', '');
}
$titles = ['login' => 'ورود با موبایل', 'otp' => 'دریافت رمز یک‌بارمصرف', 'verify' => 'تأیید شماره موبایل'];
?>
<div class="mv-account">
    <h2><?php echo esc_html($titles[$mode]); ?></h2>
    <?php wc_print_notices(); ?>
    <?php if ($mode === 'otp') : ?>
        <p>برای ورود یا ساخت حساب، شماره موبایل خود را وارد کنید.</p>
    <?php elseif ($mode === 'verify') : ?>
        <p>کد شش‌رقمی پیامک‌شده تا ۵ دقیقه اعتبار دارد.</p>
    <?php endif; ?>

    <form class="woocommerce-form woocommerce-form-login login" method="post" action="<?php echo esc_url(mv_commerce_account_url($mode)); ?>">
        <p class="form-row form-row-wide">
            <label for="mv-phone">شماره موبایل <span aria-hidden="true">*</span></label>
            <input id="mv-phone" name="mv_phone" type="tel" inputmode="tel" autocomplete="<?php echo $mode === 'login' ? 'username' : 'tel'; ?>"
                dir="ltr" placeholder="09123456789" value="<?php echo esc_attr($phone); ?>" maxlength="24" required>
        </p>

        <?php if ($mode === 'verify') : ?>
            <p class="form-row form-row-wide">
                <label for="mv-code">رمز یک‌بارمصرف <span aria-hidden="true">*</span></label>
                <input id="mv-code" name="mv_code" type="text" inputmode="numeric" autocomplete="one-time-code" dir="ltr" maxlength="6" required>
            </p>
        <?php endif; ?>

        <?php if ($mode !== 'otp') : ?>
            <p class="form-row form-row-wide">
                <label for="mv-password"><?php echo $mode === 'login' ? 'رمز عبور' : 'رمز عبور جدید (اختیاری، حداقل ۸ نویسه)'; ?></label>
                <input id="mv-password" name="mv_password" type="password" dir="ltr" maxlength="4096"
                    autocomplete="<?php echo $mode === 'login' ? 'current-password' : 'new-password'; ?>"
                    <?php echo $mode === 'login' ? 'required' : 'minlength="8"'; ?>>
            </p>
        <?php endif; ?>

        <?php wp_nonce_field(mv_commerce_account_nonce(), '_mv_nonce', false); ?>
        <input type="hidden" name="mv_action" value="<?php echo esc_attr($mode); ?>">
        <button class="button" type="submit"><?php echo esc_html([
            'login' => 'ورود',
            'otp' => 'ارسال کد ورود',
            'verify' => 'تأیید و ورود',
        ][$mode]); ?></button>
    </form>

    <?php if ($mode === 'login') : ?>
        <a class="mv-account-alt" href="<?php echo esc_url(mv_commerce_account_url('otp')); ?>">ورود یا عضویت با رمز یک‌بارمصرف</a>
    <?php else : ?>
        <?php if ($mode === 'verify') : ?>
            <a class="mv-account-alt" href="<?php echo esc_url(mv_commerce_account_url('otp')); ?>">درخواست کد جدید</a>
        <?php endif; ?>
        <a class="mv-account-alt" href="<?php echo esc_url(mv_commerce_account_url()); ?>">ورود با رمز عبور</a>
    <?php endif; ?>
</div>
