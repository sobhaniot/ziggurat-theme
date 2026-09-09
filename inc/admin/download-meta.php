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
        .zigurat-version-history-admin{padding-top:8px;border-top:1px solid #dcdcde}
        .zigurat-version-history-admin h3{margin:0 0 6px}
        .zigurat-version-row{display:grid;gap:12px;padding:16px;margin:14px 0;border:1px solid #dcdcde;border-radius:8px;background:#f9f9f9}
        .zigurat-version-row-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
        .zigurat-version-row>label{display:flex;flex-direction:column;gap:6px;font-weight:600}
        .zigurat-version-remove{width:max-content}
        @media(max-width:782px){.zigurat-download-fields{grid-template-columns:1fr}}
        @media(max-width:782px){.zigurat-version-row-grid{grid-template-columns:1fr 1fr}}
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

        <div class="wide zigurat-version-history-admin">
            <h3>آرشیو نسخه‌های قبلی</h3>
            <p class="zigurat-download-help">با ثبت نسخه جدید، فایل فعلی به‌صورت خودکار به این آرشیو منتقل می‌شود و حذف نخواهد شد. ردیف‌های آرشیو را نیز می‌توانید دستی ویرایش کنید.</p>
            <div id="zigurat-download-version-history">
                <?php foreach (zigurat_download_version_history($post->ID) as $index => $item):
                    $history_file_id = absint($item['file_id'] ?? 0);
                    $history_file_name = $history_file_id ? basename((string) get_attached_file($history_file_id)) : '';
                ?>
                    <div class="zigurat-version-row" data-index="<?php echo esc_attr($index); ?>">
                        <div class="zigurat-version-row-grid">
                            <label><span>نسخه</span><input type="text" name="zigurat_download[version_history][<?php echo esc_attr($index); ?>][version]" value="<?php echo esc_attr($item['version'] ?? ''); ?>"></label>
                            <label><span>تاریخ انتشار</span><input type="date" name="zigurat_download[version_history][<?php echo esc_attr($index); ?>][release_date]" value="<?php echo esc_attr($item['release_date'] ?? ''); ?>"></label>
                            <label><span>حجم</span><input type="text" name="zigurat_download[version_history][<?php echo esc_attr($index); ?>][file_size]" value="<?php echo esc_attr($item['file_size'] ?? ''); ?>"></label>
                            <label><span>فرمت</span><input type="text" name="zigurat_download[version_history][<?php echo esc_attr($index); ?>][file_format]" value="<?php echo esc_attr($item['file_format'] ?? ''); ?>"></label>
                        </div>
                        <label><span>فایل نسخه</span><span class="zigurat-download-file-row"><input type="hidden" class="zigurat-version-file-id" name="zigurat_download[version_history][<?php echo esc_attr($index); ?>][file_id]" value="<?php echo esc_attr($history_file_id); ?>"><input type="text" class="zigurat-version-file-name" value="<?php echo esc_attr($history_file_name); ?>" readonly placeholder="فایلی انتخاب نشده است"><button type="button" class="button zigurat-version-select-file">انتخاب فایل</button></span></label>
                        <label><span>لینک خارجی</span><input type="url" name="zigurat_download[version_history][<?php echo esc_attr($index); ?>][external_url]" value="<?php echo esc_attr($item['external_url'] ?? ''); ?>"></label>
                        <label><span>تغییرات نسخه</span><textarea rows="3" name="zigurat_download[version_history][<?php echo esc_attr($index); ?>][changelog]"><?php echo esc_textarea($item['changelog'] ?? ''); ?></textarea></label>
                        <button type="button" class="button-link-delete zigurat-version-remove">حذف ردیف از آرشیو</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button" id="zigurat-download-add-version">افزودن نسخه قدیمی</button>
        </div>
    </div>
    <script type="text/html" id="tmpl-zigurat-download-version-row">
        <div class="zigurat-version-row" data-index="{{data.index}}">
            <div class="zigurat-version-row-grid">
                <label><span>نسخه</span><input type="text" name="zigurat_download[version_history][{{data.index}}][version]"></label>
                <label><span>تاریخ انتشار</span><input type="date" name="zigurat_download[version_history][{{data.index}}][release_date]"></label>
                <label><span>حجم</span><input type="text" name="zigurat_download[version_history][{{data.index}}][file_size]"></label>
                <label><span>فرمت</span><input type="text" name="zigurat_download[version_history][{{data.index}}][file_format]"></label>
            </div>
            <label><span>فایل نسخه</span><span class="zigurat-download-file-row"><input type="hidden" class="zigurat-version-file-id" name="zigurat_download[version_history][{{data.index}}][file_id]"><input type="text" class="zigurat-version-file-name" readonly placeholder="فایلی انتخاب نشده است"><button type="button" class="button zigurat-version-select-file">انتخاب فایل</button></span></label>
            <label><span>لینک خارجی</span><input type="url" name="zigurat_download[version_history][{{data.index}}][external_url]"></label>
            <label><span>تغییرات نسخه</span><textarea rows="3" name="zigurat_download[version_history][{{data.index}}][changelog]"></textarea></label>
            <button type="button" class="button-link-delete zigurat-version-remove">حذف ردیف از آرشیو</button>
        </div>
    </script>
    <?php
}

function zigurat_sanitize_download_version_history($items)
{
    if (!is_array($items)) {
        return array();
    }
    $clean = array();
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $entry = array(
            'version'      => sanitize_text_field($item['version'] ?? ''),
            'release_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($item['release_date'] ?? '')) ? $item['release_date'] : '',
            'file_size'    => sanitize_text_field($item['file_size'] ?? ''),
            'file_format'  => sanitize_text_field($item['file_format'] ?? ''),
            'file_id'      => absint($item['file_id'] ?? 0),
            'external_url' => esc_url_raw($item['external_url'] ?? ''),
            'changelog'    => wp_kses_post($item['changelog'] ?? ''),
        );
        if ($entry['version'] && ($entry['file_id'] || $entry['external_url'])) {
            $clean[] = $entry;
        }
    }
    return $clean;
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
    $previous = array(
        'version'      => (string) zigurat_download_meta($post_id, 'version'),
        'release_date' => '',
        'file_size'    => (string) zigurat_download_meta($post_id, 'file_size'),
        'file_format'  => (string) zigurat_download_meta($post_id, 'file_format'),
        'file_id'      => absint(zigurat_download_meta($post_id, 'file_id')),
        'external_url' => (string) zigurat_download_meta($post_id, 'external_url'),
        'changelog'    => (string) zigurat_download_meta($post_id, 'changelog'),
    );
    if ($previous['file_id']) {
        $attachment_date = get_post_field('post_date', $previous['file_id']);
        if ($attachment_date) {
            $previous['release_date'] = mysql2date('Y-m-d', $attachment_date);
        }
    }
    $history = zigurat_sanitize_download_version_history($data['version_history'] ?? zigurat_download_version_history($post_id));
    $next_version = sanitize_text_field($data['version'] ?? '');
    $next_file_id = absint($data['file_id'] ?? 0);
    $next_external_url = esc_url_raw($data['external_url'] ?? '');
    $source_changed = $previous['file_id'] !== $next_file_id || $previous['external_url'] !== $next_external_url;
    if ($previous['version'] && ($previous['file_id'] || $previous['external_url']) && ($previous['version'] !== $next_version || $source_changed)) {
        $already_archived = false;
        foreach ($history as $item) {
            if ($item['version'] === $previous['version'] && absint($item['file_id']) === $previous['file_id'] && $item['external_url'] === $previous['external_url']) {
                $already_archived = true;
                break;
            }
        }
        if (!$already_archived) {
            array_unshift($history, $previous);
        }
    }
    update_post_meta($post_id, '_zig_download_version_history', $history);
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
