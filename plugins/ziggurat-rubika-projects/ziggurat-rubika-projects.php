<?php
/**
 * Plugin Name: Ziggurat Rubika Project Notices
 * Version: 1.7.3
 */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/handoff-image.php';
require_once __DIR__ . '/browser-render.php';
require_once __DIR__ . '/statement-requests.php';
require_once __DIR__ . '/group-introductions.php';

function ziggurat_bot_provider() {
    return get_option('ziggurat_bot_provider') === 'telegram' ? 'telegram' : 'rubika';
}

function ziggurat_bot_token($provider) {
    if ($provider === 'rubika' && defined('ZIGGURAT_RUBIKA_TOKEN') && ZIGGURAT_RUBIKA_TOKEN) {
        return ZIGGURAT_RUBIKA_TOKEN;
    }
    return (string) get_option('ziggurat_bot_token_' . $provider, '');
}

function ziggurat_bot_group_option($provider) {
    return $provider === 'telegram' ? 'ziggurat_telegram_groups' : 'ziggurat_rubika_groups';
}

add_filter('cron_schedules', function ($schedules) {
    $schedules['ziggurat_reminder_check'] = ['interval' => 300, 'display' => 'Ziggurat reminder check'];
    return $schedules;
});

add_action('init', function () {
    if (get_option('ziggurat_rubika_daily_enabled') && !wp_next_scheduled('ziggurat_rubika_check_daily')) {
        wp_schedule_event(time(), 'ziggurat_reminder_check', 'ziggurat_rubika_check_daily');
    }
});

register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('ziggurat_rubika_check_daily');
    wp_clear_scheduled_hook('ziggurat_rubika_poll_statements');
    wp_unschedule_hook('ziggurat_rubika_group_intro');
});

add_action('ziggurat_rubika_check_daily', function () {
    if (!get_option('ziggurat_rubika_daily_enabled')) {
        wp_clear_scheduled_hook('ziggurat_rubika_check_daily');
        return;
    }
    if (!function_exists('zigurat_workflow_project_records') || !ziggurat_bot_token('rubika')) return;
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Tehran'));
    if ($now->format('H:i') < get_option('ziggurat_rubika_daily_time', '09:00')) return;
    if (get_option('ziggurat_rubika_last_scan') === $now->format('Y-m-d')) return;
    foreach (zigurat_workflow_project_records() as $project) {
        if ($project['stage'] === 'production' && get_post_meta($project['id'], '_zigurat_executor_reminder', true) === 'daily') {
            ziggurat_queue_project_notice($project['id'], 'daily', 0, $now->format('Y-m-d'));
        }
    }
    update_option('ziggurat_rubika_last_scan', $now->format('Y-m-d'), false);
});

function ziggurat_queue_project_notice($id, $kind, $only_partner = 0, $day = '') {
    if (!ziggurat_bot_token('rubika')) return;
    $project = zigurat_workflow_project_record($id);
    if (!$project) return;
    $groups = (array) get_option('ziggurat_rubika_groups', []);
    $destinations = [];
    $closed = $kind === 'daily' ? (array) get_post_meta($id, '_zigurat_work_project_closed_partners', true) : [];
    foreach ($project['work_items'] as $item) {
        foreach ($item['partner_ids'] as $partner_id) {
            if (isset($closed['partner-' . $partner_id])) continue;
            if ((!$only_partner || $only_partner === (int) $partner_id) && !empty($groups[$partner_id])) {
                $destinations[$groups[$partner_id]] = true;
            }
        }
    }
    $event = $day !== '' ? 'daily-' . $day : wp_generate_uuid4();
    foreach (array_keys($destinations) as $group) {
        $notice_key = '_ziggurat_rubika_notice_' . hash('sha256', 'rubika:' . $group . ':' . $event);
        if ($day !== '' && !add_post_meta($id, $notice_key, 'scheduling', true)) continue;
        $recent_creation = 'ziggurat_rubika_created_' . hash('sha256', $id . ':' . $group);
        if ($kind === 'started' && get_transient($recent_creation)) continue;
        $job = wp_schedule_single_event(time(), 'ziggurat_rubika_send_project',
            [$id, 0, (string) $group, 'rubika', $kind, $event, $only_partner], true);
        if ($job === true && $kind === 'created') {
            set_transient($recent_creation, 1, MINUTE_IN_SECONDS);
        }
        if ($kind === 'finished') delete_transient($recent_creation);
        update_post_meta($id, '_ziggurat_rubika_notice_' . hash('sha256', 'rubika:' . $group . ':' . $event),
            is_wp_error($job) || !$job ? 'queue_error' : 'queued');
    }
}

