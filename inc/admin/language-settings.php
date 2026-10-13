<?php
if (!defined('ABSPATH')) {
    exit;
}

function zigurat_sanitize_multilingual_enabled($value)
{
    return (string) $value === '1' ? '1' : '0';
}

function zigurat_register_language_settings()
{
    register_setting(
        'zigurat_language_settings',
        'zigurat_multilingual_enabled',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'zigurat_sanitize_multilingual_enabled',
            'default'           => '1',
        )
    );
}
add_action('admin_init', 'zigurat_register_language_settings');

function zigurat_add_language_settings_page()
{
    add_theme_page(
        'زبان‌های سایت',
        'زبان‌های سایت',
        'manage_options',
        'zigurat-language-settings',
        'zigurat_render_language_settings_page'
    );
}
add_action('admin_menu', 'zigurat_add_language_settings_page');

function zigurat_render_language_settings_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }
    $enabled = function_exists('zigurat_multilingual_enabled') && zigurat_multilingual_enabled();
    ?>
    <div class="wrap">
        <h1>زبان‌های سایت</h1>
        <p class="description">نمایش نسخه‌های انگلیسی، عربی و ترکی سایت را از این بخش مدیریت کنید.</p>

        <form action="options.php" method="post">
            <?php settings_fields('zigurat_language_settings'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">قابلیت چندزبانه</th>
                    <td>
                        <input type="hidden" name="zigurat_multilingual_enabled" value="0">
                        <label for="zigurat-multilingual-enabled">
                            <input id="zigurat-multilingual-enabled" type="checkbox" name="zigurat_multilingual_enabled" value="1" <?php checked($enabled); ?>>
                            فعال‌کردن زبان‌های انگلیسی، عربی و ترکی
                        </label>
                        <p class="description">در حالت غیرفعال، سایت فقط فارسی است و انتخابگر زبان و لینک‌های نسخه‌های دیگر نمایش داده نمی‌شوند.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('ذخیره تنظیمات زبان'); ?>
        </form>
    </div>
    <?php
}
