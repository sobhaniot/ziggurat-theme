<?php
if (!defined('ABSPATH')) exit;

function ziggurat_rubika_intro_status($id, $group, $status) {
    update_post_meta($id, '_ziggurat_rubika_intro', ['group' => $group, 'status' => $status]);
}

function ziggurat_rubika_queue_introductions($previous, $groups) {
    $previous = (array) $previous;
    foreach ((array) $groups as $id => $group) {
        $id = (int) $id;
        if (!is_string($group) || $group === '' || ($previous[$id] ?? '') === $group) continue;
        $args = [$id, $group];
        ziggurat_rubika_intro_status($id, $group, 'queued');
        if (!wp_next_scheduled('ziggurat_rubika_group_intro', $args)
            && !wp_schedule_single_event(time(), 'ziggurat_rubika_group_intro', $args)) {
            ziggurat_rubika_intro_status($id, $group, 'failed');
        }
    }
}

add_action('update_option_ziggurat_rubika_groups', 'ziggurat_rubika_queue_introductions', 10, 2);
add_action('add_option_ziggurat_rubika_groups', static function ($option, $groups) {
    ziggurat_rubika_queue_introductions([], $groups);
}, 10, 2);

function ziggurat_rubika_send_introduction($id, $group, $automatic = false) {
    if (!is_string($group) || !preg_match('/^g0[A-Za-z0-9_-]{1,126}$/D', $group)
        || ziggurat_rubika_statement_partner($group) !== $id) {
        return 'invalid_group';
    }
    $lock = 'ziggurat_rubika_intro_' . hash('sha256', $id . ':' . $group);
    if (!add_option($lock, time(), '', false)) return 'busy';
    try {
        $state = get_post_meta($id, '_ziggurat_rubika_intro', true);
        if ($automatic && (($state['group'] ?? '') !== $group || ($state['status'] ?? '') !== 'queued')) {
            return 'skipped';
        }
        $partners = function_exists('zigurat_workflow_project_partners')
            ? array_column(zigurat_workflow_project_partners(), 'name', 'id') : [];
        $name = $partners[$id] ?? '';
        $token = ziggurat_bot_token('rubika');
        $status = 'failed';
        if ($token && $name !== '') {
            $text = "سلام {$name} عزیز\nاین گروه برای دریافت اعلان‌های پروژه‌های اجرایی شما در زیگورات است.";
            if (get_option('ziggurat_rubika_daily_enabled')) {
                $text .= "\nبرای کارت‌هایی که روی حالت روزانه هستند، یادآوری پروژه هم در همین گروه فرستاده می‌شود.";
            }
            if (get_option('ziggurat_rubika_statements_enabled')) {
                $text .= "\n\nبرای دریافت آخرین PDF صورت‌حساب، در همین گروه پیام /حساب یا /صورت حساب را بفرستید. بات فایل را در پاسخ به همان پیام می‌فرستد.";
            }
            $result = ziggurat_rubika_statement_api($token, 'sendMessage', ['chat_id' => $group, 'text' => $text]);
            if (!empty($result['message_id'])) $status = 'sent';
        }
        if (ziggurat_rubika_statement_partner($group) === $id) {
            ziggurat_rubika_intro_status($id, $group, $status);
        }
        return $status;
    } finally {
        delete_option($lock);
    }
}

add_action('ziggurat_rubika_group_intro', static function ($id, $group) {
    if (ziggurat_rubika_send_introduction((int) $id, $group, true) === 'busy') {
        wp_schedule_single_event(time() + 30, 'ziggurat_rubika_group_intro', [(int) $id, $group]);
    }
}, 10, 2);

add_action('admin_post_ziggurat_rubika_send_intro', static function () {
    if (!current_user_can('manage_options') || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        wp_die('دسترسی مجاز نیست.', '', ['response' => 403]);
    }
    $id = absint($_POST['partner_id'] ?? 0);
    check_admin_referer('ziggurat_rubika_send_intro_' . $id);
    $groups = (array) get_option(ziggurat_bot_group_option('rubika'), []);
    $status = ziggurat_rubika_send_introduction($id, $groups[$id] ?? '');
    wp_safe_redirect(add_query_arg([
        'page' => 'ziggurat-rubika',
        'intro_result' => $status,
    ], admin_url('options-general.php')));
    exit;
});

function ziggurat_rubika_intro_labels() {
    return [
        'queued' => 'پیام معرفی در صف ارسال است.',
        'sent' => 'پیام معرفی ارسال شد.',
        'failed' => 'ارسال پیام معرفی تأیید نشد.',
        'invalid_group' => 'یک شناسهٔ گروه معتبر و یکتا ذخیره کنید.',
        'busy' => 'ارسال پیام معرفی در حال انجام است.',
    ];
}