add_action('added_post_meta', function ($meta_id, $id, $key) {
    if ($key === '_zigurat_work_project_stage' && get_post_type($id) === 'zig_work_project') {
        $GLOBALS['ziggurat_notice_created'][$id] = true;
    }
}, 10, 3);

add_action('update_post_meta', function ($meta_id, $id, $key, $value) {
    if (get_post_type($id) !== 'zig_work_project') return;
    if ($key === '_zigurat_work_project_stage' && !isset($GLOBALS['ziggurat_notice_stage'][$id])) {
        $GLOBALS['ziggurat_notice_stage'][$id] = (string) get_post_meta($id, $key, true);
    }
    if ($key !== '_zigurat_work_project_closed_partners' || ($_POST['action'] ?? '') !== 'zigurat_close_workflow_partner_assignment') return;
    $old = (array) get_post_meta($id, $key, true);
    foreach (array_diff_key((array) $value, $old) as $assignment => $details) {
        if (preg_match('/^partner-([0-9]+)$/D', $assignment, $match)) {
            $GLOBALS['ziggurat_notice_closed'][$id][(int) $match[1]] = $assignment;
        }
    }
}, 10, 4);

// The first closure may insert the metadata rather than update it.
add_action('added_post_meta', function ($meta_id, $id, $key, $value) {
    if ($key !== '_zigurat_work_project_closed_partners' || ($_POST['action'] ?? '') !== 'zigurat_close_workflow_partner_assignment') return;
    foreach ((array) $value as $assignment => $details) {
        if (preg_match('/^partner-([0-9]+)$/D', $assignment, $match)) {
            $GLOBALS['ziggurat_notice_closed'][$id][(int) $match[1]] = $assignment;
        }
    }
}, 10, 4);

add_action('zigurat_workflow_project_edit_saved', function ($id) {
    $GLOBALS['ziggurat_notice_saved'][$id] = true;
});

add_action('shutdown', function () {
    $created = $GLOBALS['ziggurat_notice_created'] ?? [];
    $saved = $GLOBALS['ziggurat_notice_saved'] ?? [];
    foreach ($created as $id => $unused) ziggurat_queue_project_notice($id, 'created');
    foreach ($GLOBALS['ziggurat_notice_stage'] ?? [] as $id => $previous) {
        if (isset($created[$id])) continue;
        $current = get_post_meta($id, '_zigurat_work_project_stage', true);
        if ($current === $previous) continue;
        if ($previous === 'production') {
            ziggurat_queue_project_notice($id, 'finished');
            unset($saved[$id]);
        } elseif ($current === 'production' && !isset($saved[$id])) {
            ziggurat_queue_project_notice($id, 'started');
        }
    }
    foreach (array_diff_key($saved, $created) as $id => $unused) ziggurat_queue_project_notice($id, 'saved');
    foreach ($GLOBALS['ziggurat_notice_closed'] ?? [] as $id => $partners) {
        $closed = (array) get_post_meta($id, '_zigurat_work_project_closed_partners', true);
        foreach ($partners as $partner => $assignment) {
            if (isset($closed[$assignment])) ziggurat_queue_project_notice($id, 'work_received', $partner);
        }
    }
});

