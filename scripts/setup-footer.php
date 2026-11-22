<?php
defined('WP_CLI') && WP_CLI || exit;
if (!function_exists('mv_footer_default_items')) {
    WP_CLI::error('Activate the Moalem Varzesh theme first.');
}

$ids = [];
$paths = [];
foreach (mv_support_pages() as $key => [$slug]) {
    $id = mv_support_page_id($key);
    if ($id) {
        $ids[$key] = $id;
        $paths[rawurldecode($slug)] = wp_make_link_relative(get_permalink($id));
    }
}
update_option('mv_support_page_ids', $ids);

$replacements = [
    'about' => ['>فرم تماس<' => '>صفحهٔ تماس<'],
    'terms' => [
        'پرداخت اینترنتی از طریق درگاه فعال زرین‌پال انجام می‌شود.' => 'برای پرداخت اینترنتی، از روش پرداختی استفاده کنید که در صفحهٔ تسویه‌حساب نمایش داده می‌شود.',
    ],
    'privacy' => [
        'فرم تماس برای دریافت درخواست و پاسخ‌گویی است. اطلاعاتی که در پیام می‌نویسید به مسئول رسیدگی فروشگاه ارسال می‌شود. برای پیگیری سفارش، شماره سفارش کافی است؛ رمز بانکی، رمز یک‌بارمصرف، CVV2 یا تصویر مدارک هویتی را در فرم تماس نفرستید.' =>
            'پیام‌هایی که از راه‌های معرفی‌شده در صفحهٔ تماس می‌فرستید برای پاسخ‌گویی به درخواست شما بررسی می‌شوند. برای پیگیری، شمارهٔ سفارش را اعلام کنید. رمز بانکی، کد ورود، CVV2 یا تصویر مدارک هویتی را برای پشتیبانی نفرستید.',
        'درگاه فعال فروشگاه زرین‌پال است. پردازش پرداخت در مسیر ارائه‌دهنده پرداخت انجام می‌شود' =>
            'در خریدهایی که با درگاه اینترنتی پرداخت می‌شوند، پردازش پرداخت در مسیر ارائه‌دهندهٔ پرداخت انجام می‌شود',
        'پیوندهای تلگرام، واتساپ، اینستاگرام، ایتا و بله شما را به سرویس مستقل دیگری می‌برند.' =>
            'پیوند شبکه‌های اجتماعی شما را به سرویسی مستقل از فروشگاه می‌برد.',
        'رهگیری منبع ورود و کمپین سفارش در تنظیمات فروشگاه غیرفعال شده است.' =>
            'اگر قابلیت انتساب سفارش ووکامرس فعال باشد، ممکن است منبع ورود و اطلاعات نشست با سفارش ثبت شود.',
    ],
    'cookies' => [
        'این سایت از وردپرس، ووکامرس، قالب Athletex و المنتور استفاده می‌کند.' =>
            'این فروشگاه با وردپرس، ووکامرس و قالب اختصاصی معلم ورزش اجرا می‌شود.',
        ' فهرست علاقه‌مندی و مقایسه نیز ممکن است برای نگهداری انتخاب در مرورگر از ذخیره محلی یا کوکی استفاده کنند؛ از این قابلیت‌ها تنها در صورت نیاز استفاده کنید.' => '',
        'قابلیت انتساب سفارش ووکامرس (Sourcebuster) که منبع ورود، کمپین، صفحات نشست و اطلاعات مرورگر را ثبت می‌کرد، در تنظیمات فروشگاه غیرفعال شده است. این قابلیت برای ثبت خرید ضروری نیست. کوکی‌های قدیمی آن ممکن است تا انقضا یا پاک‌سازی مرورگر باقی بمانند.' =>
            'قابلیت انتساب سفارش ووکامرس، در صورت فعال بودن، از کوکی‌های نشست برای تشخیص منبع ورود به فروشگاه استفاده می‌کند. دادهٔ انتساب هنگامی در ووکامرس ثبت می‌شود که سفارشی ثبت شده باشد. این قابلیت برای ثبت خرید ضروری نیست.',
        'در صفحات اصلی و تماس بررسی‌شده، اسکریپت مستقیم Google Analytics، Google Tag Manager، Meta Pixel، ویدئوی YouTube، نقشه یا چت بیرونی شناسایی نشد. این نتیجه به صفحات و وضعیت بررسی‌شده محدود است.' =>
            'کوکی‌هایی که یک سامانهٔ مستقل به‌کار می‌برد ممکن است با کوکی‌های فروشگاه متفاوت باشند.',
    ],
];
$changed = 0;
foreach ($ids as $key => $id) {
    if ($key === 'guides') continue;
    $original = get_post_field('post_content', $id, 'raw');
    $links = new WP_HTML_Tag_Processor(strtr($original, $replacements[$key] ?? []));
    while ($links->next_tag('A')) {
        $href = $links->get_attribute('href');
        if (!is_string($href)) continue;
        $parsed = wp_parse_url($href);
        if (!in_array(strtolower($parsed['host'] ?? ''), ['moalem-varzesh.ir', 'www.moalem-varzesh.ir'], true)) continue;
        $path = trim(rawurldecode($parsed['path'] ?? ''), '/');
        if (!isset($paths[$path])) continue;
        $new = $paths[$path] . (isset($parsed['query']) ? '?' . $parsed['query'] : '')
            . (isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '');
        $links->set_attribute('href', $new);
    }
    $content = $links->get_updated_html();
    if ($content !== $original) {
        $saved = wp_update_post(['ID' => $id, 'post_content' => wp_slash($content)], true);
        if (is_wp_error($saved)) WP_CLI::error($saved->get_error_message());
        ++$changed;
    }
}

$locations = get_nav_menu_locations();
foreach (mv_footer_default_items() as $location => $entries) {
    if (!empty($locations[$location])) continue;
    $label = 'فوتر · ' . mv_footer_locations()[$location];
    $existing = wp_get_nav_menu_object($label);
    $menu = $existing ? (int) $existing->term_id : wp_create_nav_menu($label);
    if (is_wp_error($menu)) WP_CLI::error($menu->get_error_message());
    if (!wp_get_nav_menu_items($menu)) {
        foreach ($entries as [$id, $title]) {
            $item = wp_update_nav_menu_item($menu, 0, [
                'menu-item-title' => wp_slash($title),
                'menu-item-object-id' => $id,
                'menu-item-object' => 'page',
                'menu-item-type' => 'post_type',
                'menu-item-status' => 'publish',
            ]);
            if (is_wp_error($item)) WP_CLI::error($item->get_error_message());
        }
    }
    $locations[$location] = $menu;
}
set_theme_mod('nav_menu_locations', $locations);
WP_CLI::success(sprintf('Footer menu locations ready. %d support pages found, %d updated.', count($ids), $changed));
