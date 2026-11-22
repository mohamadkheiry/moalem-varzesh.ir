<?php
defined('WP_CLI') && WP_CLI || exit;
require_once __DIR__ . '/catalog-category-plan.php';

$catalog = dirname(__DIR__) . '/catalog';
$load = static fn(string $name): array => json_decode(file_get_contents("$catalog/$name.json"), true, 512, JSON_THROW_ON_ERROR);
$products = $load('products');
$terms = array_column($load('categories'), null, 'id');
global $wpdb;
$local = [];
foreach ($wpdb->get_results("SELECT p.ID, m.meta_value FROM $wpdb->posts p INNER JOIN $wpdb->postmeta m ON p.ID=m.post_id
    WHERE p.post_type='product' AND p.post_status='publish' AND m.meta_key='_mv_source_id'") as $row) {
    $local[(int) $row->meta_value] = (int) $row->ID;
}
if (array_diff(array_column($products, 'id'), array_keys($local))) {
    WP_CLI::error('Import the catalog before running product SEO.');
}
foreach ([
    'table-tennis' => ['تنیس روی میز', 'توپ‌ها و وسایل تنیس روی میز برای تمرین و بازی.'],
    'توپ-های-ورزشی' => ['توپ‌های ورزشی', 'توپ‌های ورزشی موجود برای تنیس روی میز و تمرین پیلاتس.'],
] as $slug => [$name, $description]) {
    if (get_term_by('slug', sanitize_title($slug), 'product_cat')) {
        continue;
    }
    $new_term = wp_insert_term($name, 'product_cat', [
        'slug' => $slug, 'description' => $description,
    ]);
    if (is_wp_error($new_term)) {
        WP_CLI::error($new_term->get_error_message());
    }
}
foreach ([564 => 271, 565 => 288] as $old => $new) {
    $alias = get_term_by('slug', sanitize_title(rawurldecode($terms[$old]['slug'])), 'product_cat');
    $target = get_term_by('slug', sanitize_title(rawurldecode($terms[$new]['slug'])), 'product_cat');
    if (!$alias || !$target) {
        WP_CLI::error('A source product category is missing.');
    }
    update_term_meta($alias->term_id, '_mv_category_redirect', $target->term_id);
}
$plain = static function (string $value): string {
    $value = html_entity_decode(wp_strip_all_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/[\s\x{200F}\x{200E}]+/u', ' ', $value));
};
$clip = static function (string $value, int $limit): string {
    if (mb_strlen($value) <= $limit) {
        return $value;
    }
    $prefix = mb_substr($value, 0, $limit - 1);
    $words = preg_replace('/\s+\S*$/u', '', $prefix);
    return preg_replace('/[ |,،؛.]+$/u', '', $words ?: $prefix) . '…';
};
$missing_summaries = [
    9765 => 'لباس تیم ملی فرانسه با طرح امباپه و شمارهٔ ۱۰. عکس‌ها و گزینه‌های سایز و چاپ را در همین صفحه مقایسه کنید.',
    9751 => 'لباس تیم ملی اسپانیا با طرح یامال و شمارهٔ ۱۹. پیش از سفارش، عکس‌های مدل و گزینه‌های سایز و چاپ را ببینید.',
];
$notes = [
    'kits' => 'طرح، اندازه و گزینه‌های لباس را مقایسه کنید.',
    'futsal' => 'نمای زیرهٔ سالنی و گزینه‌های سایز را ببینید.',
    'turf' => 'نوع زیره، کاربرد و گزینه‌های اندازه را بررسی کنید.',
    'socks' => 'ارتفاع ساق، نوع کف و محدودهٔ سایز را بررسی کنید.',
    'protection' => 'جنس، شیوهٔ بستن و اندازه را در مشخصات ببینید.',
    'training' => 'ابعاد، عکس‌ها و گزینه‌های قابل انتخاب را مقایسه کنید.',
    'sizing' => 'اندازه و برش لباس را از جدول و عکس‌ها بررسی کنید.',
    'buying' => 'عکس‌ها، مشخصات و گزینه‌های خرید را بررسی کنید.',
];
$metadata = [];
$used_titles = [];
$changed_categories = 0;
$completed_summaries = 0;
$cleaned_content = 0;
foreach ($products as $source) {
    $id = $local[$source['id']];
    $product = wc_get_product($id);
    $category_ids = [];
    foreach (mv_catalog_category_plan($source, $terms) as $slug) {
        $term = get_term_by('slug', $slug, 'product_cat');
        if (!$term) {
            WP_CLI::error("Missing normalized category $slug");
        }
        $category_ids[] = (int) $term->term_id;
    }
    $existing = array_map('intval', $product->get_category_ids());
    sort($existing);
    sort($category_ids);
    if ($existing !== $category_ids) {
        $product->set_category_ids($category_ids);
        ++$changed_categories;
    }
    if (!$plain($product->get_short_description())) {
        $summary = $missing_summaries[$source['id']] ?? ($plain($source['short_description']) ?: $clip($plain($source['description']), 240));
        $product->set_short_description('<p>' . esc_html($summary) . '</p>');
        ++$completed_summaries;
    }
    if ($source['id'] === 9825 && str_contains($product->get_description(), '[MESSI]')) {
        $clean = preg_replace('~<span\b[^>]*>\s*\[MESSI\]\s*</span>~iu', '(MESSI)', $product->get_description());
        $product->set_description(str_replace('[MESSI]', '(MESSI)', $clean));
        ++$cleaned_content;
    }
    if ($product->get_changes()) {
        $product->save();
    }
    $topic = mv_catalog_topic($source);
    $name = str_replace(['Mizano', 'کتونی'], ['Mizuno', 'کفش'], $plain($product->get_name()));
    $primary = explode('|', $name, 2)[0];
    $title = $clip(trim($primary), 70);
    if (isset($used_titles[$title])) {
        $title = $name;
    }
    if (isset($used_titles[$title])) {
        WP_CLI::error('Non-unique product title for ' . $source['id']);
    }
    $used_titles[$title] = true;
    $excerpt = $plain($product->get_short_description());
    $short_name = $clip(trim($primary), 82);
    $description = $short_name . '. ' . $notes[$topic];
    if ($excerpt !== '') {
        $first = preg_split('/(?<=[.!؟])\s+/u', $excerpt, 2)[0];
        if (mb_strlen($first) >= 42 && !str_contains($first, 'تستی')) {
            $description = $clip($short_name . ' | ' . $first, 155);
        }
    }
    if (isset($metadata[$description])) {
        $description = $clip($name, 100) . '. ' . $notes[$topic];
    }
    if (isset($metadata[$description])) {
        WP_CLI::error('Non-unique product snippet for ' . $source['id']);
    }
    $metadata[$description] = true;
    update_post_meta($id, '_mv_seo_title', wp_slash($title));
    update_post_meta($id, '_mv_seo_description', wp_slash($description));
    if (get_post_meta($id, '_mv_seo_title', true) !== $title
        || get_post_meta($id, '_mv_seo_description', true) !== $description) {
        WP_CLI::error('Could not save Persian SEO metadata for product ' . $source['id']);
    }
    update_post_meta($id, '_mv_seo_topic', $topic);
}

$pictures = array_column($products, 'images', 'id');
foreach ([
    'کتونی-فوتسال' => 12080,
    'کتونی-فوتبال-استوک-ریز' => 11770,
    'کتونی-فوتبال-استوک' => 11794,
] as $slug => $source_id) {
    $term = get_term_by('slug', sanitize_title($slug), 'product_cat');
    $source_url = $pictures[$source_id][1]['src'] ?? '';
    if (!$term || !$source_url) {
        continue;
    }
    $photo = get_posts([
        'post_type' => 'attachment', 'post_status' => 'inherit', 'fields' => 'ids', 'numberposts' => 1,
        'meta_key' => '_mv_source_media', 'meta_value' => $source_url,
    ]);
    if ($photo) {
        update_term_meta($term->term_id, '_mv_home_shoe_image', $photo[0]);
    }
}
update_option('mv_catalog_editorial_schema', 1);
WP_CLI::success(sprintf('SEO metadata for %d products, %d category assignments repaired, %d missing summaries completed, %d editor placeholders removed.',
    count($metadata), $changed_categories, $completed_summaries, $cleaned_content));
