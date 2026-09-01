<?php
function zigurat_register_project_post_type()
{
    $labels = array(
        'name'               => 'پروژه‌ها',
        'singular_name'      => 'پروژه',
        'add_new'            => 'افزودن پروژه',
        'add_new_item'       => 'پروژه جدید',
        'edit_item'          => 'ویرایش پروژه',
        'new_item'           => 'پروژه جدید',
        'view_item'          => 'مشاهده پروژه',
        'search_items'       => 'جستجوی پروژه',
        'not_found'          => 'پروژه‌ای پیدا نشد',
        'menu_name'          => 'پروژه‌ها',
    );
    register_post_type('project', array(
        'labels' => $labels,
        'public' => true,
        'map_meta_cap' => true,
        'capabilities' => array(
            'edit_post' => 'edit_zigurat_project',
            'read_post' => 'read_zigurat_project',
            'delete_post' => 'delete_zigurat_project',
            'edit_posts' => 'manage_options',
            'edit_others_posts' => 'manage_options',
            'publish_posts' => 'manage_options',
            'read_private_posts' => 'manage_options',
            'delete_posts' => 'manage_options',
            'delete_private_posts' => 'manage_options',
            'delete_published_posts' => 'manage_options',
            'delete_others_posts' => 'manage_options',
            'edit_private_posts' => 'manage_options',
            'edit_published_posts' => 'manage_options',
            'create_posts' => 'manage_options',
        ),
        'menu_icon' => 'dashicons-building',
        'has_archive' => true,
        'rewrite' => array(
            'slug' => 'projects'
        ),
        'supports' => array(
            'title',
            'editor',
            'thumbnail',
            'excerpt',
            'custom-fields'
        ),
        'show_in_rest' => true
    ));
}
add_action('init', 'zigurat_register_project_post_type');
function zigurat_register_article_post_type()
{
    $labels = array(
        'name'               => 'مطالب',
        'singular_name'      => 'مطلب',
        'add_new'            => 'افزودن مطلب',
        'add_new_item'       => 'افزودن مطلب جدید',
        'edit_item'          => 'ویرایش مطلب',
        'new_item'           => 'مطلب جدید',
        'view_item'          => 'مشاهده مطلب',
        'search_items'       => 'جستجوی مطالب',
        'not_found'          => 'مطلبی پیدا نشد',
        'menu_name'          => 'مطالب',
    );
    register_post_type('article', array(
        'labels' => $labels,
        'public' => true,
        'menu_icon' => 'dashicons-media-document',
        'has_archive' => true,
        'rewrite' => array(
            'slug' => 'articles'
        ),
        'supports' => array(
            'title',
            'editor',
            'thumbnail',
            'excerpt',
            'custom-fields'
        ),
        'show_in_rest' => true
    ));
}
add_action(
    'init',
    'zigurat_register_article_post_type'
);
function zigurat_register_brand_post_type()
{
    $labels = array(
        'name'               => 'برندها',
        'singular_name'      => 'برند',
        'add_new'            => 'افزودن برند',
        'add_new_item'       => 'افزودن برند جدید',
        'edit_item'          => 'ویرایش برند',
        'new_item'           => 'برند جدید',
        'view_item'          => 'مشاهده برند',
        'search_items'       => 'جستجوی برند',
        'not_found'          => 'برندی پیدا نشد',
        'menu_name'          => 'برندها',
    );
    register_post_type(
        'brand',
        array(
            'labels' => $labels,
            'public' => false,
            'publicly_queryable' => false,
            'exclude_from_search' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_icon' => 'dashicons-groups',
            'has_archive' => false,
            'rewrite' => false,
            'supports' => array(
                'title',
                'thumbnail',
                'page-attributes'
            ),
            'show_in_rest' => true
        )
    );
}
add_action(
    'init',
    'zigurat_register_brand_post_type'
);

function zigurat_register_service_post_type()
{
    $labels = array(
        'name'          => 'خدمات',
        'singular_name' => 'خدمت',
        'add_new'       => 'افزودن خدمت',
        'add_new_item'  => 'افزودن خدمت جدید',
        'edit_item'     => 'ویرایش خدمت',
        'new_item'      => 'خدمت جدید',
        'view_item'     => 'مشاهده خدمت',
        'search_items'  => 'جست‌وجوی خدمات',
        'not_found'     => 'خدمتی پیدا نشد',
        'menu_name'     => 'خدمات',
    );

    register_post_type('service', array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => false,
        'show_in_menu'       => true,
        'menu_icon'          => 'dashicons-screenoptions',
        'has_archive'        => false,
        'rewrite'            => false,
        'supports'           => array(
            'title',
            'editor',
            'thumbnail',
            'excerpt',
            'page-attributes'
        ),
        'show_in_rest'       => true,
    ));
}
add_action('init', 'zigurat_register_service_post_type');
