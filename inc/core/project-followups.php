<?php
if (!defined('ABSPATH')) exit;

function zigurat_project_followup_timestamp($value) {
    return zigurat_crm_parse_datetime($value); // Same Jalali validation and Tehran timezone as CRM.
}
function zigurat_project_followup_schedule($id, $timestamp, $force = false) {
    $old = (int)get_post_meta($id, '_zigurat_project_reminder_at', true);
    $timestamp = (int)$timestamp;
    if ($old === $timestamp && !$force) return;
    if ($old) wp_clear_scheduled_hook('zigurat_project_followup_send', array((int)$id, $old));
    if ($old !== $timestamp) {
        delete_post_meta($id, '_zigurat_project_reminder_notified');
        delete_post_meta($id, '_zigurat_project_reminder_devices');
    }
    update_post_meta($id, '_zigurat_project_reminder_at', $timestamp);
    update_post_meta($id, '_zigurat_project_reminder_check_at', $timestamp);
    if ($timestamp) wp_schedule_single_event(max(time()+10, $timestamp), 'zigurat_project_followup_send', array((int)$id, $timestamp));
}
function zigurat_project_followup_meta($meta_id, $id, $key, $value) {
    if (!in_array($key, array('_zigurat_work_project_followup','_zigurat_work_project_repeat_daily'), true)) return;
    $post = get_post($id);
    if ($post && $post->post_type === 'zig_work_project') {
        $raw = $key === '_zigurat_work_project_followup' ? $value : get_post_meta($id, '_zigurat_work_project_followup', true);
        zigurat_project_followup_schedule($id, $post->post_status === 'private' ? zigurat_project_followup_timestamp($raw) : 0, $key === '_zigurat_work_project_repeat_daily');
    }
}
add_action('added_post_meta', 'zigurat_project_followup_meta', 10, 4);
add_action('updated_post_meta', 'zigurat_project_followup_meta', 10, 4);
add_action('deleted_post_meta', 'zigurat_project_followup_meta', 10, 4);
add_action('transition_post_status', function ($new, $old, $post) {
    if ($new !== $old && $post->post_type === 'zig_work_project') {
        zigurat_project_followup_schedule($post->ID, $new === 'private' ? zigurat_project_followup_timestamp(get_post_meta($post->ID, '_zigurat_work_project_followup', true)) : 0);
    }
}, 10, 3);
add_action('before_delete_post', function ($id, $post) {
    if ($post->post_type === 'zig_work_project') zigurat_project_followup_schedule($id, 0);
}, 10, 2);

function zigurat_project_followup_send($id, $due) {
    $id = absint($id); $due = (int)$due;
    $repeat = (bool)get_post_meta($id, '_zigurat_work_project_repeat_daily', true);
    $cycle = zigurat_followup_delivery_cycle($due, $repeat);
    $post = get_post($id);
    if (!$post || $post->post_type !== 'zig_work_project' || $post->post_status !== 'private'
        || !$due || $due > time() || (int)get_post_meta($id, '_zigurat_project_reminder_at', true) !== $due
        || !$cycle) return;
    $notified = (int)get_post_meta($id, '_zigurat_project_reminder_notified', true);
    if ($notified >= $cycle) {
        update_post_meta($id, '_zigurat_project_reminder_check_at', $repeat ? zigurat_followup_next_cycle($cycle) : 0);
        return;
    }
    global $wpdb;
    $lock = 'zig_proj_push_'.md5($wpdb->prefix.$id);
    if ((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock)) !== 1) return;
    try {
        wp_cache_delete($id, 'post_meta');
        $current_post = get_post($id);
        if (!$current_post || $current_post->post_status !== 'private' || (int)get_post_meta($id, '_zigurat_project_reminder_at', true) !== $due) return;
        if ((int)get_post_meta($id, '_zigurat_project_reminder_notified', true) >= $cycle) return;
        $delivered = get_post_meta($id, '_zigurat_project_reminder_devices', true);
        $delivered = is_array($delivered) ? $delivered : array();
        $sent = false; $failed = false;
        foreach (get_users(array('role__in'=>array('administrator','contributor'), 'fields'=>'ids')) as $user_id) {
            $subscriptions = get_user_meta($user_id, '_zigurat_crm_push_subscriptions', true);
            $subscriptions = is_array($subscriptions) ? $subscriptions : array();
            foreach ($subscriptions as $key=>$subscription) {
                $device = md5((string)($subscription['endpoint'] ?? ''));
                if (($delivered[$device] ?? 0) >= $cycle) { $sent = true; continue; }
                $code = zigurat_crm_send_push($subscription, array(
                    'title'=>'پیگیری پروژه: '.$post->post_title,
                    'body'=>'زمان پیگیری این پروژه رسیده است — '.zigurat_crm_datetime_label($due),
                    'url'=>zigurat_workflow_project_url(array('project'=>$id)),
                    'tag'=>'zigurat-project-'.$id.'-'.$cycle,
                ));
                if (in_array($code, array(200,201,202), true)) { $delivered[$device]=$cycle; $sent=true; }
                elseif (in_array($code, array(404,410), true)) unset($subscriptions[$key]);
                else $failed=true;
            }
            update_user_meta($user_id, '_zigurat_crm_push_subscriptions', $subscriptions);
        }
        // Do not let an in-flight delivery mark a newly edited schedule as sent.
        wp_cache_delete($id, 'post_meta');
        if ((int)get_post_meta($id, '_zigurat_project_reminder_at', true) !== $due) return;
        update_post_meta($id, '_zigurat_project_reminder_devices', $delivered);
        if ($sent && !$failed) {
            update_post_meta($id, '_zigurat_project_reminder_notified', $cycle);
            update_post_meta($id, '_zigurat_project_reminder_check_at', $repeat ? zigurat_followup_next_cycle($cycle) : 0);
        } else {
            update_post_meta($id, '_zigurat_project_reminder_check_at', time()+600);
        }
    } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); }
}
add_action('zigurat_project_followup_send', 'zigurat_project_followup_send', 10, 2);

// Reuse CRM's five-minute recovery cron; normal actions never poll the browser.
add_action('zigurat_crm_check_due_reminders', function () {
    // Backfill scheduling metadata lazily for pre-existing reminders.
    $legacy = get_posts(array('post_type'=>'zig_work_project','post_status'=>'private','posts_per_page'=>50,'fields'=>'ids',
        'meta_query'=>array(array('key'=>'_zigurat_project_reminder_at','value'=>0,'compare'=>'>','type'=>'NUMERIC'), array('key'=>'_zigurat_project_reminder_check_at','compare'=>'NOT EXISTS'))));
    foreach ($legacy as $id) zigurat_project_followup_schedule($id, (int)get_post_meta($id, '_zigurat_project_reminder_at', true), true);
    $ids = get_posts(array('post_type'=>'zig_work_project','post_status'=>'private','posts_per_page'=>50,'fields'=>'ids',
        'meta_key'=>'_zigurat_project_reminder_check_at','orderby'=>'meta_value_num','order'=>'ASC',
        'meta_query'=>array(
            array('key'=>'_zigurat_project_reminder_check_at','value'=>0,'compare'=>'>','type'=>'NUMERIC'),
            array('key'=>'_zigurat_project_reminder_check_at','value'=>time(),'compare'=>'<=','type'=>'NUMERIC'),
        )));
    foreach ($ids as $id) zigurat_project_followup_send($id, (int)get_post_meta($id, '_zigurat_project_reminder_at', true));
});
