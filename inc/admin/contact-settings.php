<?php
if (!defined('ABSPATH')) {
    exit;
}

function zigurat_register_contact_settings()
{
    register_setting(
        'zigurat_contact_settings',
        'zigurat_contact_details',
        array('sanitize_callback' => 'zigurat_sanitize_contact_details')
    );
}
add_action('admin_init', 'zigurat_register_contact_settings');

function zigurat_sanitize_contact_details($details)
{
    return array(
        'phone'   => sanitize_text_field($details['phone'] ?? ''),
        'email'   => sanitize_email($details['email'] ?? ''),
        'address' => sanitize_textarea_field($details['address'] ?? ''),
    );
}

function zigurat_add_contact_settings_page()
{
    add_theme_page(
        'اطلاعات تماس',
        'اطلاعات تماس',
        'manage_options',
        'zigurat-contact-settings',
        'zigurat_render_contact_settings_page'
    );
}
add_action('admin_menu', 'zigurat_add_contact_settings_page');

function zigurat_render_contact_settings_page()
{
    $details = zigurat_get_contact_details();
    ?>
    <div class="wrap">
        <h1>اطلاعات تماس</h1>
        <form action="options.php" method="post">
            <?php settings_fields('zigurat_contact_settings'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="zigurat-contact-phone">شماره تماس</label></th>
                    <td><input class="regular-text" id="zigurat-contact-phone" name="zigurat_contact_details[phone]" type="text" value="<?php echo esc_attr($details['phone']); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="zigurat-contact-email">ایمیل</label></th>
                    <td><input class="regular-text" id="zigurat-contact-email" name="zigurat_contact_details[email]" type="email" value="<?php echo esc_attr($details['email']); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="zigurat-contact-address">نشانی</label></th>
                    <td><textarea class="large-text" id="zigurat-contact-address" name="zigurat_contact_details[address]" rows="3"><?php echo esc_textarea($details['address']); ?></textarea></td>
                </tr>
            </table>
            <?php submit_button('ذخیره تغییرات'); ?>
        </form>
    </div>
    <?php
}
