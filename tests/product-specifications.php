<?php
defined('WP_CLI') && WP_CLI || exit;

$failures = [];
$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$products = wc_get_products(['status' => 'publish', 'limit' => -1]);
$covered = 0;
$groups = 0;
$description_tables = 0;
$known_labels = [12196 => 'رنگ', 11889 => 'رنگ و مقاومت', 11476 => 'تعداد در بسته', 11397 => 'رنگ'];
foreach ($products as $product) {
    $id = (int) $product->get_meta('_mv_source_id');
    $description = $product->get_description();
    if ($tables = preg_match_all('/<table\b/i', $description)) {
        $rendered = mv_description_specifications($description);
        $expect(preg_match_all('/<table\b/i', $rendered) === 0, 'Legacy description table rendered as specifications for ' . $id);
        $expect(substr_count($rendered, 'class="mv-spec-row"') === preg_match_all('/<tr\b/i', $description) - $tables,
            'Description specification cells are preserved for ' . $id);
        $description_tables += $tables;
    }
    $input = [];
    foreach ($product->get_attributes() as $attribute) {
        if ($attribute->get_visible()) {
            $input['attribute_' . sanitize_title_with_dashes($attribute->get_name())] = ['label' => wc_attribute_label($attribute->get_name())];
        }
    }
    if (!$input) continue;
    ++$covered;
    $specs = mv_product_spec_rows($input, $product);
    $labels = array_column($specs, 'label');
    $expect((bool) $specs, 'Visible specifications for ' . $id);
    foreach ($specs as $row) {
        $items = $row['options'] ?? [];
        $expect($items === array_values(array_unique($items)), 'Unique display options for ' . $id);
        $expect(!in_array('', $items, true), 'No blank option for ' . $id);
        $groups += isset($row['groups']) ? 1 : 0;
    }
    if (isset($known_labels[$id])) {
        $expect(in_array($known_labels[$id], $labels, true), 'Accurate attribute label for ' . $id);
        unset($known_labels[$id]);
    }
    if ($id === 12556) {
        $expect(($specs[0]['options'] ?? []) === ['سایز ۰', 'سایز ۱', 'سایز ۲', 'سایز ۳ (S)', 'سایز ۴ (M)', 'سایز ۵ (XL)', 'سایز ۶ (2XL)'], 'Sorted, deduplicated Messi sizes');
        $expect(($specs[1]['groups']['بدون چاپ'] ?? []) === ['سایز ۶ (2XL)' => 'سایز ۶ (2XL)'], 'Size associated with unprinted choice');
        $expect(isset($specs[1]['groups']['چاپ نام و شمارهٔ دلخواه']), 'Custom print group');
        $zero_variation = wc_get_product_id_by_sku('BAS-52969476-00');
        $zero = $zero_variation ? wc_get_product($zero_variation) : null;
        $expect($zero && $zero->get_parent_id() === $product->get_id() && $zero->is_purchasable()
            && in_array('0', $zero->get_attributes(), true), 'Real purchasable size zero is preserved');
    }
}
$expect(!$known_labels, 'Found the products with known misleading labels');
$expect(mv_is_custom_print(['سایز 2XL با اسم وشماره دلخواه']), 'Custom print alias with missing space');
$expect(mv_is_custom_print(['سایز ۴ با نام‌و‌شمارهٔ دلخواه']), 'Custom print alias with Persian punctuation');
$expect(!mv_is_custom_print(['سایز ۴ بدون چاپ', 'چاپ یامال']), 'Standard print choices do not require a custom name');
$comparison = '<table><tr><th>سایز</th><th>قد</th><th>عرض</th></tr><tr><td>۱</td><td>۲</td><td>۳</td></tr></table>';
$expect(mv_description_specifications($comparison) === $comparison, 'A multi-column size guide retains its table structure');
if ($failures) {
    WP_CLI::error(implode("\n", $failures));
}
WP_CLI::success(sprintf('%d published products, %d with specifications, %d grouped print rows, %d description tables.',
    count($products), $covered, $groups, $description_tables));
