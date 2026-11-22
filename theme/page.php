<?php
if (!function_exists('is_cart') || !(is_cart() || is_checkout() || is_account_page())) {
    require __DIR__ . '/index.php';
    return;
}

$stage = is_cart() ? 'cart' : (is_checkout() ? 'checkout' : 'account');
if (is_order_received_page()) {
    $stage = 'receipt';
} elseif (is_checkout_pay_page()) {
    $stage = 'pay';
}
$guest_login = $stage === 'account' && !is_user_logged_in() && function_exists('mv_commerce_account_mode');
$titles = ['cart' => 'سبد خرید', 'checkout' => 'تسویه‌حساب', 'receipt' => 'نتیجهٔ سفارش', 'pay' => 'پرداخت سفارش'];
$descriptions = [
    'cart' => 'کالاها، تعداد و مبلغ سبد را پیش از ادامهٔ خرید بررسی کنید.',
    'checkout' => 'نشانی تحویل را وارد کنید و جزئیات سفارش را پیش از ثبت بررسی کنید.',
];
get_header();
?>
<main id="main" class="wrap main-content mv-purchase mv-purchase--<?php echo esc_attr($stage); ?>">
    <?php while (have_posts()) : the_post(); ?>
        <?php if (!$guest_login) : ?>
            <header class="mv-purchase-head">
                <div>
                    <h1><?php echo esc_html($titles[$stage] ?? get_the_title()); ?></h1>
                    <?php if (isset($descriptions[$stage])) : ?><p><?php echo esc_html($descriptions[$stage]); ?></p><?php endif; ?>
                </div>
                <?php if (in_array($stage, ['cart', 'checkout'], true)) : ?>
                    <?php mv_purchase_steps($stage); ?>
                <?php endif; ?>
            </header>
        <?php endif; ?>
        <article <?php post_class('mv-purchase-content'); ?>>
            <?php the_content(); ?>
        </article>
    <?php endwhile; ?>
</main>
<?php get_footer(); ?>
