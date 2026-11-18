<?php
if (!defined('ABSPATH')) exit;

add_filter('cron_schedules', static function ($schedules) {
    $schedules['ziggurat_statement_minute'] = ['interval' => 60, 'display' => 'Ziggurat Rubika statements'];
    return $schedules;
});

add_action('init', static function () {
    $hook = 'ziggurat_rubika_poll_statements';
    $scheduled = wp_next_scheduled($hook);
    $should_poll = get_option('ziggurat_rubika_statements_enabled') || ziggurat_rubika_company_discovery_active('rubika') || ziggurat_rubika_company_discovery_active('bale');
    if ($should_poll) {
        if (!$scheduled) wp_schedule_event(time(), 'ziggurat_statement_minute', $hook);
    } elseif ($scheduled) {
        wp_clear_scheduled_hook($hook);
    }
});

function ziggurat_rubika_statement_api($token, $method, array $payload = [], $provider = 'rubika') {
    $base = $provider === 'bale' ? 'https://tapi.bale.ai/bot' . $token : 'https://botapi.rubika.ir/v3/' . rawurlencode($token);
    if ($provider === 'bale' && isset($payload['reply_to_message_id'])) {
        $payload['reply_to_message_id'] = (int) $payload['reply_to_message_id'];
    }
    $response = wp_remote_post($base . '/' . $method, [
        'timeout' => 8,
        'redirection' => 0,
        'headers' => ['Content-Type' => 'application/json'],
        'body' => wp_json_encode((object) $payload),
    ]);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) return null;
    $body = json_decode(wp_remote_retrieve_body($response), true);
    if ($provider === 'bale') {
        $result = $body['result'] ?? null;
        if (empty($body['ok']) || !is_array($result)) return null;
        if ($method === 'getMe') return ['bot' => $result];
        if ($method === 'getChat') {
            return ['chat' => [
                'chat_id' => (string) ($result['id'] ?? ''),
                'chat_type' => in_array($result['type'] ?? '', ['group', 'supergroup'], true) ? 'Group' : '',
            ]];
        }
        return $result;
    }
    return ($body['status'] ?? '') === 'OK' && is_array($body['data'] ?? null) ? $body['data'] : null;
}

function ziggurat_rubika_statement_command($text, $username) {
    if (!is_string($text) || strlen($text) > 256) return false;
    $text = str_replace(['ي', 'ك'], ['ی', 'ک'], $text);
    $text = trim(preg_replace('/[\s\x{200C}\x{200E}\x{200F}]+/u', ' ', $text) ?? '');
    $mention = '@' . $username . ' ';
    if (strncasecmp($text, $mention, strlen($mention)) === 0) {
        $text = substr($text, strlen($mention));
    }
    return in_array($text, ['حساب', 'صورت حساب', '/حساب', '/صورت حساب'], true);
}

function ziggurat_bale_is_group($group) {
    if (!ziggurat_bot_valid_group('bale', $group)) return false;
    $chat = ziggurat_rubika_statement_api(ziggurat_bot_token('bale'), 'getChat', ['chat_id' => $group], 'bale')['chat'] ?? [];
    return ($chat['chat_type'] ?? '') === 'Group' && ($chat['chat_id'] ?? '') === $group;
}

function ziggurat_rubika_statement_partner($group, $provider = 'rubika') {
    $matches = array_keys((array) get_option(ziggurat_bot_group_option($provider), []), $group, true);
    $id = count($matches) === 1 ? (int) $matches[0] : 0;
    return $id && function_exists('zigurat_statement_partner') && zigurat_statement_partner($id) ? $id : 0;
}

