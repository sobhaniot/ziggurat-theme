<?php
function zigurat_save_brand_meta($post_id)
{
    if (
        !isset($_POST['brand_nonce_field'])
    ) {
        return;
    }
    if (
        !wp_verify_nonce(
            $_POST['brand_nonce_field'],
            'brand_nonce'
        )
    ) {
        return;
    }
    if (
        defined('DOING_AUTOSAVE') &&
        DOING_AUTOSAVE
    ) {
        return;
    }
    if (
        get_post_type($post_id) != 'brand'
    ) {
        return;
    }
    if (
        isset($_POST['brand_website'])
    ) {
        update_post_meta(
            $post_id,
            '_brand_website',
            esc_url_raw(
                $_POST['brand_website']
            )
        );
    }
}
add_action(
    'save_post',
    'zigurat_save_brand_meta'
);
