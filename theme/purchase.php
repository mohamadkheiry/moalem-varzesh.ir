<?php
defined('ABSPATH') || exit;

function mv_purchase_steps(string $stage): void {
    $past_cart = $stage === 'checkout';
    ?>
    <nav class="mv-purchase-steps" aria-label="مراحل خرید">
        <ol>
            <li class="<?php echo $past_cart ? 'is-complete' : 'is-current'; ?>">
                <?php if ($past_cart) : ?>
                    <a href="<?php echo esc_url(wc_get_cart_url()); ?>"><span><?php mv_icon('check'); ?></span>سبد خرید</a>
                <?php else : ?>
                    <span aria-current="step"><span aria-hidden="true">۱</span>سبد خرید</span>
                <?php endif; ?>
            </li>
            <li<?php if ($past_cart) echo ' class="is-current"'; ?>>
                <span<?php if ($past_cart) echo ' aria-current="step"'; ?>><span aria-hidden="true">۲</span>تسویه‌حساب</span>
            </li>
            <li><span><span aria-hidden="true">۳</span>نتیجهٔ سفارش</span></li>
        </ol>
    </nav>
    <?php
}

add_action('woocommerce_before_cart_table', static function (): void { ?>
    <div class="mv-cart-heading">
        <h2>کالاهای سبد</h2>
        <a href="<?php echo esc_url(mv_shop_url()); ?>">ادامهٔ خرید <?php mv_icon('arrow'); ?></a>
    </div>
<?php });

add_action('wp', static function (): void {
    if (!function_exists('is_cart') || !is_cart()) {
        return;
    }
    remove_action('woocommerce_cart_is_empty', 'wc_empty_cart_message', 10);
    add_action('woocommerce_cart_is_empty', static function (): void { ?>
        <div class="wc-empty-cart-message cart-empty mv-cart-empty">
            <span class="mv-cart-empty-icon"><?php mv_icon('bag'); ?></span>
            <h2>سبد خریدتان خالی است</h2>
            <p>از فروشگاه لباس، کفش و لوازم ورزشی موردنیازتان را پیدا کنید.</p>
        </div>
    <?php });
    add_filter('woocommerce_return_to_shop_text', static fn(): string => 'رفتن به فروشگاه');
});

add_action('woocommerce_checkout_before_order_review_heading', static function (): void {
    echo '<section class="mv-checkout-review" aria-labelledby="order_review_heading">';
}, 1);

add_action('woocommerce_checkout_after_order_review', static function (): void {
    echo '</section>';
}, PHP_INT_MAX);

add_filter('woocommerce_checkout_coupon_message', static function (): string {
    return '<a href="#" role="button" aria-controls="woocommerce-checkout-form-coupon" aria-expanded="false" class="showcoupon">وارد کردن کد تخفیف</a>';
});

add_filter('woocommerce_get_privacy_policy_text', static function (string $text, string $type): string {
    $default = 'Your personal data will be used to process your order, support your experience throughout this website, and for other purposes described in our [privacy_policy].';
    if ($type === 'checkout' && $text === $default) {
        return 'از اطلاعات شخصی شما برای رسیدگی به سفارش، پشتیبانی هنگام استفاده از سایت و موارد توضیح‌داده‌شده در [privacy_policy] استفاده می‌کنیم.';
    }
    return $text;
}, 10, 2);

function mv_checkout_item_image(string $name, array $item): string {
    return '<span class="mv-review-item-image">' . $item['data']->get_image('woocommerce_gallery_thumbnail', ['alt' => '']) . '</span>' . $name;
}

add_action('woocommerce_review_order_before_cart_contents', static function (): void {
    add_filter('woocommerce_cart_item_name', 'mv_checkout_item_image', 20, 2);
});

add_action('woocommerce_review_order_after_cart_contents', static function (): void {
    remove_filter('woocommerce_cart_item_name', 'mv_checkout_item_image', 20);
});