function ziggurat_rubika_statement_confirmation($id, $saved_at) {
    $name = trim(get_post_meta($id, '_application_first_name', true) . ' '
        . get_post_meta($id, '_application_last_name', true));
    if ($name === '') $name = get_the_title($id);
    $recipient = $name !== '' ? ' «' . $name . '»' : '';
    $date_text = '';
    if ($saved_at && function_exists('zigurat_inventory_gregorian_to_jalali')) {
        $date = (new DateTimeImmutable('@' . $saved_at))->setTimezone(new DateTimeZone('Asia/Tehran'));
        $jalali = zigurat_inventory_gregorian_to_jalali((int) $date->format('Y'), (int) $date->format('m'), (int) $date->format('d'));
        $digits = array_combine(str_split('0123456789'), ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹']);
        $date_text = ' (به‌روزشده در ' . strtr(sprintf('%04d/%02d/%02d', ...$jalali), $digits) . ')';
    }
    return "صورت‌حساب{$recipient}{$date_text} برای شما ارسال گردید.\nلطفاً بررسی نمایید.";
}

function ziggurat_rubika_statement_reply($token, array $update, $username, $provider = 'rubika') {
    $message = $update['new_message'] ?? [];
    $group = $update['chat_id'] ?? '';
    $message_id = $message['message_id'] ?? '';
    if (($update['type'] ?? '') !== 'NewMessage' || ($message['sender_type'] ?? '') !== 'User' || !empty($message['is_edited'])
        || !is_string($group) || !ziggurat_bot_valid_group($provider, $group)
        || !is_string($message_id) || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $message_id)
        || (int) ($message['time'] ?? 0) < time() - 900
        || !ziggurat_rubika_statement_command($message['text'] ?? '', $username)) return;
    $id = ziggurat_rubika_statement_partner($group, $provider);
    if (!$id || !function_exists('zigurat_resolve_private_application_file_path')) return;
    $seen = 'ziggurat_stmt_' . hash('sha256', $provider . ':' . $group . ':' . $message_id);
    if (get_transient($seen)) return;

    $chat = ziggurat_rubika_statement_api($token, 'getChat', ['chat_id' => $group], $provider)['chat'] ?? [];
    if (($chat['chat_type'] ?? '') !== 'Group' || ($chat['chat_id'] ?? '') !== $group) return;

    $fields = ['chat_id' => $group, 'reply_to_message_id' => $message_id];
    $path = zigurat_resolve_private_application_file_path(get_post_meta($id, '_zigurat_statement_sample_pdf', true));
    set_transient($seen, 1, HOUR_IN_SECONDS);
    if (!$path) {
        ziggurat_rubika_statement_api($token, 'sendMessage', $fields + ['text' => 'صورت‌حساب هنوز در سایت موجود نیست.'], $provider);
        return;
    }
    $saved_at = is_file($path) ? filemtime($path) : false;
    if ($provider === 'bale') {
        if (ziggurat_rubika_statement_partner($group, $provider) !== $id) return;
        $sent = ziggurat_bot_upload_image('https://tapi.bale.ai/bot' . $token . '/sendDocument',
            $fields + ['caption' => ziggurat_rubika_statement_confirmation($id, $saved_at)],
            $path, 'document', 'application/pdf', 'collaborator-statement.pdf');
        if (empty($sent[1]['result']['message_id'])) {
            ziggurat_rubika_statement_api($token, 'sendMessage', $fields + ['text' => 'ارسال صورت‌حساب تأیید نشد.'], $provider);
        }
        return;
    }

    $ticket = ziggurat_rubika_statement_api($token, 'requestSendFile', ['type' => 'File']);
    $url = $ticket['upload_url'] ?? '';
    $host = is_string($url) ? strtolower((string) wp_parse_url($url, PHP_URL_HOST)) : '';
    $file_id = '';
    if (($host === 'rubika.ir' || substr($host, -10) === '.rubika.ir')
        && !wp_parse_url($url, PHP_URL_USER) && !wp_parse_url($url, PHP_URL_PASS)
        && in_array(wp_parse_url($url, PHP_URL_PORT), [null, 443], true)) {
        $uploaded = ziggurat_bot_upload_image($url, [], $path, 'file', 'application/pdf', 'collaborator-statement.pdf');
        if (($uploaded[0] ?? 0) === 200) {
            $file_id = $uploaded[1]['data']['file_id'] ?? $uploaded[1]['file_id'] ?? '';
        }
    }
    if (ziggurat_rubika_statement_partner($group) !== $id) return;
    if (is_string($file_id) && $file_id !== '') {
        $sent = ziggurat_rubika_statement_api($token, 'sendFile', $fields + ['file_id' => $file_id]);
        if (!empty($sent['message_id'])) {
            if (ziggurat_rubika_statement_partner($group) === $id) {
                ziggurat_rubika_statement_api($token, 'sendMessage', [
                    'chat_id' => $group,
                    'reply_to_message_id' => (string) $sent['message_id'],
                    'text' => ziggurat_rubika_statement_confirmation($id, $saved_at),
                ]);
            }
            return;
        }
    }
    ziggurat_rubika_statement_api($token, 'sendMessage', $fields + ['text' => 'ارسال صورت‌حساب انجام نشد. لطفاً دوباره درخواست بدهید.']);
}

