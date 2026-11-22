<?php get_header(); ?>
<main id="main" class="wrap main-content">
    <?php if (is_404()) : ?>
        <div class="article"><h1>صفحه پیدا نشد</h1><p>می‌توانید نام کالا را جست‌وجو کنید یا به فروشگاه بروید.</p>
        <a class="button-primary" href="<?php echo esc_url(mv_shop_url()); ?>">مشاهده فروشگاه</a></div>
    <?php elseif (have_posts()) : ?>
        <?php if (is_home()) : ?><h1>راهنمای خرید و مطالب ورزشی</h1>
        <?php elseif (!is_singular()) : the_archive_title('<h1>', '</h1>'); endif; ?>
        <?php while (have_posts()) : the_post(); ?>
            <article <?php post_class('article'); ?>>
                <?php if (is_singular()) :
                    the_title('<h1>', '</h1>');
                    the_content();
                    wp_link_pages();
                else : ?>
                    <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <?php the_excerpt();
                endif; ?>
            </article>
        <?php endwhile;
        if (!is_singular()) the_posts_pagination(); ?>
    <?php else : ?>
        <div class="article"><h1>نتیجه‌ای پیدا نشد</h1><p>عبارت دیگری را جست‌وجو کنید.</p></div>
    <?php endif; ?>
</main>
<?php get_footer(); ?>
