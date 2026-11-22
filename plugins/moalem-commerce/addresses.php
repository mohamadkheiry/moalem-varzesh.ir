<?php
defined('ABSPATH') || exit;

function mv_address_book(int $user): array {
    $book = $user ? get_user_meta($user, '_mv_address_book', true) : null;
    return is_array($book) ? $book : ['default' => '', 'items' => []];
}

function mv_address_url(array $args = []): string {
    return add_query_arg($args, wc_get_account_endpoint_url('edit-address'));
}

function mv_address_fields(): array {
    return [
        'label' => ['عنوان نشانی', 55, false, 'text', 'off'],
        'first_name' => ['نام گیرنده', 60, true, 'text', 'shipping given-name'],
        'last_name' => ['نام خانوادگی گیرنده', 60, true, 'text', 'shipping family-name'],
        'state' => ['استان', 12, true, 'select', 'shipping address-level1'],
        'city' => ['شهر', 85, true, 'text', 'shipping address-level2'],
        'address_1' => ['خیابان، کوچه و پلاک', 190, true, 'text', 'shipping address-line1'],
        'address_2' => ['طبقه، واحد یا اطلاعات تکمیلی', 155, false, 'text', 'shipping address-line2'],
        'postcode' => ['کد پستی ۱۰ رقمی', 10, true, 'text', 'shipping postal-code'],
        'phone' => ['شماره موبایل گیرنده', 24, true, 'tel', 'shipping tel'],
    ];
}

function mv_address_states(): array {
    return (array) WC()->countries->get_states('IR');
}

function mv_address_from_profile(int $user, string $type): array {
    $customer = new WC_Customer($user);
    $address = [];
    foreach (array_keys(mv_address_fields()) as $field) {
        $getter = 'get_' . $type . '_' . $field;
        if (method_exists($customer, $getter)) {
            $address[$field] = $customer->$getter();
        }
    }
    $address['phone'] = ($address['phone'] ?? '') ?: $customer->get_billing_phone();
    return $address;
}

function mv_address_input(): array {
    $address = [];
    foreach (mv_address_fields() as $key => [, $length]) {
        $value = sanitize_text_field(mv_commerce_field('mv_ship_' . $key));
        $address[$key] = $key === 'postcode' ? $value : mb_substr($value, 0, $length);
    }
    return $address;
}

function mv_address_validate(array $address) {
    foreach (mv_address_fields() as $key => [$label, , $required]) {
        if ($required && $address[$key] === '') {
            return new WP_Error('address_required', sprintf('لطفاً «%s» را وارد کنید.', $label));
        }
    }
    if (!isset(mv_address_states()[$address['state']])) {
        return new WP_Error('address_state', 'استان را از فهرست انتخاب کنید.');
    }
    $address['postcode'] = mv_commerce_digits($address['postcode']);
    if (!preg_match('/^[0-9]{10}$/D', $address['postcode'])) {
        return new WP_Error('address_postcode', 'کد پستی باید ۱۰ رقم باشد.');
    }
    $address['phone'] = mv_commerce_phone($address['phone']);
    if ($address['phone'] === '') {
        return new WP_Error('address_phone', 'شماره موبایل گیرنده را بررسی کنید.');
    }
    $address['country'] = 'IR';
    return $address;
}

function mv_address_change(int $user, string $action, string $id, array $address = []) {
    return mv_commerce_with_lock(mv_commerce_key('address-book', (string) $user), static function () use ($user, $action, $id, $address) {
        wp_cache_delete($user, 'user_meta');
        $before = $book = mv_address_book($user);
        if ($id !== 'new' && !isset($book['items'][$id])) {
            return new WP_Error('address_missing', 'این نشانی پیدا نشد.');
        }
        if ($action === 'save') {
            if ($id === 'new' && count($book['items']) >= 20) {
                return new WP_Error('address_limit', 'حداکثر ۲۰ نشانی می‌توانید ذخیره کنید.');
            }
            $id = $id === 'new' ? wp_generate_uuid4() : $id;
            $book['items'][$id] = $address;
            $book['default'] = $book['default'] ?: $id;
        } elseif ($action === 'default') {
            $book['default'] = $id;
        } else {
            unset($book['items'][$id]);
            if ($book['default'] === $id) {
                $book['default'] = array_key_first($book['items']) ?? '';
            }
        }
        if ($book !== $before && !update_user_meta($user, '_mv_address_book', $book)) {
            return new WP_Error('address_save', 'ذخیرهٔ نشانی انجام نشد. دوباره تلاش کنید.');
        }
        return true;
    });
}

