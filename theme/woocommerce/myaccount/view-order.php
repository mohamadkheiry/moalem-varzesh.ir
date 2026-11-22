<?php
/**
 * Customer order details. Based on WooCommerce myaccount/view-order.php.
 * @version 10.6.0
 */
defined('ABSPATH') || exit;
$notes = $order->get_customer_order_notes();
$status = $order->get_status();
$date = $order->get_date_created();
$phase = ['pending' => 0, 'on-hold' => 0, 'processing' => 1, 'completed' => 2][$status] ?? null;
?>
<section class="mv-customer-section mv-order-detail" aria-labelledby="mv-order-detail-title">
    <a class="mv-order-back" href="<?php echo esc_url(mv_orders_url(mv_order_filter())); ?>"><?php mv_icon('return'); ?> بازگشت به سفارش‌ها</a>
    <header class="mv-area-heading"><?php mv_icon('box'); ?><h2 id="mv-order-detail-title">سفارش <bdi><?php echo esc_html($order->get_order_number()); ?></bdi></h2>
        <span class="mv-order-status" data-status="<?php echo esc_attr($status); ?>"><?php echo esc_html(mv_order_status_name($status)); ?></span>
    </header>
    <?php if ($date) : ?><p class="mv-order-date">ثبت‌شده در <time datetime="<?php echo esc_attr($date->date('c')); ?>"><?php echo esc_html(wc_format_datetime($date, 'j F Y')); ?></time></p><?php endif; ?>
    <?php if (has_filter('woocommerce_order_details_status')) :
        $status_text = sprintf(
            esc_html__('Order #%1$s was placed on %2$s and is currently %3$s.', 'woocommerce'),
            '<mark class="order-number">' . esc_html($order->get_order_number()) . '</mark>',
            '<mark class="order-date">' . esc_html($date ? wc_format_datetime($date) : '') . '</mark>',
            '<mark class="order-status">' . esc_html(wc_get_order_status_name($status)) . '</mark>'
        );
        ?>
        <p><?php echo wp_kses_post(apply_filters('woocommerce_order_details_status', $status_text, $order)); ?></p>
    <?php endif; ?>
    <?php if ($phase !== null) : ?>
        <ol class="mv-order-progress" aria-label="وضعیت پیشرفت سفارش">
            <?php foreach (['ثبت سفارش', 'پردازش', 'تکمیل'] as $step => $label) : ?>
                <li class="<?php echo $step <= $phase ? 'is-reached' : ''; ?>" <?php if ($step === $phase) echo 'aria-current="step"'; ?>>
                    <span aria-hidden="true"><?php if ($step <= $phase) mv_icon('check'); ?></span><?php echo esc_html($label); ?>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
    <div class="mv-order-actions"><?php mv_order_actions($order, false); ?></div>
    <?php if ($notes) : ?>
        <section class="mv-order-updates" aria-labelledby="mv-order-updates-title">
            <h3 id="mv-order-updates-title">به‌روزرسانی‌های سفارش</h3>
            <ul>
                <?php foreach ($notes as $note) : ?>
                    <li><time><?php echo esc_html(get_comment_date('j F Y، H:i', $note)); ?></time>
                        <?php echo wp_kses_post(wpautop(wptexturize($note->comment_content))); ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</section>
<?php do_action('woocommerce_view_order', $order_id); ?>
