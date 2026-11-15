<?php
if (!defined('ABSPATH')) exit;

function ziggurat_browser_render_key($project, $name) {
    return hash('sha256', wp_json_encode([
        $project['id'], $project['title'], $project['notes'], $name,
        get_post_meta($project['id'], '_zigurat_work_project_photo', true),
    ]));
}

function ziggurat_browser_render_file($key) {
    return trailingslashit(zigurat_private_application_directory()) . 'handoff-' . $key . '.png';
}

add_action('wp_footer', function () {
    if (zigurat_is_manager() && wp_script_is('zigurat-project-handoff', 'enqueued')) {
        echo '<script>window.zigguratRubikaBrowserRender=true;</script>';
    }
}, 5);

function ziggurat_browser_render_jobs($project) {
    $groups = (array) get_option('ziggurat_rubika_groups', []);
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
        $key = ziggurat_browser_render_key($project, $name);
        $jobs[$key] = ['key'=>$key, 'card'=>[
            'title'=>$project['title'], 'photos'=>array_values(zigurat_project_handoff_photo_urls($project['id'])),
            'selectedPerson'=>['name'=>$name], 'tasks'=>$tasks, 'included'=>array_fill(0,count($tasks),true),
        ]];
    }
    return $jobs;
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
    wp_send_json_success();
}
add_action('wp_ajax_ziggurat_rubika_render_jobs','ziggurat_browser_render_ajax');
add_action('wp_ajax_ziggurat_rubika_store_render','ziggurat_browser_render_ajax');
