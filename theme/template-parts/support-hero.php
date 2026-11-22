<?php
defined('ABSPATH') || exit;
$key = $args['key'];
$id = (int) $args['id'];
$settings = mv_support_settings()['pages'][$key];
?>
<header class="mv-support-hero mv-support-hero--<?php echo esc_attr($key); ?>" style="--mv-support-tone:<?php echo esc_attr(sanitize_hex_color($settings['accent'])); ?>">
    <div class="mv-support-hero-copy">
        <h1><?php echo esc_html($args['title']); ?></h1>
        <?php if ($settings['intro'] !== '') : ?><p><?php echo esc_html($settings['intro']); ?></p><?php endif; ?>
    </div>
    <figure class="mv-support-hero-image">
        <?php mv_support_cover($key, $id, (int) $settings['image']); ?>
    </figure>
</header>
