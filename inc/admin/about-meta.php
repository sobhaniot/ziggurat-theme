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
    $stats = array(
        'projects' => 'پروژه اجرا شده',
        'experience' => 'سال تجربه',
        'clients' => 'مشتری',
        'cities' => 'شهر اجرا'
    );
?>
    <table class="form-table">
        <?php foreach ($stats as $key => $label):
            $value = get_post_meta(
                $post->ID,
                '_about_' . $key,
                true
            );
            $suffix = get_post_meta(
                $post->ID,
                '_about_' . $key . '_suffix',
                true
            );
        ?>
            <tr>
                <th>
                    <?php echo $label; ?>
                </th>
                <td>
                    <input
                        type="number"
                        name="about_<?php echo $key; ?>"
                        value="<?php echo esc_attr($value); ?>"
                        placeholder="عدد"
                        style="width:120px;">
                    <input
                        type="text"
                        name="about_<?php echo $key; ?>_suffix"
                        value="<?php echo esc_attr($suffix); ?>"
                        placeholder="پسوند"
                        style="width:120px;">
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php
}
