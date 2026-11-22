<?php
defined('ABSPATH') || exit;

function mv_order_filters(): array {
    return [
        'all' => ['همهٔ سفارش‌ها', []],
        'active' => ['جاری', ['wc-pending', 'wc-processing', 'wc-on-hold', 'wc-failed']],
        'completed' => ['تکمیل‌شده', ['wc-completed']],
        'cancelled' => ['لغوشده', ['wc-cancelled']],
        'refunded' => ['بازپرداخت‌شده', ['wc-refunded']],
    ];
}

function mv_order_filter(): string {
    $filter = isset($_GET['mv_orders']) && is_string($_GET['mv_orders']) ? wp_unslash($_GET['mv_orders']) : '';
    return isset(mv_order_filters()[$filter]) ? $filter : 'all';
}

function mv_orders_url(string $filter = 'all', int $page = 1): string {
    $url = wc_get_endpoint_url('orders', $page > 1 ? $page : '', wc_get_page_permalink('myaccount'));
    return $filter === 'all' ? $url : add_query_arg('mv_orders', $filter, $url);
}

function mv_order_detail_url(string $url): string {
    $filter = mv_order_filter();
    return $filter === 'all' ? $url : add_query_arg('mv_orders', $filter, $url);
}

add_filter('woocommerce_my_account_my_orders_query', static function (array $query): array {
    $statuses = mv_order_filters()[mv_order_filter()][1];
    if ($statuses) {
        $query['status'] = $statuses;
    }
    $query['limit'] = 8;
    return $query;
});

add_filter('woocommerce_account_menu_items', static function (array $menu): array {
    $primary = ['dashboard' => 'نمای کلی', 'orders' => 'سفارش‌های من', 'edit-address' => 'نشانی‌های من', 'edit-account' => 'اطلاعات حساب'];
    return array_intersect_key($primary, $menu) + $menu;
});

add_filter('woocommerce_my_account_edit_address_title', static function (string $title, string $type): string {
    return ['billing' => 'ویرایش نشانی صورتحساب', 'shipping' => 'ویرایش نشانی ارسال'][$type] ?? $title;
}, 10, 2);

add_action('woocommerce_before_edit_account_address_form', static function (): void {
    if (!get_query_var('edit-address') || !function_exists('mv_address_url')) {
        return;
    }
    echo '<a class="mv-order-back" href="' . esc_url(mv_address_url()) . '">';
    mv_icon('return');
    echo 'بازگشت به نشانی‌ها</a>';
});

function mv_order_status_name(string $status): string {
    $labels = [
        'pending' => 'در انتظار پرداخت', 'processing' => 'در حال پردازش', 'on-hold' => 'در انتظار بررسی',
        'completed' => 'تکمیل‌شده', 'cancelled' => 'لغوشده', 'refunded' => 'بازپرداخت‌شده', 'failed' => 'پرداخت ناموفق',
    ];
    return $labels[$status] ?? wc_get_order_status_name($status);
}

function mv_order_actions(WC_Order $order, bool $include_view = true): void {
    $labels = ['view' => 'جزئیات سفارش', 'pay' => 'پرداخت سفارش', 'cancel' => 'لغو سفارش'];
    foreach (wc_get_account_orders_actions($order) as $key => $action) {
        if ($key === 'view' && !$include_view) {
            continue;
        }
        $label = $labels[$key] ?? $action['name'];
        $url = $key === 'view' ? mv_order_detail_url($action['url']) : $action['url'];
        printf('<a class="mv-area-action %s" href="%s" aria-label="%s">%s</a>',
            $key === 'pay' ? 'mv-area-primary' : 'mv-area-secondary', esc_url($url),
            esc_attr($label . ' ' . $order->get_order_number()), esc_html($label));
    }
}

add_filter('woocommerce_order_item_name', static function (string $name, $item): string {
    if (!is_account_page() || !is_wc_endpoint_url('view-order') || !$item instanceof WC_Order_Item_Product) {
        return $name;
    }
    $product = $item->get_product();
    if (!$product) {
        return $name;
    }
    return '<span class="mv-order-line-image">' . $product->get_image('woocommerce_gallery_thumbnail', ['alt' => '']) . '</span>' . $name;
}, 20, 2);

add_action('woocommerce_before_checkout_form', static function (): void {
    if (!is_user_logged_in() || !function_exists('mv_address_book')) {
        return;
    }
    $book = mv_address_book(get_current_user_id());
    if (!$book['items']) {
        return;
    }
    $choice = mv_checkout_address_choice($book);
    ?>
    <section class="mv-checkout-address-picker" aria-labelledby="mv-checkout-address-heading">
        <div>
            <h2 id="mv-checkout-address-heading"><?php mv_icon('pin'); ?> نشانی تحویل</h2>
            <a href="<?php echo esc_url(mv_address_url()); ?>">مدیریت نشانی‌ها</a>
        </div>
        <form method="get" action="<?php echo esc_url(wc_get_checkout_url()); ?>">
            <label class="screen-reader-text" for="mv-checkout-address">نشانی ذخیره‌شده</label>
            <select name="mv_checkout_address" id="mv-checkout-address">
                <?php foreach ($book['items'] as $id => $address) : ?>
                    <option value="<?php echo esc_attr($id); ?>" <?php selected($choice, $id); ?>>
                        <?php echo esc_html(($address['label'] ?: $address['city'] . '، ' . $address['address_1']) . ($id === $book['default'] ? ' (پیش‌فرض)' : '')); ?>
                    </option>
                <?php endforeach; ?>
                <option value="manual" <?php selected($choice, 'manual'); ?>>وارد کردن نشانی دیگر</option>
            </select>
            <button class="mv-area-action mv-area-primary" type="submit">استفاده از نشانی</button>
        </form>
    </section>
    <?php
}, 20);
