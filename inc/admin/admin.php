<?php
function zigurat_change_post_menu_labels()
{
    global $menu;
    global $submenu;
    $menu[5][0] = 'انبار';
    $submenu['edit.php'][5][0]  = 'همه تراکنشها';
    $submenu['edit.php'][10][0] = 'افزودن تراکنش';
    $submenu['edit.php'][15][0] = 'نوع تراکنش';
    $submenu['edit.php'][16][0] = 'برچسب';
}
add_action('admin_menu', 'zigurat_change_post_menu_labels');
function zigurat_change_post_object_labels()
{
    global $wp_post_types;
    $labels = &$wp_post_types['post']->labels;
    $labels->name = 'انبار';
    $labels->singular_name = 'تراکنش';
    $labels->add_new = 'افزودن تراکنش';
    $labels->add_new_item = 'افزودن تراکنش جدید';
    $labels->edit_item = 'ویرایش تراکنش';
    $labels->new_item = 'تراکنش جدید';
    $labels->view_item = 'مشاهده تراکنش';
    $labels->search_items = 'جستجوی تراکنش';
    $labels->not_found = 'تراکنشی یافت نشد';
    $labels->all_items = 'همه تراکنشها';
    $labels->menu_name = 'انبار';
    $labels->name_admin_bar = 'تراکنش';
}
add_action('init', 'zigurat_change_post_object_labels');
