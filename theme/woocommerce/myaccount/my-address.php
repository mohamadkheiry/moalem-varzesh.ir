<?php
/**
 * Saved addresses and legacy WooCommerce address settings.
 * @version 9.3.0
 */
defined('ABSPATH') || exit;
if (!function_exists('mv_address_book')) {
    require WC()->plugin_path() . '/templates/myaccount/my-address.php';
    return;
}
$user_id = get_current_user_id();
$book = mv_address_book($user_id);
$addresses = $book['items'];
$id = isset($_GET['mv_address']) && is_string($_GET['mv_address']) ? wp_unslash($_GET['mv_address']) : '';
$editing = $id === 'new' || isset($addresses[$id]);
$remove = $id !== 'new' && isset($addresses[$id]) && ($_GET['mv_remove'] ?? '') === '1';
$back = mv_address_url();
$states = mv_address_states();
$type_labels = ['billing' => 'صورتحساب', 'shipping' => 'ارسال'];
?>
<section class="mv-customer-section" aria-labelledby="mv-address-heading">
    <header class="mv-area-heading"><?php mv_icon('pin'); ?><h2 id="mv-address-heading">نشانی‌های من</h2>
        <?php if (!$editing) : ?><a class="mv-area-action mv-area-primary" href="<?php echo esc_url(mv_address_url(['mv_address' => 'new'])); ?>"><?php mv_icon('plus'); ?> افزودن نشانی</a><?php endif; ?>
    </header>
    <?php if ($editing) :
        $prefill = isset($_GET['mv_from']) && is_string($_GET['mv_from']) ? wp_unslash($_GET['mv_from']) : '';
        $address = $id === 'new' && isset($type_labels[$prefill])
            ? mv_address_from_profile($user_id, $prefill)
            : ($addresses[$id] ?? []);
        if (mv_commerce_field('mv_address_action') === 'save') {
            $address = mv_address_input();
        }
        ?>
        <?php if ($remove) : ?>
            <form method="post" class="mv-address-editor">
                <h3>حذف نشانی</h3>
                <p>نشانی «<?php echo esc_html($address['label'] ?: $address['city'] . '، ' . $address['address_1']); ?>» از دفترچهٔ شما حذف می‌شود.</p>
                <input type="hidden" name="mv_address_id" value="<?php echo esc_attr($id); ?>">
                <?php wp_nonce_field('mv-address-remove-' . $id, '_mv_address_nonce', false); ?>
                <div class="mv-area-form-actions">
                    <button class="mv-area-action mv-area-danger" name="mv_address_action" value="remove">حذف نشانی</button>
                    <a class="mv-area-action mv-area-secondary" href="<?php echo esc_url($back); ?>">انصراف</a>
                </div>
            </form>
        <?php else : ?>
            <form method="post" class="mv-address-editor">
                <h3><?php echo $id === 'new' ? 'نشانی تازه' : 'ویرایش نشانی'; ?></h3>
                <div class="mv-address-fields">
                    <?php foreach (mv_address_fields() as $key => [$label, $max, $required, $type, $autocomplete]) :
                        $field = 'mv_ship_' . $key;
                        $value = $address[$key] ?? '';
                        ?>
                        <div class="mv-address-field<?php if (in_array($key, ['label', 'address_1', 'address_2'], true)) echo ' mv-address-wide'; ?>">
                            <label for="<?php echo esc_attr($field); ?>"><?php echo esc_html($label); ?>
                                <?php if (!$required) : ?><small>اختیاری</small><?php endif; ?>
                            </label>
                            <?php if ($type === 'select') : ?>
                                <select id="<?php echo esc_attr($field); ?>" name="<?php echo esc_attr($field); ?>" autocomplete="<?php echo esc_attr($autocomplete); ?>" required>
                                    <option value="">انتخاب استان</option>
                                    <?php foreach ($states as $code => $name) : ?>
                                        <option value="<?php echo esc_attr($code); ?>" <?php selected($value, $code); ?>><?php echo esc_html($name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else : ?>
                                <input id="<?php echo esc_attr($field); ?>" name="<?php echo esc_attr($field); ?>"
                                    type="<?php echo esc_attr($type); ?>" value="<?php echo esc_attr($value); ?>"
                                    maxlength="<?php echo esc_attr($max); ?>" autocomplete="<?php echo esc_attr($autocomplete); ?>"
                                    <?php if ($required) echo 'required'; ?>
                                    <?php if (in_array($key, ['postcode', 'phone'], true)) echo $key === 'phone' ? 'dir="ltr" inputmode="tel"' : 'dir="ltr" inputmode="numeric"'; ?>>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="mv_address_id" value="<?php echo esc_attr($id); ?>">
                <?php wp_nonce_field('mv-address-save-' . $id, '_mv_address_nonce', false); ?>
                <div class="mv-area-form-actions">
                    <button class="mv-area-action mv-area-primary" name="mv_address_action" value="save">ذخیرهٔ نشانی</button>
                    <a class="mv-area-action mv-area-secondary" href="<?php echo esc_url($back); ?>">انصراف</a>
                </div>
            </form>
        <?php endif; ?>
    <?php else : ?>
        <?php if ($id !== '') wc_print_notice('این نشانی پیدا نشد.', 'notice'); ?>
        <?php if (!$addresses) : ?>
            <div class="mv-area-empty">
                <?php mv_icon('pin'); ?>
                <h3>هنوز نشانی ذخیره نکرده‌اید</h3>
                <p>نشانی تحویل را اینجا ذخیره کنید تا هنگام خرید فقط آن را انتخاب کنید.</p>
            </div>
        <?php else : ?>
            <div class="mv-address-stack">
                <?php
                $default = $book['default'];
                if (isset($addresses[$default])) $addresses = [$default => $addresses[$default]] + $addresses;
                foreach ($addresses as $address_id => $address) :
                    $primary = $default === $address_id;
                    $edit = mv_address_url(['mv_address' => $address_id]);
                    ?>
                    <article class="mv-address-card<?php if ($primary) echo ' is-default'; ?>">
                        <div class="mv-address-card-icon"><?php mv_icon('pin'); ?></div>
                        <div class="mv-address-card-body">
                            <header>
                                <h3><?php echo esc_html($address['label'] ?: $address['city']); ?></h3>
                                <?php if ($primary) : ?><span>نشانی پیش‌فرض</span><?php endif; ?>
                            </header>
                            <?php $location = array_unique([$states[$address['state']] ?? '', $address['city']]); ?>
                            <p><?php echo esc_html(implode('، ', array_filter(array_merge($location, [$address['address_1'], $address['address_2']])))); ?></p>
                            <dl>
                                <div><dt>گیرنده</dt><dd><?php echo esc_html($address['first_name'] . ' ' . $address['last_name']); ?></dd></div>
                                <div><dt>موبایل</dt><dd><bdi dir="ltr"><?php echo esc_html($address['phone']); ?></bdi></dd></div>
                                <div><dt>کد پستی</dt><dd><bdi dir="ltr"><?php echo esc_html($address['postcode']); ?></bdi></dd></div>
                            </dl>
                            <footer class="mv-address-card-actions">
                                <a class="mv-area-action mv-area-secondary" href="<?php echo esc_url($edit); ?>">ویرایش</a>
                                <?php if (!$primary) : ?>
                                    <form method="post" action="<?php echo esc_url($back); ?>">
                                        <input type="hidden" name="mv_address_id" value="<?php echo esc_attr($address_id); ?>">
                                        <input type="hidden" name="_mv_address_nonce" value="<?php echo esc_attr(wp_create_nonce('mv-address-default-' . $address_id)); ?>">
                                        <button class="mv-area-action mv-area-secondary" name="mv_address_action" value="default">پیش‌فرض شود</button>
                                    </form>
                                <?php endif; ?>
                                <a class="mv-area-action mv-area-quiet" href="<?php echo esc_url(add_query_arg('mv_remove', '1', $edit)); ?>">حذف</a>
                            </footer>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <details class="mv-legacy-addresses">
            <summary>نشانی‌های ثبت‌شده در حساب</summary>
            <div>
                <?php foreach ($type_labels as $type => $label) :
                    if ($type === 'shipping' && (!wc_shipping_enabled() || wc_ship_to_billing_address_only())) continue;
                    $formatted = wc_get_account_formatted_address($type, $user_id);
                    $url = wc_get_endpoint_url('edit-address', $type, wc_get_page_permalink('myaccount'));
                    ?>
                    <article>
                        <h3><?php echo esc_html('نشانی ' . $label); ?></h3>
                        <address><?php echo $formatted ? wp_kses_post($formatted) : 'هنوز ثبت نشده است.'; ?></address>
                        <?php do_action('woocommerce_my_account_after_my_address', $type); ?>
                        <div class="mv-address-card-actions">
                            <a class="mv-area-action mv-area-secondary" href="<?php echo esc_url($url); ?>"><?php echo $formatted ? 'ویرایش' : 'ثبت نشانی'; ?></a>
                            <?php if ($formatted) : ?>
                                <a class="mv-area-action mv-area-secondary" href="<?php echo esc_url(mv_address_url(['mv_address' => 'new', 'mv_from' => $type])); ?>">کپی در دفترچه</a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </details>
    <?php endif; ?>
</section>
