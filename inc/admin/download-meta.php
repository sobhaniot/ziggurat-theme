<?php
if (!defined('ABSPATH')) {
    exit;
}

function zigurat_add_download_meta_box()
{
    add_meta_box('zigurat-download-details', 'مشخصات و منبع دانلود', 'zigurat_render_download_meta_box', 'zig_download', 'normal', 'high');
}
add_action('add_meta_boxes_zig_download', 'zigurat_add_download_meta_box');

function zigurat_render_download_meta_box($post)
{
    wp_nonce_field('zigurat_save_download_meta', 'zigurat_download_meta_nonce');
    $fields = array(
        'version'       => array('نسخه فایل', 'text', 'مثلاً 2.4.1'),
        'file_size'     => array('حجم فایل', 'text', 'مثلاً 8.5 مگابایت'),
        'file_format'   => array('فرمت فایل', 'text', 'مثلاً RBZ، ZIP یا PDF'),
        'developer'     => array('سازنده / ناشر', 'text', 'نام شرکت یا توسعه‌دهنده'),
        'license'       => array('نوع مجوز', 'text', 'رایگان، تجاری، آزمایشی و...'),
        'official_url'  => array('وب‌سایت یا صفحه رسمی', 'url', 'https://example.com'),
    );
    ?>
    <style>
        .zigurat-download-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;direction:rtl}
        .zigurat-download-fields label{display:flex;flex-direction:column;gap:6px;font-weight:600}
        .zigurat-download-fields input,.zigurat-download-fields textarea{width:100%;font-weight:400}
        .zigurat-download-fields .wide{grid-column:1/-1}
        .zigurat-download-file-row{display:flex;gap:8px;align-items:center}
        .zigurat-download-file-row input{flex:1}
        .zigurat-download-help{margin:4px 0 0;color:#646970;font-weight:400}
        @media(max-width:782px){.zigurat-download-fields{grid-template-columns:1fr}}
    </style>
    <div class="zigurat-download-fields">
        <?php foreach ($fields as $key => $field): ?>
            <label>
                <span><?php echo esc_html($field[0]); ?></span>
                <input type="<?php echo esc_attr($field[1]); ?>" name="zigurat_download[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr(zigurat_download_meta($post->ID, $key)); ?>" placeholder="<?php echo esc_attr($field[2]); ?>">
            </label>
        <?php endforeach; ?>

        <label class="wide">
            <span>فایل داخلی از کتابخانه رسانه</span>
            <span class="zigurat-download-file-row">
                <input type="hidden" id="zigurat-download-file-id" name="zigurat_download[file_id]" value="<?php echo esc_attr(absint(zigurat_download_meta($post->ID, 'file_id'))); ?>">
                <input type="text" id="zigurat-download-file-name" value="<?php
                    $attachment_id = absint(zigurat_download_meta($post->ID, 'file_id'));
                    echo esc_attr($attachment_id ? basename((string) get_attached_file($attachment_id)) : '');
                ?>" readonly placeholder="هنوز فایلی انتخاب نشده است">
                <button type="button" class="button button-secondary" id="zigurat-download-select-file">انتخاب فایل</button>
                <button type="button" class="button" id="zigurat-download-remove-file">حذف انتخاب</button>
            </span>
            <span class="zigurat-download-help">برای PDF، ZIP و RBZ از کتابخانه رسانه استفاده کنید. فایل اجرایی نرم‌افزار را ترجیحاً از لینک رسمی سازنده ارائه دهید.</span>
        </label>

        <label class="wide">
            <span>لینک دانلود خارجی / رسمی</span>
            <input type="url" name="zigurat_download[external_url]" value="<?php echo esc_attr(zigurat_download_meta($post->ID, 'external_url')); ?>" placeholder="https://example.com/download">
            <span class="zigurat-download-help">اگر فایل داخلی انتخاب شده باشد، همان فایل اولویت دارد.</span>
        </label>

        <label class="wide">
            <span>نیازمندی‌های سیستم</span>
            <textarea rows="3" name="zigurat_download[requirements]" placeholder="نسخه سیستم‌عامل، حافظه و سایر پیش‌نیازها"><?php echo esc_textarea(zigurat_download_meta($post->ID, 'requirements')); ?></textarea>
        </label>
        <label class="wide">
            <span>راهنمای نصب</span>
            <textarea rows="5" name="zigurat_download[installation]" placeholder="مراحل نصب و فعال‌سازی"><?php echo esc_textarea(zigurat_download_meta($post->ID, 'installation')); ?></textarea>
        </label>
        <label class="wide">
            <span>تغییرات نسخه</span>
            <textarea rows="5" name="zigurat_download[changelog]" placeholder="ویژگی‌ها و تغییرات این نسخه"><?php echo esc_textarea(zigurat_download_meta($post->ID, 'changelog')); ?></textarea>
        </label>
    </div>
    <?php
}

