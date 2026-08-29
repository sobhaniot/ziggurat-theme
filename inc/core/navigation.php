<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * منوی اصلی را یک‌بار با ساختار مصوب سایت همگام می‌کند.
 * نسخه‌دار بودن این فرایند مانع بازنویسی تغییرات بعدی مدیر در هر درخواست می‌شود.
 */
function zigurat_sync_main_navigation($force = false)
{
    $version = '2';
    if (!$force && get_option('zigurat_main_navigation_version') === $version) {
        return;
    }

    $locations = get_nav_menu_locations();
    $menu_id = isset($locations['main-menu']) ? absint($locations['main-menu']) : 0;
    if (!$menu_id) {
        $menu = wp_get_nav_menu_object('Main Menu');
        $menu_id = $menu ? (int) $menu->term_id : (int) wp_create_nav_menu('Main Menu');
    }
    if (!$menu_id) {
        return;
    }

    foreach ((array) wp_get_nav_menu_items($menu_id) as $menu_item) {
        wp_delete_post($menu_item->ID, true);
    }

    $items = array(
        array('title' => 'خانه', 'slug' => 'home'),
        array('title' => 'پروژه‌ها', 'url' => get_post_type_archive_link('project') ?: home_url('/projects/')),
        array('title' => 'مطالب', 'url' => get_post_type_archive_link('article') ?: home_url('/articles/')),
        array('title' => 'دانلودها', 'url' => get_post_type_archive_link('zig_download') ?: home_url('/downloads/')),
        array('title' => 'همکاری با ما', 'slug' => 'cooperation'),
        array('title' => 'تماس با ما', 'slug' => 'contact'),
    );

    $created = 0;
    foreach ($items as $position => $item) {
        $menu_item = array(
            'menu-item-title'    => $item['title'],
            'menu-item-status'   => 'publish',
            'menu-item-position' => $position + 1,
        );
        if (!empty($item['slug'])) {
            $page = get_page_by_path($item['slug'], OBJECT, 'page');
            if (!$page) {
                continue;
            }
            $menu_item['menu-item-object'] = 'page';
            $menu_item['menu-item-object-id'] = $page->ID;
            $menu_item['menu-item-type'] = 'post_type';
        } else {
            $menu_item['menu-item-type'] = 'custom';
            $menu_item['menu-item-url'] = $item['url'];
        }

        $result = wp_update_nav_menu_item($menu_id, 0, $menu_item);
        if ($result && !is_wp_error($result)) {
            $created++;
        }
    }

    if ($created !== count($items)) {
        return;
    }
    $locations['main-menu'] = $menu_id;
    set_theme_mod('nav_menu_locations', $locations);
    update_option('zigurat_main_navigation_version', $version, false);
}
add_action('init', 'zigurat_sync_main_navigation', 80);