add_action('ziggurat_rubika_poll_statements', static function () {
    foreach (['rubika', 'bale'] as $provider) {
        ziggurat_bot_poll_provider($provider);
    }
});

function ziggurat_bot_poll_provider($provider) {
    $statements_enabled = (bool) get_option('ziggurat_rubika_statements_enabled');
    $discovery_enabled = ziggurat_rubika_company_discovery_active($provider);
    if (!$statements_enabled && !$discovery_enabled) return;
    if ($statements_enabled && !function_exists('zigurat_statement_partner')) return;
    $token = ziggurat_bot_token($provider);
    if (!$token) return;
    $lock = ziggurat_bot_option($provider, 'statement_poll_lock');
    if (!add_option($lock, time(), '', false)) return;
    // Release the claim on normal completion and on a PHP shutdown.
    $locked = true;
    register_shutdown_function(static function () use ($lock, &$locked) {
        if ($locked) delete_option($lock);
    });
    try {
        $bot_key = hash('sha256', $token);
        $identity = (array) get_transient(ziggurat_bot_option($provider, 'statement_identity'));
        if (($identity['key'] ?? '') !== $bot_key) {
            $bot = ziggurat_rubika_statement_api($token, 'getMe', [], $provider)['bot'] ?? [];
            $username = ltrim((string) ($bot['username'] ?? ''), '@');
            if (!preg_match('/^[A-Za-z0-9_]{1,64}$/D', $username)) return;
            $identity = ['key' => $bot_key, 'username' => $username];
            set_transient(ziggurat_bot_option($provider, 'statement_identity'), $identity, HOUR_IN_SECONDS);
        }
        $state_key = ziggurat_bot_option($provider, 'statement_cursor');
        $state = (array) get_option($state_key, []);
        $args = ['limit' => 10];
        if ($provider === 'bale') {
            $args['timeout'] = 0;
            if (($state['key'] ?? '') === $bot_key && !empty($state['offset'])) $args['offset'] = (int) $state['offset'];
            $updates = ziggurat_rubika_statement_api($token, 'getUpdates', $args, $provider);
            if (!is_array($updates)) return;
            $deadline = microtime(true) + 12;
            foreach ($updates as $entry) {
                if (microtime(true) >= $deadline) break;
                if (!is_array($entry) || !isset($entry['update_id'])) continue;
                $message = $entry['message'] ?? [];
                if (in_array($message['chat']['type'] ?? '', ['group', 'supergroup'], true) && empty($message['from']['is_bot'])) {
                    $update = ['type' => 'NewMessage', 'chat_id' => (string) ($message['chat']['id'] ?? ''), 'new_message' => [
                        'sender_type' => 'User', 'message_id' => (string) ($message['message_id'] ?? ''),
                        'time' => (int) ($message['date'] ?? 0), 'text' => $message['text'] ?? '',
                    ]];
                    ziggurat_rubika_handle_company_group_discovery($token, $update, $identity['username'], $provider);
                    if ($statements_enabled) ziggurat_rubika_statement_reply($token, $update, $identity['username'], $provider);
                }
                update_option($state_key, ['key' => $bot_key, 'offset' => (int) $entry['update_id'] + 1], false);
            }
            return;
        }
        if (($state['key'] ?? '') === $bot_key && !empty($state['offset'])) $args['offset_id'] = $state['offset'];
        $batch = ziggurat_rubika_statement_api($token, 'getUpdates', $args);
        if (!is_array($batch['updates'] ?? null)) return;
        $deadline = microtime(true) + 12;
        foreach ($batch['updates'] as $update) {
            if (microtime(true) >= $deadline) return;
            if (!is_array($update)) continue;
            ziggurat_rubika_handle_company_group_discovery($token, $update, $identity['username']);
            if ($statements_enabled && get_option(ziggurat_bot_group_option('rubika'), [])) {
                ziggurat_rubika_statement_reply($token, $update, $identity['username']);
            }
        }
        $next = $batch['next_offset_id'] ?? '';
        if (is_string($next) && $next !== '') update_option($state_key, ['key' => $bot_key, 'offset' => $next], false);
    } finally {
        delete_option($lock);
        $locked = false;
    }
}
