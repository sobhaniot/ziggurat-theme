<?php
function zigurat_change_post_menu_labels()
{
    global $menu;
    global $submenu;
    if (isset($menu[5][0])) {
        $menu[5][0] = 'انبار';
    }
    $submenu_labels = array(
        5  => 'همه تراکنشها',
        10 => 'افزودن تراکنش',
        15 => 'نوع تراکنش',
        16 => 'برچسب',
    );
    foreach ($submenu_labels as $position => $label) {
        if (isset($submenu['edit.php'][$position][0])) {
            $submenu['edit.php'][$position][0] = $label;
        }
    }
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
