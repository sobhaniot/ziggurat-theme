<?php
if (!defined('ABSPATH')) {
    exit;
}
/**
 * ساخت Taxonomy عمومی
 */
function zigurat_register_taxonomy_helper(
    $taxonomy,
    $singular_name,
    $plural_name,
    $slug,
    $post_types = array('post'),
    $hierarchical = true
) {
    $labels = array(
        'name' => _x($plural_name, 'taxonomy general name'),
        'singular_name' => _x($singular_name, 'taxonomy singular name'),
        'search_items' => __('جستجوی ' . $plural_name),
        'all_items' => __('همه ' . $plural_name),
        'edit_item' => __('ویرایش ' . $singular_name),
        'update_item' => __('به‌روزرسانی ' . $singular_name),
        'add_new_item' => __('افزودن ' . $singular_name . ' جدید'),
        'new_item_name' => __('نام ' . $singular_name . ' جدید'),
        'menu_name' => __($plural_name),
    );
    $args = array(
        'hierarchical' => $hierarchical,
        'labels' => $labels,
        'show_ui' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'query_var' => true,
        'rewrite' => array(
            'slug' => $slug
        ),
    );
    register_taxonomy(
        $taxonomy,
        $post_types,
        $args
    );
}
add_action('init', function () {
    // کالاها
    zigurat_register_taxonomy_helper(
        'item_name',
        'نام کالا',
        'نام کالاها',
        'item_name',
        array('post')
    );
    // آیتم‌های پروژه
    zigurat_register_taxonomy_helper(
        'project_item',
        'آیتم پروژه',
        'آیتم‌های پروژه',
        'project_item',
        array('post')
    );
    // کارمندان
    zigurat_register_taxonomy_helper(
        'employee',
        'نام کارمند',
        'کارمندها',
        'employee',
        array('post')
    );
    // نوع اجرا
    zigurat_register_taxonomy_helper(
        'project_type',
        'نوع اجرا',
        'نوع اجرا',
        'project-type',
        array('project')
    );
    // متریال
    zigurat_register_taxonomy_helper(
        'project_material',
        'متریال',
        'متریال',
        'project-material',
        array('project')
    );
    // خدمات
    zigurat_register_taxonomy_helper(
        'project_service',
        'خدمت',
        'خدمات',
        'project-service',
        array('project')
    );
    // شهر اجرا
    zigurat_register_taxonomy_helper(
        'project_city',
        'شهر',
        'شهرها',
        'project-city',
        array('project')
    );
    zigurat_register_taxonomy_helper(
        'article_category',
        'دسته مطلب',
        'دسته‌بندی مطالب',
        'article-category',
        array('article')
    );
    zigurat_register_taxonomy_helper(
        'article_tag',
        'برچسب',
        'برچسب‌های مطالب',
        'article-tag',
        array('article'),
        false
    );
});
