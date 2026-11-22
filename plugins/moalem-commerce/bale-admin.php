<?php
defined('ABSPATH') || exit;

function mv_bale_admin_url(array $args = []): string {
    return add_query_arg(['page' => 'mv-bale'] + $args, admin_url('admin.php'));
}

function mv_bale_feedback(string $state): void {
    wp_safe_redirect(mv_bale_admin_url(['mv_result' => $state]), 303);
    exit;
}

add_action('admin_menu', static function (): void {
    if (class_exists('WooCommerce')) {
        $screen = add_submenu_page('woocommerce', 'انتشار در کانال بله', 'کانال بله', 'manage_options', 'mv-bale', 'mv_bale_admin_page');
        add_action('load-' . $screen, static function (): void {
            wp_enqueue_style('mv-bale-admin', plugins_url('assets/bale-admin.css', __FILE__), [], '1.4.0');
        });
    }
});

add_action('admin_post_mv_bale_settings', static function (): void {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی به تنظیمات کانال مجاز نیست.', '', ['response' => 403]);
    }
    check_admin_referer('mv-bale-settings');
    $raw_channel = trim(mv_commerce_field('channel'));
    $channel = mv_bale_channel($raw_channel);
    $raw_url = trim(mv_commerce_field('channel_url'));
    $url = preg_match('~^https://ble\.ir/[A-Za-z0-9_/-]+/?$~iD', $raw_url) ? esc_url_raw($raw_url, ['https']) : '';
    $raw_base = trim(mv_commerce_field('public_base'));
    $base = mv_bale_public_base($raw_base);
    if (($raw_channel && !$channel) || ($raw_url && !$url) || ($raw_base && !$base)) {
        mv_bale_feedback('invalid_address');
    }
    $token = trim(mv_commerce_field('token'));
    $clear = mv_commerce_field('clear_token') === '1';
    if (($clear && $token) || ($token && !mv_bale_token_valid($token))) {
        mv_bale_feedback('invalid_token');
    }
    $product = !$clear && mv_commerce_field('product') === '1';
    $post = !$clear && mv_commerce_field('post') === '1';
    if (($product || $post) && (!$channel || (!$token && !mv_bale_token()) || !mv_bale_public_base($base ?: home_url('/')))) {
        mv_bale_feedback('required');
    }
    if ($token && !mv_bale_store_token($token)) {
        mv_bale_feedback('token_storage');
    }
    if ($clear) {
        delete_option('mv_bale_token');
        delete_option('mv_bale_verified');
    }
    $verification = 'saved';
    if ($product || $post) {
        $active_token = mv_bale_token();
        if (!mv_bale_verified($active_token, $channel)) {
            $state = mv_bale_check_connection($active_token, $channel);
            if ($state === 'connected') {
                update_option('mv_bale_verified', mv_bale_connection_signature($active_token, $channel), false);
            } else {
                $verification = $state;
                $product = $post = false;
            }
        }
    }
    update_option('mv_bale_settings', [
        'channel' => $channel, 'channel_url' => $url, 'public_base' => $base,
        'footer' => mv_bale_limit_text(sanitize_textarea_field(mv_commerce_field('footer')), 180),
        'product_button' => mv_bale_limit_text(sanitize_text_field(mv_commerce_field('product_button')), 42) ?: 'مشاهده و خرید از فروشگاه',
        'post_button' => mv_bale_limit_text(sanitize_text_field(mv_commerce_field('post_button')), 42) ?: 'خواندن مقاله',
        'product' => $product, 'post' => $post,
    ], false);
    mv_bale_feedback($verification);
});

function mv_bale_check_connection(string $token, string $channel): string {
    if (!$token || !$channel) {
        return 'required';
    }
    $bot = mv_bale_request($token, 'getMe');
    if (is_wp_error($bot)) {
        return 'connection_error';
    }
    $chat = mv_bale_request($token, 'getChat', ['chat_id' => $channel]);
    if (is_wp_error($chat) || ($chat['type'] ?? '') !== 'channel' || empty($bot['id'])) {
        return 'invalid_channel';
    }
    $member = mv_bale_request($token, 'getChatMember', ['chat_id' => $channel, 'user_id' => $bot['id']]);
    if (is_wp_error($member)) {
        return 'channel_permission';
    }
    $role = $member['status'] ?? '';
    return $role === 'creator' || ($role === 'administrator' && !empty($member['can_post_messages']))
        ? 'connected' : 'channel_permission';
}

