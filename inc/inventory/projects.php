<?php
if (!defined('ABSPATH')) {
    exit;
}

function zigurat_inventory_is_internal_project($project)
{
    $project = $project instanceof WP_Post ? $project : get_post(absint($project));
    return $project
        && $project->post_type === 'project'
        && (bool) get_post_meta($project->ID, '_zigurat_inventory_only', true);
}

function zigurat_inventory_internal_project_status($project_id)
{
    $status = sanitize_key((string) get_post_meta(absint($project_id), '_zigurat_inventory_project_status', true));
    return $status === 'archived' ? 'archived' : 'active';
}

function zigurat_inventory_project_is_selectable($project)
{
    $project = $project instanceof WP_Post ? $project : get_post(absint($project));
    if (!$project || $project->post_type !== 'project') {
        return false;
    }
    if (!zigurat_inventory_is_internal_project($project)) {
        return $project->post_status === 'publish';
    }
    return in_array($project->post_status, array('draft', 'private'), true)
        && zigurat_inventory_internal_project_status($project->ID) === 'active';
}

function zigurat_inventory_get_project_groups($include_archived = false)
{
    $public = get_posts(array(
        'post_type' => 'project',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'no_found_rows' => true,
        'meta_query' => array(
            array(
                'key' => '_zigurat_inventory_only',
                'compare' => 'NOT EXISTS',
            ),
        ),
    ));
    $internal = get_posts(array(
        'post_type' => 'project',
        'post_status' => array('draft', 'private'),
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'no_found_rows' => true,
        'meta_key' => '_zigurat_inventory_only',
        'meta_value' => '1',
    ));
    $active_internal = array();
    $archived_internal = array();
    foreach ($internal as $project) {
        if (zigurat_inventory_internal_project_status($project->ID) === 'archived') {
            $archived_internal[] = $project;
        } else {
            $active_internal[] = $project;
        }
    }
    return array(
        'public' => $public,
        'internal' => $active_internal,
        'archived' => $include_archived ? $archived_internal : array(),
    );
}

function zigurat_inventory_internal_project_code($project_id)
{
    $project_id = absint($project_id);
    $code = trim((string) get_post_meta($project_id, '_zigurat_inventory_project_code', true));
    if ($code === '' && $project_id) {
        $code = 'INV-' . str_pad((string) $project_id, 6, '0', STR_PAD_LEFT);
        update_post_meta($project_id, '_zigurat_inventory_project_code', $code);
    }
    return $code;
}

/**
 * نام پروژه را برای مقایسه یکسان می‌کند؛ اختلاف حروف عربی/فارسی، نیم‌فاصله
 * و فاصله‌های اضافه نباید امکان ساخت دو پروژه هم‌نام را ایجاد کند.
 */
function zigurat_inventory_normalize_project_name($name)
{
    $name = wp_strip_all_tags((string) $name);
    $name = strtr($name, array(
        'ي' => 'ی',
        'ى' => 'ی',
        'ك' => 'ک',
        'ة' => 'ه',
        'ۀ' => 'ه',
        'أ' => 'ا',
        'إ' => 'ا',
    ));
    $name = preg_replace('/[\s\x{200c}\x{200e}\x{200f}]+/u', ' ', trim($name));
    return function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
}

/** پروژه‌های دارای نام دقیقاً یکسان را بدون اتکا به collation دیتابیس برمی‌گرداند. */
function zigurat_inventory_find_projects_by_name($name, $exclude_id = 0, $statuses = null, $internal_only = null)
{
    $needle = zigurat_inventory_normalize_project_name($name);
    if ($needle === '') {
        return array();
    }
    $statuses = is_array($statuses) && $statuses
        ? array_values(array_map('sanitize_key', $statuses))
        : array('publish', 'draft', 'pending', 'private', 'future');
    $projects = get_posts(array(
        'post_type' => 'project',
        'post_status' => $statuses,
        'posts_per_page' => -1,
        'orderby' => 'ID',
        'order' => 'ASC',
        'no_found_rows' => true,
        'exclude' => absint($exclude_id) ? array(absint($exclude_id)) : array(),
    ));
    return array_values(array_filter($projects, function ($project) use ($needle, $internal_only) {
        $is_internal = zigurat_inventory_is_internal_project($project);
        if ($internal_only === true && !$is_internal) {
            return false;
        }
        if ($internal_only === false && $is_internal) {
            return false;
        }
        return zigurat_inventory_normalize_project_name($project->post_title) === $needle;
    }));
}

