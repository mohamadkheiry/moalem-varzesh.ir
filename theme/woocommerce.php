<?php get_header(); ?>
<main id="main" class="wrap main-content woocommerce<?php echo is_product() ? ' mv-product-page' : ' mv-catalog-page'; ?>">
    <?php woocommerce_breadcrumb(); ?>
    <?php woocommerce_content(); ?>
</main>
<?php get_footer(); ?>
