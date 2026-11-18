<?php
if (!defined('ABSPATH')) exit;

function ziggurat_bot_debug_clean($text) {
    $text = (string) $text;
    foreach (['rubika', 'bale'] as $provider) {
        $token = ziggurat_bot_token($provider);
        if ($token !== '') $text = str_replace([$token, rawurlencode($token)], '[REDACTED]', $text);
    }
    $text = preg_replace('~https?://\S+~u', '[URL]', $text);
    return mb_substr(wp_strip_all_tags($text), 0, 500);
}

function ziggurat_bot_debug_event($type, array $details) {
    $events = (array) get_option('ziggurat_bot_debug_events', []);
    $events[] = ['time' => gmdate('c'), 'type' => $type, 'details' => $details];
    update_option('ziggurat_bot_debug_events', array_slice($events, -200), false);
}

function ziggurat_bot_debug_status($meta_id, $project_id, $key, $value) {
    if (strpos($key, '_ziggurat_rubika_notice_') !== 0 || !is_scalar($value)) return;
    $map = (array) get_post_meta($project_id, '_ziggurat_bot_notice_details', true);
    $entry = (array) ($map[$key] ?? []);
    $entry['status'] = sanitize_key((string) $value);
    $entry['updated_at'] = gmdate('c');
    $map[$key] = $entry;
    update_post_meta($project_id, '_ziggurat_bot_notice_details', array_slice($map, -80, null, true));
}
add_action('added_post_meta', 'ziggurat_bot_debug_status', 10, 4);
add_action('updated_post_meta', 'ziggurat_bot_debug_status', 10, 4);

function ziggurat_bot_debug_report($project_id = 0) {
    $query = new WP_Query([
        'post_type' => 'zig_work_project', 'post_status' => 'private',
        'posts_per_page' => 50, 'orderby' => 'modified', 'order' => 'DESC',
        'post__in' => $project_id ? [$project_id] : [],
    ]);
    $cards = [];
    $partners = function_exists('zigurat_workflow_project_partners')
        ? array_column(zigurat_workflow_project_partners(), 'name', 'id') : [];
    foreach ($query->posts as $post) {
        $record = zigurat_workflow_project_record($post->ID);
        if (!$record) continue;
        $notices = (array) get_post_meta($post->ID, '_ziggurat_bot_notice_details', true);
        foreach ((array) get_post_meta($post->ID) as $key => $values) {
            if (strpos($key, '_ziggurat_rubika_notice_') !== 0 || isset($notices[$key])) continue;
            $notices[$key] = ['status' => (string) ($values[0] ?? ''), 'legacy' => true];
        }
        $destinations = [];
        foreach (['rubika', 'bale'] as $provider) {
            $groups = (array) get_option(ziggurat_bot_group_option($provider), []);
            foreach ($record['work_items'] as $item) {
                foreach ($item['partner_ids'] as $id) {
                    $destinations[$provider . '-' . $id] = [
                        'provider' => $provider, 'type' => 'installer', 'name' => $partners[$id] ?? (string) $id,
                        'group_configured' => !empty($groups[$id]),
                    ];
                }
            }
            $destinations[$provider . '-company'] = [
                'provider' => $provider, 'type' => 'company', 'name' => $record['client'] ?? '',
                'group_configured' => ziggurat_rubika_company_group($record['client'] ?? '', $provider) !== '',
            ];
        }
        $jobs = ziggurat_browser_render_jobs($record);
        $cards[] = [
            'id' => $post->ID, 'title' => $record['title'], 'stage' => $record['stage'],
            'destinations' => array_values($destinations), 'notices' => array_values($notices),
            'images_waiting_for_browser' => count($jobs),
        ];
    }
    $queue = [];
    foreach ((array) _get_cron_array() as $timestamp => $hooks) {
        foreach (['ziggurat_rubika_send_project', 'ziggurat_rubika_group_intro', 'ziggurat_rubika_poll_statements'] as $hook) {
            foreach ($hooks[$hook] ?? [] as $job) {
                $args = $job['args'];
                if ($project_id && $hook === 'ziggurat_rubika_send_project' && (int) ($args[0] ?? 0) !== $project_id) continue;
                $queue[] = ['scheduled_at' => gmdate('c', $timestamp), 'overdue_seconds' => max(0, time() - $timestamp),
                    'hook' => $hook, 'args' => $args];
            }
        }
    }
    return [
        'generated_at' => gmdate('c'), 'delivery_providers' => ['rubika', 'bale'],
        'tokens_configured' => ['rubika' => ziggurat_bot_token('rubika') !== '', 'bale' => ziggurat_bot_token('bale') !== ''],
        'wp_cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
        'cards' => $cards, 'queue' => array_slice($queue, 0, 200),
        'recent_events' => array_reverse((array) get_option('ziggurat_bot_debug_events', [])),
    ];
}

add_action('rest_api_init', static function () {
    register_rest_route('ziggurat-bot/v1', '/diagnostics', [
        'methods' => 'GET',
        'permission_callback' => static function () { return current_user_can('manage_options'); },
        'args' => ['project_id' => ['sanitize_callback' => 'absint']],
        'callback' => static function ($request) {
            $response = rest_ensure_response(ziggurat_bot_debug_report(absint($request['project_id'])));
            $response->header('Cache-Control', 'private, no-store');
            return $response;
        },
    ]);
});

add_action('admin_menu', static function () {
    add_management_page('وضعیت ارسال بات', 'وضعیت ارسال بات', 'manage_options', 'ziggurat-bot-diagnostics', static function () {
        if (!current_user_can('manage_options')) return;
        $id = absint($_GET['project_id'] ?? 0);
        echo '<div class="wrap"><h1>وضعیت ارسال بات</h1><form method="get"><input type="hidden" name="page" value="ziggurat-bot-diagnostics"><label>شناسه کارت <input type="number" name="project_id" min="1" value="' . esc_attr($id ?: '') . '"></label> <button class="button">بررسی</button></form>';
        echo '<p>گزارش ۵۰ کارت اخیر، صف ارسال، تصویرهای منتظر مرورگر و خطاهای بات. وضعیت sent یعنی پیام‌رسان ارسال را پذیرفته است.</p>';
        echo '<pre dir="ltr" style="white-space:pre-wrap;background:#fff;padding:20px;max-height:75vh;overflow:auto">' . esc_html(wp_json_encode(ziggurat_bot_debug_report($id), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre></div>';
    });
});

add_action('http_api_debug', static function ($response, $context, $class, $args, $url) {
    if ($context !== 'response') return;
    $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
    if (!in_array($host, ['botapi.rubika.ir', 'tapi.bale.ai'], true)) return;
    $method = basename((string) wp_parse_url($url, PHP_URL_PATH));
    $status = is_wp_error($response) ? 0 : wp_remote_retrieve_response_code($response);
    $body = is_wp_error($response) ? [] : json_decode(wp_remote_retrieve_body($response), true);
    if (!is_wp_error($response) && $status === 200 && (!empty($body['ok']) || ($body['status'] ?? '') === 'OK')) return;
    ziggurat_bot_debug_event('api_error', [
        'provider' => $host === 'tapi.bale.ai' ? 'bale' : 'rubika',
        'method' => sanitize_key($method), 'http_status' => $status,
        'code' => is_wp_error($response) ? $response->get_error_code() : ($body['error_code'] ?? $body['status'] ?? ''),
        'message' => ziggurat_bot_debug_clean(is_wp_error($response) ? $response->get_error_message() : ($body['description'] ?? $body['message'] ?? '')),
    ]);
}, 10, 5);
