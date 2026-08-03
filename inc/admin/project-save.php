<?php
if (!defined('ABSPATH')) {
    exit;
}
/**
 * ذخیره اطلاعات پروژه
 */
function zigurat_save_project_meta($post_id)
{
    // بررسی nonce
    if (
        !isset($_POST['zigurat_project_nonce_field']) ||
        !wp_verify_nonce(
            $_POST['zigurat_project_nonce_field'],
            'zigurat_project_nonce'
        )
    ) {
        return;
    }
    // جلوگیری از Auto Save
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    // فقط برای پروژه‌ها
    if (get_post_type($post_id) !== 'project') {
        return;
    }
    // دسترسی کاربر
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    $fields = array(
        'project_city',
        'project_client',
        'project_date',
        'project_type',
        'project_duration'
    );
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta(
                $post_id,
                '_' . $field,
                sanitize_text_field($_POST[$field])
            );
        }
    }
    // ذخیره گالری تصاویر
    if (isset($_POST['project_gallery'])) {
        $gallery = sanitize_text_field($_POST['project_gallery']);
        update_post_meta(
            $post_id,
            '_project_gallery',
            $gallery
        );
    }
}
add_action('save_post', 'zigurat_save_project_meta');
