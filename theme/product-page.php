<?php
defined('ABSPATH') || exit;

function mv_storefront_price_html(WC_Product $product, bool $compact = false): string {
    if (!$product->is_type('variable')) {
        return $product->get_price_html();
    }

    $format_range = static function (string $html, $from, $to) use ($compact): string {
        if (!is_numeric($from) || !is_numeric($to)) {
            return $html;
        }
        $minimum = '<span class="mv-price-bound"><span class="mv-price-caption">از</span> ' . wc_price($from) . '</span>';
        if ($compact) {
            return $minimum;
        }
        return '<span class="mv-price-range">' . $minimum
            . ' <span class="mv-price-bound"><span class="mv-price-caption">تا</span> ' . wc_price($to) . '</span></span>';
    };

    add_filter('woocommerce_format_price_range', $format_range, 20, 3);
    try {
        return $product->get_price_html();
    } finally {
        remove_filter('woocommerce_format_price_range', $format_range, 20);
    }
}

add_action('wp_enqueue_scripts', static function (): void {
    if (function_exists('is_product') && is_product()) {
        wp_enqueue_style('mv-product-page', get_theme_file_uri('assets/product-page.css'), ['moalem-varzesh'], wp_get_theme()->get('Version'));
    }
}, 21);

add_action('wp', static function (): void {
    if (!function_exists('is_product') || !is_product()) {
        return;
    }
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40);
}, 20);

add_action('woocommerce_single_product_summary', static function (): void {
    echo '<div class="mv-product-info">';
}, 1);

add_action('woocommerce_single_product_summary', static function (): void {
    global $product;
    if (!$product instanceof WC_Product) {
        return;
    }
    $categories = get_the_terms($product->get_id(), 'product_cat');
    $category = is_array($categories) ? end($categories) : false;
    $link = $category ? get_term_link($category) : false;
    $availability = $product->get_availability();
    $stock = trim(wp_strip_all_tags($availability['availability']));
    if ($stock === '') {
        $stock = $product->is_type('variable') ? 'موجودی به انتخاب شما بستگی دارد' : 'موجود';
    }
    ?>
    <div class="mv-product-context">
        <?php if (is_string($link)) : ?><a href="<?php echo esc_url($link); ?>"><?php echo esc_html($category->name); ?></a><?php endif; ?>
        <span class="mv-product-stock <?php echo esc_attr($availability['class']); ?>"><?php echo esc_html($stock); ?></span>
    </div>
    <?php
}, 3);

add_action('woocommerce_single_product_summary', static function (): void {
    global $product;
    woocommerce_template_single_meta();
    echo '</div><div class="mv-product-purchase" id="mv-product-options">';
    if ($product instanceof WC_Product && ($price = mv_storefront_price_html($product)) !== '') {
        echo '<p class="price">' . wp_kses_post($price) . '</p>';
    }
    echo '<div class="mv-product-buy">';
}, 29);
add_action('woocommerce_single_product_summary', static function (): void {
    echo '</div>';
    if ($contact = mv_support_page_id('contact')) {
        printf('<a class="mv-product-help" href="%s">پرسش دربارهٔ کالا', esc_url(get_permalink($contact)));
        mv_icon('chat');
        echo '</a>';
    }
    echo '</div>';
}, 31);

add_filter('woocommerce_product_tabs', static function (array $tabs): array {
    foreach (['description' => 'معرفی کالا', 'additional_information' => 'مشخصات', 'reviews' => 'دیدگاه‌ها'] as $key => $title) {
        if (isset($tabs[$key])) {
            $tabs[$key]['title'] = $title;
        }
    }
    return $tabs;
});
