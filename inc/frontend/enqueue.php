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
    zigurat_enqueue_theme_script('form-inputs');

    if (function_exists('zigurat_should_show_site_intro') && zigurat_should_show_site_intro()) {
        zigurat_enqueue_theme_style('site-intro', array());
        zigurat_enqueue_theme_script('site-intro');
    }

    if (is_front_page()) {
        zigurat_enqueue_theme_script('about-counter');
        zigurat_enqueue_theme_script('iran-project-map');
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
    }
    if (is_page('portfolio') || is_page_template('page-portfolio.php')) {
        zigurat_enqueue_theme_style('portfolio');
        $portfolio_id = get_queried_object_id();
        $portfolio_pdf_id = $portfolio_id ? (int) get_post_meta($portfolio_id, '_zigurat_portfolio_pdf_id', true) : 0;
        if ($portfolio_pdf_id && get_post_mime_type($portfolio_pdf_id) === 'application/pdf') {
            $page_flip_path = get_template_directory() . '/assets/vendor/page-flip/page-flip.browser.js';
            wp_enqueue_script(
                'zigurat-page-flip',
                get_template_directory_uri() . '/assets/vendor/page-flip/page-flip.browser.js',
                array(),
                is_file($page_flip_path) ? filemtime($page_flip_path) : null,
                true
            );
            zigurat_enqueue_theme_script('portfolio-flipbook', array('zigurat-page-flip'));
            wp_localize_script('zigurat-portfolio-flipbook', 'ziguratPortfolioConfig', array(
                'pdfModuleUrl' => wp_make_link_relative(get_template_directory_uri() . '/assets/vendor/pdfjs/pdf.min.mjs'),
                'workerUrl'    => wp_make_link_relative(get_template_directory_uri() . '/assets/vendor/pdfjs/pdf.worker.min.mjs'),
                'labels'       => array(
                    'loading' => 'در حال آماده‌سازی کاتالوگ…',
                    'error'   => 'نمایش کاتالوگ با مشکل روبه‌رو شد. می‌توانید فایل PDF را مستقیماً دانلود کنید.',
                ),
            ));
        }
    }
    if (is_page('login') || is_page_template('page-login.php')) {
        zigurat_enqueue_theme_style('manager');
        zigurat_enqueue_theme_style('invoice');
        zigurat_enqueue_theme_script('manager-login');
        zigurat_enqueue_theme_script('pricing-calculator');
        zigurat_enqueue_theme_script('invoice-calculator');
        zigurat_enqueue_theme_script('letter-calculator', array('zigurat-pricing-calculator'));
        $manager_section = isset($_GET['manager-section']) ? sanitize_key(wp_unslash($_GET['manager-section'])) : '';
        if ($manager_section === 'applications' && zigurat_is_manager()) {
            zigurat_enqueue_theme_script('manager-partner-map');
        }
        if ($manager_section === 'letters' && zigurat_is_manager()) {
            wp_enqueue_editor();
            zigurat_enqueue_theme_style('letters');
            zigurat_enqueue_theme_script('letters');
            wp_localize_script('zigurat-letters', 'ziguratLettersConfig', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'stampLayoutNonce' => wp_create_nonce('zigurat_letter_stamp_layout'),
            ));
        }
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

    if (is_post_type_archive('zig_download') || is_singular('zig_download')) {
        zigurat_enqueue_theme_style('downloads');
        if (is_post_type_archive('zig_download')) {
            zigurat_enqueue_theme_script('archive-filters');
        }
    }

    if (is_singular(array('article', 'zig_download'))) {
        zigurat_enqueue_theme_style('comments');
        if (comments_open() && get_option('thread_comments')) {
            wp_enqueue_script('comment-reply');
        }
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
        zigurat_enqueue_theme_script('invoice', array('zigurat-form-inputs'));
        zigurat_enqueue_theme_script('invoice-calculator', array('zigurat-invoice'));
        zigurat_enqueue_theme_script('panel-ajax', array('zigurat-invoice', 'zigurat-invoice-calculator'));
        wp_localize_script('zigurat-invoice', 'ziguratInvoiceConfig', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'customerNonce' => wp_create_nonce('zigurat_invoice_customer_lookup'),
            'stampLayoutNonce' => wp_create_nonce('zigurat_invoice_stamp_layout'),
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

    if (function_exists('zigurat_should_show_live_views_counter') && zigurat_should_show_live_views_counter()) {
        zigurat_enqueue_theme_style('manager-live', array());
        zigurat_enqueue_theme_script('manager-views');
        $views_websocket_url = defined('ZIGURAT_VIEWS_WEBSOCKET_URL')
            ? (string) ZIGURAT_VIEWS_WEBSOCKET_URL
            : (string) apply_filters('zigurat_views_websocket_url', '');
        if ($views_websocket_url !== '' && !preg_match('#^wss?://#i', $views_websocket_url)) {
            $views_websocket_url = '';
        }
        wp_localize_script('zigurat-manager-views', 'ziguratManagerViewsConfig', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zigurat_live_views'),
            'pollInterval' => 30000,
            'websocketUrl' => $views_websocket_url,
        ));
    }

    /* Keep input/select geometry consistent after all page-specific styles. */
    zigurat_enqueue_theme_style('form-controls', array());
}
add_action('wp_enqueue_scripts', 'zigurat_enqueue_assets');
