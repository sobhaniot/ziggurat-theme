<?php
if (!defined('ABSPATH')) exit;

function ziggurat_handoff_completion_date($id) {
    $latest = '';
    foreach ((array) get_post_meta($id, '_zigurat_work_project_history', true) as $entry) {
        if (!is_array($entry) || ($entry['from'] ?? '') !== 'production'
            || !in_array($entry['to'] ?? '', ['lead', 'proforma', 'approval', 'delivery', 'payment'], true)) continue;
        $time = (string) ($entry['time'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $time) && $time > $latest) $latest = $time;
    }
    $saved = (string) get_post_meta($id, '_ziggurat_last_completion_at', true);
    if ($saved > $latest) $latest = $saved;
    if ($latest === '') return 'ثبت نشده';
    update_post_meta($id, '_ziggurat_last_completion_at', $latest);
    return function_exists('zigurat_inventory_format_jalali_datetime')
        ? zigurat_inventory_format_jalali_datetime($latest) : $latest;
}

function ziggurat_handoff_production_date($id) {
    $first = (string) get_post_meta($id, '_ziggurat_first_production_at', true);
    foreach ((array) get_post_meta($id, '_zigurat_work_project_history', true) as $entry) {
        if (!is_array($entry) || ($entry['to'] ?? '') !== 'production' || ($entry['from'] ?? '') === 'production') continue;
        $time = (string) ($entry['time'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $time) && ($first === '' || $time < $first)) $first = $time;
    }
    if ($first === '') return 'ثبت نشده';
    update_post_meta($id, '_ziggurat_first_production_at', $first);
    return function_exists('zigurat_inventory_format_jalali_datetime')
        ? zigurat_inventory_format_jalali_datetime($first) : $first;
}

add_action('updated_post_meta', static function ($meta_id, $id, $key, $value) {
    if ($key === '_zigurat_work_project_history') {
        ziggurat_handoff_production_date($id);
        ziggurat_handoff_completion_date($id);
    }
}, 10, 4);
add_action('added_post_meta', static function ($meta_id, $id, $key, $value) {
    if ($key === '_zigurat_work_project_history') {
        ziggurat_handoff_production_date($id);
        ziggurat_handoff_completion_date($id);
    }
}, 10, 4);

function ziggurat_installer_handoff_fields($project) {
    $details = [
        'مشتری نهایی / محل اجرا' => trim((string) ($project['execution_site'] ?? '')) ?: 'ثبت نشده',
        'شماره تلفن' => trim((string) ($project['phone'] ?? '')) ?: 'ثبت نشده',
        'آدرس' => trim((string) ($project['address'] ?? '')) ?: 'ثبت نشده',
        'تاریخ ارجاع' => ziggurat_handoff_production_date($project['id']),
    ];
    if (($project['stage'] ?? '') !== 'production' && ziggurat_handoff_completion_date($project['id']) !== 'ثبت نشده') {
        $details['تاریخ خاتمه'] = ziggurat_handoff_completion_date($project['id']);
    }
    return ['projectDetails' => $details];
}

function ziggurat_browser_render_key($project, $name, $person_photo_identity = '') {
    return hash('sha256', wp_json_encode([
        $project['id'], $project['title'], $project['notes'], $name, $person_photo_identity,
        get_post_meta($project['id'], '_zigurat_work_project_photo', true),
        ziggurat_installer_handoff_fields($project), 'layout-1.13',
    ]));
}

function ziggurat_browser_render_file($key) {
    return trailingslashit(zigurat_private_application_directory()) . 'handoff-' . $key . '.png';
}

function ziggurat_company_handoff_fields($project, $finished) {
    return [
        'showExecutor' => false,
        'companyName' => trim((string) ($project['client'] ?? '')),
        'companyMessage' => $finished
            ? 'اجرای پروژهٔ شما توسط تیم زیگورات به پایان رسید.'
                . "\nتاریخ ارجاع: " . ziggurat_handoff_production_date($project['id'])
                . "\nتاریخ خاتمه: " . ziggurat_handoff_completion_date($project['id'])
            : 'پروژهٔ شما در تاریخ ' . ziggurat_handoff_production_date($project['id'])
                . ' در برنامهٔ اجرایی شرکت زیگورات قرار گرفت.',
    ];
}

