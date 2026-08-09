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
                    <?php the_custom_logo(); ?>
                <?php else : ?>
                    <a href="<?php echo esc_url(home_url('/')); ?>" rel="home" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?>">
                        <span>ZIGGURAT</span>
                    </a>
                <?php endif; ?>
            </div>
            <!-- منوی اصلی -->
            <nav id="main-menu" class="main-navigation" aria-label="منوی اصلی">
                <?php
                wp_nav_menu(array(
                    'theme_location' => 'main-menu',
                    'container'      => false,
                    'menu_class'     => 'menu',
                    'fallback_cb'    => false
                ));
                ?>
            </nav>
            <!-- دکمه ورود -->
            <div class="header-buttons">
                <a href="<?php echo esc_url(home_url('/login')); ?>" class="btn-login">
                    پنل مدیران
                </a>
            </div>
            <!-- دکمه موبایل -->
            <button id="mobile-menu-btn" type="button" aria-label="باز کردن منو" aria-controls="main-menu" aria-expanded="false">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </header>
