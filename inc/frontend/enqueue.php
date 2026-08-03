<?php
if (!defined('ABSPATH')) {
    exit;
}
function zigurat_enqueue_assets()
{
    $theme_uri  = get_template_directory_uri();
    $theme_path = get_template_directory();
    /*
    |--------------------------------------------------------------------------
    | CSS ها
    |--------------------------------------------------------------------------
    */
    $css_folder = $theme_path . '/assets/css/';
    $css_uri    = $theme_uri . '/assets/css/';
    if (is_dir($css_folder)) {
        $css_files = glob($css_folder . '*.css');
        foreach ($css_files as $css_file) {
            $filename = basename($css_file, '.css');
            wp_enqueue_style(
                'zigurat-' . $filename,
                $css_uri . $filename . '.css',
                array(),
                filemtime($css_file)
            );
        }
    }
    /*
    |--------------------------------------------------------------------------
    | JS ها
    |--------------------------------------------------------------------------
    */
    $js_folder = $theme_path . '/assets/js/';
    $js_uri    = $theme_uri . '/assets/js/';
    if (is_dir($js_folder)) {
        $js_files = glob($js_folder . '*.js');
        foreach ($js_files as $js_file) {
            $filename = basename($js_file, '.js');
            wp_enqueue_script(
                'zigurat-' . $filename,
                $js_uri . $filename . '.js',
                array('jquery'),
                filemtime($js_file),
                true
            );
        }
    }
    /*
    |--------------------------------------------------------------------------
    | GLightbox
    |--------------------------------------------------------------------------
    */
    wp_enqueue_style(
        'glightbox',
        'https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css',
        array(),
        '3.3.1'
    );
    wp_enqueue_script(
        'glightbox',
        'https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js',
        array(),
        '3.3.1',
        true
    );
}
add_action(
    'wp_enqueue_scripts',
    'zigurat_enqueue_assets'
);
