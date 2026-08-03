<?php
function zigurat_admin_assets($hook)
{
    global $post_type;
    if ($post_type !== 'project') {
        return;
    }
    // بارگذاری Media Library
    wp_enqueue_media();
    // CSS پنل
    wp_enqueue_style(
        'zigurat-admin',
        get_template_directory_uri() . '/assets/css/admin.css',
        array(),
        '1.0'
    );
    // JS گالری
    wp_enqueue_script(
        'zigurat-project-gallery',
        get_template_directory_uri() . '/assets/js/project-gallery.js',
        array('jquery'),
        '1.0',
        true
    );
}
add_action('admin_enqueue_scripts', 'zigurat_admin_assets');
