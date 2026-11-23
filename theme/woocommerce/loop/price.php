<?php
/**
 * Compact catalog price.
 *
 * @version 1.6.4
 */
defined('ABSPATH') || exit;

global $product;
if ($product instanceof WC_Product && ($price = mv_storefront_price_html($product, true)) !== '') : ?>
    <span class="price"><?php echo $price; ?></span>
<?php endif; ?>