add_action('ziggurat_rubika_send_project', function ($id, $attempt, $group = null, $provider = 'rubika', $kind = 'created', $event = '', $only_partner = 0) {
    if (!$group || $provider !== 'rubika') return;
    $key = '_ziggurat_rubika_notice_' . hash('sha256', $provider . ':' . $group . ($event ? ':' . $event : ''));
    if (!ziggurat_bot_token($provider) || !function_exists('zigurat_workflow_project_record')) {
        update_post_meta($id, $key, 'configuration_error');
        return;
    }
    $project = zigurat_workflow_project_record($id);
    if ($kind === 'daily' && (!get_option('ziggurat_rubika_daily_enabled') || ($project['stage'] ?? '') !== 'production'
        || get_post_meta($id, '_zigurat_executor_reminder', true) !== 'daily'
        || $event !== 'daily-' . wp_date('Y-m-d', null, new DateTimeZone('Asia/Tehran')))) return;
    if (!$project || get_post_meta($id, $key, true) === 'sent') return;
    if (!empty($project['archived'])) {
        // Collaborator work can be closed after the project has been settled and archived.
        $can_notify = $kind === 'work_received'
            && get_post_meta($id, '_zigurat_work_project_archived_at', true)
            && !get_post_meta($id, '_zigurat_work_project_deleted_at', true);
        if (!$can_notify) {
            update_post_meta($id, $key, 'project_archived');
            return;
        }
    }

    // A unique option provides an atomic claim even if two cron workers overlap.
    $lock = 'ziggurat_rubika_job_' . $id . hash('sha256', $provider . ':' . $group . ($event ? ':' . $event : ''));
    if (!add_option($lock, time(), '', false)) return;
    try {
        if (get_post_meta($id, $key, true) === 'sent') return;
        $groups = (array) get_option(ziggurat_bot_group_option($provider), []);
        $partners = array_column(zigurat_workflow_project_partners(), 'name', 'id');
        $names = [];
        $closed = $kind === 'daily' ? (array) get_post_meta($id, '_zigurat_work_project_closed_partners', true) : [];
        foreach ($project['work_items'] as $item) {
            foreach ($item['partner_ids'] as $partner_id) {
                if (isset($closed['partner-' . $partner_id])) continue;
                if ((!$only_partner || $only_partner === (int) $partner_id) && (string) ($groups[$partner_id] ?? '') === $group && isset($partners[$partner_id])) {
                    $names[$partner_id] = $partners[$partner_id];
                }
            }
        }
        if (!$names) {
            update_post_meta($id, $key, 'destination_changed');
            return;
        }
        if ($kind === 'work_received') {
            $sent = wp_remote_post('https://botapi.rubika.ir/v3/' . rawurlencode(ziggurat_bot_token('rubika')) . '/sendMessage', [
                'timeout'=>15, 'redirection'=>0, 'headers'=>['Content-Type'=>'application/json'],
                'body'=>wp_json_encode(['chat_id'=>$group,'text'=>$project['title'] . ' — ' . implode('، ', $names) . ' — کارکرد گرفته شد']),
            ]);
            $payload = json_decode(wp_remote_retrieve_body($sent), true);
            update_post_meta($id, $key, !empty($payload['data']['message_id']) ? 'sent' : 'send_error');
            return;
        }
        $name = implode('، ', $names);
        $cached = ziggurat_browser_render_file(ziggurat_browser_render_key($project, $name));
        $temporary = !is_file($cached);
        $image = $temporary ? ziggurat_handoff_image($project, $name) : $cached;
        if (!$image) {
            if ($attempt < 3) {
                $retry = wp_schedule_single_event(time()+60, 'ziggurat_rubika_send_project',
                    [$id,$attempt+1,$group,$provider,$kind,$event,$only_partner],true);
                if ($retry === true) return;
            }
            update_post_meta($id, $key, 'render_error');
            return;
        }
        $caption = trim(wp_strip_all_tags($project['notes']));

        if ($kind === 'finished') $caption = 'پروژه «' . $project['title'] . '» خاتمه یافت.';
        $token = ziggurat_bot_token($provider);
        try {
            if ($provider === 'telegram') {
                $fields = ['chat_id'=>$group];
                if ($caption !== '') $fields['caption'] = wp_html_excerpt($caption,1024);
                $response = ziggurat_bot_upload_image('https://api.telegram.org/bot'.$token.'/sendPhoto',$fields,$image,'photo');
            } else {
                $base = 'https://botapi.rubika.ir/v3/'.rawurlencode($token).'/';
                $json = ['timeout'=>15,'redirection'=>0,'headers'=>['Content-Type'=>'application/json']];
                $ticket = wp_remote_post($base.'requestSendFile',$json+['body'=>wp_json_encode(['type'=>'Image'])]);
                $upload_url = json_decode(wp_remote_retrieve_body($ticket),true)['data']['upload_url'] ?? '';
                $uploaded = $upload_url ? ziggurat_bot_upload_image($upload_url,[],$image,'file') : false;
                $file_id = $uploaded[1]['data']['file_id'] ?? $uploaded[1]['file_id'] ?? '';
                if (!$file_id) {
                    update_post_meta($id,$key,'upload_error');
                    return;
                }
                $fields = ['chat_id'=>$group,'file_id'=>$file_id];
                if ($caption !== '') $fields['text'] = $caption;
                $sent = wp_remote_post($base.'sendFile',$json+['body'=>wp_json_encode($fields)]);
                $response = is_wp_error($sent) ? false : [wp_remote_retrieve_response_code($sent),json_decode(wp_remote_retrieve_body($sent),true)];
            }
        } finally {
            if ($temporary) unlink($image);
        }
        if (!$response) {
            update_post_meta($id, $key, 'delivery_unknown');
            return;
        }
        [$status,$payload] = $response;
        if ($status === 200 && (!empty($payload['data']['message_id']) || !empty($payload['result']['message_id']))) {
            update_post_meta($id, $key, 'sent');
            return;
        }
        if ($status === 429 && $attempt < 3) {
            $retry = wp_schedule_single_event(time() + 120 * (2 ** $attempt), 'ziggurat_rubika_send_project', array($id, $attempt + 1, $group, $provider, $kind, $event, $only_partner), true);
            update_post_meta($id, $key, is_wp_error($retry) ? 'queue_error' : 'queued');
            return;
        }
        update_post_meta($id, $key, 'send_error');
    } finally {
        delete_option($lock);
    }
}, 10, 7);

