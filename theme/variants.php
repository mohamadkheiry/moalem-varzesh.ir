<?php
defined('ABSPATH') || exit;

function mv_store_design(): string {
    return ($_COOKIE['mv_store_design'] ?? '') === 'secondary' ? 'secondary' : 'primary';
}

if (mv_store_design() === 'secondary' && !defined('DONOTCACHEPAGE')) {
    define('DONOTCACHEPAGE', true);
}

add_action('template_redirect', static function (): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || is_feed() || is_embed()) {
        return;
    }
    $choice = $_GET['mv_design'] ?? '';
    if (in_array($choice, ['secondary', 'primary'], true)) {
        nocache_headers();
        $purpose = strtolower((string) ($_SERVER['HTTP_SEC_PURPOSE'] ?? $_SERVER['HTTP_PURPOSE'] ?? ''));
        if (str_contains($purpose, 'prefetch')) {
            status_header(204);
            exit;
        }
        setcookie('mv_store_design', $choice === 'secondary' ? $choice : '', [
            'expires' => $choice === 'secondary' ? time() + 30 * DAY_IN_SECONDS : time() - DAY_IN_SECONDS,
            'path' => COOKIEPATH ?: '/',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        wp_safe_redirect(remove_query_arg('mv_design'), 303);
        exit;
    }
    if (mv_store_design() === 'secondary') {
        nocache_headers();
    }
}, 1);

add_filter('body_class', static function (array $classes): array {
    $classes[] = 'mv-' . mv_store_design();
    return $classes;
});

add_filter('wp_robots', static function (array $robots): array {
    if (mv_store_design() === 'secondary') {
        unset($robots['index']);
        $robots['noindex'] = true;
    }
    return $robots;
});

add_action('wp_enqueue_scripts', static function (): void {
    if (mv_store_design() !== 'secondary') {
        return;
    }
    $version = wp_get_theme()->get('Version');
    wp_enqueue_style('mv-secondary-native', get_theme_file_uri('assets/secondary/native.css'), ['moalem-varzesh'], $version);
    wp_enqueue_style('mv-secondary-theme', get_theme_file_uri('assets/secondary/storefront.css'), ['mv-secondary-native'], $version);
}, 40);

function mv_secondary_markup(string $fragment): string {
    if (mv_store_design() !== 'secondary' || !function_exists('wc_get_cart_url')) {
        return '';
    }
    static $fragments;
    if ($fragments === null) {
        $file = get_theme_file_path('assets/secondary/components.json');
        $fragments = is_readable($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
    }
    $destinations = [
        'home' => [home_url('/'), is_front_page()],
        'shop' => [mv_shop_url(), is_shop() || is_product() || is_product_taxonomy()],
        'account' => [wc_get_page_permalink('myaccount'), is_account_page()],
        'cart' => [wc_get_cart_url(), is_cart() || is_checkout()],
    ];
    $replace = [];
    foreach ($destinations as $key => [$url, $active]) {
        $replace['__MV_' . $key . '_href__'] = esc_url($url);
        $replace['__MV_' . $key . '_current__'] = $active ? 'page' : 'false';
    }
    return strtr($fragments[$fragment] ?? '', $replace);
}