function zigurat_inventory_find_project_by_name($name, $exclude_id = 0, $statuses = null, $internal_only = null)
{
    $projects = zigurat_inventory_find_projects_by_name($name, $exclude_id, $statuses, $internal_only);
    return $projects ? reset($projects) : null;
}

function zigurat_inventory_save_internal_project($data, $project_id = 0)
{
    if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', 'فقط مدیرکل می‌تواند پروژه داخلی را مدیریت کند.');
    }
    $name = sanitize_text_field(wp_unslash((string) ($data['internal_project_name'] ?? '')));
    $client = sanitize_text_field(wp_unslash((string) ($data['internal_project_client'] ?? '')));
    $notes = sanitize_textarea_field(wp_unslash((string) ($data['internal_project_notes'] ?? '')));
    if ($name === '') {
        return new WP_Error('internal_project_name', 'نام پروژه داخلی را وارد کنید.');
    }

    $project_id = absint($project_id);
    $duplicate = zigurat_inventory_find_project_by_name($name, $project_id);
    if ($duplicate) {
        return new WP_Error(
            'internal_project_duplicate',
            'پروژه‌ای با همین نام قبلاً ثبت شده است.',
            array('project_id' => (int) $duplicate->ID)
        );
    }
    if ($project_id) {
        $project = get_post($project_id);
        if (!$project || !zigurat_inventory_is_internal_project($project)) {
            return new WP_Error('internal_project_invalid', 'پروژه داخلی معتبر نیست.');
        }
        $saved_id = wp_update_post(array(
            'ID' => $project_id,
            'post_title' => $name,
            'post_status' => 'draft',
        ), true);
    } else {
        $saved_id = wp_insert_post(array(
            'post_type' => 'project',
            'post_status' => 'draft',
            'post_title' => $name,
            'post_content' => '',
            'post_author' => get_current_user_id(),
        ), true);
    }
    if (is_wp_error($saved_id)) {
        return $saved_id;
    }

    $saved_id = absint($saved_id);
    update_post_meta($saved_id, '_zigurat_inventory_only', '1');
    update_post_meta($saved_id, '_zigurat_inventory_project_status', 'active');
    update_post_meta($saved_id, '_zigurat_inventory_project_client', $client);
    update_post_meta($saved_id, '_zigurat_inventory_project_notes', $notes);
    update_post_meta($saved_id, '_project_client', $client);
    zigurat_inventory_internal_project_code($saved_id);
    clean_post_cache($saved_id);
    return $saved_id;
}

function zigurat_inventory_set_internal_project_archived($project_id, $archived)
{
    if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', 'فقط مدیرکل می‌تواند پروژه داخلی را بایگانی کند.');
    }
    $project = get_post(absint($project_id));
    if (!$project || !zigurat_inventory_is_internal_project($project)) {
        return new WP_Error('internal_project_invalid', 'پروژه داخلی معتبر نیست.');
    }
    $updated_id = wp_update_post(array(
        'ID' => $project->ID,
        'post_status' => $archived ? 'private' : 'draft',
    ), true);
    if (is_wp_error($updated_id)) {
        return $updated_id;
    }
    update_post_meta($project->ID, '_zigurat_inventory_project_status', $archived ? 'archived' : 'active');
    clean_post_cache($project->ID);
    return (int) $project->ID;
}

/**
 * گردش‌های یک پروژه داخلی تکراری را به پروژه عمومی وصل می‌کند و نسخه داخلی را
 * بدون حذف هیچ سابقه‌ای بایگانی می‌کند.
 */
