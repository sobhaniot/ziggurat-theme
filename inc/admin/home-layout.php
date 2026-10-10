<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sections available on the public home page.
 * Array order is also the default order used before the setting is saved.
 */
function zigurat_home_section_definitions()
{
    return array(
        'intro' => array(
            'label'       => 'معرفی',
            'description' => 'تصویر و متن ابتدایی صفحه اصلی',
            'template'    => 'template-parts/hero',
        ),
        'services' => array(
            'label'       => 'خدمات',
            'description' => 'معرفی خدمات زیگورات',
            'template'    => 'template-parts/services',
        ),
        'projects' => array(
            'label'       => 'آخرین پروژه‌ها',
            'description' => 'جدیدترین نمونه‌کارهای ثبت‌شده',
            'template'    => 'template-parts/projects',
        ),
        'about' => array(
            'label'       => 'درباره زیگورات',
            'description' => 'معرفی مجموعه و آمار فعالیت',
            'template'    => 'template-parts/about',
        ),
        'activity' => array(
            'label'       => 'گستره فعالیت',
            'description' => 'نقشه پروژه‌های اجراشده در ایران',
            'template'    => 'template-parts/iran-project-map',
        ),
        'articles' => array(
            'label'       => 'مطالب',
            'description' => 'آخرین مطالب منتشرشده',
            'template'    => 'template-parts/latest-posts',
        ),
        'clients' => array(
            'label'       => 'مشتری‌ها',
            'description' => 'نشان مشتریان زیگورات',
            'template'    => 'template-parts/clients',
        ),
    );
}

function zigurat_sanitize_home_section_order($order)
{
    $valid_keys = array_keys(zigurat_home_section_definitions());
    $order = is_array($order) ? array_map('sanitize_key', $order) : array();
    $order = array_values(array_unique(array_intersect($order, $valid_keys)));

    foreach ($valid_keys as $key) {
        if (!in_array($key, $order, true)) {
            $order[] = $key;
        }
    }

    return $order;
}

function zigurat_get_home_section_order()
{
    return zigurat_sanitize_home_section_order(get_option('zigurat_home_section_order', array()));
}

function zigurat_register_home_layout_setting()
{
    register_setting(
        'zigurat_home_layout_settings',
        'zigurat_home_section_order',
        array(
            'type'              => 'array',
            'sanitize_callback' => 'zigurat_sanitize_home_section_order',
            'default'           => array_keys(zigurat_home_section_definitions()),
        )
    );
}
add_action('admin_init', 'zigurat_register_home_layout_setting');

function zigurat_add_home_layout_settings_page()
{
    add_theme_page(
        'چیدمان صفحه اصلی',
        'چیدمان صفحه اصلی',
        'manage_options',
        'zigurat-home-layout',
        'zigurat_render_home_layout_settings_page'
    );
}
add_action('admin_menu', 'zigurat_add_home_layout_settings_page');

function zigurat_enqueue_home_layout_admin_assets($hook)
{
    if ($hook !== 'appearance_page_zigurat-home-layout') {
        return;
    }

    wp_enqueue_script('jquery-ui-sortable');
    wp_enqueue_style(
        'zigurat-home-layout-admin',
        get_theme_file_uri('/assets/css/home-layout-admin.css'),
        array(),
        (string) filemtime(get_theme_file_path('/assets/css/home-layout-admin.css'))
    );
    wp_enqueue_script(
        'zigurat-home-layout-admin',
        get_theme_file_uri('/assets/js/home-layout-admin.js'),
        array('jquery', 'jquery-ui-sortable'),
        (string) filemtime(get_theme_file_path('/assets/js/home-layout-admin.js')),
        true
    );
}
add_action('admin_enqueue_scripts', 'zigurat_enqueue_home_layout_admin_assets');

function zigurat_render_home_layout_settings_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $sections = zigurat_home_section_definitions();
    $order = zigurat_get_home_section_order();
    ?>
    <div class="wrap zigurat-home-layout-admin">
        <h1>چیدمان صفحه اصلی</h1>
        <p class="description">بخش‌ها را با دستگیره جابه‌جا کنید یا از دکمه‌های بالا و پایین استفاده کنید؛ سپس تغییرات را ذخیره کنید.</p>

        <form action="options.php" method="post">
            <?php settings_fields('zigurat_home_layout_settings'); ?>
            <ol class="zigurat-home-layout-list" data-home-layout-list>
                <?php foreach ($order as $key) : ?>
                    <?php $section = $sections[$key]; ?>
                    <li data-home-section="<?php echo esc_attr($key); ?>">
                        <span class="zigurat-home-layout-handle" aria-hidden="true">⋮⋮</span>
                        <span class="zigurat-home-layout-number" data-home-layout-number></span>
                        <span class="zigurat-home-layout-copy">
                            <strong><?php echo esc_html($section['label']); ?></strong>
                            <small><?php echo esc_html($section['description']); ?></small>
                        </span>
                        <span class="zigurat-home-layout-actions">
                            <button class="button" type="button" data-home-layout-up aria-label="انتقال <?php echo esc_attr($section['label']); ?> به بالا">↑</button>
                            <button class="button" type="button" data-home-layout-down aria-label="انتقال <?php echo esc_attr($section['label']); ?> به پایین">↓</button>
                        </span>
                        <input type="hidden" name="zigurat_home_section_order[]" value="<?php echo esc_attr($key); ?>">
                    </li>
                <?php endforeach; ?>
            </ol>
            <?php submit_button('ذخیره ترتیب صفحه اصلی'); ?>
        </form>
    </div>
    <?php
}
