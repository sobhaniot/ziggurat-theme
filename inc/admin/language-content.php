<?php
if (!defined('ABSPATH')) {
    exit;
}

function zigurat_add_language_content_meta_box()
{
    if (function_exists('zigurat_multilingual_enabled') && !zigurat_multilingual_enabled()) {
        return;
    }
    foreach (array('page', 'project', 'article', 'service') as $post_type) {
        add_meta_box('zigurat-language-content', 'ترجمه‌های بین‌المللی', 'zigurat_render_language_content_meta_box', $post_type, 'normal', 'default');
    }
}
add_action('add_meta_boxes', 'zigurat_add_language_content_meta_box');

function zigurat_render_language_content_meta_box($post)
{
    wp_nonce_field('zigurat_save_language_content', 'zigurat_language_content_nonce');
    foreach (zigurat_site_languages() as $code => $language) {
        if ($code === 'fa') {
            continue;
        }
        $title = get_post_meta($post->ID, '_zigurat_title_' . $code, true);
        $excerpt = get_post_meta($post->ID, '_zigurat_excerpt_' . $code, true);
        $content = get_post_meta($post->ID, '_zigurat_content_' . $code, true);
        ?>
        <details style="margin:0 0 10px;padding:12px;border:1px solid #dcdcde;border-radius:6px;background:#fff;"<?php echo $code === 'en' ? ' open' : ''; ?>>
            <summary style="cursor:pointer;font-weight:700;"><?php echo esc_html($language['name']); ?></summary>
            <p><label><strong>عنوان</strong><br><input type="text" class="widefat" name="zigurat_language[<?php echo esc_attr($code); ?>][title]" value="<?php echo esc_attr($title); ?>" dir="<?php echo esc_attr($language['dir']); ?>"></label></p>
            <p><label><strong>خلاصه</strong><br><textarea class="widefat" rows="3" name="zigurat_language[<?php echo esc_attr($code); ?>][excerpt]" dir="<?php echo esc_attr($language['dir']); ?>"><?php echo esc_textarea($excerpt); ?></textarea></label></p>
            <p><label><strong>محتوا (HTML مجاز است)</strong><br><textarea class="widefat" rows="8" name="zigurat_language[<?php echo esc_attr($code); ?>][content]" dir="<?php echo esc_attr($language['dir']); ?>"><?php echo esc_textarea($content); ?></textarea></label></p>
        </details>
        <?php
    }
    echo '<p class="description">اگر ترجمه‌ای خالی باشد، محتوای فارسی همان بخش نمایش داده می‌شود.</p>';
}

function zigurat_save_language_content_meta($post_id)
{
    if (!isset($_POST['zigurat_language_content_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['zigurat_language_content_nonce'])), 'zigurat_save_language_content')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    $submitted = isset($_POST['zigurat_language']) && is_array($_POST['zigurat_language']) ? wp_unslash($_POST['zigurat_language']) : array();
    foreach (zigurat_site_languages() as $code => $language) {
        if ($code === 'fa') {
            continue;
        }
        $values = isset($submitted[$code]) && is_array($submitted[$code]) ? $submitted[$code] : array();
        $fields = array(
            'title' => sanitize_text_field((string) ($values['title'] ?? '')),
            'excerpt' => sanitize_textarea_field((string) ($values['excerpt'] ?? '')),
            'content' => wp_kses_post((string) ($values['content'] ?? '')),
        );
        foreach ($fields as $field => $value) {
            $key = '_zigurat_' . $field . '_' . $code;
            if ($value === '') {
                delete_post_meta($post_id, $key);
            } else {
                update_post_meta($post_id, $key, $value);
            }
        }
    }
}
add_action('save_post', 'zigurat_save_language_content_meta');

