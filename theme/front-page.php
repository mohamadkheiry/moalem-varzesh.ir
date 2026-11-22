<?php get_header(); $secondary = mv_store_design() === 'secondary'; ?>
<main id="main" class="wrap">
    <?php get_template_part('template-parts/hero'); ?>
    <?php if (!$secondary) get_template_part('template-parts/home-shoes'); ?>
    <?php $terms = mv_top_categories(4); if ($terms) : ?>
    <section class="section" aria-labelledby="categories-title">
        <div class="section-head"><h2 id="categories-title">دسته‌بندی کالاها</h2></div>
        <div class="category-grid">
            <?php foreach ($terms as $term) :
                $url = get_term_link($term);
                if (is_wp_error($url)) {
                    continue;
                }
                $image_id = (int) get_term_meta($term->term_id, 'thumbnail_id', true);
                ?>
                <a class="category-tile" href="<?php echo esc_url($url); ?>">
                    <?php if ($image_id) {
                        echo wp_get_attachment_image($image_id, 'thumbnail', false, ['alt' => '', 'loading' => 'lazy', 'sizes' => '(min-width: 768px) 100px, 54px']);
                    } ?>
                    <span><?php echo esc_html($term->name); ?></span>
                    <?php mv_icon('arrow'); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    <?php if ($secondary) :
        get_template_part('template-parts/home-new');
        get_template_part('template-parts/home-shoes');
    endif; ?>
    <?php get_template_part('template-parts/home-guides'); ?>
    <?php if (class_exists('WooCommerce')) : ?>
    <?php $weekly = mv_weekly_products(); if ($weekly) : ?>
    <section class="section mv-home-weekly woocommerce" aria-labelledby="weekly-title">
        <div class="section-head">
            <h2 id="weekly-title">محصولات منتخب این هفته</h2>
        </div>
        <?php echo do_shortcode(sprintf('[products ids="%s" limit="4" columns="4" orderby="post__in" visibility="visible"]',
            implode(',', array_map('absint', $weekly)))); ?>
    </section>
    <?php endif; ?>
    <?php if (!$secondary) get_template_part('template-parts/home-new'); ?>
    <?php endif; ?>
</main>
<?php get_footer(); ?>
