<?php
if (!defined('ABSPATH')) {
    exit;
}

function zigurat_enqueue_theme_style($name, $dependencies = array('zigurat-main'))
{
    $path = get_template_directory() . '/assets/css/' . $name . '.css';
    if (is_file($path)) {
        wp_enqueue_style('zigurat-' . $name, get_template_directory_uri() . '/assets/css/' . $name . '.css', $dependencies, filemtime($path));
    }
}

function zigurat_enqueue_theme_script($name, $dependencies = array())
{
    $path = get_template_directory() . '/assets/js/' . $name . '.js';
    if (is_file($path)) {
        wp_enqueue_script('zigurat-' . $name, get_template_directory_uri() . '/assets/js/' . $name . '.js', $dependencies, filemtime($path), true);
        wp_script_add_data('zigurat-' . $name, 'strategy', 'defer');
    }
}

function zigurat_enqueue_assets()
{
    if (is_front_page()) {
        zigurat_enqueue_theme_style('front-page', array());
    } else {
        zigurat_enqueue_theme_style('main', array());
        zigurat_enqueue_theme_style('header');
        zigurat_enqueue_theme_style('footer');
        zigurat_enqueue_theme_style('responsive');
    }
    zigurat_enqueue_theme_script('header');

    if (is_front_page()) {
        zigurat_enqueue_theme_script('about-counter');
    }

    if (is_page('about') || is_page_template('page-about.php')) {
        zigurat_enqueue_theme_style('page-about');
        zigurat_enqueue_theme_script('about-counter');
    }
    if (is_page('services') || is_page_template('page-services.php')) {
        zigurat_enqueue_theme_style('services');
    }
    if (is_page('contact') || is_page_template('page-contact.php')) {
        zigurat_enqueue_theme_style('contact-page');
    }
    if (is_page('cooperation') || is_page_template('page-cooperation.php')) {
        zigurat_enqueue_theme_style('cooperation');
        zigurat_enqueue_theme_script('cooperation');
    }
    if (is_page('login') || is_page_template('page-login.php')) {
        zigurat_enqueue_theme_style('manager');
        zigurat_enqueue_theme_script('manager-login');
        zigurat_enqueue_theme_script('pricing-calculator');
    }

    if (is_post_type_archive('article')) {
        zigurat_enqueue_theme_style('archive-article');
        zigurat_enqueue_theme_style('article');
    } elseif (is_singular('article')) {
        zigurat_enqueue_theme_style('article');
    }

    if (is_post_type_archive('project')) {
        zigurat_enqueue_theme_style('projects');
        zigurat_enqueue_theme_script('archive-filters');
    } elseif (is_singular('project')) {
        zigurat_enqueue_theme_style('project-single');
        zigurat_enqueue_theme_script('lightbox');
    }

    if (is_tax() || is_search() || is_home() || is_404()) {
        zigurat_enqueue_theme_style('archive-generic');
    }

    $inventory_pages = array('inventory-list', 'inventory-transactions', 'inventory-catalog', 'add-item', 'subtract-item');
    if (is_page($inventory_pages)) {
        zigurat_enqueue_theme_style('inventory');
        zigurat_enqueue_theme_style('panel-ajax');
        zigurat_enqueue_theme_script('inventory-catalog');
        zigurat_enqueue_theme_script('panel-ajax', array('zigurat-inventory-catalog'));
        wp_localize_script('zigurat-panel-ajax', 'ziguratPanelAjaxConfig', array(
            'paths' => array_map(static function ($slug) {
                return (string) wp_parse_url(zigurat_inventory_page_url($slug), PHP_URL_PATH);
            }, $inventory_pages),
        ));
    }
    if (is_page('invoices') || is_page_template('page-invoices.php')) {
        zigurat_enqueue_theme_style('invoice');
        zigurat_enqueue_theme_style('panel-ajax');
        zigurat_enqueue_theme_script('invoice');
        zigurat_enqueue_theme_script('invoice-calculator', array('zigurat-invoice'));
        zigurat_enqueue_theme_script('panel-ajax', array('zigurat-invoice', 'zigurat-invoice-calculator'));
        wp_localize_script('zigurat-invoice', 'ziguratInvoiceConfig', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'customerNonce' => wp_create_nonce('zigurat_invoice_customer_lookup'),
            'managerName' => (static function () {
                $user = wp_get_current_user();
                $name = trim((string) $user->display_name);
                return $name !== '' ? $name : (string) $user->user_login;
            })(),
        ));
        wp_localize_script('zigurat-panel-ajax', 'ziguratPanelAjaxConfig', array(
            'paths' => array((string) wp_parse_url(zigurat_invoice_page_url(), PHP_URL_PATH)),
        ));
    }
}
add_action('wp_enqueue_scripts', 'zigurat_enqueue_assets');
