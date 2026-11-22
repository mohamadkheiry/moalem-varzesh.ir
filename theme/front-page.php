<?php get_header(); ?>
<main id="main" class="wrap">
    <section class="hero" aria-labelledby="home-title">
        <picture>
            <source media="(max-width: 767px)" srcset="<?php echo esc_url(get_theme_file_uri('assets/hero-mobile.webp')); ?>">
            <img src="<?php echo esc_url(get_theme_file_uri('assets/hero.webp')); ?>" width="1600" height="687" alt="" fetchpriority="high" decoding="async">
        </picture>
        <div class="hero-copy">
            <h1 id="home-title"><span>لباس و تجهیزات</span> <span>ورزشی</span></h1>
            <p>پوشاک، کفش و لوازم تمرین</p>
            <a class="button-primary" href="<?php echo esc_url(mv_shop_url()); ?>">مشاهده فروشگاه <?php mv_icon('arrow'); ?></a>
        </div>
    </section>
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
    <?php if (class_exists('WooCommerce')) : ?>
    <section class="section woocommerce" aria-labelledby="new-title">
        <div class="section-head">
            <h2 id="new-title">تازه‌های فروشگاه</h2>
            <a class="text-link" href="<?php echo esc_url(mv_shop_url()); ?>">همهٔ کالاها <?php mv_icon('arrow'); ?></a>
        </div>
        <?php echo do_shortcode('[products limit="8" columns="4" orderby="date" order="DESC" visibility="visible"]'); ?>
    </section>
    <?php endif; ?>
</main>
<?php get_footer(); ?>
