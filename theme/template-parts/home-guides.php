<?php
defined('ABSPATH') || exit;
$topics = [
    'futsal-court-sole-guide' => 'خرید کفش',
    'shirt-flat-measure' => 'اندازه‌گیری لباس',
    'store-goalkeeper-gloves' => 'نگهداری تجهیزات',
];
$guides = get_posts([
    'post_name__in' => array_keys($topics), 'orderby' => 'post_name__in', 'posts_per_page' => count($topics),
]);
?>
<section class="section mv-home-guides" aria-labelledby="home-guides-title">
    <div class="section-head mv-home-head">
        <h2 id="home-guides-title">راهنمای خرید و مقالات ورزشی</h2>
        <a class="text-link" href="<?php echo esc_url(mv_guides_url()); ?>">همهٔ مقاله‌ها <?php mv_icon('arrow'); ?></a>
    </div>
    <?php if ($guides) : ?>
        <div class="mv-home-guide-grid">
            <?php foreach ($guides as $guide) : ?>
                <article class="mv-home-guide">
                    <a href="<?php echo esc_url(get_permalink($guide)); ?>">
                        <div class="mv-home-guide-image" aria-hidden="true">
                            <?php mv_guide_image($guide->ID, 'medium_large', '(min-width: 768px) 30vw, 90vw'); ?>
                        </div>
                        <div class="mv-home-guide-copy">
                            <p class="mv-home-guide-topic"><?php echo esc_html($topics[$guide->post_name]); ?></p>
                            <h3><?php echo esc_html(get_the_title($guide)); ?></h3>
                            <p class="mv-home-guide-excerpt"><?php echo esc_html(wp_strip_all_tags(get_the_excerpt($guide))); ?></p>
                        </div>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
