<?php get_header(); $secondary = mv_store_design() === 'secondary'; ?>
<main id="main" class="wrap mv-home-page">
    <?php get_template_part('template-parts/hero'); ?>
    <?php if (!$secondary) get_template_part('template-parts/home-shoes'); ?>
    <?php $terms = mv_top_categories(4); if ($terms) :
        $category_art = [
            'ball-sports' => 'jersey',
            'کفش-های-ورزشی' => 'boots',
            'جوراب-ورزشی' => 'socks',
            'اکسسوری' => 'guards',
        ];
        ?>
    <section class="section mv-home-categories" aria-labelledby="categories-title">
        <div class="section-head mv-home-head">
            <h2 id="categories-title">دسته‌بندی کالاها</h2>
            <a class="text-link" href="<?php echo esc_url(mv_shop_url()); ?>">همهٔ کالاها <?php mv_icon('arrow'); ?></a>
        </div>
        <div class="category-grid">
            <?php foreach ($terms as $term) :
                $url = get_term_link($term);
                if (is_wp_error($url)) {
                    continue;
                }
                $image_id = (int) get_term_meta($term->term_id, 'thumbnail_id', true);
                $image = $image_id ? wp_get_attachment_image($image_id, 'woocommerce_thumbnail', false, [
                    'alt' => '', 'loading' => 'lazy', 'decoding' => 'async',
                    'sizes' => '(min-width: 1024px) 22vw, 46vw',
                ]) : '';
                $art = $category_art[rawurldecode($term->slug)] ?? '';
                ?>
                <a class="category-tile" href="<?php echo esc_url($url); ?>">
                    <div class="mv-home-category-image">
                        <?php if ($image) : echo $image; elseif ($art) : ?>
                            <img src="<?php echo esc_url(get_theme_file_uri('assets/hero-' . $art . '-420.webp')); ?>"
                                alt="" loading="lazy" decoding="async">
                        <?php else : mv_icon('cart'); endif; ?>
                    </div>
                    <span class="mv-home-category-name"><?php echo esc_html($term->name); ?> <?php mv_icon('arrow'); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    <?php if ($secondary) :
        get_template_part('template-parts/home-new');
        get_template_part('template-parts/home-shoes');
    endif; ?>
    <?php if (class_exists('WooCommerce')) : ?>
    <?php $weekly = mv_weekly_products(); if ($weekly) : ?>
    <section class="section mv-home-weekly woocommerce" aria-labelledby="weekly-title">
        <div class="section-head mv-home-head">
            <h2 id="weekly-title">محصولات منتخب این هفته</h2>
            <a class="text-link" href="<?php echo esc_url(mv_shop_url()); ?>">ادامهٔ خرید <?php mv_icon('arrow'); ?></a>
        </div>
        <?php echo do_shortcode(sprintf('[products ids="%s" limit="4" columns="4" orderby="post__in" visibility="visible"]',
            implode(',', array_map('absint', $weekly)))); ?>
    </section>
    <?php endif; ?>
    <?php if (!$secondary) get_template_part('template-parts/home-new'); ?>
    <?php endif; ?>
    <?php get_template_part('template-parts/home-guides'); ?>
</main>
<?php get_footer(); ?>
