<?php
defined('ABSPATH') || exit;
$links = [
    ['instagram', 'اینستاگرام', 'https://www.instagram.com/moalemvarzesh_store/'],
    ['telegram', 'تلگرام', 'https://t.me/MoalemVarzesh_Store'],
    ['whatsapp', 'واتساپ', 'https://wa.me/message/EMDTPXP6JVVMC1'],
    ['chat', 'ایتا', 'https://eitaa.com/MoalemVarzesh_Store'],
];
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
        </ul>
    </nav>
</details>
