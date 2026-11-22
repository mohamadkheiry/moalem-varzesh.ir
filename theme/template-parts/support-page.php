<?php
defined('ABSPATH') || exit;
$key = $args['key'];
$related_pages = [
    'about' => ['guides', 'contact', 'returns'],
    'contact' => ['returns', 'guides', 'about'],
    'terms' => ['returns', 'privacy', 'contact'],
    'returns' => ['contact', 'terms', 'guides'],
    'privacy' => ['cookies', 'terms', 'contact'],
    'cookies' => ['privacy', 'terms', 'contact'],
];
?>
<main id="main" class="wrap main-content mv-support-page mv-support-page--<?php echo esc_attr($key); ?>">
    <?php while (have_posts()) : the_post();
        $id = get_the_ID();
        $title = get_the_title();
        [$html, $sections] = mv_support_readable_content(apply_filters('the_content', get_the_content()), $title);
        ?>
        <nav class="mv-support-crumbs" aria-label="مسیر صفحه">
            <a href="<?php echo esc_url(home_url('/')); ?>">خانه</a><span aria-hidden="true">/</span>
            <span aria-current="page"><?php echo esc_html($title); ?></span>
        </nav>
        <?php get_template_part('template-parts/support-hero', null, compact('key', 'id', 'title')); ?>
        <div class="mv-support-layout">
            <?php if (count($sections) > 2) : ?>
                <nav class="mv-support-outline" aria-label="فهرست بخش‌های صفحه">
                    <h2>در این صفحه</h2>
                    <ul>
                        <?php foreach ($sections as [$anchor, $label]) : ?>
                            <li><a href="#<?php echo esc_attr(rawurlencode($anchor)); ?>"><?php echo esc_html($label); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            <?php endif; ?>
            <article class="mv-support-body">
                <?php echo $html; wp_link_pages(); ?>
            </article>
        </div>
        <section class="mv-support-related" aria-labelledby="support-related-title">
            <h2 id="support-related-title">صفحه‌های مرتبط</h2>
            <div>
                <?php foreach ($related_pages[$key] ?? [] as $related) :
                    $destination = mv_support_page_id($related);
                    if (!$destination) continue;
                    ?>
                    <a href="<?php echo esc_url(get_permalink($destination)); ?>">
                        <?php echo esc_html(mv_support_pages()[$related][1]); ?><?php mv_icon('arrow'); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endwhile; ?>
</main>