add_action('admin_init', function () {
    register_setting('ziggurat_bot', 'ziggurat_rubika_statements_enabled', [
        'sanitize_callback' => 'absint',
        'default' => 0,
    ]);
    register_setting('ziggurat_bot', 'ziggurat_rubika_daily_enabled', [
        'sanitize_callback' => 'absint',
        'default' => 0,
    ]);
    register_setting('ziggurat_bot', 'ziggurat_rubika_daily_time', [
        'sanitize_callback' => function ($value) {
            return is_string($value) && preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $value) ? $value : '09:00';
        },
        'default' => '09:00',
    ]);
    register_setting('ziggurat_bot', 'ziggurat_bot_provider', array(
        'sanitize_callback' => function ($value) { return $value === 'telegram' ? 'telegram' : 'rubika'; },
    ));
    foreach (array('rubika', 'telegram') as $provider) {
        $option = 'ziggurat_bot_token_' . $provider;
        register_setting('ziggurat_bot', $option, array(
            'sanitize_callback' => function ($value) use ($option) {
                $token = trim((string) $value);
                if ($token === '') return get_option($option, '');
                if (!preg_match('/^[a-zA-Z0-9:_-]{1,256}$/D', $token)) {
                    add_settings_error($option, 'invalid_token', 'قالب توکن معتبر نیست.');
                    return get_option($option, '');
                }
                return $token;
            },
        ));
        register_setting('ziggurat_bot', ziggurat_bot_group_option($provider), array(
            'sanitize_callback' => function ($values) use ($provider) {
                $groups = array();
                $pattern = $provider === 'telegram' ? '/^-[0-9]{1,20}$/D' : '/^g0[a-zA-Z0-9_-]{1,126}$/D';
                foreach ((array) $values as $id => $value) {
                    $group = trim((string) $value);
                    if (absint($id) && preg_match($pattern, $group)) {
                        $groups[absint($id)] = $group;
                    } elseif ($group !== '') {
                        add_settings_error('ziggurat_bot', 'invalid_group', 'یکی از شناسه‌های گروه معتبر نیست و ذخیره نشد.');
                    }
                }
                return $groups;
            },
        ));
    }
});