function ziggurat_browser_render_company_key($project, $finished) {
    return ziggurat_browser_render_key($project, '', wp_json_encode(ziggurat_company_handoff_fields($project, $finished)));
}

function ziggurat_browser_render_person_photo($people) {
    if (count($people) !== 1) return ['url' => '', 'identity' => '', 'id' => 0];
    $person_id = (int) array_key_first($people);
    $files = (array) get_post_meta($person_id, '_application_files', true);
    $photo = $files['photo'][0] ?? '';
    if (empty($photo)) return ['url' => '', 'identity' => '', 'id' => $person_id];
    return [
        'url' => zigurat_application_private_file_url($person_id, 'photo:0'),
        'identity' => wp_json_encode($photo),
        'id' => $person_id,
    ];
}

add_action('wp_footer', function () {
    if (zigurat_is_manager() && wp_script_is('zigurat-project-handoff', 'enqueued')) {
        echo '<script>window.zigguratRubikaBrowserRender=true;</script>';
    }
}, 5);

function ziggurat_browser_render_jobs($project) {
    $jobs = [];
    foreach (['rubika', 'bale'] as $provider) {
        $jobs += ziggurat_browser_render_provider_jobs($project, $provider);
    }
    return $jobs;
}

function ziggurat_browser_render_provider_jobs($project, $provider) {
    if (!ziggurat_bot_token($provider)) return [];
    $groups = (array) get_option(ziggurat_bot_group_option($provider), []);
    $partners = array_column(zigurat_workflow_project_partners(), 'name', 'id');
    $names = [];
    foreach ($project['work_items'] as $item) {
        foreach ($item['partner_ids'] as $id) {
            if (empty($groups[$id]) || !isset($partners[$id])) continue;
            $names[$groups[$id]][$id] = $partners[$id];
        }
    }
    $jobs = [];
    $tasks = array_values(array_filter(array_map('trim', preg_split('/\R/u', $project['notes']) ?: []), 'strlen'));
    foreach ($names as $people) {
        $name = implode('، ', $people);
        $person_photo = ziggurat_browser_render_person_photo($people);
        $key = ziggurat_browser_render_key($project, $name, $person_photo['identity']);
        if (is_file(ziggurat_browser_render_file($key))) continue;
        $jobs[$key] = ['key'=>$key, 'card'=>[
            'title'=>$project['title'], 'photos'=>array_values(zigurat_project_handoff_photo_urls($project['id'])),
            'selectedPerson'=>['name'=>$name, 'photo'=>$person_photo['url']], 'tasks'=>$tasks, 'included'=>array_fill(0,count($tasks),true),
        ] + ziggurat_installer_handoff_fields($project)];
    }
    if (ziggurat_rubika_company_group($project['client'] ?? '', $provider) !== '') {
        foreach ([false, true] as $finished) {
            $key = ziggurat_browser_render_company_key($project, $finished);
            if (is_file(ziggurat_browser_render_file($key))) continue;
            $jobs[$key] = ['key'=>$key, 'card'=>[
                'title'=>$project['title'], 'photos'=>array_values(zigurat_project_handoff_photo_urls($project['id'])),
                'tasks'=>$tasks, 'included'=>array_fill(0,count($tasks),true),
            ] + ziggurat_company_handoff_fields($project, $finished)];
        }
    }
    return $jobs;
}