function zigurat_save_download_meta($post_id)
{
    if (!isset($_POST['zigurat_download_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['zigurat_download_meta_nonce'])), 'zigurat_save_download_meta')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id) || get_post_type($post_id) !== 'zig_download') {
        return;
    }
    $data = isset($_POST['zigurat_download']) && is_array($_POST['zigurat_download'])
        ? wp_unslash($_POST['zigurat_download'])
        : array();
    $text_fields = array('version', 'file_size', 'file_format', 'developer', 'license');
    foreach ($text_fields as $key) {
        update_post_meta($post_id, '_zig_download_' . $key, sanitize_text_field($data[$key] ?? ''));
    }
    update_post_meta($post_id, '_zig_download_file_id', absint($data['file_id'] ?? 0));
    foreach (array('official_url', 'external_url') as $key) {
        update_post_meta($post_id, '_zig_download_' . $key, esc_url_raw($data[$key] ?? ''));
    }
    update_post_meta($post_id, '_zig_download_requirements', sanitize_textarea_field($data['requirements'] ?? ''));
    foreach (array('installation', 'changelog') as $key) {
        update_post_meta($post_id, '_zig_download_' . $key, wp_kses_post($data[$key] ?? ''));
    }
    if (!metadata_exists('post', $post_id, '_zig_download_count')) {
        add_post_meta($post_id, '_zig_download_count', 0, true);
    }
}
add_action('save_post_zig_download', 'zigurat_save_download_meta');

function zigurat_download_admin_assets($hook)
{
    if (!in_array($hook, array('post.php', 'post-new.php'), true) || get_current_screen()->post_type !== 'zig_download') {
        return;
    }
    wp_enqueue_media();
    $path = get_template_directory() . '/assets/js/download-admin.js';
    wp_enqueue_script('zigurat-download-admin', get_template_directory_uri() . '/assets/js/download-admin.js', array(), is_file($path) ? filemtime($path) : null, true);
}
add_action('admin_enqueue_scripts', 'zigurat_download_admin_assets');

function zigurat_download_columns($columns)
{
    $columns['download_type'] = 'نوع فایل';
    $columns['download_version'] = 'نسخه';
    $columns['download_count'] = 'دانلود';
    return $columns;
}
add_filter('manage_zig_download_posts_columns', 'zigurat_download_columns');

function zigurat_download_column_content($column, $post_id)
{
    if ($column === 'download_type') {
        echo esc_html(implode('، ', zigurat_download_term_names($post_id, 'download_type')) ?: '—');
    } elseif ($column === 'download_version') {
        echo esc_html((string) zigurat_download_meta($post_id, 'version', '—'));
    } elseif ($column === 'download_count') {
        echo esc_html(number_format_i18n(zigurat_download_count($post_id)));
    }
}
add_action('manage_zig_download_posts_custom_column', 'zigurat_download_column_content', 10, 2);

/** RBZ در اصل بسته ZIP افزونه SketchUp است؛ فقط مدیران اجازه بارگذاری دارند. */
function zigurat_download_upload_mimes($mimes)
{
    if (current_user_can('manage_options')) {
        $mimes['rbz'] = 'application/zip';
    }
    return $mimes;
}
add_filter('upload_mimes', 'zigurat_download_upload_mimes');

function zigurat_download_rbz_filetype($data, $file, $filename, $mimes, $real_mime)
{
    if (!current_user_can('manage_options') || strtolower((string) pathinfo($filename, PATHINFO_EXTENSION)) !== 'rbz') {
        return $data;
    }
    if (in_array($real_mime, array('application/zip', 'application/x-zip-compressed', 'application/octet-stream'), true)) {
        $data['ext'] = 'rbz';
        $data['type'] = 'application/zip';
        $data['proper_filename'] = false;
    }
    return $data;
}
add_filter('wp_check_filetype_and_ext', 'zigurat_download_rbz_filetype', 10, 5);
