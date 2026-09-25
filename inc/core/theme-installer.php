<?php
if (!defined('ABSPATH')) {
    exit;
}
/**
 * اجرای نصب فقط یک بار بعد از فعال شدن قالب
 */
add_action('after_switch_theme', 'ziggurat_run_theme_installer');
function ziggurat_run_theme_installer()
{
    ziggurat_create_default_pages();
    ziggurat_create_default_services();
    ziggurat_create_main_menu();
    zigurat_ensure_cooperation_page_and_menu(true);
    zigurat_sync_main_navigation(true);
    ziggurat_set_front_page();
}

function ziggurat_create_default_services()
{
    $services = array(
        'signage' => array(
            'title'       => 'تابلو سازی',
            'description' => 'طراحی و اجرای انواع تابلوهای تبلیغاتی، چلنیوم، حروف برجسته و نورپردازی.',
        ),
        'commercial-decoration' => array(
            'title'       => 'دکوراسیون تجاری',
            'description' => 'طراحی و اجرای دکور فروشگاه‌ها، فضاهای اداری و نمایشگاهی.',
        ),
        'composite-facade' => array(
            'title'       => 'کامپوزیت و نما',
            'description' => 'اجرای نمای کامپوزیت، سازه‌های مدرن و ترکیب متریال.',
        ),
        'lighting' => array(
            'title'       => 'نورپردازی',
            'description' => 'طراحی سیستم نور برای ایجاد جلوه بصری بهتر.',
        ),
        'metal-structures' => array(
            'title'       => 'سازه فلزی',
            'description' => 'ساخت سازه‌های فلزی، استندها و المان‌های تبلیغاتی.',
        ),
        'design-execution' => array(
            'title'       => 'طراحی و اجرا',
            'description' => 'از ایده اولیه تا اجرای کامل پروژه در کنار شما هستیم.',
        ),
    );

    $order = 1;
    foreach ($services as $slug => $service) {
        if (get_page_by_path($slug, OBJECT, 'service')) {
            $order++;
            continue;
        }

        wp_insert_post(array(
            'post_type'    => 'service',
            'post_status'  => 'publish',
            'post_title'   => $service['title'],
            'post_name'    => $slug,
            'post_content' => $service['description'],
            'menu_order'   => $order,
        ));
        $order++;
    }
}
function ziggurat_create_default_pages()
{
    $pages = array(
        'home' => array(
            'title'    => 'خانه',
            'template' => 'default'
        ),
        'about' => array(
            'title'    => 'درباره زیگورات',
            'template' => 'page-about.php'
        ),
        'services' => array(
            'title'    => 'خدمات',
            'template' => 'page-services.php'
        ),
        'contact' => array(
            'title'    => 'تماس با ما',
            'template' => 'page-contact.php'
        ),
        'cooperation' => array(
            'title'    => 'همکاری با ما',
            'template' => 'page-cooperation.php'
        ),
        'portfolio' => array(
            'title'    => 'پورتفولیو',
            'template' => 'page-portfolio.php'
        ),
        'login' => array(
            'title'    => 'ورود',
            'template' => 'page-login.php'
        ),
        'inventory-list' => array(
            'title'    => 'لیست انبار',
            'template' => 'page-inventory-list.php'
        ),
        'inventory-transactions' => array(
            'title'    => 'گردش انبار',
            'template' => 'page-inventory-transactions.php'
        ),
        'add-item' => array(
            'title'    => 'افزودن کالا',
            'template' => 'page-add-item.php'
        ),
        'subtract-item' => array(
            'title'    => 'کسر کالا',
            'template' => 'page-subtract-item.php'
        ),
        'inventory-catalog' => array(
            'title'    => 'تعریف دسته و کالا',
            'template' => 'page-inventory-catalog.php'
        ),
        'invoices' => array(
            'title'    => 'بخش فاکتور',
            'template' => 'page-invoices.php'
        )
    );
    foreach ($pages as $slug => $page) {
        if (get_page_by_path($slug))
            continue;
        $id = wp_insert_post(array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_title' => $page['title'],
            'post_name' => $slug
        ));
        if ($id && $page['template'] != 'default') {
            update_post_meta(
                $id,
                '_wp_page_template',
                $page['template']
            );
        }
    }
}
function ziggurat_set_front_page()
{
    $home = get_page_by_path('home');
    if (!$home)
        return;
    update_option('show_on_front', 'page');
    update_option(
        'page_on_front',
        $home->ID
    );
}
function ziggurat_create_main_menu()
{
    $menu_name = 'Main Menu';
    $menu = wp_get_nav_menu_object($menu_name);
    if (!$menu) {
        $menu_id = wp_create_nav_menu($menu_name);
    } else {
        $menu_id = $menu->term_id;
    }
    $items = array(
        array(
            'title' => 'خانه',
            'object' => 'page',
            'slug' => 'home'
        ),
        array(
            'title' => 'پروژه‌ها',
            'url' => home_url('/projects/')
        ),
        array(
            'title' => 'کاتالوگ دیجیتال',
            'object' => 'page',
            'slug' => 'portfolio'
        ),
        array(
            'title' => 'مطالب',
            'url' => get_post_type_archive_link('article')
        ),
        array(
            'title' => 'همکاری با ما',
            'object' => 'page',
            'slug' => 'cooperation'
        ),
        array(
            'title' => 'تماس با ما',
            'object' => 'page',
            'slug' => 'contact'
        )
    );

    $existing_items = wp_get_nav_menu_items($menu_id);
    if ($existing_items) {
        foreach ($existing_items as $existing_item) {
            wp_delete_post($existing_item->ID, true);
        }
    }

    foreach ($items as $item) {
        $menu_item = array(
            'menu-item-title' => $item['title'],
            'menu-item-status' => 'publish'
        );

        if (isset($item['slug'])) {
            $page = get_page_by_path($item['slug']);
            if (!$page) {
                continue;
            }

            $menu_item['menu-item-object'] = 'page';
            $menu_item['menu-item-object-id'] = $page->ID;
            $menu_item['menu-item-type'] = 'post_type';
        } else {
            $menu_item['menu-item-type'] = 'custom';
            $menu_item['menu-item-url'] = $item['url'];
        }

        wp_update_nav_menu_item($menu_id, 0, $menu_item);
    }
    $locations = get_theme_mod('nav_menu_locations');
    $locations['main-menu'] = $menu_id;
    set_theme_mod(
        'nav_menu_locations',
        $locations
    );
}

