<?php
/**
 * Readable product specifications with separate size and print options.
 * @version 9.3.0
 */
defined('ABSPATH') || exit;
$rows = mv_product_spec_rows($product_attributes, $product);
if (!$rows) {
    return;
}
get_template_part('template-parts/product-specifications', null, ['rows' => $rows]);
if ($product->is_type('variable') && $product->is_purchasable() && $product->is_in_stock()) : ?>
    <a class="mv-spec-purchase-link" href="#mv-product-options">انتخاب گزینهٔ خرید <?php mv_icon('arrow'); ?></a>
<?php endif; ?>