add_action('admin_post_mv_bale_check', static function (): void {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی به تنظیمات کانال مجاز نیست.', '', ['response' => 403]);
    }
    check_admin_referer('mv-bale-check');
    $token = mv_bale_token();
    $channel = mv_bale_settings()['channel'];
    $state = mv_bale_check_connection($token, $channel);
    if ($state === 'connected') {
        update_option('mv_bale_verified', mv_bale_connection_signature($token, $channel), false);
    }
    mv_bale_feedback($state);
});

add_action('admin_post_mv_bale_process', static function (): void {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی به صف انتشار مجاز نیست.', '', ['response' => 403]);
    }
    $id = absint(mv_commerce_field('post_id'));
    check_admin_referer('mv-bale-process-' . $id);
    mv_bale_retry($id);
    mv_bale_send($id);
    mv_bale_feedback('processed');
});

add_action('add_meta_boxes', static function (): void {
    if (!current_user_can('manage_options')) {
        return;
    }
    add_meta_box('mv-bale-note', 'پست کانال بله', static function (WP_Post $post): void {
        wp_nonce_field('mv-bale-note-' . $post->ID, 'mv_bale_nonce');
        $status = mv_bale_delivery($post->ID)['status'] ?? '';
        ?>
        <label for="mv-bale-note-text">متن تکمیلی پست کانال (اختیاری)</label>
        <textarea id="mv-bale-note-text" name="mv_bale_note" rows="3" maxlength="180" style="width:100%"><?php echo esc_textarea(get_post_meta($post->ID, '_mv_bale_note', true)); ?></textarea>
        <p class="description">عنوان، تصویر، توضیح و قیمت از همین محتوا خوانده می‌شوند.</p>
        <?php if ($status) : ?><p><?php echo esc_html(mv_bale_status_label($status)); ?></p><?php endif; ?>
        <?php
    }, ['post', 'product'], 'side');
});

add_action('save_post', static function (int $id, WP_Post $post): void {
    if (!in_array($post->post_type, ['post', 'product'], true) || wp_is_post_revision($id) || wp_is_post_autosave($id)
        || !current_user_can('manage_options') || !current_user_can('edit_post', $id)
        || !wp_verify_nonce(mv_commerce_field('mv_bale_nonce'), 'mv-bale-note-' . $id)) {
        return;
    }
    update_post_meta($id, '_mv_bale_note', mv_bale_limit_text(sanitize_textarea_field(mv_commerce_field('mv_bale_note')), 180));
}, 10, 2);

function mv_bale_status_label(string $status): string {
    return [
        'queued' => 'در صف ارسال', 'sending' => 'در حال ارسال', 'sent' => 'منتشر شد',
        'retry' => 'در انتظار فرصت ارسال مجدد', 'paused' => 'متوقف به‌دلیل تنظیمات یا وضعیت محتوا',
        'rejected' => 'ارسال از سوی بله رد شد', 'uncertain' => 'وضعیت ارسال نیاز به بررسی کانال دارد',
    ][$status] ?? '';
}

