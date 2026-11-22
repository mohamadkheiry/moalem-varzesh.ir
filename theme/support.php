<?php
defined('ABSPATH') || exit;

function mv_support_pages(): array {
    return [
        'about' => ['about-us', 'دربارهٔ معلم ورزش', 'با فروشگاه و گروه‌های کالا آشنا شوید و راه پیگیری سفارش و خرید تیمی را پیدا کنید.', '#285b48', 'about'],
        'contact' => ['contact-us', 'تماس با ما', 'برای پرسش دربارهٔ کالا یا پیگیری سفارش، از راه‌های ارتباطی معلم ورزش استفاده کنید.', '#336153', 'contact'],
        'guides' => ['blog', 'راهنمای خرید و مقالات ورزشی', 'برای خرید بهتر، راهنمای انتخاب و نگهداری کفش، لباس و لوازم ورزش را بخوانید.', '#235767', 'guides'],
        'terms' => ['terms-and-conditions', 'قوانین و مقررات', 'اطلاعات انتخاب کالا، ثبت سفارش، پرداخت و پیگیری خرید را پیش از سفارش بخوانید.', '#4c5941', 'terms'],
        'returns' => ['refund_returns-2', 'شرایط مرجوعی و بازپرداخت', 'شرایط کالا، راه ثبت درخواست و روش پیگیری مرجوعی را اینجا ببینید.', '#775239', 'returns'],
        'privacy' => ['حریم-خصوصی-معلم-ورزش', 'حریم خصوصی', 'با شیوهٔ استفاده از اطلاعات حساب و سفارش و راه ارسال درخواست آشنا شوید.', '#31545d', 'policy'],
        'cookies' => ['سیاست-کوکی', 'سیاست کوکی', 'ببینید فروشگاه برای ورود و نگهداری سبد خرید چگونه از کوکی‌های مرورگر استفاده می‌کند.', '#526443', 'cookies'],
    ];
}

function mv_support_page_id(string $key): int {
    static $resolved = [];
    if (!isset($resolved[$key])) {
        $option = ['guides' => 'page_for_posts', 'privacy' => 'wp_page_for_privacy_policy', 'terms' => 'woocommerce_terms_page_id'][$key] ?? '';
        $seeded = get_option('mv_support_page_ids', []);
        $id = $option ? (int) get_option($option) : (int) ($seeded[$key] ?? 0);
        if (!$id || get_post_status($id) !== 'publish' || get_post_type($id) !== 'page') {
            $page = get_page_by_path(mv_support_pages()[$key][0]);
            $id = $page && $page->post_status === 'publish' ? $page->ID : 0;
        }
        $resolved[$key] = $id;
    }
    return $resolved[$key];
}

function mv_support_page_key(int $id): string {
    foreach (array_keys(mv_support_pages()) as $key) {
        if ($id && $id === mv_support_page_id($key)) {
            return $key;
        }
    }
    return '';
}

function mv_footer_locations(): array {
    return [
        'mv_footer_help' => 'راهنما و پشتیبانی',
        'mv_footer_policies' => 'سیاست‌های فروشگاه',
        'mv_footer_shop' => 'دسترسی سریع',
    ];
}

function mv_footer_default_items(): array {
    $groups = [
        'mv_footer_help' => ['guides', 'contact', 'about'],
        'mv_footer_policies' => ['terms', 'returns', 'privacy', 'cookies'],
    ];
    $items = [];
    foreach ($groups as $location => $keys) {
        foreach ($keys as $key) {
            if ($id = mv_support_page_id($key)) {
                $items[$location][] = [$id, mv_support_pages()[$key][1]];
            }
        }
    }
    if (function_exists('wc_get_page_id')) {
        foreach (['shop' => 'فروشگاه', 'myaccount' => 'حساب کاربری', 'cart' => 'سبد خرید'] as $key => $label) {
            $id = wc_get_page_id($key);
            if ($id > 0 && get_post_status($id) === 'publish') {
                $items['mv_footer_shop'][] = [$id, $label];
            }
        }
    }
    return $items;
}

