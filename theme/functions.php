<?php
defined('ABSPATH') || exit;

function mv_theme_setup(): void {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('woocommerce', [
        'thumbnail_image_width' => 440,
        'single_image_width' => 800,
        'product_grid' => ['default_rows' => 4, 'default_columns' => 4],
    ]);
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
}
add_action('after_setup_theme', 'mv_theme_setup');

add_action('wp_enqueue_scripts', static function (): void {
    wp_enqueue_style('moalem-varzesh', get_stylesheet_uri(), [], wp_get_theme()->get('Version'));
}, 20);

function mv_icon(string $name): void {
    $icons = [
        'arrow' => '<path d="M19 12H5m6-6-6 6 6 6"/>',
        'search' => '<circle cx="11" cy="11" r="6.8"/><path d="m16 16 4.2 4.2"/>',
        'user' => '<circle cx="12" cy="8" r="3.2"/><path d="M5 21v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2z"/>',
        'cart' => '<path d="M2.5 4h2.7l2.5 11.2h10.6L21 7.5H6"/><circle cx="9" cy="20" r="1.25"/><circle cx="17" cy="20" r="1.25"/>',
        'brand' => '<path d="M4 17c4.5-7.4 9-10.3 15-10.7M7 20l6.3-5.8h5l-3 4.2M4 10l4.7-3.2 5 .8"/><circle cx="17" cy="3.7" r="1.5" fill="currentColor" stroke="none"/>',
    ];
    if (isset($icons[$name])) {
        echo '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $icons[$name] . '</svg>';
    }
}

function mv_shop_url(): string {
    return function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
}

function mv_top_categories(int $limit = 8): array {
    $terms = get_terms(['taxonomy' => 'product_cat', 'parent' => 0, 'hide_empty' => true]);
    if (is_wp_error($terms)) {
        return [];
    }
    $default = (int) get_option('default_product_cat');
    $terms = array_values(array_filter($terms, static fn(WP_Term $t): bool => $t->term_id !== $default));
    usort($terms, static fn(WP_Term $a, WP_Term $b): int => $b->count <=> $a->count ?: strcmp($a->name, $b->name));
    return array_slice($terms, 0, $limit);
}

add_action('woocommerce_archive_description', static function (): void {
    if (!is_shop() || is_search()) {
        return;
    }
    $terms = mv_top_categories(12);
    if (!$terms) {
        return;
    }
    echo '<nav class="mv-shop-cats" aria-label="دسته‌بندی کالاها">';
    foreach ($terms as $term) {
        $url = get_term_link($term);
        if (!is_wp_error($url)) {
            printf('<a href="%s">%s</a>', esc_url($url), esc_html($term->name));
        }
    }
    echo '</nav>';
}, 25);

add_filter('loop_shop_per_page', static fn(): int => 16);

function mv_meta_description(): string {
    if (is_front_page()) {
        return 'فروشگاه معلم ورزش، خرید پوشاک ورزشی، کیت فوتبال، کفش ورزشی و لوازم تمرین.';
    }
    if (is_singular()) {
        $description = get_post_meta(get_queried_object_id(), '_mv_source_description', true);
        return $description ?: get_the_excerpt(get_queried_object_id());
    }
    if (is_tax('product_cat')) {
        $term = get_queried_object();
        return $term->description ?: sprintf('مشاهده و خرید محصولات دستهٔ %s در فروشگاه معلم ورزش.', $term->name);
    }
    if (function_exists('is_shop') && is_shop()) {
        return 'محصولات فروشگاه معلم ورزش را جست‌وجو کنید و قیمت، سایز و موجودی را ببینید.';
    }
    return '';
}

function mv_archive_canonical(): string {
    $page = max(1, (int) get_query_var('paged'));
    if (is_tax('product_cat')) {
        $url = get_term_link(get_queried_object());
    } elseif (function_exists('is_shop') && is_shop()) {
        $url = mv_shop_url();
    } else {
        return '';
    }
    if (is_wp_error($url)) {
        return '';
    }
    if ($page <= 1) {
        return $url;
    }
    global $wp_rewrite;
    return trailingslashit($url) . user_trailingslashit($wp_rewrite->pagination_base . '/' . $page, 'paged');
}

add_action('wp_head', static function (): void {
    if (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('AIOSEO_VERSION')) {
        return;
    }
    $description = wp_html_excerpt(trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags(mv_meta_description()))), 160, '…');
    $canonical = is_singular() ? wp_get_canonical_url() : mv_archive_canonical();
    if ($description !== '') {
        printf("\n<meta name=\"description\" content=\"%s\">\n", esc_attr($description));
        printf("<meta property=\"og:description\" content=\"%s\">\n", esc_attr($description));
    }
    if ($canonical) {
        printf("<meta property=\"og:url\" content=\"%s\">\n", esc_url($canonical));
        printf("<meta property=\"og:title\" content=\"%s\">\n", esc_attr(wp_get_document_title()));
    }
    if (!is_singular() && $canonical) {
        printf("<link rel=\"canonical\" href=\"%s\">\n", esc_url($canonical));
    }
    if (is_singular('product') && has_post_thumbnail()) {
        printf("<meta property=\"og:image\" content=\"%s\">\n", esc_url(get_the_post_thumbnail_url(get_queried_object_id(), 'large')));
    }
}, 5);

add_filter('wp_robots', static function (array $robots): array {
    $commerce_private = function_exists('is_cart') && (is_cart() || is_checkout() || is_account_page());
    $filtered = isset($_GET['orderby']) || isset($_GET['min_price']) || isset($_GET['max_price']);
    if ($commerce_private || $filtered) {
        unset($robots['index']);
        $robots['noindex'] = true;
    }
    return $robots;
});

add_filter('woocommerce_currencies', static function (array $currencies): array {
    $currencies['IRT'] = 'تومان ایران';
    return $currencies;
});
add_filter('woocommerce_currency_symbol', static fn(string $symbol, string $currency): string => $currency === 'IRT' ? 'تومان' : $symbol, 10, 2);


if (wp_get_environment_type() === 'local') {
    add_filter('action_scheduler_allow_async_request_runner', '__return_false');
    add_filter('pre_wp_mail', '__return_true');
}

require_once __DIR__ . '/print-options.php';
