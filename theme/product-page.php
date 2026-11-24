<?php
defined('ABSPATH') || exit;

function mv_storefront_price_html(WC_Product $product, bool $compact = false): string {
    $tags = wp_kses_allowed_html('post');
    $tags['bdi'] = ['dir' => true];
    if (!$product->is_type('variable')) {
        return wp_kses($product->get_price_html(), $tags);
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
        return wp_kses($product->get_price_html(), $tags);
    } finally {
        remove_filter('woocommerce_format_price_range', $format_range, 20);
    }
}

add_action('wp_enqueue_scripts', static function (): void {
    if (function_exists('is_product') && is_product()) {
        $version = wp_get_theme()->get('Version');
        wp_enqueue_style('mv-product-page', get_theme_file_uri('assets/product-page.css'), ['moalem-varzesh'], $version);
        wp_enqueue_script('mv-product-page', get_theme_file_uri('assets/product-page.js'), ['wc-single-product'], $version, true);
        $product = wc_get_product(get_queried_object_id());
        if ($product && $product->is_type('variable')) {
            wp_enqueue_script('mv-variation-options', get_theme_file_uri('assets/variation-options.js'), ['wc-add-to-cart-variation'], $version, true);
        }
    }
}, 21);

add_filter('woocommerce_loop_add_to_cart_link', static function (string $html, WC_Product $product): string {
    if (!$product->is_type('variable') || !$product->is_purchasable() || !$product->is_in_stock()) {
        return $html;
    }

    $tag = new WP_HTML_Tag_Processor($html);
    if (!$tag->next_tag('a') || $tag->get_attribute('href') !== $product->get_permalink()) {
        return $html;
    }

    $tag->add_class('mv-choose-options');
    $tag->set_attribute('href', $product->get_permalink() . '#mv-product-options');
    return $tag->get_updated_html();
}, 10, 2);

add_filter('woocommerce_product_add_to_cart_text', static function (string $text, WC_Product $product): string {
    return $product->is_type('variable') && $product->is_purchasable() && $product->is_in_stock()
        ? 'انتخاب گزینه‌ها'
        : $text;
}, 10, 2);

add_action('wp', static function (): void {
    if (!function_exists('is_product') || !is_product()) {
        return;
    }
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40);
    add_filter('woocommerce_variation_is_active', static function (bool $active, WC_Product_Variation $variation): bool {
        return $active && $variation->is_in_stock();
    }, 10, 2);
}, 20);

add_action('woocommerce_single_product_summary', static function (): void {
    echo '<div class="mv-product-info">';
}, 1);

add_action('woocommerce_single_product_summary', static function (): void {
    global $product;
    if (!$product instanceof WC_Product) {
        return;
    }
    $availability = $product->get_availability();
    $stock = trim(wp_strip_all_tags($availability['availability']));
    if ($stock === '') {
        $stock = $product->is_type('variable') ? 'موجودی پس از انتخاب گزینه مشخص می‌شود' : 'آمادهٔ سفارش';
    }
    ?>
    <div class="mv-product-context">
        <span class="mv-product-stock <?php echo esc_attr($availability['class']); ?>" aria-live="polite" aria-atomic="true"><?php echo esc_html($stock); ?></span>
    </div>
    <?php
}, 3);

add_action('woocommerce_single_product_summary', static function (): void {
    global $product;
    woocommerce_template_single_meta();
    echo '</div><div class="mv-product-purchase" id="mv-product-options" role="region" aria-label="گزینه‌های خرید" tabindex="-1">';
    if ($product instanceof WC_Product && ($price = mv_storefront_price_html($product)) !== '') {
        echo '<p class="price">' . $price . '</p>';
    }
    echo '<div class="mv-product-buy">';
}, 29);
add_action('woocommerce_single_product_summary', static function (): void {
    echo '</div>';
    $help_links = [
        'contact' => ['پرسش دربارهٔ کالا', 'chat'],
        'returns' => ['شرایط مرجوعی', 'arrow'],
    ];
    $has_help = false;
    foreach ($help_links as $key => [$label, $icon]) {
        $page = mv_support_page_id($key);
        if (!$page) {
            continue;
        }
        if (!$has_help) {
            echo '<div class="mv-product-help-links">';
            $has_help = true;
        }
        printf('<a class="mv-product-help" href="%s">', esc_url(get_permalink($page)));
        mv_icon($icon);
        echo esc_html($label) . '</a>';
    }
    if ($has_help) {
        echo '</div>';
    }
    echo '</div>';
}, 31);

add_filter('woocommerce_product_related_products_heading', static function (): string {
    return 'کالاهای مرتبط';
});

add_filter('woocommerce_product_review_comment_form_args', static function (array $args): array {
    $args['title_reply'] = 'دیدگاه خود را بنویسید';
    $args['label_submit'] = 'ثبت دیدگاه';
    return $args;
});

function mv_product_description_headings(string $html): string {
    if (!function_exists('is_product') || !is_product() || get_post_type() !== 'product' || stripos($html, '<h1') === false) {
        return $html;
    }
    $parts = wp_html_split($html);
    for ($index = 1; $index < count($parts); $index += 2) {
        $parts[$index] = preg_replace('~^(</?)h1(?=[\s>])~i', '$1h3', $parts[$index]);
    }
    return implode('', $parts);
}

add_filter('the_content', 'mv_product_description_headings', 21);
add_filter('woocommerce_short_description', 'mv_product_description_headings', 21);

add_filter('woocommerce_product_tabs', static function (array $tabs): array {
    foreach (['description' => 'معرفی کالا', 'additional_information' => 'مشخصات', 'reviews' => 'دیدگاه‌ها'] as $key => $title) {
        if (isset($tabs[$key])) {
            $tabs[$key]['title'] = $title;
        }
    }
    return $tabs;
});
