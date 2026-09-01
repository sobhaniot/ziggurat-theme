<?php
if (!defined('ABSPATH')) {
    exit;
}
/**
 * متاباکس آمار درباره ما
 */
function zigurat_add_about_stats_meta_box()
{
    global $post;
    if (!$post) {
        return;
    }
    if (
        $post->post_type !== 'page'
        ||
        $post->post_name !== 'about'
    ) {
        return;
    }
    add_meta_box(
        'zigurat_about_stats',
        'آمار شرکت',
        'zigurat_about_stats_callback',
        'page',
        'normal',
        'high'
    );
}
add_action(
    'add_meta_boxes',
    'zigurat_add_about_stats_meta_box'
);
function zigurat_about_stats_callback($post)
{
    wp_nonce_field(
        'zigurat_about_stats_nonce',
        'zigurat_about_stats_nonce_field'
    );
    $project_stats = zigurat_get_project_stats();
    $stats = array(
        'projects'   => array('label' => 'پروژه اجرا شده', 'automatic' => true, 'value' => $project_stats['projects']),
        'experience' => array('label' => 'سال تجربه', 'automatic' => false),
        'cities'     => array('label' => 'شهر اجرا', 'automatic' => true, 'value' => $project_stats['cities']),
        'provinces'  => array('label' => 'استان اجرا', 'automatic' => true, 'value' => $project_stats['provinces']),
    );
?>
    <table class="form-table">
        <?php foreach ($stats as $key => $config):
            $value = $config['automatic']
                ? $config['value']
                : get_post_meta($post->ID, '_about_' . $key, true);
            $suffix = get_post_meta(
                $post->ID,
                '_about_' . $key . '_suffix',
                true
            );
        ?>
            <tr>
                <th>
                    <?php echo esc_html($config['label']); ?>
                </th>
                <td>
                    <input
                        type="number"
                        <?php if (!$config['automatic']): ?>name="about_<?php echo esc_attr($key); ?>"<?php endif; ?>
                        value="<?php echo esc_attr($value); ?>"
                        placeholder="عدد"
                        style="width:120px;" <?php readonly($config['automatic']); ?>>
                    <input
                        type="text"
                        name="about_<?php echo $key; ?>_suffix"
                        value="<?php echo esc_attr($suffix); ?>"
                        placeholder="پسوند"
                        style="width:120px;">
                    <?php if ($config['automatic']): ?>
                        <span class="description">این مقدار از پروژه‌های منتشرشده به‌صورت خودکار محاسبه و در دیتابیس ذخیره می‌شود.</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php
}

/** داده یکپارچه آمار برای صفحه اول و صفحه درباره ما. */
function zigurat_get_about_stats($about_id)
{
    $project_stats = zigurat_get_project_stats();
    $defaults = array(
        'projects'   => 'پروژه اجرا شده',
        'experience' => 'سال تجربه',
        'cities'     => 'شهر اجرا',
        'provinces'  => 'استان اجرا',
    );
    $values = array(
        'projects'   => $project_stats['projects'],
        'experience' => (int) get_post_meta($about_id, '_about_experience', true),
        'cities'     => $project_stats['cities'],
        'provinces'  => $project_stats['provinces'],
    );

    $result = array();
    foreach ($values as $key => $value) {
        $suffix = get_post_meta($about_id, '_about_' . $key . '_suffix', true);
        $result[$key] = array(
            'value'  => $value,
            'suffix' => $suffix !== '' ? $suffix : $defaults[$key],
        );
    }
    return $result;
}
