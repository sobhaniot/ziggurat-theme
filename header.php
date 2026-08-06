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
                <a href="<?php echo esc_url(home_url('/')); ?>">
                    <?php
                    if (has_custom_logo()) {
                        the_custom_logo();
                    } else {
                    ?>
                        <span>ZIGGURAT</span>
                    <?php
                    }
                    ?>
                </a>
            </div>
            <!-- منوی اصلی -->
            <nav id="main-menu" class="main-navigation">
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
            <button id="mobile-menu-btn" aria-label="باز کردن منو">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </header>
