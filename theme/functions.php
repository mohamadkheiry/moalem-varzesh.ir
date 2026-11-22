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
    if (is_home() || is_category() || is_singular('post')) {
        wp_enqueue_style('moalem-editorial', get_theme_file_uri('assets/editorial.css'), ['moalem-varzesh'], wp_get_theme()->get('Version'));
    }
    if (function_exists('is_cart') && (is_cart() || is_checkout() || is_account_page())) {
        $version = wp_get_theme()->get('Version');
        wp_enqueue_style('moalem-purchase', get_theme_file_uri('assets/purchase.css'), ['moalem-varzesh'], $version);
        if (is_cart()) {
            wp_enqueue_script('moalem-purchase', get_theme_file_uri('assets/purchase.js'), [], $version, ['strategy' => 'defer', 'in_footer' => true]);
        }
    }
    wp_enqueue_script('moalem-social-links', get_theme_file_uri('assets/social-links.js'), [], wp_get_theme()->get('Version'), ['strategy' => 'defer', 'in_footer' => true]);
    if (is_front_page()) {
        wp_enqueue_script('moalem-hero-slider', get_theme_file_uri('assets/hero-slider.js'), [], wp_get_theme()->get('Version'), ['strategy' => 'defer', 'in_footer' => true]);
    }
}, 20);

function mv_icon(string $name): void {
    $icons = [
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'bag' => '<path d="M5 8h14l1 13H4L5 8Z"/><path d="M9 10V6a3 3 0 0 1 6 0v4"/>',
        'arrow' => '<path d="M19 12H5m6-6-6 6 6 6"/>',
        'search' => '<circle cx="11" cy="11" r="6.8"/><path d="m16 16 4.2 4.2"/>',
        'user' => '<circle cx="12" cy="8" r="3.2"/><path d="M5 21v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2z"/>',
        'cart' => '<path d="M2.5 4h2.7l2.5 11.2h10.6L21 7.5H6"/><circle cx="9" cy="20" r="1.25"/><circle cx="17" cy="20" r="1.25"/>',
        'brand' => '<path d="M4 17c4.5-7.4 9-10.3 15-10.7M7 20l6.3-5.8h5l-3 4.2M4 10l4.7-3.2 5 .8"/><circle cx="17" cy="3.7" r="1.5" fill="currentColor" stroke="none"/>',
        'social' => '<circle cx="5" cy="12" r="2.6"/><circle cx="18" cy="5" r="2.6"/><circle cx="18" cy="19" r="2.6"/><path d="m7.3 10.8 8.4-4.6M7.3 13.2l8.4 4.6"/>',
        'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.3" cy="6.7" r="1.05" fill="currentColor" stroke="none"/>',
        'telegram' => '<path d="M21 4 3 11.2l5.2 2 2.4 6.2 3-3.3 4.3 3.1L21 4ZM8.2 13.2 17 7.5"/>',
        'whatsapp' => '<path d="M20.5 11.9a8.5 8.5 0 0 1-12.2 7.6l-4.6 1 1-4.3a8.5 8.5 0 1 1 15.8-4.3Z"/><path d="M9 8.5c.3 3 2.6 5.5 5.6 6l1.1-1.3-1.8-1.1-.9.5a6 6 0 0 1-2.3-2.5l.5-.7-1-1.8Z"/>',
        'chat' => '<path d="M20.6 11.8a8.6 8.6 0 0 1-8.6 8.6 9 9 0 0 1-3.8-.9l-4.6 1 .9-4.4a8.6 8.6 0 1 1 16.1-4.3Z"/><path d="M8.8 11.8h6.4"/>',
        'phone' => '<path d="m8 4 2.2 4.1-2.1 2.2a13 13 0 0 0 5.6 5.6l2.2-2.1L20 16v2.2c0 1-.8 1.8-1.8 1.8A14.2 14.2 0 0 1 4 5.8C4 4.8 4.8 4 5.8 4Z"/>',
    ];
    if (isset($icons[$name])) {
        echo '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $icons[$name] . '</svg>';
    }
}

require_once __DIR__ . '/purchase.php';

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
        $id = get_queried_object_id();
        $description = get_post_meta($id, '_mv_seo_description', true) ?: get_post_meta($id, '_mv_source_description', true);
        return $description ?: get_the_excerpt(get_queried_object_id());
    }
    if (is_home()) {
        return 'راهنماهای فارسی معلم ورزش برای انتخاب کفش فوتسال و فوتبال، سایزبندی، چاپ لباس و مراقبت از تجهیزات تمرین.';
    }
    if (is_category()) {
        return get_queried_object()->description ?: 'راهنماهای خرید و نگهداری تجهیزات ورزشی معلم ورزش.';
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
    if (is_tax('product_cat') || is_category()) {
        $url = get_term_link(get_queried_object());
    } elseif (is_home() && get_option('page_for_posts')) {
        $url = get_permalink((int) get_option('page_for_posts'));
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

function mv_external_seo(): bool {
    return defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('AIOSEO_VERSION');
}

add_filter('document_title_parts', static function (array $parts): array {
    if (is_singular(['product', 'post']) && !mv_external_seo()) {
        $title = get_post_meta(get_queried_object_id(), '_mv_seo_title', true);
        if ($title) {
            $parts['title'] = $title;
        }
    }
    return $parts;
});

add_action('wp_head', static function (): void {
    if (mv_external_seo()) {
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
    if (is_singular(['product', 'post'])) {
        printf("<meta property=\"og:type\" content=\"%s\">\n", is_singular('post') ? 'article' : 'product');
        if (has_post_thumbnail(get_queried_object_id())) {
            printf("<meta property=\"og:image\" content=\"%s\">\n", esc_url(get_the_post_thumbnail_url(get_queried_object_id(), 'large')));
        }
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
require_once __DIR__ . '/editorial.php';
require_once __DIR__ . '/mobile-store.php';
