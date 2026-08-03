<?php
if (!defined('ABSPATH')) {
    exit;
}
function zigurat_brand_meta_box()
{
    add_meta_box(
        'brand_info',
        'اطلاعات برند',
        'zigurat_brand_meta_callback',
        'brand',
        'normal',
        'high'
    );
}
add_action(
    'add_meta_boxes',
    'zigurat_brand_meta_box'
);
function zigurat_brand_meta_callback($post)
{
    wp_nonce_field(
        'brand_nonce',
        'brand_nonce_field'
    );
    $website = get_post_meta(
        $post->ID,
        '_brand_website',
        true
    );
?>
    <input
        type="url"
        name="brand_website"
        value="<?php echo esc_attr($website); ?>"
        class="widefat"
        placeholder="https://example.com">
<?php
}
