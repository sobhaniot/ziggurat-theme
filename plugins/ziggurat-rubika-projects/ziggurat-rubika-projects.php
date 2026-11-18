<?php
/**
 * Plugin Name: Ziggurat Rubika Project Notices
 * Version: 1.13.4
 */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/handoff-image.php';
require_once __DIR__ . '/browser-render.php';
require_once __DIR__ . '/statement-requests.php';
require_once __DIR__ . '/group-discovery.php';
require_once __DIR__ . '/group-introductions.php';
require_once __DIR__ . '/diagnostics.php';

add_action('wp_enqueue_scripts', function () {
    if (!function_exists('zigurat_is_manager') || !zigurat_is_manager()
        || !wp_script_is('zigurat-project-handoff', 'enqueued')) return;
    wp_enqueue_script(
        'ziggurat-rubika-render-poller',
        plugin_dir_url(__FILE__) . 'browser-render-poller.js',
        ['zigurat-project-handoff'],
        '1.13.4',
        true
    );
}, 100);

add_filter('script_loader_src', function ($src, $handle) {
    if ($handle !== 'zigurat-project-handoff' || !wp_script_is('ziggurat-rubika-render-poller', 'enqueued')) return $src;
    return add_query_arg('ver', '1.13.4', plugin_dir_url(__FILE__) . 'project-handoff-render.js');
}, 10, 2);

function ziggurat_bot_provider() {
    return get_option('ziggurat_bot_provider') === 'bale' ? 'bale' : 'rubika';
}

function ziggurat_bot_token($provider) {
    if ($provider === 'rubika' && defined('ZIGGURAT_RUBIKA_TOKEN') && ZIGGURAT_RUBIKA_TOKEN) {
        return ZIGGURAT_RUBIKA_TOKEN;
    }
    return (string) get_option('ziggurat_bot_token_' . $provider, '');
}

function ziggurat_bot_group_option($provider) {
    return $provider === 'bale' ? 'ziggurat_bale_groups' : 'ziggurat_rubika_groups';
}

function ziggurat_bot_option($provider, $suffix) {
    return 'ziggurat_' . ($provider === 'bale' ? 'bale' : 'rubika') . '_' . $suffix;
}

function ziggurat_bot_valid_group($provider, $group) {
    return (bool) preg_match($provider === 'bale' ? '/^-?[1-9][0-9]{0,18}$/D' : '/^g0[A-Za-z0-9_-]{1,126}$/D', $group);
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
    if (!function_exists('zigurat_workflow_project_records')) return;
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
    foreach (['rubika', 'bale'] as $provider) {
        ziggurat_queue_provider_notice($id, $kind, $only_partner, $day, $provider);
    }
}

