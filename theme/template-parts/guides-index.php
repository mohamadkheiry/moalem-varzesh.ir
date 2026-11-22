<?php
defined('ABSPATH') || exit;
$topic = is_category() ? get_queried_object() : null;
$categories = get_categories(['hide_empty' => true]);
?>
<main id="main" class="wrap editorial-index">
    <?php if ($topic) : ?>
        <header class="editorial-banner">
            <h1><?php echo esc_html($topic->name); ?></h1>
            <?php if ($topic->description) : ?><p><?php echo esc_html($topic->description); ?></p><?php endif; ?>
        </header>
    <?php else : ?>
        <?php $guides_id = mv_support_page_id('guides'); ?>
        <?php get_template_part('template-parts/support-hero', null, [
            'key' => 'guides', 'id' => $guides_id, 'title' => $guides_id ? get_the_title($guides_id) : 'راهنمای خرید و مقالات ورزشی',
        ]); ?>
    <?php endif; ?>
    <nav class="guide-topics" aria-label="موضوعات مقاله‌ها">
        <a href="<?php echo esc_url(mv_guides_url()); ?>" <?php if (!$topic) echo 'aria-current="page"'; ?>>همهٔ مقاله‌ها</a>
        <?php foreach ($categories as $category) :
            if (!str_starts_with($category->slug, 'guide-')) {
                continue;
            }
            $url = get_category_link($category->term_id);
            ?>
            <a href="<?php echo esc_url($url); ?>" <?php if ($topic && $topic->term_id === $category->term_id) echo 'aria-current="page"'; ?>><?php echo esc_html($category->name); ?></a>
        <?php endforeach; ?>
    </nav>
    <?php if (have_posts()) : ?>
        <section class="guide-grid" aria-label="مقاله‌ها">
            <?php $order = 0; while (have_posts()) : the_post();
                $id = get_the_ID();
                $feature = !$topic && !is_paged() && $order++ === 0;
                ?>
                <article <?php post_class($feature ? 'guide-card guide-feature' : 'guide-card'); ?>>
                    <a class="guide-card-image" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
                        <?php mv_guide_image($id, 'large', $feature ? '(min-width: 768px) 44vw, 90vw' : '(min-width: 1024px) 31vw, (min-width: 630px) 45vw, 90vw', $feature); ?>
                    </a>
                    <div class="guide-card-content">
                        <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                        <p><?php echo esc_html(wp_strip_all_tags(get_the_excerpt())); ?></p>
                    </div>
                </article>
            <?php endwhile; ?>
        </section>
        <?php the_posts_pagination(['mid_size' => 1, 'prev_text' => 'صفحهٔ قبل', 'next_text' => 'صفحهٔ بعد']); ?>
    <?php else : ?>
        <p>مقاله‌ای در این موضوع پیدا نشد.</p>
    <?php endif; ?>
</main>
