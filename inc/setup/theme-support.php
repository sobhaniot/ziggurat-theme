<?php
if (!defined('ABSPATH')) {
    exit;
}
function zigurat_theme_setup()
{
    // عنوان سایت
    add_theme_support('title-tag');
    // لوگوی سفارشی
    add_theme_support('custom-logo', array(
        'height'      => 100,
        'width'       => 300,
        'flex-height' => true,
        'flex-width'  => true,
    ));
    // تصویر شاخص
    add_theme_support('post-thumbnails');
    add_image_size('zigurat-project-card', 720, 560, true);
    add_image_size('zigurat-article-card', 720, 450, true);
    add_image_size('zigurat-about-image', 900, 720, false);
    add_image_size('zigurat-brand-card', 360, 240, false);
    // HTML5
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
    ));
    // منوها
    register_nav_menus(array(
        'main-menu' => 'منوی اصلی',
        'footer-menu' => 'منوی فوتر'
    ));
    add_post_type_support('page', 'excerpt');
}
add_action(
    'after_setup_theme',
    'zigurat_theme_setup'
);