function mv_footer_link_contents(string $title, int $page_id): string {
    $icon = match (mv_support_page_key($page_id)) {
        'guides' => 'book',
        'about' => 'info',
        'contact' => 'chat',
        'terms' => 'document',
        'returns' => 'return',
        'privacy' => 'shield',
        'cookies' => 'check',
        default => 'arrow',
    };
    if ($icon === 'arrow' && $page_id && function_exists('wc_get_page_id')) {
        $icon = match ($page_id) {
            wc_get_page_id('shop') => 'bag',
            wc_get_page_id('myaccount') => 'user',
            wc_get_page_id('cart') => 'cart',
            default => 'arrow',
        };
    }
    ob_start();
    ?><span class="mv-footer-link-icon" data-icon="<?php echo esc_attr($icon); ?>" aria-hidden="true"><?php mv_icon($icon); ?></span><span class="mv-footer-link-label"><?php echo $title; ?></span><?php
    return (string) ob_get_clean();
}

add_filter('nav_menu_item_title', static function (string $title, WP_Post $item, stdClass $args): string {
    if (!isset(mv_footer_locations()[$args->theme_location ?? ''])) {
        return $title;
    }
    return mv_footer_link_contents($title, $item->object === 'page' ? (int) $item->object_id : 0);
}, 10, 3);

function mv_support_defaults(): array {
    $defaults = [
        'footer_intro' => 'فروشگاه پوشاک، کفش و تجهیزات ورزشی',
        'footer_credit' => 'تمام حقوق فروشگاه معلم ورزش محفوظ است.',
        'footer_accent' => '#285b48',
        'headings' => mv_footer_locations(),
        'pages' => [],
    ];
    foreach (mv_support_pages() as $key => [, , $intro, $accent]) {
        $defaults['pages'][$key] = ['intro' => $intro, 'accent' => $accent, 'image' => 0];
    }
    return $defaults;
}

function mv_support_settings(): array {
    $settings = get_option('mv_support_design', []);
    return array_replace_recursive(mv_support_defaults(), is_array($settings) ? $settings : []);
}

function mv_support_cover(string $key, int $id, int $image): void {
    $attachment = $image && wp_attachment_is_image($image) ? $image : get_post_thumbnail_id($id);
    if ($attachment) {
        echo wp_get_attachment_image($attachment, 'large', false, [
            'alt' => '', 'sizes' => '(min-width: 768px) 47vw, 94vw', 'fetchpriority' => 'high', 'loading' => 'eager', 'decoding' => 'async',
        ]);
        return;
    }
    $filename = 'assets/support/' . mv_support_pages()[$key][4];
    ?>
    <picture>
        <source media="(max-width: 767px)" srcset="<?php echo esc_url(get_theme_file_uri($filename . '-640.webp')); ?>">
        <img src="<?php echo esc_url(get_theme_file_uri($filename . '-1200.webp')); ?>"
            alt="" width="1200" height="800" fetchpriority="high" decoding="async">
    </picture>
    <?php
}

function mv_support_readable_content(string $html, string $page_title): array {
    $links = [];
    $html = preg_replace_callback('~<h([12])\b([^>]*)>(.*?)</h\1>~is', static function (array $match) use (&$links, $page_title): string {
        [, , $attributes, $contents] = $match;
        $title = trim(html_entity_decode(wp_strip_all_tags($contents), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($title === $page_title) {
            return '';
        }
        $tag = new WP_HTML_Tag_Processor('<h2' . $attributes . '>');
        $tag->next_tag();
        $anchor = $tag->get_attribute('id') ?: 'mv-support-section-' . (count($links) + 1);
        $tag->set_attribute('id', $anchor);
        $links[] = [$anchor, $title];
        return $tag->get_updated_html() . $contents . '</h2>';
    }, $html);
    return [$html, $links];
}

if (is_admin()) {
    require_once __DIR__ . '/support-admin.php';
}
