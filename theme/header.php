<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip" href="#main">رفتن به محتوا</a>
<header class="site-header">
    <div class="wrap header-top">
        <a class="brand" href="<?php echo esc_url(home_url('/')); ?>"><?php mv_icon('brand'); ?><span><?php bloginfo('name'); ?></span></a>
        <form class="site-search" role="search" action="<?php echo esc_url(home_url('/')); ?>" method="get">
            <label class="screen-reader-text" for="site-search">جست‌وجوی کالا</label>
            <input id="site-search" name="s" type="search" value="<?php echo esc_attr(get_search_query(false)); ?>" placeholder="جست‌وجوی کالا" required>
            <input type="hidden" name="post_type" value="product">
            <button type="submit" aria-label="جست‌وجو"><?php mv_icon('search'); ?></button>
        </form>
        <?php if ($actions = mv_secondary_markup('actions')) : ?>
            <?php echo $actions; ?>
        <?php elseif (function_exists('wc_get_cart_url')) : ?>
        <div class="header-actions">
            <a class="icon-link" href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>" aria-label="حساب کاربری" title="حساب کاربری" <?php if (is_account_page()) echo 'aria-current="page"'; ?>><?php mv_icon('user'); ?></a>
            <a class="icon-link" href="<?php echo esc_url(wc_get_cart_url()); ?>" aria-label="سبد خرید" title="سبد خرید" <?php if (is_cart()) echo 'aria-current="page"'; ?>><?php mv_icon('cart'); ?></a>
        </div>
        <?php endif; ?>
    </div>
    <nav class="wrap main-nav" aria-label="منوی اصلی">
        <a href="<?php echo esc_url(home_url('/')); ?>" <?php if (is_front_page()) echo 'aria-current="page"'; ?>>خانه</a>
        <div class="mv-shop-nav">
            <a href="<?php echo esc_url(mv_shop_url()); ?>" <?php if (function_exists('is_shop') && (is_shop() || is_product_taxonomy())) echo 'aria-current="page"'; ?>>فروشگاه</a>
            <?php if (mv_catalog_term('football')) get_template_part('template-parts/shop-menu'); ?>
        </div>
        <?php $contact = get_page_by_path('contact-us'); if ($contact) : ?>
        <a href="<?php echo esc_url(get_permalink($contact)); ?>" <?php if (is_page($contact)) echo 'aria-current="page"'; ?>>تماس با ما</a>
        <?php endif; ?>
        <?php $guide_id = (int) get_option('page_for_posts'); if ($guide_id && ($guide = get_permalink($guide_id))) : ?>
        <a class="guide-link" href="<?php echo esc_url($guide); ?>" <?php if (is_home()) echo 'aria-current="page"'; ?>>راهنمای خرید <?php mv_icon('arrow'); ?></a>
        <?php endif; ?>
    </nav>
</header>
