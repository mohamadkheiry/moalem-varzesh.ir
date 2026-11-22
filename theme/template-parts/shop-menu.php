<?php
defined('ABSPATH') || exit;
$groups = [
    ['پوشاک و فوتبال', '', [
        ['تی-شرت-ورزشی', 'تی‌شرت و پوشاک تمرینی', [
            ['جوراب-ورزشی', '', []],
            ['شلوار-ورزشی', '', []],
            ['شورت-ورزشی', '', []],
        ]],
        ['football', 'فوتبال و کیت‌ها', [
            ['football-club-kits', '', []],
            ['national-team-kits', '', []],
            ['لباس-فوتبال', '', []],
        ]],
    ]],
    ['کفش ورزشی', 'کفش-های-ورزشی', [
        ['کتونی-فوتسال', '', []],
        ['کتونی-فوتبال-استوک-ریز', '', []],
        ['کتونی-فوتبال-استوک', '', []],
    ]],
    ['توپ‌های ورزشی', 'توپ-های-ورزشی', [
        ['table-tennis', '', []],
    ]],
    ['لوازم جانبی', 'اکسسوری', [
        ['دستکش', '', []],
        ['دستکش-دروازه-بانی-فوتبال', '', []],
    ]],
];
?>
<details class="mv-shop-disclosure" data-shop-disclosure>
    <summary aria-label="دسته‌بندی‌های فروشگاه" aria-controls="mv-catalog-menu"><?php mv_icon('chevron'); ?></summary>
    <div id="mv-catalog-menu" class="mv-catalog-menu">
        <?php foreach ($groups as [$label, $slug, $links]) :
            $term = $slug ? mv_catalog_term($slug) : null;
            $href = $term ? get_term_link($term) : false;
            ?>
            <div class="mv-catalog-group">
                <h2 class="mv-catalog-heading">
                    <?php if ($href && !is_wp_error($href)) : ?>
                        <a href="<?php echo esc_url($href); ?>" <?php if (is_tax('product_cat', $term->term_id)) echo 'aria-current="page"'; ?>><?php echo esc_html($label); ?></a>
                    <?php else : echo esc_html($label); endif; ?>
                </h2>
                <?php mv_catalog_menu_links($links); ?>
            </div>
        <?php endforeach; ?>
    </div>
</details>
