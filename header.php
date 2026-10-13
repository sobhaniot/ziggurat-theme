<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
    <?php wp_body_open(); ?>
    <header id="main-header" class="site-header">
        <div class="container">
            <!-- لوگو -->
            <div class="logo">
                <?php if (has_custom_logo()) : ?>
                    <?php zigurat_site_logo('eager'); ?>
                <?php else : ?>
                    <a href="<?php echo esc_url(zigurat_localized_url(home_url('/'))); ?>" rel="home" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?>">
                        <span>ZIGGURAT</span>
                    </a>
                <?php endif; ?>
            </div>
            <!-- منوی اصلی -->
            <nav id="main-menu" class="main-navigation" aria-label="<?php echo esc_attr(zigurat_t('primary_menu')); ?>">
                <?php
                wp_nav_menu(array(
                    'theme_location' => 'main-menu',
                    'container'      => false,
                    'menu_class'     => 'menu',
                    'fallback_cb'    => false
                ));
                ?>
            </nav>
            <?php zigurat_render_language_switcher(); ?>
            <!-- دکمه ورود -->
            <div class="header-buttons">
                <a href="<?php echo esc_url(zigurat_localized_url(home_url('/login'))); ?>" class="btn-login">
                    <?php echo esc_html(zigurat_t('manager_panel')); ?>
                </a>
            </div>
            <!-- دکمه موبایل -->
            <button id="mobile-menu-btn" type="button" aria-label="<?php echo esc_attr(zigurat_t('open_menu')); ?>" aria-controls="main-menu" aria-expanded="false">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </header>
