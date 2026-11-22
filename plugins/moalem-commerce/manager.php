<?php
defined('ABSPATH') || exit;

function mv_commerce_manager_phone(): string {
    $value = get_option('mv_commerce_manager_phone', '');
    return is_string($value) ? mv_commerce_phone($value) : '';
}

function mv_commerce_sanitize_manager_phone($value): string {
    if (is_string($value) && trim($value) === '') {
        return '';
    }
    $phone = is_string($value) ? mv_commerce_phone($value) : '';
    if (!$phone) {
        add_settings_error('mv_commerce_manager_phone', 'invalid_manager_phone', 'شماره موبایل مدیر معتبر نیست.');
        return mv_commerce_manager_phone();
    }
    return $phone;
}

add_action('admin_init', static function (): void {
    register_setting('mv_store_manager', 'mv_commerce_manager_phone', [
        'type' => 'string',
        'sanitize_callback' => 'mv_commerce_sanitize_manager_phone',
        'default' => '',
    ]);
});

add_action('admin_menu', static function (): void {
    if (class_exists('WooCommerce')) {
        add_submenu_page(
            'woocommerce',
            'مدیر فروشگاه',
            'مدیر فروشگاه',
            'manage_options',
            'mv-store-manager',
            'mv_commerce_manager_page'
        );
    }
});

function mv_commerce_manager_page(): void {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی به تنظیمات مدیر فروشگاه مجاز نیست.', '', ['response' => 403]);
    }
    ?>
    <div class="wrap">
        <h1>مدیر فروشگاه</h1>
        <?php settings_errors(); ?>
        <form method="post" action="options.php">
            <?php settings_fields('mv_store_manager'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="mv-manager-phone">شماره موبایل مدیر</label></th>
                    <td>
                        <input id="mv-manager-phone" name="mv_commerce_manager_phone" type="tel"
                            class="regular-text" dir="ltr" maxlength="24" aria-describedby="mv-manager-phone-help"
                            value="<?php echo esc_attr(mv_commerce_manager_phone()); ?>">
                        <p class="description" id="mv-manager-phone-help">
                            پیامک سفارش‌های جدید به این شماره ارسال می‌شود. برای غیرفعال کردن آن، شماره را خالی بگذارید.
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button('ذخیره شماره مدیر'); ?>
        </form>
    </div>
    <?php
}
