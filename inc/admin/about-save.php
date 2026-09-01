<?php
if (!defined('ABSPATH')) {
    exit;
}
function zigurat_save_about_stats($post_id)
{
    if (
        !isset($_POST['zigurat_about_stats_nonce_field'])
        ||
        !wp_verify_nonce(
            $_POST['zigurat_about_stats_nonce_field'],
            'zigurat_about_stats_nonce'
        )
    ) {
        return;
    }
    if (
        defined('DOING_AUTOSAVE')
        &&
        DOING_AUTOSAVE
    ) {
        return;
    }
    if (
        get_post_type($post_id) !== 'page'
    ) {
        return;
    }
    $fields = array(
        'about_experience',
        'about_projects_suffix',
        'about_experience_suffix',
        'about_cities_suffix',
        'about_provinces_suffix'
    );
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta(
                $post_id,
                '_' . $field,
                sanitize_text_field(
                    $_POST[$field]
                )
            );
        }
    }
}
add_action(
    'save_post',
    'zigurat_save_about_stats'
);
