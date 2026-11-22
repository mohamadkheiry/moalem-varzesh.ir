<footer class="site-footer">
    <div class="wrap">
        <div class="footer-grid">
            <div>
                <a class="brand" href="<?php echo esc_url(home_url('/')); ?>"><?php mv_icon('brand'); ?><span><?php bloginfo('name'); ?></span></a>
                <p>فروشگاه پوشاک، کفش و تجهیزات ورزشی</p>
            </div>
            <div>
                <h2>راهنمای خرید</h2>
                <nav aria-label="راهنمای خرید">
                    <?php
                    $links = [
                        'about-us' => 'درباره ما',
                        'contact-us' => 'تماس با ما',
                        'terms-and-conditions' => 'قوانین و مقررات',
                        'refund_returns-2' => 'مرجوعی و بازپرداخت',
                        'blog' => 'راهنمای خرید',
                    ];
                    foreach ($links as $slug => $label) {
                        $page = get_page_by_path($slug);
                        if ($page) {
                            printf('<a href="%s">%s</a>', esc_url(get_permalink($page)), esc_html($label));
                        }
                    }
                    echo get_the_privacy_policy_link();
                    ?>
                </nav>
            </div>
            <?php if (get_option('mv_enamad_enabled')) : ?>
            <div>
                <h2>نماد اعتماد الکترونیکی</h2>
                <a class="trust" href="https://trustseal.enamad.ir/?id=647991&amp;Code=pvY06MWdhx4lH2PAHUq3b5UJ1rqBjv2h" target="_blank" rel="noopener" referrerpolicy="origin" aria-label="استعلام اینماد دامنه moalem-varzesh.ir">
                    <img src="<?php echo esc_url(get_theme_file_uri('assets/enamad.png')); ?>" alt="اینماد معلم ورزش" width="72" height="72">
                </a>
                <?php if (wp_get_environment_type() === 'local') : ?><p>اینماد دامنهٔ moalem-varzesh.ir</p><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="footer-end">تمام حقوق فروشگاه معلم ورزش محفوظ است.</div>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
