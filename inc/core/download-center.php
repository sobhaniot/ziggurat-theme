<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * مدل محتوای مرکز دانلود.
 */
function zigurat_register_download_center()
{
    register_post_type('zig_download', array(
        'labels' => array(
            'name'               => 'مرکز دانلود',
            'singular_name'      => 'فایل دانلودی',
            'add_new'            => 'افزودن فایل',
            'add_new_item'       => 'افزودن فایل جدید',
            'edit_item'          => 'ویرایش فایل',
            'new_item'           => 'فایل جدید',
            'view_item'          => 'مشاهده فایل',
            'search_items'       => 'جستجو در مرکز دانلود',
            'not_found'          => 'فایلی پیدا نشد',
            'not_found_in_trash' => 'فایلی در زباله‌دان نیست',
            'menu_name'          => 'مرکز دانلود',
        ),
        'public'       => true,
        'has_archive'  => 'downloads',
        'rewrite'      => array('slug' => 'downloads', 'with_front' => false),
        'menu_icon'    => 'dashicons-download',
        'menu_position'=> 24,
        'show_in_rest' => true,
        'supports'     => array('title', 'editor', 'excerpt', 'thumbnail', 'revisions'),
    ));

    zigurat_register_taxonomy_helper('download_type', 'نوع فایل', 'انواع فایل', 'download-type', array('zig_download'));
    zigurat_register_taxonomy_helper('download_category', 'دسته دانلود', 'دسته‌های دانلود', 'download-category', array('zig_download'));
    zigurat_register_taxonomy_helper('sketchup_version', 'نسخه SketchUp', 'نسخه‌های SketchUp', 'sketchup-version', array('zig_download'), false);
    zigurat_register_taxonomy_helper('download_os', 'سیستم‌عامل', 'سیستم‌عامل‌ها', 'download-os', array('zig_download'), false);
}
add_action('init', 'zigurat_register_download_center', 20);

/** ایجاد داده‌های پایه و بازسازی rewrite فقط یک بار. */
function zigurat_install_download_center()
{
    $version = '1';
    if (get_option('zigurat_download_center_version') === $version) {
        return;
    }

    foreach (array('پلاگین اسکچ‌آپ', 'نرم‌افزار', 'مستندات', 'قالب و فایل آموزشی') as $term) {
        if (!term_exists($term, 'download_type')) {
            wp_insert_term($term, 'download_type');
        }
    }
    foreach (array('ویندوز', 'مک', 'چندسیستمی') as $term) {
        if (!term_exists($term, 'download_os')) {
            wp_insert_term($term, 'download_os');
        }
    }

    flush_rewrite_rules(false);
    update_option('zigurat_download_center_version', $version, false);
}
add_action('init', 'zigurat_install_download_center', 90);

function zigurat_download_meta($post_id, $key, $default = '')
{
    $value = get_post_meta($post_id, '_zig_download_' . $key, true);
    return $value === '' ? $default : $value;
}

function zigurat_download_count($post_id)
{
    return max(0, (int) zigurat_download_meta($post_id, 'count', 0));
}

function zigurat_download_source_url($post_id)
{
    $attachment_id = absint(zigurat_download_meta($post_id, 'file_id'));
    if ($attachment_id) {
        $url = wp_get_attachment_url($attachment_id);
        if ($url) {
            return $url;
        }
    }
    $url = esc_url_raw((string) zigurat_download_meta($post_id, 'external_url'));
    return $url && wp_http_validate_url($url) ? $url : '';
}

function zigurat_download_token($post_id)
{
    return substr(hash_hmac('sha256', 'zigurat-download-' . absint($post_id), wp_salt('nonce')), 0, 24);
}

function zigurat_download_action_url($post_id)
{
    return add_query_arg(array(
        'zigurat_resource_download' => absint($post_id),
        'token' => zigurat_download_token($post_id),
    ), home_url('/'));
}

/** دانلود یا انتقال به منبع رسمی و افزایش شمارنده. */
function zigurat_handle_download_request()
{
    if (empty($_GET['zigurat_resource_download']) || empty($_GET['token'])) {
        return;
    }
    $post_id = absint($_GET['zigurat_resource_download']);
    $token = sanitize_text_field(wp_unslash($_GET['token']));
    if (!$post_id || !hash_equals(zigurat_download_token($post_id), $token) || get_post_status($post_id) !== 'publish' || get_post_type($post_id) !== 'zig_download') {
        wp_die('پیوند دانلود معتبر نیست.', 'دانلود نامعتبر', array('response' => 403));
    }
    $url = zigurat_download_source_url($post_id);
    if (!$url) {
        wp_die('فایل یا پیوند دانلود هنوز تنظیم نشده است.', 'فایل موجود نیست', array('response' => 404));
    }

    global $wpdb;
    $meta_key = '_zig_download_count';
    if (!metadata_exists('post', $post_id, $meta_key)) {
        add_post_meta($post_id, $meta_key, 0, true);
    }
    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->postmeta} SET meta_value = CAST(meta_value AS UNSIGNED) + 1 WHERE post_id = %d AND meta_key = %s",
        $post_id,
        $meta_key
    ));
    clean_post_cache($post_id);
    nocache_headers();
    wp_redirect($url, 302, 'Zigurat Download Center');
    exit;
}
add_action('template_redirect', 'zigurat_handle_download_request', 0);

/** فیلتر و مرتب‌سازی آرشیو مرکز دانلود. */
function zigurat_filter_download_archive($query)
{
    if (is_admin() || !$query->is_main_query() || !$query->is_post_type_archive('zig_download')) {
        return;
    }
    $query->set('posts_per_page', 12);
    $tax_query = array();
    foreach (array('download_type', 'download_category', 'sketchup_version', 'download_os') as $taxonomy) {
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
    if (!empty($_GET['download_search']) && is_string($_GET['download_search'])) {
        $query->set('s', sanitize_text_field(wp_unslash($_GET['download_search'])));
    }
    $sort = isset($_GET['download_sort']) && is_string($_GET['download_sort'])
        ? sanitize_key(wp_unslash($_GET['download_sort']))
        : 'latest';
    if ($sort === 'popular') {
        $query->set('meta_key', '_zig_download_count');
        $query->set('orderby', array('meta_value_num' => 'DESC', 'date' => 'DESC'));
    } else {
        $query->set('orderby', 'date');
        $query->set('order', 'DESC');
    }
}
add_action('pre_get_posts', 'zigurat_filter_download_archive');

function zigurat_download_term_names($post_id, $taxonomy)
{
    $terms = get_the_terms($post_id, $taxonomy);
    if (!$terms || is_wp_error($terms)) {
        return array();
    }
    return wp_list_pluck($terms, 'name');
}

function zigurat_download_primary_type($post_id)
{
    $names = zigurat_download_term_names($post_id, 'download_type');
    return $names ? reset($names) : 'فایل دانلودی';
}