function ziggurat_queue_provider_notice($id, $kind, $only_partner, $day, $provider) {
    if (!ziggurat_bot_token($provider)) return;
    $project = zigurat_workflow_project_record($id);
    if (!$project) return;
    $groups = (array) get_option(ziggurat_bot_group_option($provider), []);
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
    $company_kind = '';
    if ($kind === 'finished') {
        $company_kind = 'company_finished';
    } elseif ($kind === 'started' || ($kind === 'created' && $project['stage'] === 'production')) {
        $company_kind = 'company_started';
    } elseif ($kind === 'saved' && $project['stage'] === 'production') {
        $company_kind = 'company_saved';
    }
    $company_group = $company_kind !== '' ? ziggurat_rubika_company_group($project['client'] ?? '', $provider) : '';
    $destination_kinds = array_fill_keys(array_keys($destinations), $kind);
    if ($company_group !== '') $destination_kinds[$company_group] = $company_kind;
    $event = $day !== '' ? 'daily-' . $day : wp_generate_uuid4();
    foreach ($destination_kinds as $group => $notice_kind) {
        $group = (string) $group;
        $notice_key = '_ziggurat_rubika_notice_' . hash('sha256', $provider . ':' . $group . ':' . $event);
        if ($day !== '' && !add_post_meta($id, $notice_key, 'scheduling', true)) continue;
        $creation_scope = $group === $company_group ? 'company:' : '';
        $recent_creation = 'ziggurat_rubika_created_' . hash('sha256', $provider . ':' . $creation_scope . $id . ':' . $group);
        if ($kind === 'started' && get_transient($recent_creation)) continue;
        $details = (array) get_post_meta($id, '_ziggurat_bot_notice_details', true);
        $details[$notice_key] = [
            'provider' => $provider,
            'recipient_type' => strpos($notice_kind, 'company_') === 0 ? 'company' : 'installer',
            'group' => (string) $group, 'kind' => $notice_kind,
            'event' => $event, 'created_at' => gmdate('c'),
        ];
        update_post_meta($id, '_ziggurat_bot_notice_details', array_slice($details, -80, null, true));
        $job = wp_schedule_single_event(time(), 'ziggurat_rubika_send_project',
            [$id, 0, (string) $group, $provider, $notice_kind, $event, $only_partner], true);
        if ($job === true && $kind === 'created') {
            set_transient($recent_creation, 1, MINUTE_IN_SECONDS);
        }
        if ($notice_kind === 'finished' || $notice_kind === 'company_finished') delete_transient($recent_creation);
        update_post_meta($id, '_ziggurat_rubika_notice_' . hash('sha256', $provider . ':' . $group . ':' . $event),
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
        } elseif ($current === 'production') {
            ziggurat_queue_project_notice($id, 'started');
            unset($saved[$id]);
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
    if (!$group || !in_array($provider, ['rubika', 'bale'], true)) return;
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
        $company_notice = in_array($kind, ['company_started', 'company_saved', 'company_finished'], true);
        $groups = (array) get_option(ziggurat_bot_group_option($provider), []);
        $names = [];
        if ($company_notice) {
            if (ziggurat_rubika_company_group($project['client'] ?? '', $provider) !== $group) {
                update_post_meta($id, $key, 'destination_changed');
                return;
            }
        } else {
            $partners = array_column(zigurat_workflow_project_partners(), 'name', 'id');
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
        }
        if ($provider === 'bale' && !ziggurat_bale_is_group($group)) {
            update_post_meta($id, $key, 'destination_unverified');
            return;
        }
        if ($kind === 'work_received') {
            $payload = ziggurat_rubika_statement_api(ziggurat_bot_token($provider), 'sendMessage', [
                'chat_id' => $group,
                'text' => $project['title'] . ' — ' . implode('، ', $names) . ' — کارکرد گرفته شد',
            ], $provider);
            update_post_meta($id, $key, !empty($payload['message_id']) ? 'sent' : 'send_error');
                        return;
        }
        if ($company_notice) {
            $finished = $kind === 'company_finished';
            $cached = ziggurat_browser_render_file(ziggurat_browser_render_company_key($project, $finished));
            $temporary = !is_file($cached);
            $image = $temporary ? ziggurat_handoff_image($project, '', 0, ziggurat_company_handoff_fields($project, $finished)) : $cached;
        } else {
            $name = implode('، ', $names);
            $person_photo = ziggurat_browser_render_person_photo($names);
            $cached = ziggurat_browser_render_file(ziggurat_browser_render_key($project, $name, $person_photo['identity']));
            $temporary = !is_file($cached);
            $image = $temporary ? ziggurat_handoff_image($project, $name, $person_photo['id']) : $cached;
        }
        if (!$image) {
            ziggurat_bot_debug_event('render_error', ['project_id' => $id, 'provider' => $provider, 'kind' => $kind, 'attempt' => $attempt, 'cached_image_exists' => is_file($cached)]);
            if ($attempt < 3) {
                $retry = wp_schedule_single_event(time()+60, 'ziggurat_rubika_send_project',
                    [$id,$attempt+1,$group,$provider,$kind,$event,$only_partner],true);
                if ($retry === true) {
                    update_post_meta($id, $key, 'waiting_image');
                    return;
                }
            }
            update_post_meta($id, $key, 'render_error');
            return;
        }
        $caption = trim(wp_strip_all_tags($project['notes']));

        if ($kind === 'finished' || $kind === 'company_finished') $caption = 'پروژه «' . $project['title'] . '» خاتمه یافت.';
        if ($kind === 'company_started') $caption = 'اجرای پروژه «' . $project['title'] . '» شروع شد.';
        if ($kind === 'company_saved') $caption = 'برگهٔ اجرای پروژه «' . $project['title'] . '» به‌روزرسانی شد.';
        $token = ziggurat_bot_token($provider);
        try {
            if ($provider === 'bale') {
                $fields = ['chat_id'=>$group, 'from_chat_id'=>$group];
                if ($caption !== '') $fields['caption'] = wp_html_excerpt($caption,1024);
                $response = ziggurat_bot_upload_image('https://tapi.bale.ai/bot'.$token.'/sendPhoto',$fields,$image,'photo');
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
        ziggurat_bot_debug_event('delivery_result', [
            'project_id' => $id, 'provider' => $provider, 'kind' => $kind, 'http_status' => $status,
            'message_id' => (string) ($payload['data']['message_id'] ?? $payload['result']['message_id'] ?? ''),
            'error_code' => $payload['error_code'] ?? $payload['status'] ?? '',
            'description' => ziggurat_bot_debug_clean($payload['description'] ?? $payload['message'] ?? ''),
        ]);
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
        'sanitize_callback' => function ($value) { return $value === 'bale' ? 'bale' : 'rubika'; },
    ));
    foreach (['rubika', 'bale'] as $provider) {
        $company_option = ziggurat_bot_option($provider, 'company_groups');
        register_setting('ziggurat_bot', $company_option, array(
            'sanitize_callback' => static function ($values) use ($provider, $company_option) {
                $previous = (array) get_option($company_option, []);
                if ($values === null) return $previous;
                $values = (array) $values;
                $selected = $values['add_company'] ?? '';
                unset($values['add_company']);
                $groups = [];
                foreach ((array) $values as $key => $entry) {
                    if (!preg_match('/^[a-f0-9]{32}$/D', (string) $key) || !is_array($entry)) continue;
                    $name = sanitize_text_field((string) ($entry['name'] ?? ''));
                    $group = trim((string) ($entry['group'] ?? ''));
                    if ($name === '') continue;
                    if ($group !== '' && !ziggurat_bot_valid_group($provider, $group)) {
                        add_settings_error('ziggurat_bot', 'invalid_company_group', 'شناسهٔ یکی از گروه‌های شرکت معتبر نیست و ذخیره نشد.');
                        if (isset($previous[$key])) $groups[$key] = $previous[$key];
                        continue;
                    }
                    $groups[$key] = ['name' => $name, 'group' => $group, 'selected' => true];
                }
                if (is_string($selected) && $selected !== '' && !isset($groups[$selected])) {
                    $available = ziggurat_rubika_available_companies();
                    if (isset($available[$selected])) {
                        $groups[$selected] = ['name' => $available[$selected], 'group' => '', 'selected' => true];
                    }
                }
                return $groups;
            },
        ));
        $option = 'ziggurat_bot_token_' . $provider;
        register_setting('ziggurat_bot', $option, array(
            'sanitize_callback' => function ($value) use ($option, $provider) {
                $token = trim((string) $value);
                if ($token === '') return get_option($option, '');
                $pattern = $provider === 'bale' ? '/^[0-9]+:[A-Za-z0-9_-]{1,230}$/D' : '/^[a-zA-Z0-9:_-]{1,256}$/D';
                if (!preg_match($pattern, $token)) {
                    add_settings_error($option, 'invalid_token', 'قالب توکن معتبر نیست.');
                    return get_option($option, '');
                }
                return $token;
            },
        ));
        register_setting('ziggurat_bot', ziggurat_bot_group_option($provider), array(
            'sanitize_callback' => function ($values) use ($provider) {
                $groups = (array) get_option(ziggurat_bot_group_option($provider), []);
                if (!is_array($values)) return $groups;
                $add = absint($values['add_partner'] ?? 0);
                unset($values['add_partner']);
                foreach ((array) $values as $id => $value) {
                    if (!ctype_digit((string) $id) || !absint($id) || !is_scalar($value)) continue;
                    $group = trim((string) $value);
                    if ($group === '' || ziggurat_bot_valid_group($provider, $group)) {
                        $groups[absint($id)] = $group;
                    } elseif ($group !== '') {
                        add_settings_error('ziggurat_bot', 'invalid_group', 'یکی از شناسه‌های گروه معتبر نیست و ذخیره نشد.');
                    }
                }
                if ($add && !array_key_exists($add, $groups) && function_exists('zigurat_workflow_project_partners')
                    && isset(array_column(zigurat_workflow_project_partners(), 'name', 'id')[$add])) {
                    $groups[$add] = '';
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
        printf('<fieldset><legend>صورت‌حساب همکاران</legend><input type="hidden" name="ziggurat_rubika_statements_enabled" value="0"><label><input type="checkbox" name="ziggurat_rubika_statements_enabled" value="1"%s> پاسخ به درخواست صورت‌حساب در گروه‌های ثبت‌شده</label><p>PDF فعلی همکار به همان گروه فرستاده می‌شود و برای اعضای گروه قابل مشاهده است.</p></fieldset>',
            checked(get_option('ziggurat_rubika_statements_enabled'), 1, false));
        printf('<fieldset><legend>یادآوری روزانهٔ مجری</legend><input type="hidden" name="ziggurat_rubika_daily_enabled" value="0"><label><input type="checkbox" name="ziggurat_rubika_daily_enabled" value="1"%s> فعال‌سازی یادآوری روزانه برای کارت‌هایی که روی حالت روزانه هستند</label><p><label>ساعت تهران <input type="time" name="ziggurat_rubika_daily_time" value="%s" required></label></p></fieldset>',
            checked(get_option('ziggurat_rubika_daily_enabled'), 1, false),
            esc_attr(get_option('ziggurat_rubika_daily_time', '09:00')));
        echo '<label for="ziggurat-bot-provider">تنظیمات پیام‌رسان</label> <select id="ziggurat-bot-provider" name="ziggurat_bot_provider">';
        foreach (array('rubika' => 'روبیکا', 'bale' => 'بله') as $provider => $label) {
            printf('<option value="%s"%s>%s</option>', esc_attr($provider), selected(ziggurat_bot_provider(), $provider, false), esc_html($label));
        }
        echo '</select><p class="description">اعلان‌ها به تمام گروه‌های ثبت‌شده در روبیکا و بله ارسال می‌شوند.</p>';
        foreach (array('rubika' => 'روبیکا', 'bale' => 'بله') as $provider => $label) {
            $is_active = $provider === ziggurat_bot_provider();
            printf('<fieldset data-ziggurat-provider="%s"%s>', esc_attr($provider), $is_active ? '' : ' hidden disabled');
            $option = ziggurat_bot_group_option($provider);
            $company_option = ziggurat_bot_option($provider, 'company_groups');
            $groups = (array) get_option($option, array());
            $partner_discovery = array_column(ziggurat_rubika_partner_discovery_rows($provider), 'code', 'id');
            printf('<h2>%1$s</h2><label for="token-%2$s">توکن %1$s</label> <input id="token-%2$s" name="ziggurat_bot_token_%2$s" type="password" dir="ltr" autocomplete="new-password"> <span>%3$s</span>',
                esc_html($label), esc_attr($provider), ziggurat_bot_token($provider) ? 'توکن ثبت شده است. برای حفظ آن، کادر را خالی بگذارید.' : 'توکن ثبت نشده است.');
            if ($provider === 'rubika' && defined('ZIGGURAT_RUBIKA_TOKEN') && ZIGGURAT_RUBIKA_TOKEN) {
                echo '<p>توکن تعریف‌شده در تنظیمات سرور اولویت دارد.</p>';
            }
            echo '<h3>گروه نصاب‌ها</h3>';
            $available_partners = array_diff_key(array_column($partners, 'name', 'id'), $groups);
            if ($available_partners) {
                printf('<p><label for="ziggurat-add-partner-%1$s">نصاب</label> <select id="ziggurat-add-partner-%1$s" name="%2$s[add_partner]"><option value="">انتخاب نصاب</option>', esc_attr($provider), esc_attr($option));
                foreach ($available_partners as $id => $name) {
                    printf('<option value="%d">%s</option>', $id, esc_html($name));
                }
                echo '</select> <button type="submit" class="button">افزودن نصاب</button></p>';
            }
            echo '<table class="form-table"><tbody>';
            foreach ($partners as $partner) {
                $id = absint($partner['id']);
                if (!array_key_exists($id, $groups)) continue;
                printf('<tr><th scope="row"><label for="%1$s-%2$d">%3$s</label></th><td><input class="regular-text" dir="ltr" id="%1$s-%2$d" name="%1$s[%2$d]" value="%4$s" maxlength="128">',
                    esc_attr($option), $id, esc_html($partner['name']), esc_attr($groups[$id] ?? ''));
                {
                    $group = $groups[$id] ?? '';
                    if ($group === '' && !empty($partner_discovery[$id])) {
                        printf('<p class="description">کد شناسایی گروه نصاب: <code>%s</code> — بات را در گروه نصاب با این کد منشن کنید تا شناسه خودکار ذخیره شود.</p>', esc_html($partner_discovery[$id]));
                    }
                    if ($group !== '' && ziggurat_rubika_statement_partner($group, $provider) === $id) {
                        printf(' <button type="submit" class="button button-secondary" form="ziggurat-intro-%d-%s" aria-label="%s">ارسال پیام معرفی</button>',
                            $id, esc_attr($provider), esc_attr('ارسال پیام معرفی برای ' . $partner['name']));
                        $intro_forms[] = [$id, $provider];
                    }
                    $state = get_post_meta($id, '_' . ziggurat_bot_option($provider, 'intro'), true);
                    if ($group !== '' && ($state['group'] ?? '') === $group && isset($intro_labels[$state['status'] ?? ''])) {
                        printf('<p class="description">%s</p>', esc_html($intro_labels[$state['status']]));
                    }
                }
                echo '</td></tr>';
            }
            if (!array_intersect_key(array_column($partners, 'name', 'id'), $groups)) echo '<tr><td>هنوز نصابی به فهرست اضافه نشده است.</td></tr>';
            echo '</tbody></table>';
            {
                echo '<h3>گروه اعلان شرکت‌ها</h3><p class="description">در شروع و پایان اجرا و هنگام ذخیرهٔ کارت در ستون «در حال اجرا»، تصویر برگهٔ اجرا بدون مشخصات مجری برای گروه شرکت ارسال می‌شود. برای ثبت خودکار، کد را در همان گروه با منشن بات بفرستید.</p>';
                $company_rows = ziggurat_rubika_company_discovery_rows($provider);
                $selected_companies = array_column($company_rows, 'name', 'key');
                $available_companies = array_diff_key(ziggurat_rubika_available_companies(), $selected_companies);
                if ($available_companies) {
                    printf('<p><label for="ziggurat-add-company-%1$s">شرکت</label> <select id="ziggurat-add-company-%1$s" name="%2$s[add_company]"><option value="">انتخاب شرکت</option>', esc_attr($provider), esc_attr($company_option));
                    foreach ($available_companies as $key => $name) {
                        printf('<option value="%s">%s</option>', esc_attr($key), esc_html($name));
                    }
                    echo '</select> <button type="submit" class="button">افزودن به فهرست</button></p>';
                }
                echo '<table class="form-table"><tbody>';
                foreach ($company_rows as $company) {
                    $key = $company['key'];
                    printf('<tr><th scope="row">%s</th><td><input class="regular-text" dir="ltr" name="' . esc_attr($company_option) . '[%s][group]" value="%s" placeholder="شناسه گروه"> <input type="hidden" name="' . esc_attr($company_option) . '[%s][name]" value="%s">',
                        esc_html($company['name']), esc_attr($key), esc_attr($company['group']), esc_attr($key), esc_attr($company['name']));
                    if ($company['group'] === '' && $company['code'] !== '') {
                        printf('<p class="description">کد شناسایی گروه: <code>%s</code> — در گروه، بات را با این کد منشن کنید.</p>', esc_html($company['code']));
                    }
                    echo '</td></tr>';
                }
                if (!$company_rows) echo '<tr><td>هنوز شرکتی به فهرست اضافه نشده است.</td></tr>';
                echo '</tbody></table>';
            }
            echo '</fieldset>';
        }
        submit_button();
        echo '</form>';
        echo <<<'HTML'
<script>
(() => {
    const select = document.getElementById('ziggurat-bot-provider');
    const panels = select.form.querySelectorAll('[data-ziggurat-provider]');
    function showSelectedProvider() {
        panels.forEach((panel) => {
            const selected = panel.dataset.zigguratProvider === select.value;
            panel.hidden = !selected;
            panel.disabled = !selected;
        });
    }
    select.addEventListener('change', showSelectedProvider);
    showSelectedProvider();
})();
</script>
HTML;
        foreach ($intro_forms as [$id, $provider]) {
            printf('<form id="ziggurat-intro-%1$d-%3$s" method="post" action="%2$s"><input type="hidden" name="action" value="ziggurat_rubika_send_intro"><input type="hidden" name="partner_id" value="%1$d"><input type="hidden" name="provider" value="%3$s">',
                $id, esc_url(admin_url('admin-post.php')), esc_attr($provider));
            wp_nonce_field('ziggurat_rubika_send_intro_' . $id);
            echo '</form>';
        }
        echo '</div>';
    });
});
