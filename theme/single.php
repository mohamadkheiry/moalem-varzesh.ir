<?php get_header(); ?>
<main id="main" class="wrap editorial-single">
    <?php while (have_posts()) : the_post();
        $id = get_the_ID();
        $categories = get_the_category();
        $topic = $categories[0] ?? null;
        $content = get_post_field('post_content', $id);
        preg_match_all('/<h2\s+id="([A-Za-z0-9_-]+)"\s*>(.*?)<\/h2>/su', $content, $headings, PREG_SET_ORDER);
        ?>
        <article <?php post_class('guide-article'); ?>>
            <nav class="guide-crumbs" aria-label="مسیر مقاله">
                <a href="<?php echo esc_url(mv_guides_url()); ?>">راهنمای خرید و مطالب ورزشی</a>
                <?php if ($topic) : ?>
                    <span aria-hidden="true">/</span>
                    <a href="<?php echo esc_url(get_category_link($topic)); ?>"><?php echo esc_html($topic->name); ?></a>
                <?php endif; ?>
            </nav>
            <header class="guide-header">
                <div class="guide-intro">
                    <h1><?php the_title(); ?></h1>
                    <?php if (has_excerpt()) : ?><p><?php echo esc_html(wp_strip_all_tags(get_the_excerpt())); ?></p><?php endif; ?>
                    <p class="guide-byline">تحریریهٔ معلم ورزش</p>
                </div>
                <?php if (has_post_thumbnail()) : ?>
                    <figure class="guide-hero-image">
                        <?php mv_guide_image($id, 'large', '(min-width: 96rem) 668px, (min-width: 48rem) 44vw, 92vw', true); ?>
                    </figure>
                <?php endif; ?>
            </header>
            <div class="guide-layout">
                <div class="guide-body"><?php the_content(); wp_link_pages(); ?></div>
                <?php if (count($headings) > 2 && get_post_meta($id, '_mv_guide_key', true)) : ?>
                    <nav class="guide-outline" aria-label="فهرست مقاله">
                        <h2>در این راهنما</h2>
                        <ul>
                            <?php foreach ($headings as [, $key, $title]) : ?>
                                <li><a href="#<?php echo esc_attr($key); ?>"><?php echo esc_html(html_entity_decode(wp_strip_all_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8')); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </article>
    <?php endwhile; ?>
</main>
<?php get_footer(); ?>
