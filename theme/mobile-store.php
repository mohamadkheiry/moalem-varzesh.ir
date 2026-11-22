<?php
defined('ABSPATH') || exit;

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use Automattic\WooCommerce\StoreApi\Utilities\CartTokenUtils;

add_filter('woocommerce_store_api_add_to_cart_data', static function (array $data, WP_REST_Request $request): array {
    $product = wc_get_product((int) $data['id']);
    if (!$product || !$product->is_type('variation') || !mv_is_custom_print($product->get_attributes())) {
        return $data;
    }
    $fields = mv_print_fields([
        'mv_print_name' => $request->get_param('mv_print_name'),
        'mv_print_number' => $request->get_param('mv_print_number'),
    ]);
    if (!mv_valid_print($fields)) {
        throw new RouteException('mv_print_required', 'برای چاپ سفارشی، نام و شمارهٔ یک یا دو رقمی را وارد کنید.', 400);
    }
    $data['cart_item_data']['mv_print'] = $fields;
    return $data;
}, 10, 2);

add_filter('rest_request_after_callbacks', static function ($response, array $handler, WP_REST_Request $request) {
    if ($request->get_route() !== '/wc/store/v1/cart'
        || $request->get_method() !== 'GET'
        || $request->get_param('mv_checkout') !== '1'
        || !($response instanceof WP_REST_Response)) {
        return $response;
    }

    if (!class_exists(CartTokenUtils::class)) {
        return new WP_Error('mv_checkout_unavailable', 'انتقال سبد به تسویه‌حساب در دسترس نیست.', ['status' => 503]);
    }

    $session = WC()->session;
    $cart = WC()->cart;
    $payload = CartTokenUtils::get_cart_token_payload($request->get_header('Cart-Token') ?: '');
    $customer = (string) ($payload['user_id'] ?? '');
    if ($customer === '' || !$session || $customer !== (string) $session->get_customer_id() || is_user_logged_in()) {
        return new WP_Error('mv_session_invalid', 'نشست سبد خرید معتبر نیست.', ['status' => 401]);
    }
    if (!$cart || $cart->is_empty()) {
        return new WP_Error('mv_cart_empty', 'سبد خرید خالی است.', ['status' => 400]);
    }

    $data = $response->get_data();
    $data['extensions'] = (array) ($data['extensions'] ?? []);
    $data['extensions']['moalem-varzesh']['checkout_url'] = add_query_arg(
        'session',
        CartTokenUtils::get_cart_link_token($customer),
        wc_get_checkout_url()
    );
    $response->set_data($data);
    $response->header('Cache-Control', 'private, no-store', true);
    return $response;
}, 10, 3);

const MV_MOBILE_PREVIEW_ORIGINS = ['http://127.0.0.1:8091', 'http://localhost:8091'];

function mv_is_local_store_request(WP_REST_Request $request): bool {
    return wp_get_environment_type() === 'local' && str_starts_with($request->get_route(), '/wc/store/v1/');
}

// The static Expo preview runs on its own loopback port on the development machine.
add_filter('rest_pre_dispatch', static function ($result, WP_REST_Server $server, WP_REST_Request $request) {
    if (!mv_is_local_store_request($request)) {
        return $result;
    }
    $origin = $request->get_header('Origin');
    if ($origin && !in_array($origin, MV_MOBILE_PREVIEW_ORIGINS, true)
        && untrailingslashit($origin) !== untrailingslashit(home_url('/'))) {
        return new WP_Error('mv_origin_forbidden', 'مبدأ درخواست برای پیش‌نمایش محلی مجاز نیست.', ['status' => 403]);
    }
    return $result;
}, 30, 3);

add_filter('rest_pre_serve_request', static function (bool $served, $result, WP_REST_Request $request): bool {
    if (!mv_is_local_store_request($request)) {
        return $served;
    }
    $origin = $request->get_header('Origin');
    if (!$origin) {
        return $served;
    }
    if (in_array($origin, MV_MOBILE_PREVIEW_ORIGINS, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Headers: Content-Type, Cart-Token');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Expose-Headers: Cart-Token, X-WP-Total, X-WP-TotalPages');
    } elseif (untrailingslashit($origin) !== untrailingslashit(home_url('/'))) {
        header_remove('Access-Control-Allow-Origin');
        header_remove('Access-Control-Allow-Credentials');
        header_remove('Access-Control-Expose-Headers');
    }
    header('Vary: Origin', false);
    return $served;
}, 30, 3);
