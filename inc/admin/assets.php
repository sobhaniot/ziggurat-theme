<?php
function zigurat_admin_assets($hook)
{
    global $post_type;
    if (!in_array($post_type, array('project', 'article', 'page'), true)) {
        return;
    }
    // CSS پنل
    wp_enqueue_style(
        'zigurat-admin',
        get_template_directory_uri() . '/assets/css/admin.css',
        array(),
        filemtime(get_template_directory() . '/assets/css/admin.css')
    );
    wp_enqueue_script(
        'zigurat-project-seo',
        get_template_directory_uri() . '/assets/js/project-seo.js',
        array('wp-data'),
        filemtime(get_template_directory() . '/assets/js/project-seo.js'),
        true
    );
    if ($post_type === 'project') {
        // بارگذاری Media Library و مدیریت گالری فقط برای پروژه‌ها
        wp_enqueue_media();
        wp_enqueue_script(
            'zigurat-project-gallery',
            get_template_directory_uri() . '/assets/js/project-gallery.js',
            array('jquery'),
            filemtime(get_template_directory() . '/assets/js/project-gallery.js'),
            true
        );
    }
}
add_action('admin_enqueue_scripts', 'zigurat_admin_assets');
