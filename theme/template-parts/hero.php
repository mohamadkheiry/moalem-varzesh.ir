<?php
defined('ABSPATH') || exit;
$slides = [
    ['jersey', 'لباس ورزشی', 686, 780],
    ['boots', 'کفش فوتبال', 780, 622],
    ['ball', 'توپ فوتبال', 766, 780],
    ['gloves', 'دستکش دروازه‌بانی', 780, 780],
    ['guards', 'ساق‌بند فوتبال', 780, 780],
    ['socks', 'جوراب ورزشی', 780, 780],
];
$secondary = mv_store_design() === 'secondary';
if ($secondary) {
    $boots = $slides[1];
    array_splice($slides, 1, 1);
    array_unshift($slides, $boots);
}
?>
<section class="hero" data-hero aria-roledescription="اسلایدر" aria-labelledby="home-title">
    <div class="hero-copy">
        <h1 id="home-title"><span>لباس و تجهیزات</span> <span>ورزشی</span></h1>
        <p>پوشاک، کفش و لوازم تمرین</p>
        <?php if ($action = mv_secondary_markup('hero')) : ?>
            <?php echo $action; ?>
        <?php else : ?>
            <a class="button-primary" href="<?php echo esc_url(mv_shop_url()); ?>">مشاهده فروشگاه <?php mv_icon('arrow'); ?></a>
        <?php endif; ?>
    </div>
    <div class="hero-stage" aria-hidden="true">
        <?php foreach ($slides as $index => [$asset, , $width, $height]) : ?>
            <picture class="hero-slide hero-slide-<?php echo esc_attr($asset); ?><?php echo $index === 0 ? ' is-active' : ''; ?>">
                <source media="(max-width: 767px)" <?php echo $index === 0 ? 'srcset' : 'data-srcset'; ?>="<?php echo esc_url(get_theme_file_uri('assets/hero-' . $asset . '-420.webp')); ?>">
                <img <?php echo $index === 0 ? 'src' : 'data-src'; ?>="<?php echo esc_url(get_theme_file_uri('assets/hero-' . $asset . '-780.webp')); ?>"
                    width="<?php echo esc_attr($width); ?>" height="<?php echo esc_attr($height); ?>" alt="" decoding="async"<?php echo $index === 0 ? ' fetchpriority="high"' : ''; ?>>
            </picture>
        <?php endforeach; ?>
    </div>
    <div class="hero-controls" hidden>
        <div class="hero-indicators" role="group" aria-label="انتخاب تصویر">
            <?php foreach ($slides as $index => [, $label]) : ?>
                <button class="hero-dot" type="button" data-hero-index="<?php echo esc_attr($index); ?>"
                    aria-label="<?php echo esc_attr($label); ?>" aria-pressed="<?php echo $index === 0 ? 'true' : 'false'; ?>"></button>
            <?php endforeach; ?>
        </div>
        <div class="hero-transport">
            <button class="hero-control hero-previous" type="button" data-hero-step="-1" aria-label="تصویر قبلی"><?php mv_icon('arrow'); ?></button>
            <button class="hero-control" type="button" data-hero-step="1" aria-label="تصویر بعدی"><?php mv_icon('arrow'); ?></button>
            <button class="hero-control hero-toggle" type="button" data-hero-toggle aria-label="توقف حرکت خودکار">
                <svg class="hero-pause" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M9 6v12m6-12v12"/></svg>
                <svg class="hero-play" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m9 5 11 7-11 7z"/></svg>
            </button>
        </div>
        <span class="screen-reader-text" data-hero-announcement aria-live="polite"></span>
    </div>
</section>