/**
 * Keep the public portfolio landing page available on existing installations.
 * Existing editor content is never overwritten.
 */
function zigurat_ensure_portfolio_page()
{
    $required_version = '3';
    if ((string) get_option('zigurat_portfolio_page_version', '') === $required_version) {
        return;
    }

    $page = get_page_by_path('portfolio', OBJECT, 'page');
    if (!$page) {
        $content = '<!-- wp:paragraph {"fontSize":"medium"} -->'
            . '<p class="has-medium-font-size">نسخه دیجیتال کاتالوگ زیگورات به‌زودی در این بخش قرار می‌گیرد.</p>'
            . '<!-- /wp:paragraph -->';

        $page_id = wp_insert_post(array(
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => 'پورتفولیو',
            'post_name'    => 'portfolio',
            'post_content' => $content,
        ));
    } else {
        $page_id = (int) $page->ID;
        if ($page->post_title !== 'پورتفولیو') {
            wp_update_post(array(
                'ID'         => $page_id,
                'post_title' => 'پورتفولیو',
            ));
        }
        if (trim((string) $page->post_content) === '') {
            wp_update_post(array(
                'ID'           => $page_id,
                'post_content' => '<!-- wp:paragraph {"fontSize":"medium"} --><p class="has-medium-font-size">نسخه دیجیتال کاتالوگ زیگورات به‌زودی در این بخش قرار می‌گیرد.</p><!-- /wp:paragraph -->',
            ));
        } elseif (strpos((string) $page->post_content, 'برای افزودن کاتالوگ، از ویرایشگر وردپرس بلوک') !== false) {
            wp_update_post(array(
                'ID'           => $page_id,
                'post_content' => '<!-- wp:paragraph {"fontSize":"medium"} --><p class="has-medium-font-size">نسخه دیجیتال کاتالوگ زیگورات به‌زودی در این بخش قرار می‌گیرد.</p><!-- /wp:paragraph -->',
            ));
        }
    }

    if (!empty($page_id) && !is_wp_error($page_id)) {
        update_post_meta((int) $page_id, '_wp_page_template', 'page-portfolio.php');
        update_option('zigurat_portfolio_page_version', $required_version, false);
    }
}
add_action('init', 'zigurat_ensure_portfolio_page', 31);
