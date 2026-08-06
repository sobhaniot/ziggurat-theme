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
    $hierarchical = true,
    $show_meta_box = true,
    $show_ui = true
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
        'show_ui' => $show_ui,
        'show_admin_column' => $show_ui,
        'show_in_rest' => $show_ui,
        'query_var' => true,
        'rewrite' => array(
            'slug' => $slug
        ),
    );
    if (!$show_meta_box) {
        $args['meta_box_cb'] = false;
    }
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
    // دسته قدیمی نوع اجرا؛ فقط برای انتقال داده‌های قبلی ثبت می‌شود.
    zigurat_register_taxonomy_helper(
        'project_type',
        'نوع فعالیت/اجرا',
        'انواع فعالیت و اجرا',
        'project-type',
        array('project'),
        true,
        false,
        false
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
        array('project'),
        true,
        false
    );
    // استان اجرا
    zigurat_register_taxonomy_helper(
        'project_province',
        'استان',
        'استان‌ها',
        'project-province',
        array('project'),
        true,
        false
    );
    // کارفرما / شرکت
    zigurat_register_taxonomy_helper(
        'project_client',
        'کارفرما',
        'کارفرماها',
        'project-client',
        array('project'),
        false,
        false
    );
    // نوع فعالیت/اجرا برای فیلتر آرشیو
    zigurat_register_taxonomy_helper(
        'project_sign_type',
        'نوع فعالیت/اجرا',
        'انواع فعالیت و اجرا',
        'project-sign-type',
        array('project'),
        true,
        false
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

/** فیلتر دسته و برچسب در آرشیو مطالب. */
add_action('pre_get_posts', function ($query) {
    if (is_admin() || !$query->is_main_query() || !$query->is_post_type_archive('article')) {
        return;
    }

    $tax_query = array();
    foreach (array('article_category', 'article_tag') as $taxonomy) {
        if (!empty($_GET[$taxonomy]) && is_string($_GET[$taxonomy])) {
            $tax_query[] = array(
                'taxonomy' => $taxonomy,
                'field'    => 'slug',
                'terms'    => sanitize_title(wp_unslash($_GET[$taxonomy])),
            );
        }
    }
    if (count($tax_query) > 1) {
        $tax_query['relation'] = 'AND';
    }
    if ($tax_query) {
        $query->set('tax_query', $tax_query);
    }
    if (!empty($_GET['article_search']) && is_string($_GET['article_search'])) {
        $query->set('s', sanitize_text_field(wp_unslash($_GET['article_search'])));
    }
});
