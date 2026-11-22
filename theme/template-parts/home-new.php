<?php
defined('ABSPATH') || exit;
if (!class_exists('WooCommerce')) return;
?>
<section class="section woocommerce" aria-labelledby="new-title">
    <div class="section-head">
        <h2 id="new-title">تازه‌های فروشگاه</h2>
        <a class="text-link" href="<?php echo esc_url(mv_shop_url()); ?>">همهٔ کالاها <?php mv_icon('arrow'); ?></a>
    </div>
    <?php echo do_shortcode('[products limit="8" columns="4" orderby="date" order="DESC" visibility="visible"]'); ?>
</section>
