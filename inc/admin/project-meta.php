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
    $province = get_post_meta($post->ID, '_project_province', true);
    $neighborhood = get_post_meta($post->ID, '_project_neighborhood', true);
    $client   = get_post_meta($post->ID, '_project_client', true);
    $date     = get_post_meta($post->ID, '_project_date', true);
    $type     = get_post_meta($post->ID, '_project_type', true);
    $duration = get_post_meta($post->ID, '_project_duration', true);
    $featured = (bool) get_post_meta($post->ID, '_project_featured_for_client', true);
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
            <th><label for="project_neighborhood">محله اجرا</label></th>
            <td>
                <input type="text" id="project_neighborhood"
                    name="project_neighborhood"
                    value="<?php echo esc_attr($neighborhood); ?>"
                    class="regular-text">
                <p class="description">اختیاری است؛ اگر خالی بماند در صفحه پروژه نمایش داده نمی‌شود.</p>
            </td>
        </tr>
        <tr>
            <th><label for="project_province">استان اجرا</label></th>
            <td>
                <select id="project_province" name="project_province" class="regular-text">
                    <option value="">انتخاب استان</option>
                    <?php if ($province !== '' && !in_array($province, zigurat_project_province_options(), true)): ?>
                        <option value="<?php echo esc_attr($province); ?>" selected><?php echo esc_html($province); ?> (مقدار قدیمی)</option>
                    <?php endif; ?>
                    <?php foreach (zigurat_project_province_options() as $province_option): ?>
                        <option value="<?php echo esc_attr($province_option); ?>" <?php selected($province, $province_option); ?>>
                            <?php echo esc_html($province_option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">استان‌های دارای پروژه به‌صورت خودکار روی نقشه صفحه اصلی رنگی می‌شوند.</p>
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
            <th><label for="project_type">نوع فعالیت/اجرا</label></th>
            <td>
                <input type="text"
                    name="project_type"
                    value="<?php echo esc_attr($type); ?>"
                    class="regular-text">
                <p class="description">برای نمونه: تابلوسازی، چاپ، بازسازی، دکوراسیون یا نورپردازی</p>
            </td>
        </tr>
        <tr>
            <th>نمایش در صفحه اول</th>
            <td>
                <label>
                    <input type="checkbox" name="project_featured_for_client" value="1" <?php checked($featured); ?>>
                    این پروژه، پروژهٔ برگزیدهٔ این کارفرما باشد
                </label>
                <p class="description">اگر برای یک کارفرما چند پروژه برگزیده شود، جدیدترین پروژه نمایش داده می‌شود.</p>
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
