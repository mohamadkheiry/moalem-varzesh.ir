<?php get_header(); ?>
<main id="main" class="wrap main-content woocommerce<?php if (is_product()) echo ' mv-product-page'; ?>">
    <?php woocommerce_breadcrumb(); ?>
    <?php woocommerce_content(); ?>
</main>
<?php get_footer(); ?>
