<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Expose the theme's SEO fields to authenticated REST clients such as n8n.
 * The capability check keeps these protected meta keys private from writers
 * who cannot edit the corresponding post.
 */
function zigurat_register_seo_rest_meta()
{
    foreach (array('page', 'project', 'article') as $post_type) {
        foreach (array('_zigurat_seo_title', '_zigurat_seo_description') as $meta_key) {
            register_post_meta($post_type, $meta_key, array(
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback'     => function ($allowed, $key, $post_id) {
                    return current_user_can('edit_post', $post_id);
                },
            ));
        }
    }
}
add_action('init', 'zigurat_register_seo_rest_meta');

function zigurat_add_seo_meta_box()
{
    foreach (array('page', 'project', 'article') as $post_type) {
        add_meta_box('zigurat-seo-settings', 'تنظیمات سئو', 'zigurat_render_seo_meta_box', $post_type, 'normal', 'default');
    }
}
add_action('add_meta_boxes', 'zigurat_add_seo_meta_box');

function zigurat_render_seo_meta_box($post)
{
    wp_nonce_field('zigurat_save_seo_meta', 'zigurat_seo_nonce');
    $title = get_post_meta($post->ID, '_zigurat_seo_title', true);
    $description = get_post_meta($post->ID, '_zigurat_seo_description', true);
    ?>
    <p><label for="zigurat-seo-title"><strong>عنوان سئو</strong></label></p>
    <input id="zigurat-seo-title" name="zigurat_seo_title" type="text" class="widefat" maxlength="70" value="<?php echo esc_attr($title); ?>" placeholder="اگر خالی باشد، عنوان استاندارد وردپرس استفاده می‌شود.">
    <p class="description">پیشنهاد: حدود ۵۰ تا ۶۰ نویسه و شامل موضوع اصلی صفحه.</p>
    <p><label for="zigurat-seo-description"><strong>توضیحات متا</strong></label></p>
    <textarea id="zigurat-seo-description" name="zigurat_seo_description" class="widefat" rows="3" maxlength="170" placeholder="خلاصه‌ای روشن و ترغیب‌کننده از محتوای صفحه"><?php echo esc_textarea($description); ?></textarea>
    <p class="description">پیشنهاد: حدود ۱۴۰ تا ۱۶۰ نویسه. در صورت خالی بودن، متن به‌صورت خودکار از خلاصه یا محتوای صفحه ساخته می‌شود.</p>
    <?php
}

function zigurat_save_seo_meta($post_id)
{
    if (
        !isset($_POST['zigurat_seo_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['zigurat_seo_nonce'])), 'zigurat_save_seo_meta')
        || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        || wp_is_post_revision($post_id)
        || !current_user_can('edit_post', $post_id)
    ) {
        return;
    }
    foreach (array('title', 'description') as $key) {
        $field = 'zigurat_seo_' . $key;
        $value = isset($_POST[$field]) ? sanitize_text_field(wp_unslash($_POST[$field])) : '';
        if ($value === '') {
            delete_post_meta($post_id, '_zigurat_seo_' . $key);
        } else {
            update_post_meta($post_id, '_zigurat_seo_' . $key, $value);
        }
    }
}
add_action('save_post', 'zigurat_save_seo_meta');
