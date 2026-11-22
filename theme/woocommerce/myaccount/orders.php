<?php
/**
 * Customer order history. Based on WooCommerce myaccount/orders.php.
 * @version 9.5.0
 */
defined('ABSPATH') || exit;
$filter = mv_order_filter();
do_action('woocommerce_before_account_orders', $has_orders);
?>
<section class="mv-customer-section" aria-labelledby="mv-order-history">
    <header class="mv-area-heading"><?php mv_icon('box'); ?><h2 id="mv-order-history">سفارش‌های من</h2></header>
    <nav class="mv-order-filters" aria-label="فیلتر وضعیت سفارش">
        <?php foreach (mv_order_filters() as $id => [$label]) : ?>
            <a href="<?php echo esc_url(mv_orders_url($id)); ?>" <?php if ($filter === $id) echo 'aria-current="page"'; ?>><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
    </nav>
    <?php if ($has_orders) : ?>
        <div class="mv-order-stack">
            <?php foreach ($customer_orders->orders as $customer_order) :
                $order = wc_get_order($customer_order);
                if (!$order) continue;
                $status = $order->get_status();
                $date = $order->get_date_created();
                $items = $order->get_items();
                ?>
                <article class="mv-order-card">
                    <header class="mv-order-card-head">
                        <h3><a href="<?php echo esc_url(mv_order_detail_url($order->get_view_order_url())); ?>">سفارش <bdi><?php echo esc_html($order->get_order_number()); ?></bdi></a></h3>
                        <span class="mv-order-status" data-status="<?php echo esc_attr($status); ?>"><?php echo esc_html(mv_order_status_name($status)); ?></span>
                    </header>
                    <dl class="mv-order-summary">
                        <?php if ($date) : ?><div><dt>تاریخ ثبت</dt><dd><time datetime="<?php echo esc_attr($date->date('c')); ?>"><?php echo esc_html(wc_format_datetime($date, 'j F Y')); ?></time></dd></div><?php endif; ?>
                        <div><dt>تعداد کالا</dt><dd><?php echo esc_html(number_format_i18n($order->get_item_count())); ?></dd></div>
                        <div><dt>مبلغ سفارش</dt><dd><?php echo wp_kses_post($order->get_formatted_order_total()); ?></dd></div>
                    </dl>
                    <footer class="mv-order-card-foot">
                        <?php if ($items) : ?>
                            <ul class="mv-order-thumbnails" aria-label="کالاهای سفارش">
                                <?php foreach (array_slice($items, 0, 4) as $item) :
                                    $product = $item->get_product();
                                    ?>
                                    <li aria-label="<?php echo esc_attr($item->get_name()); ?>" title="<?php echo esc_attr($item->get_name()); ?>">
                                        <?php echo $product ? $product->get_image('woocommerce_gallery_thumbnail', ['alt' => '']) : wc_placeholder_img('woocommerce_gallery_thumbnail', ['alt' => '']); ?>
                                    </li>
                                <?php endforeach; ?>
                                <?php if (count($items) > 4) : ?><li><bdi>+<?php echo esc_html(number_format_i18n(count($items) - 4)); ?></bdi></li><?php endif; ?>
                            </ul>
                        <?php endif; ?>
                        <div class="mv-order-actions"><?php mv_order_actions($order); ?></div>
                    </footer>
                </article>
            <?php endforeach; ?>
        </div>
        <?php do_action('woocommerce_before_account_orders_pagination'); ?>
        <?php if ($customer_orders->max_num_pages > 1) : ?>
            <nav class="mv-order-pagination" aria-label="صفحه‌های سفارش‌ها">
                <?php if ($current_page > 1) : ?><a class="mv-area-action mv-area-secondary" href="<?php echo esc_url(mv_orders_url($filter, $current_page - 1)); ?>">صفحهٔ قبل</a><?php endif; ?>
                <span>صفحهٔ <?php echo esc_html(number_format_i18n($current_page)); ?></span>
                <?php if ($current_page < $customer_orders->max_num_pages) : ?><a class="mv-area-action mv-area-secondary" href="<?php echo esc_url(mv_orders_url($filter, $current_page + 1)); ?>">صفحهٔ بعد</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php else : ?>
        <div class="mv-area-empty">
            <?php mv_icon('bag'); ?>
            <h3><?php echo $filter === 'all' ? 'هنوز سفارشی ثبت نکرده‌اید' : 'سفارشی با این وضعیت ندارید'; ?></h3>
            <?php if ($filter === 'all') : ?>
                <a class="mv-area-action mv-area-primary" href="<?php echo esc_url(mv_shop_url()); ?>">مشاهدهٔ فروشگاه</a>
            <?php else : ?>
                <a class="mv-area-action mv-area-secondary" href="<?php echo esc_url(mv_orders_url()); ?>">مشاهدهٔ همهٔ سفارش‌ها</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
<?php do_action('woocommerce_after_account_orders', $has_orders); ?>
