<?php
defined('ABSPATH') || exit;
$shoe_category = mv_catalog_term('کفش-های-ورزشی');
if (!$shoe_category) {
    return;
}
$options = [
    ['کتونی-فوتسال', 'سالن و فوتسال', 'زیرهٔ تخت برای بازی روی کف سالن', 'court'],
    ['کتونی-فوتبال-استوک-ریز', 'چمن مصنوعی', 'استوک ریز برای چمن مصنوعی کوتاه', 'turf'],
    ['کتونی-فوتبال-استوک', 'چمن طبیعی', 'کفش استوک برای زمین چمن', 'grass'],
];
$all = get_term_link($shoe_category);
?>
<section class="section mv-home-shoes" aria-labelledby="shoe-finder-title">
    <div class="section-head mv-home-head">
        <div>
            <h2 id="shoe-finder-title">کفش مناسب بازی‌تان را پیدا کنید</h2>
            <p>زمین بازی‌تان را انتخاب کنید تا مدل‌های مناسب آن را ببینید.</p>
        </div>
        <?php if (!is_wp_error($all)) : ?><a class="text-link" href="<?php echo esc_url($all); ?>">همهٔ کفش‌ها <?php mv_icon('arrow'); ?></a><?php endif; ?>
    </div>
    <div class="mv-home-shoe-grid">
        <?php foreach ($options as [$slug, $label, $description, $variant]) :
            $term = mv_catalog_term($slug);
            $href = $term ? get_term_link($term) : false;
            if (!$href || is_wp_error($href)) {
                continue;
            }
            $image = (int) (get_term_meta($term->term_id, '_mv_home_shoe_image', true) ?: get_term_meta($term->term_id, 'thumbnail_id', true));
            ?>
            <a class="mv-home-shoe mv-home-shoe--<?php echo esc_attr($variant); ?>" href="<?php echo esc_url($href); ?>">
                <div class="mv-home-shoe-copy">
                    <h3><?php echo esc_html($label); ?></h3>
                    <p><?php echo esc_html($description); ?></p>
                    <span class="mv-home-card-arrow"><?php mv_icon('arrow'); ?></span>
                </div>
                <?php if ($image) {
                    echo wp_get_attachment_image($image, 'woocommerce_thumbnail', false, [
                        'alt' => '', 'loading' => 'lazy', 'decoding' => 'async',
                        'sizes' => '(min-width: 1024px) 185px, (min-width: 768px) 110px, 38vw',
                    ]);
                } ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>
