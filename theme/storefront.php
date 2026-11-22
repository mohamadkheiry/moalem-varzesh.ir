<?php
defined('ABSPATH') || exit;

function mv_catalog_term(string $slug): ?WP_Term {
    static $terms = null;
    if ($terms === null) {
        $result = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'update_term_meta_cache' => false]);
        $terms = is_wp_error($result) ? [] : array_column($result, null, 'slug');
    }
    return $terms[sanitize_title($slug)] ?? null;
}

function mv_catalog_menu_links(array $links): void {
    echo '<ul>';
    foreach ($links as [$slug, $title, $children]) {
        $term = mv_catalog_term($slug);
        $href = $term ? get_term_link($term) : false;
        if (!$href || is_wp_error($href)) {
            continue;
        }
        printf('<li><a href="%s"%s>%s</a>', esc_url($href),
            is_tax('product_cat', $term->term_id) ? ' aria-current="page"' : '',
            esc_html($title ?: $term->name));
        if ($children) {
            mv_catalog_menu_links($children);
        }
        echo '</li>';
    }
    echo '</ul>';
}

function mv_weekly_products(): array {
    $query = ['return' => 'ids', 'status' => 'publish', 'visibility' => 'visible', 'orderby' => 'date', 'order' => 'DESC', 'limit' => 8];
    $recent = wc_get_products($query);
    $pool = array_replace($query, ['stock_status' => 'instock', 'limit' => -1, 'orderby' => 'ID', 'order' => 'ASC']);
    $week = (int) wp_date('oW');
    $picks = [];
    foreach (['football', 'کفش-های-ورزشی', 'جوراب-ورزشی', 'اکسسوری'] as $slug) {
        $products = wc_get_products($pool + ['category' => [sanitize_title($slug)], 'exclude' => array_merge($recent, $picks)]);
        if ($products) {
            $picks[] = $products[$week % count($products)];
        }
    }
    if (count($picks) < 4) {
        $spares = wc_get_products($pool + ['exclude' => $picks]);
        if ($spares) {
            $offset = $week % count($spares);
            $rotated = array_merge(array_slice($spares, $offset), array_slice($spares, 0, $offset));
            $picks = array_merge($picks, array_slice($rotated, 0, 4 - count($picks)));
        }
    }
    return $picks;
}
