<?php
defined('ABSPATH') || exit;

function mv_support_sanitize_settings($submitted): array {
    $input = is_array($submitted) ? $submitted : [];
    $settings = mv_support_settings();
    foreach (['footer_intro', 'footer_credit'] as $text) {
        $settings[$text] = sanitize_text_field($input[$text] ?? $settings[$text]);
    }
    $settings['footer_accent'] = sanitize_hex_color(sanitize_text_field($input['footer_accent'] ?? '')) ?: $settings['footer_accent'];
    foreach (mv_footer_locations() as $location => $label) {
        $settings['headings'][$location] = sanitize_text_field($input['headings'][$location] ?? '') ?: $label;
    }
    foreach (mv_support_pages() as $key => $page) {
        if (!isset($input['pages'][$key]) || !is_array($input['pages'][$key])) continue;
        $source = $input['pages'][$key];
        $old = $settings['pages'][$key];
        $photo = absint($source['image'] ?? $old['image']);
        $settings['pages'][$key] = [
            'intro' => sanitize_textarea_field($source['intro'] ?? $old['intro']),
            'accent' => sanitize_hex_color(sanitize_text_field($source['accent'] ?? '')) ?: $old['accent'],
            'image' => $photo && wp_attachment_is_image($photo) ? $photo : 0,
        ];
    }
    return $settings;
}

add_action('admin_init', static function (): void {
    register_setting('mv_support_design', 'mv_support_design', [
        'type' => 'array', 'sanitize_callback' => 'mv_support_sanitize_settings',
    ]);
});
add_filter('option_page_capability_mv_support_design', static fn(): string => 'edit_theme_options');

add_action('admin_menu', static function (): void {
    $screen = add_theme_page('فوتر و صفحه‌های راهنما', 'فوتر و صفحه‌های راهنما', 'edit_theme_options', 'mv-support-design', 'mv_support_settings_screen');
    add_action('load-' . $screen, static function (): void {
        wp_enqueue_media();
        wp_enqueue_script('mv-support-admin', get_theme_file_uri('assets/support-admin.js'), [], wp_get_theme()->get('Version'), true);
        wp_enqueue_style('mv-support-admin', get_theme_file_uri('assets/support-admin.css'), [], wp_get_theme()->get('Version'));
    });
});

function mv_support_settings_screen(): void {
    if (!current_user_can('edit_theme_options')) return;
    $settings = mv_support_settings();
    $locations = get_nav_menu_locations();
    ?>
    <div class="wrap mv-support-admin">
        <h1>فوتر و صفحه‌های راهنما</h1>
        <?php settings_errors(); ?>
        <form action="options.php" method="post">
            <?php settings_fields('mv_support_design'); ?>
            <section class="mv-support-admin-panel" aria-labelledby="mv-footer-settings-title">
                <h2 id="mv-footer-settings-title">طراحی فوتر</h2>
                <div class="mv-support-admin-footer">
                    <label>معرفی کوتاه فروشگاه<input type="text" name="mv_support_design[footer_intro]" value="<?php echo esc_attr($settings['footer_intro']); ?>"></label>
                    <label>متن پایین فوتر<input type="text" name="mv_support_design[footer_credit]" value="<?php echo esc_attr($settings['footer_credit']); ?>"></label>
                    <label>رنگ فوتر<input type="color" name="mv_support_design[footer_accent]" value="<?php echo esc_attr($settings['footer_accent']); ?>"></label>
                </div>
                <div class="mv-support-admin-menus">
                    <?php foreach (mv_footer_locations() as $location => $label) :
                        $menu = !empty($locations[$location]) ? add_query_arg('menu', (int) $locations[$location], admin_url('nav-menus.php'))
                            : add_query_arg('action', 'locations', admin_url('nav-menus.php'));
                        ?>
                        <div>
                            <label>عنوان گروه <?php echo esc_html($label); ?><input type="text" name="mv_support_design[headings][<?php echo esc_attr($location); ?>]" value="<?php echo esc_attr($settings['headings'][$location]); ?>"></label>
                            <a class="button" href="<?php echo esc_url($menu); ?>">ویرایش لینک‌ها و ترتیب</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <h2>تصویر، رنگ و معرفی صفحه‌ها</h2>
            <div class="mv-support-admin-grid">
                <?php foreach (mv_support_pages() as $key => [, $label, , , $cover]) :
                    $id = mv_support_page_id($key);
                    if (!$id) continue;
                    $values = $settings['pages'][$key];
                    $name = 'mv_support_design[pages][' . $key . ']';
                    $default = get_theme_file_uri('assets/support/' . $cover . '-640.webp');
                    $page_cover = get_the_post_thumbnail_url($id, 'medium') ?: $default;
                    $thumbnail = wp_get_attachment_image_url((int) $values['image'], 'medium') ?: $page_cover;
                    ?>
                    <section class="mv-support-admin-panel mv-support-admin-card" data-support-image data-default-image="<?php echo esc_url($page_cover); ?>">
                        <h3><?php echo esc_html($label); ?></h3>
                        <img src="<?php echo esc_url($thumbnail); ?>" alt="" width="320" height="160">
                        <input type="hidden" name="<?php echo esc_attr($name); ?>[image]" value="<?php echo esc_attr($values['image']); ?>">
                        <div class="mv-support-admin-actions">
                            <button type="button" class="button" data-pick-image>انتخاب تصویر</button>
                            <button type="button" class="button" data-reset-image>بازگردانی تصویر</button>
                        </div>
                        <label>متن معرفی<textarea name="<?php echo esc_attr($name); ?>[intro]" rows="2"><?php echo esc_textarea($values['intro']); ?></textarea></label>
                        <label>رنگ سربرگ صفحه<input type="color" name="<?php echo esc_attr($name); ?>[accent]" value="<?php echo esc_attr($values['accent']); ?>"></label>
                        <div class="mv-support-admin-actions">
                            <a class="button" href="<?php echo esc_url(get_edit_post_link($id, 'raw')); ?>"><?php echo $key === 'guides' ? 'ویرایش عنوان برگهٔ مقاله‌ها' : 'ویرایش محتوای صفحه'; ?></a>
                            <a href="<?php echo esc_url(get_permalink($id)); ?>" target="_blank" rel="noopener">مشاهدهٔ صفحه</a>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
            <?php submit_button('ذخیرهٔ تغییرات'); ?>
        </form>
    </div>
    <?php
}
