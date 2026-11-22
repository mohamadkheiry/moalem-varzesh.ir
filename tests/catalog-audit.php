<?php
defined('WP_CLI') && WP_CLI || exit;

$catalog = dirname(__DIR__) . '/catalog';
$load = static fn(string $name): array => json_decode(file_get_contents("$catalog/$name.json"), true, 512, JSON_THROW_ON_ERROR);
$products = $load('products');
$variants = $load('variations');
$categories = $load('categories');
$term_plan = array_column($categories, null, 'id');
require_once dirname(__DIR__) . '/scripts/catalog-category-plan.php';
$media = $load('media');
$errors = [];
$check = static function (bool $ok, string $message) use (&$errors): void {
    if (!$ok) {
        $errors[] = $message;
    }
};
$normalize = static fn(string $value): string => html_entity_decode(str_replace('\u200c', "\u{200C}", $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

global $wpdb;
$source_ids = [];
foreach ($wpdb->get_results("SELECT p.ID, p.post_type, m.meta_value as source_id
    FROM $wpdb->posts p INNER JOIN $wpdb->postmeta m ON p.ID=m.post_id
    WHERE p.post_type IN ('product','product_variation') AND p.post_status='publish' AND m.meta_key='_mv_source_id'") as $item) {
    $check(!isset($source_ids[$item->source_id]), 'Duplicate source id ' . $item->source_id);
    $source_ids[$item->source_id] = (int) $item->ID;
}
$check(count($source_ids) === count($products) + count($variants), 'Product and variation count differs from snapshot');
$checks = [];
foreach ($products as $row) {
    $item = wc_get_product($source_ids[$row['id']] ?? 0);
    if (!$item) {
        $errors[] = 'Missing product ' . $row['id'];
        continue;
    }
    $check($item->get_type() === $row['type'], 'Product type ' . $row['id']);
    $check($item->get_name() === $normalize($row['name']), 'Name ' . $row['id']);
    $check($item->get_sku() === $row['sku'], 'SKU ' . $row['id']);
    $check($item->is_in_stock() === $row['is_in_stock'], 'Product stock ' . $row['id']);
    $check(rawurldecode(wp_parse_url($item->get_permalink(), PHP_URL_PATH)) === rawurldecode(wp_parse_url($row['permalink'], PHP_URL_PATH)), 'Permalink ' . $row['id']);
    $check(count($item->get_gallery_image_ids()) + (int) (bool) $item->get_image_id() === count($row['images']), 'Gallery ' . $row['id']);
    $actual_terms = array_map('intval', $item->get_category_ids());
    $expected_slugs = get_option('mv_catalog_editorial_schema')
        ? mv_catalog_category_plan($row, $term_plan)
        : array_map(static fn(array $term): string => sanitize_title(rawurldecode($term['slug'])), $row['categories']);
    $expected_terms = $expected_slugs
        ? array_map(static fn(string $slug): int => (int) (get_term_by('slug', $slug, 'product_cat')->term_id ?? 0), $expected_slugs)
        : [(int) get_option('default_product_cat')];
    sort($actual_terms);
    sort($expected_terms);
    $check($actual_terms === $expected_terms, 'Categories ' . $row['id']);
    $check((float) $item->get_price() === (float) $row['prices']['price'], 'Product price ' . $row['id']);
    $check(count($row['variations']) === ($item->is_type('variable') ? count($item->get_children()) : 0), 'Child count ' . $row['id']);
    foreach ($row['variations'] as $listed) {
        $matches = [];
        foreach ($listed['attributes'] as $source) {
            $name = $normalize($source['name']);
            $value = $normalize((string) $source['value']);
            foreach ($row['attributes'] as $attribute) {
                if ($normalize($attribute['name']) !== $name || empty($attribute['taxonomy']) || $value === '') {
                    continue;
                }
                foreach ($attribute['terms'] as $term) {
                    if (rawurldecode($term['slug']) === rawurldecode($value)) {
                        $value = $normalize($term['name']);
                        break;
                    }
                }
            }
            $matches['attribute_' . sanitize_title($name)] = $value;
        }
        $checks[$listed['id']] = $matches;
    }
}
foreach ($variants as $row) {
    $item = wc_get_product($source_ids[$row['id']] ?? 0);
    if (!$item) {
        $errors[] = 'Missing variation ' . $row['id'];
        continue;
    }
    $check($item->get_parent_id() === $source_ids[$row['parent']], 'Variation parent ' . $row['id']);
    $check((float) $item->get_price() === (float) $row['prices']['price'], 'Variation price ' . $row['id']);
    $check($item->is_in_stock() === $row['is_in_stock'], 'Variation stock ' . $row['id']);
    $check($item->get_variation_attributes() === $checks[$row['id']], 'Selectable attributes ' . $row['id']);
}
foreach ($categories as $category) {
    $term = get_term_by('slug', sanitize_title(rawurldecode($category['slug'])), 'product_cat');
    $check((bool) $term, 'Missing category ' . $category['id']);
}
$actual_media = [];
foreach ($wpdb->get_results("SELECT p.ID, m.meta_value as source_media FROM $wpdb->posts p
    INNER JOIN $wpdb->postmeta m ON p.ID=m.post_id WHERE p.post_type='attachment' AND m.meta_key='_mv_source_media'") as $item) {
    $check(!isset($actual_media[$item->source_media]), 'Duplicate media ' . $item->ID);
    $actual_media[$item->source_media] = (int) $item->ID;
}
foreach ($media as $url => $name) {
    $id = $actual_media[$url] ?? 0;
    $check($id > 0 && is_file(get_attached_file($id)) && (bool) wp_get_attachment_metadata($id), 'Media ' . $name);
}
if ($errors) {
    WP_CLI::error(count($errors) . " mismatches:\n" . implode("\n", array_slice($errors, 0, 25)));
}
WP_CLI::success(sprintf('03 catalog: %d products, %d variations, %d categories, %d files match the source snapshot.',
    count($products), count($variants), count($categories), count($media)));
