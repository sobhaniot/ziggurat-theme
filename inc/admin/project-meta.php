<?php
if (!defined('ABSPATH')) {
    exit;
}
/**
 * متاباکس اطلاعات پروژه
 */
function zigurat_add_project_meta_box()
{
    add_meta_box(
        'zigurat_project_info',
        'اطلاعات پروژه',
        'zigurat_project_meta_callback',
        'project',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'zigurat_add_project_meta_box');
/**
 * نمایش فرم متاباکس
 */
function zigurat_project_meta_callback($post)
{
    wp_nonce_field('zigurat_project_nonce', 'zigurat_project_nonce_field');
    $city     = get_post_meta($post->ID, '_project_city', true);
    $client   = get_post_meta($post->ID, '_project_client', true);
    $date     = get_post_meta($post->ID, '_project_date', true);
    $type     = get_post_meta($post->ID, '_project_type', true);
    $duration = get_post_meta($post->ID, '_project_duration', true);
?>
    <table class="form-table">
        <tr>
            <th>شهر اجرا</th>
            <td>
                <input type="text"
                    name="project_city"
                    value="<?php echo esc_attr($city); ?>"
                    class="regular-text">
            </td>
        </tr>
        <tr>
            <th>کارفرما</th>
            <td>
                <input type="text"
                    name="project_client"
                    value="<?php echo esc_attr($client); ?>"
                    class="regular-text">
            </td>
        </tr>
        <tr>
            <th>تاریخ اجرا</th>
            <td>
                <input type="text"
                    name="project_date"
                    value="<?php echo esc_attr($date); ?>"
                    class="regular-text">
            </td>
        </tr>
        <tr>
            <th>نوع اجرا</th>
            <td>
                <input type="text"
                    name="project_type"
                    value="<?php echo esc_attr($type); ?>"
                    class="regular-text">
            </td>
        </tr>
        <tr>
            <th>مدت زمان اجرا</th>
            <td>
                <input type="text"
                    name="project_duration"
                    value="<?php echo esc_attr($duration); ?>"
                    class="regular-text">
            </td>
        </tr>
    </table>
    <hr>
    <h2>گالری تصاویر پروژه</h2>
    <?php
    $gallery = get_post_meta(
        $post->ID,
        '_project_gallery',
        true
    );
    ?>
    <input
        type="hidden"
        id="project_gallery"
        name="project_gallery"
        value="<?php echo esc_attr($gallery); ?>">
    <p>
        <button
            type="button"
            class="button button-primary"
            id="select_gallery">
            انتخاب تصاویر
        </button>
    </p>
    <div id="gallery-preview">
        <?php
        if (!empty($gallery)) {
            $ids = explode(',', $gallery);
            foreach ($ids as $id) {
                echo '<div class="gallery-thumb">';
                echo wp_get_attachment_image(
                    $id,
                    'thumbnail'
                );
                echo '</div>';
            }
        }
        ?>
    </div>
<?php
}
