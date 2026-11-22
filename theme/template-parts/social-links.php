<?php
defined('ABSPATH') || exit;
$public_phone = function_exists('mv_commerce_public_contact_phone') ? mv_commerce_public_contact_phone() : '';
$links = [
    ['instagram', 'اینستاگرام', 'https://www.instagram.com/moalemvarzesh_store/'],
    ['telegram', 'تلگرام', 'https://t.me/MoalemVarzesh_Store'],
    ['whatsapp', 'واتساپ', 'https://wa.me/message/EMDTPXP6JVVMC1'],
    ['chat', 'ایتا', 'https://eitaa.com/MoalemVarzesh_Store'],
];
if (function_exists('mv_bale_channel_url') && ($bale_url = mv_bale_channel_url(mv_bale_settings()))) {
    $links[] = ['chat', 'کانال بله', $bale_url];
}
?>
<details class="mv-social">
    <summary class="mv-social-toggle">
        <span class="mv-social-open"><?php mv_icon('social'); ?></span>
        <span class="mv-social-close"><?php mv_icon('close'); ?></span>
        <span class="screen-reader-text">شبکه‌های اجتماعی</span>
    </summary>
    <nav class="mv-social-panel" aria-label="شبکه‌های اجتماعی فروشگاه">
        <ul>
            <?php foreach ($links as [$icon, $name, $url]) : ?>
                <li>
                    <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer">
                        <span class="mv-social-icon mv-social-<?php echo esc_attr($icon); ?>"><?php mv_icon($icon); ?></span>
                        <span><?php echo esc_html($name); ?></span>
                        <?php mv_icon('arrow'); ?>
                    </a>
                </li>
            <?php endforeach; ?>
            <?php if ($public_phone) : ?>
                <li class="mv-social-contact">
                    <a href="<?php echo esc_url('tel:+98' . substr($public_phone, 1)); ?>">
                        <span class="mv-social-icon mv-social-phone"><?php mv_icon('phone'); ?></span>
                        <span>تماس با مدیر<bdi class="mv-social-number" dir="ltr"><?php echo esc_html($public_phone); ?></bdi></span>
                        <?php mv_icon('arrow'); ?>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
</details>
