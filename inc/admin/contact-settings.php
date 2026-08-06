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
    register_setting(
        'zigurat_contact_settings',
        'zigurat_social_links',
        array('sanitize_callback' => 'zigurat_sanitize_social_links')
    );
}

function zigurat_sanitize_social_links($links)
{
    if (!is_array($links)) {
        return array();
    }
    $clean = array();
    foreach ($links as $link) {
        if (!is_array($link)) {
            continue;
        }
        $url = esc_url_raw($link['url'] ?? '');
        if (!$url) {
            continue;
        }
        $clean[] = array(
            'label'   => sanitize_text_field($link['label'] ?? ''),
            'url'     => $url,
            'icon_id' => absint($link['icon_id'] ?? 0),
        );
    }
    return $clean;
}

function zigurat_contact_settings_assets($hook)
{
    if ($hook !== 'appearance_page_zigurat-contact-settings') {
        return;
    }
    wp_enqueue_media();
}
add_action('admin_enqueue_scripts', 'zigurat_contact_settings_assets');
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
    $social_links = zigurat_get_social_links();
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
            <h2>شبکه‌های اجتماعی</h2>
            <p>هر تعداد شبکه یا حساب که نیاز دارید اضافه کنید؛ برای نمونه می‌توانید دو حساب اینستاگرام جداگانه ثبت کنید.</p>
            <table class="widefat striped" id="zigurat-social-links">
                <thead>
                    <tr>
                        <th>عنوان</th>
                        <th>لینک</th>
                        <th>آیکون انتخابی</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($social_links as $index => $link):
                        $icon_url = !empty($link['icon_id']) ? wp_get_attachment_image_url((int) $link['icon_id'], 'thumbnail') : '';
                    ?>
                        <tr>
                            <td><input class="regular-text" type="text" name="zigurat_social_links[<?php echo (int) $index; ?>][label]" value="<?php echo esc_attr($link['label'] ?? ''); ?>" placeholder="مثلاً اینستاگرام فروش"></td>
                            <td><input class="regular-text" type="url" name="zigurat_social_links[<?php echo (int) $index; ?>][url]" value="<?php echo esc_url($link['url'] ?? ''); ?>" placeholder="https://..."></td>
                            <td>
                                <input class="social-icon-id" type="hidden" name="zigurat_social_links[<?php echo (int) $index; ?>][icon_id]" value="<?php echo absint($link['icon_id'] ?? 0); ?>">
                                <img class="social-icon-preview" src="<?php echo esc_url($icon_url); ?>" alt="" style="<?php echo $icon_url ? '' : 'display:none;'; ?>width:42px;height:42px;object-fit:contain;margin-bottom:6px;">
                                <button class="button select-social-icon" type="button">انتخاب تصویر آیکون</button>
                            </td>
                            <td><button class="button-link-delete remove-social-row" type="button">حذف</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p><button class="button" id="add-social-row" type="button">افزودن شبکه اجتماعی</button></p>
            <?php submit_button('ذخیره تغییرات'); ?>
        </form>
    </div>
    <script type="text/html" id="tmpl-zigurat-social-row">
        <tr>
            <td><input class="regular-text" type="text" name="zigurat_social_links[__INDEX__][label]" placeholder="مثلاً اینستاگرام فروش"></td>
            <td><input class="regular-text" type="url" name="zigurat_social_links[__INDEX__][url]" placeholder="https://..."></td>
            <td>
                <input class="social-icon-id" type="hidden" name="zigurat_social_links[__INDEX__][icon_id]" value="">
                <img class="social-icon-preview" src="" alt="" style="display:none;width:42px;height:42px;object-fit:contain;margin-bottom:6px;">
                <button class="button select-social-icon" type="button">انتخاب تصویر آیکون</button>
            </td>
            <td><button class="button-link-delete remove-social-row" type="button">حذف</button></td>
        </tr>
    </script>
    <script>
    jQuery(function($) {
        var nextIndex = <?php echo count($social_links); ?>;
        $('#add-social-row').on('click', function() {
            var row = $('#tmpl-zigurat-social-row').html().split('__INDEX__').join(nextIndex++);
            $('#zigurat-social-links tbody').append(row);
        });
        $(document).on('click', '.remove-social-row', function() {
            $(this).closest('tr').remove();
        });
        $(document).on('click', '.select-social-icon', function() {
            var button = $(this);
            var frame = wp.media({title: 'انتخاب آیکون شبکه اجتماعی', button: {text: 'انتخاب آیکون'}, multiple: false});
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                button.siblings('.social-icon-id').val(attachment.id);
                button.siblings('.social-icon-preview').attr('src', attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url).show();
            });
            frame.open();
        });
    });
    </script>
    <?php
}