add_action('admin_menu', function () {
    add_options_page('مدیریت بات پیام‌رسان', 'مدیریت بات پیام‌رسان', 'manage_options', 'ziggurat-rubika', function () {
        if (!current_user_can('manage_options')) return;
        $partners = function_exists('zigurat_workflow_project_partners') ? zigurat_workflow_project_partners() : array();
        echo '<div class="wrap"><h1>مدیریت بات پیام‌رسان</h1>';
        settings_errors();
        $intro_labels = ziggurat_rubika_intro_labels();
        $intro_result = is_string($_GET['intro_result'] ?? null) ? sanitize_key($_GET['intro_result']) : '';
        if (isset($intro_labels[$intro_result])) {
            printf('<div class="notice %s"><p>%s</p></div>',
                $intro_result === 'sent' ? 'notice-success' : 'notice-warning',
                esc_html($intro_labels[$intro_result]));
        }
        $intro_forms = [];
        echo '<form method="post" action="options.php" autocomplete="off">';
        settings_fields('ziggurat_bot');
        printf('<fieldset><legend>صورت‌حساب همکاران در روبیکا</legend><input type="hidden" name="ziggurat_rubika_statements_enabled" value="0"><label><input type="checkbox" name="ziggurat_rubika_statements_enabled" value="1"%s> پاسخ به درخواست صورت‌حساب در گروه‌های ثبت‌شده</label><p>PDF فعلی همکار به همان گروه فرستاده می‌شود و برای اعضای گروه قابل مشاهده است.</p></fieldset>',
            checked(get_option('ziggurat_rubika_statements_enabled'), 1, false));
        printf('<fieldset><legend>یادآوری روزانهٔ مجری در روبیکا</legend><input type="hidden" name="ziggurat_rubika_daily_enabled" value="0"><label><input type="checkbox" name="ziggurat_rubika_daily_enabled" value="1"%s> فعال‌سازی یادآوری روزانه برای کارت‌هایی که روی حالت روزانه هستند</label><p><label>ساعت تهران <input type="time" name="ziggurat_rubika_daily_time" value="%s" required></label></p></fieldset>',
            checked(get_option('ziggurat_rubika_daily_enabled'), 1, false),
            esc_attr(get_option('ziggurat_rubika_daily_time', '09:00')));
        echo '<label for="ziggurat-bot-provider">پیام‌رسان فعال</label> <select id="ziggurat-bot-provider" name="ziggurat_bot_provider">';
        foreach (array('rubika' => 'روبیکا', 'telegram' => 'تلگرام') as $provider => $label) {
            printf('<option value="%s"%s>%s</option>', esc_attr($provider), selected(ziggurat_bot_provider(), $provider, false), esc_html($label));
        }
        echo '</select>';
        foreach (array('rubika' => 'روبیکا', 'telegram' => 'تلگرام') as $provider => $label) {
            $option = ziggurat_bot_group_option($provider);
            $groups = (array) get_option($option, array());
            printf('<h2>%1$s</h2><label for="token-%2$s">توکن %1$s</label> <input id="token-%2$s" name="ziggurat_bot_token_%2$s" type="password" dir="ltr" autocomplete="new-password"> <span>%3$s</span>',
                esc_html($label), esc_attr($provider), ziggurat_bot_token($provider) ? 'توکن ثبت شده است. برای حفظ آن، کادر را خالی بگذارید.' : 'توکن ثبت نشده است.');
            if ($provider === 'rubika' && defined('ZIGGURAT_RUBIKA_TOKEN') && ZIGGURAT_RUBIKA_TOKEN) {
                echo '<p>توکن تعریف‌شده در تنظیمات سرور اولویت دارد.</p>';
            }
            if ($provider === 'rubika') {
                echo '<p class="description">با ذخیرهٔ شناسهٔ جدید گروه، پیام معرفی برای همان گروه در صف ارسال قرار می‌گیرد. دکمهٔ ارسال دستی از شناسهٔ ذخیره‌شده استفاده می‌کند.</p>';
            }
            echo '<table class="form-table"><tbody>';
            foreach ($partners as $partner) {
                $id = absint($partner['id']);
                printf('<tr><th scope="row"><label for="%1$s-%2$d">%3$s</label></th><td><input class="regular-text" dir="ltr" id="%1$s-%2$d" name="%1$s[%2$d]" value="%4$s" maxlength="128">',
                    esc_attr($option), $id, esc_html($partner['name']), esc_attr($groups[$id] ?? ''));
                if ($provider === 'rubika') {
                    $group = $groups[$id] ?? '';
                    $can_send = $group !== '' && ziggurat_rubika_statement_partner($group) === $id;
                    printf(' <button type="submit" class="button button-secondary" form="ziggurat-intro-%d" aria-label="%s"%s>ارسال پیام معرفی</button>',
                        $id, esc_attr('ارسال پیام معرفی برای ' . $partner['name']), disabled(!$can_send, true, false));
                    if ($can_send) $intro_forms[] = $id;
                    $state = get_post_meta($id, '_ziggurat_rubika_intro', true);
                    if ($group !== '' && ($state['group'] ?? '') === $group && isset($intro_labels[$state['status'] ?? ''])) {
                        printf('<p class="description">%s</p>', esc_html($intro_labels[$state['status']]));
                    }
                }
                echo '</td></tr>';
            }
            if (!$partners) echo '<tr><td>همکاری برای تنظیم گروه در دسترس نیست.</td></tr>';
            echo '</tbody></table>';
        }
        submit_button();
        echo '</form>';
        foreach ($intro_forms as $id) {
            printf('<form id="ziggurat-intro-%1$d" method="post" action="%2$s"><input type="hidden" name="action" value="ziggurat_rubika_send_intro"><input type="hidden" name="partner_id" value="%1$d">',
                $id, esc_url(admin_url('admin-post.php')));
            wp_nonce_field('ziggurat_rubika_send_intro_' . $id);
            echo '</form>';
        }
        echo '</div>';
    });
});
