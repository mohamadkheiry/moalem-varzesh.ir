<?php $footer = mv_support_settings(); $defaults = mv_footer_default_items(); $assigned = get_nav_menu_locations(); ?>
<footer class="site-footer" style="--mv-footer-accent:<?php echo esc_attr(sanitize_hex_color($footer['footer_accent'])); ?>">
    <div class="wrap">
        <div class="footer-grid">
            <div class="mv-footer-brand">
                <a class="brand" href="<?php echo esc_url(home_url('/')); ?>"><?php mv_icon('brand'); ?><span><?php bloginfo('name'); ?></span></a>
                <?php if ($footer['footer_intro'] !== '') : ?><p><?php echo esc_html($footer['footer_intro']); ?></p><?php endif; ?>
            </div>
            <?php foreach (mv_footer_locations() as $location => $label) :
                $menu = wp_nav_menu([
                    'theme_location' => $location, 'echo' => false, 'container' => false,
                    'menu_class' => 'mv-footer-links', 'fallback_cb' => false,
                ]);
                if (!$menu && (!empty($assigned[$location]) || empty($defaults[$location]))) continue;
                $heading = $footer['headings'][$location];
                ?>
                <div class="mv-footer-group" data-menu="<?php echo esc_attr($location); ?>">
                    <h2><?php echo esc_html($heading); ?></h2>
                    <nav aria-label="<?php echo esc_attr($heading); ?>">
                        <?php if ($menu) : echo $menu; else : ?>
                            <ul class="mv-footer-links">
                                <?php foreach ($defaults[$location] as [$id, $label]) : ?>
                                    <li><a href="<?php echo esc_url(get_permalink($id)); ?>"<?php if ($id === get_queried_object_id()) echo ' aria-current="page"'; ?>><?php echo mv_footer_link_contents(esc_html($label), $id); ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </nav>
                </div>
            <?php endforeach; ?>
            <?php if (get_option('mv_enamad_enabled')) : ?>
            <div class="mv-footer-seal">
                <h2>نماد اعتماد الکترونیکی</h2>
                <a class="trust" href="https://trustseal.enamad.ir/?id=647991&amp;Code=pvY06MWdhx4lH2PAHUq3b5UJ1rqBjv2h" target="_blank" rel="noopener" referrerpolicy="origin" aria-label="استعلام اینماد دامنه moalem-varzesh.ir">
                    <img src="<?php echo esc_url(get_theme_file_uri('assets/enamad.png')); ?>" alt="اینماد معلم ورزش" width="72" height="72" loading="lazy">
                </a>
                <?php if (wp_get_environment_type() === 'local') : ?><p>اینماد دامنهٔ <bdi dir="ltr">moalem-varzesh.ir</bdi></p><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="footer-end mv-footer-end">
            <?php if ($footer['footer_credit'] !== '') : ?><p><?php echo esc_html($footer['footer_credit']); ?></p><?php endif; ?>
            <nav class="mv-design-switch" aria-label="نسخهٔ طراحی فروشگاه">
                <?php foreach (['primary' => 'نسخهٔ اولیه', 'secondary' => 'نسخهٔ ثانویه'] as $design => $label) : ?>
                    <a rel="nofollow" href="<?php echo esc_url(add_query_arg('mv_design', $design)); ?>" <?php if (mv_store_design() === $design) echo 'aria-current="page"'; ?>><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </nav>
        </div>
    </div>
</footer>
<?php get_template_part('template-parts/social-links'); ?>
<?php echo mv_secondary_markup('dock'); ?>
<?php wp_footer(); ?>
</body>
</html>
