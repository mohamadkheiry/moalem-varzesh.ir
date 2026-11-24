<?php
$footer = mv_support_settings();
$defaults = mv_footer_default_items();
$assigned = get_nav_menu_locations();
$guide_id = mv_support_page_id('guides');
$guide = $footer['pages']['guides'];
$android_url = esc_url($footer['footer_android_url'], ['https']);
$public_phone = function_exists('mv_commerce_public_contact_phone') ? mv_commerce_public_contact_phone() : '';
?>
<footer id="site-footer" class="site-footer" style="--mv-footer-accent:<?php echo esc_attr(sanitize_hex_color($footer['footer_accent'])); ?>">
    <div class="wrap">
        <?php if ($guide_id || $android_url) : ?>
        <div class="mv-footer-features">
            <?php if ($guide_id) : ?>
                <a class="mv-footer-guide" href="<?php echo esc_url(get_permalink($guide_id)); ?>" aria-label="خواندن راهنمای خرید">
                    <div class="mv-footer-guide-copy">
                        <strong>راهنمای خرید</strong>
                        <?php if ($guide['intro'] !== '') : ?><span class="mv-footer-guide-intro"><?php echo esc_html(wp_trim_words($guide['intro'], 18)); ?></span><?php endif; ?>
                        <span class="mv-footer-guide-action">خواندن راهنما <?php mv_icon('arrow'); ?></span>
                    </div>
                    <div class="mv-footer-guide-image" aria-hidden="true">
                        <?php mv_support_cover('guides', $guide_id, (int) $guide['image'], true); ?>
                    </div>
                </a>
            <?php endif; ?>
            <?php if ($android_url) : ?>
                <a class="mv-footer-app-download" href="<?php echo $android_url; ?>">
                    <span class="mv-footer-app-icon">
                        <img src="<?php echo esc_url(get_theme_file_uri('assets/android-robot.svg')); ?>" width="56" height="44" alt="" loading="lazy" decoding="async">
                    </span>
                    <span class="mv-footer-app-copy">
                        <strong>اپ معلم ورزش</strong>
                        <span>نسخهٔ آزمایشی اندروید™</span>
                    </span>
                    <span class="mv-footer-app-action">دانلود فایل نصب <bdi>APK</bdi>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 3v12m-5-5 5 5 5-5M5 16v4h14v-4"/></svg>
                    </span>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="footer-grid">
            <div class="mv-footer-brand">
                <a class="brand" href="<?php echo esc_url(home_url('/')); ?>"><?php mv_icon('brand'); ?><span><?php bloginfo('name'); ?></span></a>
                <?php if ($footer['footer_intro'] !== '') : ?><p><?php echo esc_html($footer['footer_intro']); ?></p><?php endif; ?>
                <?php if ($public_phone) : ?>
                    <a class="mv-footer-phone" href="<?php echo esc_url('tel:+98' . substr($public_phone, 1)); ?>" aria-label="تماس با فروشگاه <?php echo esc_attr($public_phone); ?>">
                        <?php mv_icon('phone'); ?><bdi dir="ltr"><?php echo esc_html($public_phone); ?></bdi>
                    </a>
                <?php endif; ?>
                <nav class="mv-footer-social" aria-label="شبکه‌های اجتماعی در فوتر">
                    <?php foreach (mv_public_social_links() as [$icon, $name, $url]) : ?>
                        <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr($name); ?>" title="<?php echo esc_attr($name); ?>">
                            <?php mv_icon($icon); ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
                <?php if (get_option('mv_enamad_enabled')) : ?>
                    <div class="mv-footer-seal">
                        <a class="trust" href="https://trustseal.enamad.ir/?id=647991&amp;Code=pvY06MWdhx4lH2PAHUq3b5UJ1rqBjv2h" target="_blank" rel="noopener" referrerpolicy="origin" aria-label="استعلام اینماد دامنه moalem-varzesh.ir">
                            <img src="<?php echo esc_url(get_theme_file_uri('assets/enamad.png')); ?>" alt="اینماد معلم ورزش" width="72" height="72" loading="lazy">
                        </a>
                        <div><strong>نماد اعتماد الکترونیکی</strong>
                            <?php if (wp_get_environment_type() === 'local') : ?><small>اینماد دامنهٔ <bdi dir="ltr">moalem-varzesh.ir</bdi></small><?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
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
        </div>
        <div class="footer-end mv-footer-end">
            <?php if ($footer['footer_credit'] !== '') : ?><p><?php echo esc_html($footer['footer_credit']); ?></p><?php endif; ?>
            <nav class="mv-design-switch" aria-label="نسخهٔ طراحی فروشگاه">
                <?php foreach (['primary' => 'نسخهٔ اولیه', 'secondary' => 'نسخهٔ ثانویه'] as $design => $label) : ?>
                    <a rel="nofollow" href="<?php echo esc_url(add_query_arg('mv_design', $design)); ?>" <?php if (mv_store_design() === $design) echo 'aria-current="page"'; ?>><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </nav>
        </div>
        <?php if ($android_url) : ?>
            <small class="mv-footer-app-credit">اندروید نشان تجاری گوگل است. نشان ربات با اقتباس از اثر گوگل و تحت <a href="https://creativecommons.org/licenses/by/3.0/deed.fa">مجوز کریتیو کامنز ۳.۰</a> استفاده شده است.</small>
        <?php endif; ?>
    </div>
</footer>
<?php get_template_part('template-parts/social-links'); ?>
<?php echo mv_secondary_markup('dock'); ?>
<?php wp_footer(); ?>
</body>
</html>