function ziggurat_browser_render_pending_ajax() {
    check_ajax_referer('ziggurat_workflow_projects', 'nonce');
    if (!zigurat_is_manager()) wp_send_json_error(null, 403);

    global $wpdb;
    $prefix = $wpdb->esc_like('_ziggurat_rubika_notice_') . '%';
    $since = gmdate('Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS);
    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT p.ID
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
         WHERE p.post_type = %s AND p.post_status = %s
           AND pm.meta_key LIKE %s AND pm.meta_value IN (%s, 'waiting_image')
           AND p.post_modified_gmt >= %s
         ORDER BY p.post_modified_gmt DESC
         LIMIT 30",
        'zig_work_project', 'private', $prefix, 'queued', $since
    ));

    $pending = [];
    foreach ($ids as $id) {
        $project = zigurat_workflow_project_record(absint($id));
        if (!$project || !empty($project['archived'])) continue;
        if (ziggurat_browser_render_jobs($project)) $pending[] = absint($id);
        if (count($pending) >= 10) break;
    }
    wp_send_json_success($pending);
}

function ziggurat_browser_render_ajax() {
    check_ajax_referer('zigurat_workflow_projects', 'nonce');
    if (!zigurat_is_manager()) wp_send_json_error(null, 403);
    $project = zigurat_workflow_project_record(absint($_POST['project_id'] ?? 0));
    if (!$project || !empty($project['archived'])) wp_send_json_error(null, 404);
    $jobs = ziggurat_browser_render_jobs($project);
    if (current_action() === 'wp_ajax_ziggurat_rubika_render_jobs') wp_send_json_success(array_values($jobs));
    $key = sanitize_key($_POST['key'] ?? '');
    $encoded = (string) ($_POST['png'] ?? '');
    $prefix = 'data:image/png;base64,';
    if (!isset($jobs[$key]) || strlen($encoded) > 7 * MB_IN_BYTES || strpos($encoded,$prefix)!==0) wp_send_json_error(null,400);
    $bytes = base64_decode(substr($encoded,strlen($prefix)),true);
    $size = $bytes ? @getimagesizefromstring($bytes) : false;
    if (!$size || $size[2] !== IMAGETYPE_PNG || $size[0] !== 960 || $size[1] > 16000) wp_send_json_error(null,400);
    if (is_wp_error(zigurat_prepare_private_application_directory())) wp_send_json_error(null,500);
    $dest = ziggurat_browser_render_file($key);
    $temp = tempnam(dirname($dest),'handoff-');
    if (!$temp || file_put_contents($temp,$bytes) !== strlen($bytes) || !rename($temp,$dest)) {
        if ($temp && is_file($temp)) unlink($temp);
        wp_send_json_error(null,500);
    }
    ziggurat_bot_wake_project_queue($project['id']);
    wp_send_json_success();
}

function ziggurat_bot_wake_project_queue($project_id) {
    foreach ((array) _get_cron_array() as $timestamp => $hooks) {
        foreach ($hooks['ziggurat_rubika_send_project'] ?? [] as $job) {
            $args = $job['args'];
            if ((int) ($args[0] ?? 0) !== (int) $project_id || $timestamp <= time()) continue;
            $notice_key = '_ziggurat_rubika_notice_' . hash('sha256', ($args[3] ?? 'rubika') . ':' . ($args[2] ?? '') . ':' . ($args[5] ?? ''));
            if (get_post_meta($project_id, $notice_key, true) !== 'waiting_image') continue;
            if (!wp_unschedule_event($timestamp, 'ziggurat_rubika_send_project', $args)) continue;
            $scheduled = wp_schedule_single_event(time(), 'ziggurat_rubika_send_project', $args, true);
            if (is_wp_error($scheduled) || !$scheduled) wp_schedule_single_event($timestamp, 'ziggurat_rubika_send_project', $args);
        }
    }
    spawn_cron();
}
add_action('wp_ajax_ziggurat_rubika_render_jobs','ziggurat_browser_render_ajax');
add_action('wp_ajax_ziggurat_rubika_store_render','ziggurat_browser_render_ajax');
add_action('wp_ajax_ziggurat_rubika_pending_render_projects','ziggurat_browser_render_pending_ajax');