add_action('template_redirect', static function (): void {
    if (!function_exists('is_wc_endpoint_url') || !is_account_page() || !is_wc_endpoint_url('edit-address') || !is_user_logged_in()) {
        return;
    }
    $action = mv_commerce_field('mv_address_action');
    if (!in_array($action, ['save', 'default', 'remove'], true)) {
        return;
    }
    $id = mv_commerce_field('mv_address_id');
    if (!wp_verify_nonce(mv_commerce_field('_mv_address_nonce'), 'mv-address-' . $action . '-' . $id)) {
        wc_add_notice('فرم منقضی شده است. دوباره تلاش کنید.', 'error');
        return;
    }
    if ((!wp_is_uuid($id, 4) && $id !== 'new') || ($id === 'new' && $action !== 'save')) {
        wc_add_notice('این نشانی پیدا نشد.', 'error');
        return;
    }
    $address = $action === 'save' ? mv_address_validate(mv_address_input()) : [];
    $result = is_wp_error($address) ? $address : mv_address_change(get_current_user_id(), $action, $id, $address);
    if (is_wp_error($result)) {
        wc_add_notice($result->get_error_message() ?: 'ذخیرهٔ نشانی انجام نشد. دوباره تلاش کنید.', 'error');
        return;
    }
    $messages = ['save' => 'نشانی ذخیره شد.', 'default' => 'نشانی پیش‌فرض تغییر کرد.', 'remove' => 'نشانی حذف شد.'];
    wc_add_notice($messages[$action], 'success');
    wp_safe_redirect(mv_address_url(), 303);
    exit;
}, 9);

function mv_checkout_address_choice(array $book): string {
    $requested = isset($_GET['mv_checkout_address']) && is_string($_GET['mv_checkout_address']) ? wp_unslash($_GET['mv_checkout_address']) : '';
    return $requested === 'manual' || isset($book['items'][$requested]) ? $requested : $book['default'];
}

add_filter('woocommerce_checkout_get_value', static function ($value, string $field) {
    if ($value !== null || $field === 'billing_phone' || !is_user_logged_in()
        || !preg_match('/^(?:billing|shipping)_(.+)$/D', $field, $match)) {
        return $value;
    }
    $book = mv_address_book(get_current_user_id());
    $address = $book['items'][mv_checkout_address_choice($book)] ?? [];
    return $address[$match[1]] ?? $value;
}, 10, 2);

add_action('woocommerce_checkout_before_customer_details', static function (): void {
    if (!is_user_logged_in()) {
        return;
    }
    $book = mv_address_book(get_current_user_id());
    $choice = mv_checkout_address_choice($book);
    if (isset($book['items'][$choice])) {
        echo '<input type="hidden" name="mv_checkout_delivery" value="' . esc_attr($choice) . '">';
    }
});

add_action('woocommerce_checkout_create_order', static function (WC_Order $order, array $data): void {
    $book = mv_address_book(get_current_user_id());
    $address = $book['items'][mv_commerce_field('mv_checkout_delivery')] ?? null;
    $entered_shipping_phone = !empty($data['ship_to_different_address']) && mv_commerce_field('shipping_phone') !== '';
    if (!$address || !$order->needs_shipping_address() || $entered_shipping_phone) {
        return;
    }
    $actual = $order->get_address('shipping');
    foreach (['first_name', 'last_name', 'country', 'state', 'city', 'address_1', 'address_2', 'postcode'] as $field) {
        if (($actual[$field] ?? '') !== $address[$field]) {
            return;
        }
    }
    $order->set_shipping_phone($address['phone']);
}, 20, 2);