function zigurat_inventory_merge_internal_project_into_public($internal_project_id, $public_project_id, $check_capability = true)
{
    if ($check_capability && !current_user_can('manage_options')) {
        return new WP_Error('forbidden', 'فقط مدیرکل می‌تواند پروژه‌ها را ادغام کند.');
    }
    $internal = get_post(absint($internal_project_id));
    $public = get_post(absint($public_project_id));
    if (!$internal || !zigurat_inventory_is_internal_project($internal)) {
        return new WP_Error('internal_project_invalid', 'پروژه داخلی معتبر نیست.');
    }
    if (
        !$public
        || $public->post_type !== 'project'
        || $public->post_status !== 'publish'
        || zigurat_inventory_is_internal_project($public)
        || $public->ID === $internal->ID
    ) {
        return new WP_Error('internal_project_merge_target', 'پروژه سایت برای اتصال معتبر نیست.');
    }
    if (zigurat_inventory_normalize_project_name($internal->post_title) !== zigurat_inventory_normalize_project_name($public->post_title)) {
        return new WP_Error('internal_project_merge_name', 'نام دو پروژه یکسان نیست.');
    }

    global $wpdb;
    $transactions_table = zigurat_inventory_transactions_table_name();
    $wpdb->query('START TRANSACTION');
    $updated_transactions = $wpdb->update(
        $transactions_table,
        array(
            'project_id' => (int) $public->ID,
            'project_name' => get_the_title($public),
        ),
        array('project_id' => (int) $internal->ID),
        array('%d', '%s'),
        array('%d')
    );
    if ($updated_transactions === false) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('database', 'اتصال سوابق گردش انبار انجام نشد.');
    }

    $archived_id = wp_update_post(array(
        'ID' => (int) $internal->ID,
        'post_status' => 'private',
    ), true);
    if (is_wp_error($archived_id)) {
        $wpdb->query('ROLLBACK');
        return $archived_id;
    }
    update_post_meta($internal->ID, '_zigurat_inventory_project_status', 'archived');
    update_post_meta($internal->ID, '_zigurat_inventory_merged_into', (int) $public->ID);
    $wpdb->query('COMMIT');
    clean_post_cache($internal->ID);
    clean_post_cache($public->ID);
    return array(
        'project_id' => (int) $public->ID,
        'transactions' => (int) $updated_transactions,
    );
}

/** هنگام انتشار، همان شناسه از پروژه داخلی به پروژه عمومی تبدیل می‌شود. */
function zigurat_inventory_promote_internal_project_on_publish($new_status, $old_status, $post)
{
    if (
        $new_status !== 'publish'
        || !($post instanceof WP_Post)
        || $post->post_type !== 'project'
    ) {
        return;
    }
    if (zigurat_inventory_is_internal_project($post)) {
        delete_post_meta($post->ID, '_zigurat_inventory_only');
        delete_post_meta($post->ID, '_zigurat_inventory_project_status');
        delete_post_meta($post->ID, '_zigurat_inventory_project_client');
        delete_post_meta($post->ID, '_zigurat_inventory_project_notes');
        clean_post_cache($post->ID);
        return;
    }

    // اگر پروژه سایت جداگانه ساخته شده باشد، پیش‌نویس داخلی هم‌نام را خودکار متصل می‌کنیم.
    $internal_matches = zigurat_inventory_find_projects_by_name(
        $post->post_title,
        $post->ID,
        array('draft', 'private'),
        true
    );
    foreach ($internal_matches as $internal_project) {
        if (zigurat_inventory_internal_project_status($internal_project->ID) === 'active') {
            zigurat_inventory_merge_internal_project_into_public($internal_project->ID, $post->ID, false);
        }
    }
}
add_action('transition_post_status', 'zigurat_inventory_promote_internal_project_on_publish', 10, 3);

/** برچسب قابل تشخیص در فهرست پروژه‌های پیشخوان وردپرس. */
function zigurat_inventory_internal_project_post_state($states, $post)
{
    if ($post instanceof WP_Post && zigurat_inventory_is_internal_project($post)) {
        $states['zigurat_inventory_internal'] = 'پروژه داخلی انبار';
    }
    return $states;
}
add_filter('display_post_states', 'zigurat_inventory_internal_project_post_state', 10, 2);

/** انتقال یک‌باره پروژه‌های داخلی نسخه قبلی از private به پیش‌نویس. */
function zigurat_inventory_migrate_internal_projects_to_drafts()
{
    if ((int) get_option('zigurat_inventory_project_model_version', 0) >= 2) {
        return;
    }
    $project_ids = get_posts(array(
        'post_type' => 'project',
        'post_status' => 'private',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'meta_key' => '_zigurat_inventory_only',
        'meta_value' => '1',
    ));
    foreach ($project_ids as $project_id) {
        if (zigurat_inventory_internal_project_status($project_id) === 'active') {
            wp_update_post(array('ID' => absint($project_id), 'post_status' => 'draft'));
        }
    }
    update_option('zigurat_inventory_project_model_version', 2, false);
}
add_action('init', 'zigurat_inventory_migrate_internal_projects_to_drafts', 30);
