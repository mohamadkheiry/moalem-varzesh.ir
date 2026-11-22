<?php
defined('ABSPATH') || exit;

function mv_commerce_purchase_return_url(): string {
    $id = is_scalar($_GET['mv_item'] ?? null) ? absint($_GET['mv_item']) : 0;
    if (!$id || get_post_type($id) !== 'product' || get_post_status($id) !== 'publish') {
        return wc_get_page_permalink('shop');
    }
    $url = get_permalink($id);
    $pending = WC()->session ? WC()->session->get('mv_purchase_intent') : null;
    return ($pending['id'] ?? 0) === $id ? add_query_arg('mv_resume_purchase', '1', $url) : $url;
}

add_action('wp_loaded', static function (): void {
    if (!function_exists('WC') || !WC()->session || is_admin() || wp_doing_ajax() || isset($_GET['wc-ajax'])) {
        return;
    }
    if (is_user_logged_in() && ($_GET['mv_resume_purchase'] ?? '') === '1') {
        $pending = WC()->session->get('mv_purchase_intent');
        if (!$pending) {
            return;
        }
        WC()->session->__unset('mv_purchase_intent');
        $due = (int) ($pending['due'] ?? 0);
        if ($due >= time() && !empty($pending['token'])
            && mv_commerce_write(mv_commerce_key('purchase-resume', $pending['token']), null, $due, [])) {
            // WooCommerce handles the product, variation and stock checks at priority 20.
            $_POST = wp_slash($pending['fields']);
            $_REQUEST = $_POST + $_REQUEST;
        }
        add_action('wp_loaded', static function () use ($pending): void {
            wp_safe_redirect(get_permalink($pending['id']) ?: wc_get_cart_url(), 303);
            exit;
        }, 21);
        return;
    }
    if (is_user_logged_in() || !is_scalar($_REQUEST['add-to-cart'] ?? null)) {
        return;
    }
    $id = absint($_REQUEST['add-to-cart']);
    if (!$id || get_post_type($id) !== 'product' || get_post_status($id) !== 'publish') {
        return;
    }
    $fields = ['add-to-cart' => (string) $id];
    $source = wp_unslash($_POST + $_GET);
    foreach ($source as $key => $value) {
        if (is_scalar($value) && (in_array($key, ['product_id', 'variation_id', 'quantity', 'mv_print_name', 'mv_print_number'], true)
            || str_starts_with((string) $key, 'attribute_'))) {
            $fields[$key] = wc_clean($value);
        }
    }
    WC()->session->set('mv_purchase_intent', [
        'id' => $id, 'fields' => $fields, 'due' => time() + 15 * MINUTE_IN_SECONDS, 'token' => wp_generate_uuid4(),
    ]);
    WC()->session->set_customer_session_cookie(true);
    nocache_headers();
    wp_safe_redirect(add_query_arg(['return' => 'product', 'mv_item' => $id], wc_get_page_permalink('myaccount')), 303);
    exit;
}, 19);

add_filter('woocommerce_add_to_cart_validation', static function (bool $valid): bool {
    if (!$valid || is_user_logged_in() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return $valid;
    }
    wc_add_notice('برای افزودن کالا به سبد خرید وارد حساب‌تان شوید.', 'error');
    return false;
}, 5);

add_action('template_redirect', static function (): void {
    if (!function_exists('is_cart') || is_user_logged_in() || !(is_cart() || is_checkout())
        || is_wc_endpoint_url('order-received') || is_wc_endpoint_url('order-pay')) {
        return;
    }
    nocache_headers();
    wp_safe_redirect(add_query_arg('return', is_cart() ? 'cart' : 'checkout', wc_get_page_permalink('myaccount')), 303);
    exit;
}, 8);

add_filter('woocommerce_checkout_registration_required', '__return_true');

add_filter('wp_speculation_rules_href_exclude_paths', static function (array $paths): array {
    if (!function_exists('wc_get_page_permalink')) {
        return $paths;
    }
    $home_path = untrailingslashit((string) wp_parse_url(home_url('/'), PHP_URL_PATH));
    foreach (['cart', 'checkout', 'myaccount'] as $page) {
        $path = untrailingslashit((string) wp_parse_url(wc_get_page_permalink($page), PHP_URL_PATH));
        if ($home_path !== '' && str_starts_with($path, $home_path . '/')) {
            $path = substr($path, strlen($home_path));
        }
        if ($path !== '') {
            $paths[] = $path;
            $paths[] = $path . '/*';
        }
    }
    return $paths;
});

function mv_commerce_purchase_dialog_available(): bool {
    return function_exists('is_account_page') && !is_user_logged_in() && !is_account_page();
}

add_action('wp_enqueue_scripts', static function (): void {
    if (mv_commerce_purchase_dialog_available()) {
        wp_enqueue_style('mv-account-dialog', plugins_url('assets/account-dialog.css', __FILE__), [], '1.5.1');
        wp_enqueue_script('mv-account-otp', plugins_url('assets/account-otp.js', __FILE__), [], '1.5.0', ['in_footer' => true, 'strategy' => 'defer']);
        wp_enqueue_script('mv-account-dialog', plugins_url('assets/account-dialog.js', __FILE__), ['mv-account-otp'], '1.5.1', ['in_footer' => true, 'strategy' => 'defer']);
    }
});

add_action('wp_footer', static function (): void {
    if (!mv_commerce_purchase_dialog_available()) {
        return;
    }
    $account = wc_get_page_permalink('myaccount');
    ?>
    <dialog class="mv-account-dialog" aria-label="ورود برای ادامهٔ خرید"
        data-account="<?php echo esc_url(add_query_arg('mv_modal', '1', $account)); ?>"
        data-account-page="<?php echo esc_url($account); ?>"
        data-cart="<?php echo esc_url(wc_get_cart_url()); ?>" data-checkout="<?php echo esc_url(wc_get_checkout_url()); ?>">
        <button class="mv-account-dialog-close" type="button" aria-label="بستن پنجرهٔ ورود"><span aria-hidden="true">×</span></button>
        <div class="mv-account-dialog-content"></div>
        <p class="mv-account-dialog-status" role="status" hidden></p>
    </dialog>
    <?php
}, 8);
