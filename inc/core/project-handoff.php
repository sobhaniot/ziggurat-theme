<?php
if (!defined('ABSPATH')) exit;

function zigurat_project_handoff_photo_slots($stored)
{
    if (!is_array($stored)) return array();
    return isset($stored['path']) ? array(0 => $stored) : array_intersect_key($stored, array(0, 1));
}

function zigurat_project_handoff_photo_urls($project_id)
{
    $urls = array();
    foreach (zigurat_project_handoff_photo_slots(get_post_meta($project_id, '_zigurat_work_project_photo', true)) as $slot => $file) {
        $urls[$slot] = add_query_arg(array(
            'action' => 'zigurat_project_handoff_photo',
            'project' => $project_id,
            'slot' => $slot,
            '_wpnonce' => wp_create_nonce('zigurat_project_handoff_photo_' . $project_id),
        ), admin_url('admin-post.php'));
    }
    ksort($urls);
    return $urls;
}

function zigurat_project_handoff_upload_photos($files)
{
    $photos = array();
    foreach (array('project_photo', 'project_photo_2') as $slot => $field) {
        if (($files[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        $photo = zigurat_store_private_application_file($files[$field], 'photo');
        if (is_wp_error($photo)) {
            foreach ($photos as $uploaded) zigurat_project_handoff_remove_photo($uploaded);
            return $photo;
        }
        $photos[$slot] = $photo;
    }
    return $photos;
}

function zigurat_project_handoff_save_photos($id, $photos, $removed = array())
{
    if (!$photos && !$removed) return true;
    $key = '_zigurat_work_project_photo';
    $stored = get_post_meta($id, $key, true);
    $old = zigurat_project_handoff_photo_slots($stored);
    $updated = $photos + array_diff_key($old, $removed);
    if ($updated === $old) return true;
    $saved = $stored === ''
        ? add_post_meta($id, $key, wp_slash($updated), true)
        : update_post_meta($id, $key, wp_slash($updated), $stored);
    foreach ($photos as $slot => $photo) {
        if (!$saved) {
            zigurat_project_handoff_remove_photo($photo);
            continue;
        }
        $path = zigurat_resolve_private_application_file_path($photo);
        if ($path) zigurat_project_handoff_prepare_preview($path);
    }
    if ($saved) {
        foreach ($old as $slot => $photo) {
            if (isset($photos[$slot]) || isset($removed[$slot])) zigurat_project_handoff_remove_photo($photo);
        }
    }
    return (bool) $saved;
}

function zigurat_project_handoff_prepare_preview($path)
{
    $preview = $path . '.preview.webp';
    if (is_file($preview)) return $preview;
    if (!wp_image_editor_supports(array('mime_type' => 'image/webp'))) return false;

    $editor = wp_get_image_editor($path);
    if (is_wp_error($editor)) return false;
    $editor->maybe_exif_rotate();
    $size = $editor->get_size();
    if (max($size['width'], $size['height']) > 1200 && is_wp_error($editor->resize(1200, 1200))) {
        return false;
    }
    $editor->set_quality(80);
    $temporary = $path . '.' . wp_generate_uuid4() . '.webp';
    $saved = $editor->save($temporary, 'image/webp');
    if (is_wp_error($saved) || $saved['mime-type'] !== 'image/webp' || !is_file($path) || !@rename($temporary, $preview)) {
        $saved_path = is_wp_error($saved) ? $temporary : $saved['path'];
        if (is_file($saved_path)) wp_delete_file($saved_path);
        return is_file($preview) ? $preview : false;
    }
    return $preview;
}

function zigurat_project_handoff_remove_photo($file)
{
    $path = zigurat_resolve_private_application_file_path($file);
    if ($path && zigurat_delete_private_application_file($file) && is_file($path . '.preview.webp')) {
        wp_delete_file($path . '.preview.webp');
    }
}

function zigurat_project_handoff_serve_photo()
{
    if (!zigurat_is_manager()) wp_die('دسترسی مجاز نیست.', '', array('response' => 403));
    $id = absint($_GET['project'] ?? 0);
    check_admin_referer('zigurat_project_handoff_photo_' . $id);
    $project = get_post($id);
    $photos = $project && $project->post_type === 'zig_work_project'
        ? zigurat_project_handoff_photo_slots(get_post_meta($id, '_zigurat_work_project_photo', true)) : array();
    $file = $photos[($_GET['slot'] ?? '') === '1' ? 1 : 0] ?? array();
    $path = zigurat_resolve_private_application_file_path($file);
    if (!$path) wp_die('عکس پیدا نشد.', '', array('response' => 404));
    $preview = zigurat_project_handoff_prepare_preview($path);
    nocache_headers();
    header('X-Content-Type-Options: nosniff');
    if ($preview) $path = $preview;
    header('Content-Type: ' . ($preview ? 'image/webp' : $file['mime']));
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}
add_action('admin_post_zigurat_project_handoff_photo', 'zigurat_project_handoff_serve_photo');

function zigurat_project_handoff_delete_photo($id, $post)
{
    if ($post->post_type === 'zig_work_project') {
        foreach (zigurat_project_handoff_photo_slots(get_post_meta($id, '_zigurat_work_project_photo', true)) as $photo) {
            zigurat_project_handoff_remove_photo($photo);
        }
    }
}
add_action('before_delete_post', 'zigurat_project_handoff_delete_photo', 10, 2);

function zigurat_project_handoff_data()
{
    check_ajax_referer('zigurat_workflow_projects', 'nonce');
    if (!zigurat_is_manager()) wp_send_json_error(array('message' => 'دسترسی مجاز نیست.'), 403);

    $project_id = absint($_POST['project_id'] ?? 0);
    $partner_id = absint($_POST['partner_id'] ?? 0);
    $partners = array_column(zigurat_workflow_project_partners(), null, 'id');
    if (!$project_id && !isset($partners[$partner_id])) {
        wp_send_json_error(array('message' => 'یک همکار را انتخاب کنید.'), 400);
    }
    $ids = $project_id ? array($project_id) : get_posts(array(
        'post_type' => 'zig_work_project',
        'post_status' => array('private', 'trash'),
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'orderby' => 'modified',
    ));
    if (!$project_id && $ids) update_meta_cache('post', $ids);
    $cards = array();
    foreach ($ids as $id) {
        $project = get_post($id);
        if (!$project || $project->post_type !== 'zig_work_project') continue;
        if ($project->post_status !== 'private' && !(
            $project->post_status === 'trash'
            && get_post_meta($id, '_zigurat_work_project_archived_at', true)
            && !get_post_meta($id, '_zigurat_work_project_deleted_at', true)
        )) continue;
        $people = array();
        foreach (zigurat_workflow_project_work_items($id) as $item) {
            foreach ($item['partner_ids'] as $person_id) {
                if ($partner_id && $partner_id !== $person_id) continue;
                $files = get_post_meta($person_id, '_application_files', true);
                $people['partner-' . $person_id] = array(
                    'name' => $partners[$person_id]['name'],
                    'photo' => !empty($files['photo'][0]) ? zigurat_application_private_file_url($person_id, 'photo:0') : '',
                );
            }
            if (!$partner_id && $item['other_partner'] !== '') {
                $people['other-' . $item['other_partner']] = array('name' => $item['other_partner'], 'photo' => '');
            }
        }
        if ($partner_id && !$people) continue;
        $notes = (string) get_post_meta($id, '_zigurat_work_project_notes', true);
        $photo_urls = array_values(zigurat_project_handoff_photo_urls($id));
        $cards[] = array(
            'title' => $project->post_title,
            'photo' => $photo_urls[0] ?? '',
            'photos' => $photo_urls,
            'people' => array_values($people),
            'tasks' => array_values(array_filter(array_map('trim', preg_split('/\R/u', $notes) ?: array()), 'strlen')),
        );
    }
    if ($project_id && !$cards) wp_send_json_error(array('message' => 'کارت پروژه پیدا نشد.'), 404);
    wp_send_json_success(array('cards' => $cards, 'name' => $partners[$partner_id]['name'] ?? ''));
}
add_action('wp_ajax_zigurat_project_handoff_data', 'zigurat_project_handoff_data');