function mv_bale_admin_page(): void {
    if (!current_user_can('manage_options')) {
        return;
    }
    $settings = mv_bale_settings();
    $notices = [
        'saved' => 'تنظیمات کانال ذخیره شد.', 'processed' => 'صف بررسی شد. نتیجه در جدول انتشار نمایش داده می‌شود.',
        'connected' => 'بازو متصل است و اجازهٔ انتشار در کانال را دارد.',
        'invalid_address' => 'شناسهٔ کانال، پیوند بله یا نشانی عمومی سایت معتبر نیست.',
        'invalid_token' => 'قالب توکن بازو معتبر نیست.', 'token_storage' => 'ذخیرهٔ امن توکن انجام نشد. پشتیبانی OpenSSL را بررسی کنید.',
        'required' => 'شناسهٔ کانال، توکن بازو و نشانی عمومی HTTPS را برای انتشار تنظیم کنید.',
        'connection_error' => 'بله اتصال بازو را تأیید نکرد. نو بودن توکن و دسترسی سرور را بررسی کنید.',
        'invalid_channel' => 'بله شناسهٔ واردشده را به‌عنوان کانال تأیید نکرد.',
        'channel_permission' => 'دسترسی بازو را در بخش مدیران کانال بررسی کنید. مجوز انتشار پیام لازم است.',
    ];
    $result = is_string($_GET['mv_result'] ?? null) ? sanitize_key($_GET['mv_result']) : '';
    $samples = array_merge(...array_map(static fn(string $type): array => get_posts([
        'post_type' => $type, 'post_status' => 'publish', 'numberposts' => 8, 'has_password' => false,
    ]), ['product', 'post']));
    $requested = is_scalar($_GET['preview_id'] ?? null) ? absint($_GET['preview_id']) : 0;
    $sample = $requested ? get_post($requested) : ($samples[0] ?? null);
    $preview = mv_bale_public_post($sample) ? mv_bale_message($sample, $settings, true) : null;
    $history = get_posts([
        'post_type' => ['post', 'product'], 'post_status' => 'any', 'numberposts' => 15,
        'meta_key' => '_mv_bale_delivery_updated', 'orderby' => 'meta_value_num', 'fields' => 'ids',
    ]);
    ?>
    <div class="wrap mv-bale-admin" dir="rtl">
        <h1>انتشار در کانال بله</h1>
        <p>بازو را با مجوز انتشار پیام به مدیران کانال اضافه کنید و اطلاعات اتصال را وارد کنید.</p>
        <?php if (isset($notices[$result])) : ?>
            <div class="notice <?php echo in_array($result, ['saved', 'connected', 'processed'], true) ? 'notice-success' : 'notice-error'; ?>"><p><?php echo esc_html($notices[$result]); ?></p></div>
        <?php endif; ?>
        <div class="mv-bale-grid">
            <section class="mv-bale-panel" aria-labelledby="mv-bale-settings-title">
                <h2 id="mv-bale-settings-title">اتصال و قالب پیام</h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="mv_bale_settings">
                    <?php wp_nonce_field('mv-bale-settings'); ?>
                    <label>شناسهٔ کانال<input name="channel" dir="ltr" maxlength="36" placeholder="@channel_name" value="<?php echo esc_attr($settings['channel']); ?>"></label>
                    <label>پیوند عمومی کانال (اختیاری)<input type="url" name="channel_url" dir="ltr" placeholder="https://ble.ir/channel_name" value="<?php echo esc_attr($settings['channel_url']); ?>"></label>
                    <label>توکن بازو<input type="password" name="token" dir="ltr" autocomplete="new-password" maxlength="201" placeholder="<?php echo mv_bale_token() ? 'توکن ثبت شده است' : 'توکن دریافتی از بازوی پدر'; ?>"></label>
                    <?php if (get_option('mv_bale_token')) : ?><label class="mv-bale-check"><input type="checkbox" name="clear_token" value="1"> حذف توکن ذخیره‌شده</label><?php endif; ?>
                    <label>نشانی عمومی فروشگاه<input type="url" name="public_base" dir="ltr" placeholder="https://moalem-varzesh.ir" value="<?php echo esc_attr($settings['public_base']); ?>"></label>
                    <p class="description">روی دامنهٔ عمومیِ HTTPS، خالی بگذارید تا وردپرس از نشانی خودش استفاده کند.</p>
                    <div class="mv-bale-fields">
                        <label>دکمهٔ کالا<input name="product_button" maxlength="42" value="<?php echo esc_attr($settings['product_button']); ?>"></label>
                        <label>دکمهٔ مقاله<input name="post_button" maxlength="42" value="<?php echo esc_attr($settings['post_button']); ?>"></label>
                    </div>
                    <label>امضای پیام (اختیاری)<textarea name="footer" rows="2" maxlength="180"><?php echo esc_textarea($settings['footer']); ?></textarea></label>
                    <fieldset><legend>انتشار خودکار</legend>
                        <label class="mv-bale-check"><input type="checkbox" name="product" value="1" <?php checked($settings['product']); ?>> کالاهای تازه</label>
                        <label class="mv-bale-check"><input type="checkbox" name="post" value="1" <?php checked($settings['post']); ?>> مقاله‌های تازه</label>
                    </fieldset>
                    <button type="submit" class="button button-primary">ذخیره تنظیمات</button>
                </form>
                <form class="mv-bale-test" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="mv_bale_check">
                    <?php wp_nonce_field('mv-bale-check'); ?>
                    <button type="submit" class="button">بررسی اتصال و مجوز بازو</button>
                </form>
            </section>
            <section class="mv-bale-panel" aria-labelledby="mv-bale-preview-title">
                <h2 id="mv-bale-preview-title">پیش‌نمایش پیام</h2>
                <?php if ($samples) : ?>
                    <form class="mv-bale-picker" method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                        <input type="hidden" name="page" value="mv-bale">
                        <label for="mv-bale-preview-id">نمونهٔ محتوا</label>
                        <select id="mv-bale-preview-id" name="preview_id">
                            <?php foreach ($samples as $item) : ?>
                                <option value="<?php echo (int) $item->ID; ?>" <?php selected($sample?->ID, $item->ID); ?>><?php echo esc_html(get_the_title($item)); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="button">نمایش نمونه</button>
                    </form>
                <?php endif; ?>
                <?php if ($preview) : $button = $preview['reply_markup']['inline_keyboard'][0][0]; ?>
                    <article class="mv-bale-message">
                        <?php echo wp_get_attachment_image($preview['photo'], 'medium', false, ['alt' => '']); ?>
                        <div>
                            <?php foreach (explode("\n\n", $preview['text']) as $index => $line) : ?>
                                <?php if (!$index) : ?><h3><?php echo esc_html($line); ?></h3>
                                <?php else : ?><p><?php echo esc_html($line); ?></p><?php endif; ?>
                            <?php endforeach; ?>
                            <a href="<?php echo esc_url($button['url']); ?>"><?php echo esc_html($button['text']); ?></a>
                        </div>
                    </article>
                <?php else : ?><p>یک کالا یا مقالهٔ عمومی برای پیش‌نمایش انتخاب کنید.</p><?php endif; ?>
            </section>
        </div>
        <h2>آخرین وضعیت‌های انتشار</h2>
        <?php if (!$history) : ?><p>هنوز محتوایی برای کانال در صف قرار نگرفته است.</p>
        <?php else : ?>
            <div class="mv-bale-history"><table class="widefat striped"><thead><tr><th>محتوا</th><th>کانال</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>
            <?php foreach ($history as $id) :
                $delivery = mv_bale_delivery($id);
                $status = $delivery['status'] ?? '';
                if ($status === 'sending' && ($delivery['updated'] ?? 0) < time() - 2 * MINUTE_IN_SECONDS) {
                    $status = 'uncertain';
                }
                ?>
                <tr>
                    <td><a href="<?php echo esc_url(get_edit_post_link($id)); ?>"><?php echo esc_html(get_the_title($id)); ?></a></td>
                    <td><bdi><?php echo esc_html($delivery['channel'] ?? ''); ?></bdi></td>
                    <td><?php echo esc_html(mv_bale_status_label($status)); ?><?php if (!empty($delivery['http'])) echo ' (' . (int) $delivery['http'] . ')'; ?></td>
                    <td><?php if (in_array($status, ['queued', 'paused', 'rejected', 'retry'], true)) : ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="mv_bale_process">
                            <input type="hidden" name="post_id" value="<?php echo (int) $id; ?>">
                            <?php wp_nonce_field('mv-bale-process-' . $id); ?>
                            <button type="submit" class="button button-small">پردازش ارسال</button>
                        </form>
                    <?php elseif (!empty($delivery['message'])) : ?><bdi><?php echo esc_html($delivery['message']); ?></bdi><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div>
    <?php
}
