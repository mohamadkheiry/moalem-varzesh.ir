<?php
defined('ABSPATH') || exit;

function mv_catalog_category_plan(array $product, array $terms): array {
    $aliases = [564 => 271, 565 => 288];
    $repairs = [10248 => [430], 9879 => [364], 11536 => [338], 11476 => ['table-tennis'], 11464 => ['table-tennis']];
    $source = $repairs[$product['id']] ?? array_column($product['categories'], 'id');
    return array_values(array_unique(array_map(static function ($id) use ($aliases, $terms): string {
        return is_int($id) ? sanitize_title(rawurldecode($terms[$aliases[$id] ?? $id]['slug'])) : $id;
    }, $source)));
}

function mv_catalog_topic(array $product): string {
    $repairs = [10248 => 'futsal', 9879 => 'socks', 11536 => 'training', 11476 => 'training', 11464 => 'training'];
    if (isset($repairs[$product['id']])) {
        return $repairs[$product['id']];
    }
    $ids = array_column($product['categories'], 'id');
    foreach (['futsal' => [430], 'turf' => [393, 412, 354], 'socks' => [364], 'protection' => [457, 456],
        'kits' => [269, 270, 271, 288, 452, 564, 565], 'sizing' => [414, 433, 435], 'training' => [338, 183]] as $topic => $categories) {
        if (array_intersect($ids, $categories)) {
            return $topic;
        }
    }
    return 'buying';
}
