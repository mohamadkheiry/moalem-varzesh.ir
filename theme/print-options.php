<?php
defined('ABSPATH') || exit;

const MV_CUSTOM_PRINT = 'نام و شماره دلخواه';

function mv_is_custom_print(array $values): bool {
    foreach ($values as $value) {
        if (str_contains(str_replace('شمارهٔ', 'شماره', (string) $value), MV_CUSTOM_PRINT)) {
            return true;
        }
    }
    return false;
}

function mv_print_fields(): array {
    $field = static function (string $key): string {
        return isset($_POST[$key]) && is_string($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
    };
    $digits = ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9'];
    return ['name' => $field('mv_print_name'), 'number' => strtr($field('mv_print_number'), $digits)];
}

add_action('woocommerce_before_add_to_cart_button', static function (): void {
    global $product;
    if (!$product || !$product->is_type('variable')) {
        return;
    }
    $has_custom_print = false;
    foreach ($product->get_variation_attributes() as $options) {
        if (mv_is_custom_print($options)) {
            $has_custom_print = true;
            break;
        }
    }
    if (!$has_custom_print) {
        return;
    }
    wp_enqueue_script('mv-print-options', get_theme_file_uri('assets/print-options.js'), [], wp_get_theme()->get('Version'), true);
    ?>
    <fieldset class="mv-print-fields" data-needle="<?php echo esc_attr(MV_CUSTOM_PRINT); ?>" hidden>
        <legend>نام و شمارهٔ روی لباس</legend>
        <label for="mv-print-name">نام چاپی</label>
        <input id="mv-print-name" name="mv_print_name" type="text" class="input-text" maxlength="30" disabled>
        <label for="mv-print-number">شماره چاپی</label>
        <input id="mv-print-number" name="mv_print_number" type="text" class="input-text" inputmode="numeric" maxlength="2" disabled>
    </fieldset>
    <?php
});

add_filter('woocommerce_add_to_cart_validation', static function (bool $valid, int $product_id, $quantity, int $variation_id = 0): bool {
    $variation = $variation_id ? wc_get_product($variation_id) : null;
    if (!$valid || !$variation || !mv_is_custom_print($variation->get_attributes())) {
        return $valid;
    }
    $fields = mv_print_fields();
    if ($fields['name'] === '' || mb_strlen($fields['name']) > 30 || !preg_match('/^\d{1,2}$/D', $fields['number'])) {
        wc_add_notice('برای چاپ سفارشی، نام و شمارهٔ یک یا دو رقمی را وارد کنید.', 'error');
        return false;
    }
    return true;
}, 10, 4);

add_filter('woocommerce_add_cart_item_data', static function (array $data, int $product_id, int $variation_id): array {
    $variation = $variation_id ? wc_get_product($variation_id) : null;
    if ($variation && mv_is_custom_print($variation->get_attributes())) {
        $data['mv_print'] = mv_print_fields();
    }
    return $data;
}, 10, 3);

add_filter('woocommerce_get_item_data', static function (array $data, array $cart_item): array {
    if (isset($cart_item['mv_print'])) {
        $data[] = ['key' => 'نام چاپی', 'value' => $cart_item['mv_print']['name']];
        $data[] = ['key' => 'شماره چاپی', 'value' => $cart_item['mv_print']['number']];
    }
    return $data;
}, 10, 2);

add_action('woocommerce_checkout_create_order_line_item', static function (WC_Order_Item_Product $item, string $key, array $cart_item): void {
    if (isset($cart_item['mv_print'])) {
        $item->add_meta_data('نام چاپی', $cart_item['mv_print']['name'], true);
        $item->add_meta_data('شماره چاپی', $cart_item['mv_print']['number'], true);
    }
}, 10, 3);
