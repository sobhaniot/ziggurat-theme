<?php
if (!defined('ABSPATH')) exit;

function ziggurat_rubika_company_key($name) {
    $name = trim(preg_replace('/\s+/u', ' ', (string) $name) ?? '');
    return $name === '' ? '' : md5($name);
}

function ziggurat_rubika_company_group($name, $provider = null) {
    $provider = $provider ?? ziggurat_bot_provider();
    $key = ziggurat_rubika_company_key($name);
    if ($key === '') return '';
    $groups = (array) get_option(ziggurat_bot_option($provider, 'company_groups'), []);
    $entry = (array) ($groups[$key] ?? []);
    return (string) ($entry['group'] ?? '');
}

function ziggurat_rubika_available_companies() {
    global $wpdb;
    $companies = [];
    if (function_exists('zigurat_invoices_table_name')) {
        $names = $wpdb->get_col(
            'SELECT DISTINCT customer_name FROM ' . zigurat_invoices_table_name()
            . " WHERE document_type = 'invoice' AND customer_name <> '' ORDER BY customer_name"
        );
        foreach ($names as $customer_name) {
            $name = trim((string) $customer_name);
            $key = ziggurat_rubika_company_key($name);
            if ($key !== '') $companies[$key] = $name;
        }
    }
    asort($companies, SORT_NATURAL | SORT_FLAG_CASE);
    return $companies;
}

function ziggurat_rubika_company_discovery_rows($provider = null) {
    $provider = $provider ?? ziggurat_bot_provider();
    $companies = [];
    $groups = (array) get_option(ziggurat_bot_option($provider, 'company_groups'), []);
    foreach ($groups as $key => $entry) {
        if (empty($entry['group']) && empty($entry['selected'])) continue;
        $name = trim((string) ($entry['name'] ?? ''));
        if ($name !== '' && preg_match('/^[a-f0-9]{32}$/D', (string) $key)) $companies[$key] = $name;
    }
    asort($companies, SORT_NATURAL | SORT_FLAG_CASE);
    $codes = (array) get_option(ziggurat_bot_option($provider, 'company_discovery_codes'), []);
    $partner_codes = (array) get_option(ziggurat_bot_option($provider, 'partner_discovery_codes'), []);
    $used = array_flip(array_values(array_filter(array_merge($codes, $partner_codes), 'is_string')));
    $changed = false;
    foreach ($companies as $key => $name) {
        if (!empty($groups[$key]['group'])) {
            if (isset($codes[$key])) { unset($codes[$key]); $changed = true; }
            continue;
        }
        if (!isset($codes[$key]) || !preg_match('/^[0-9]{6}$/D', (string) $codes[$key])) {
            do { $code = (string) wp_rand(100000, 999999); } while (isset($used[$code]));
            $codes[$key] = $code;
            $used[$code] = true;
            $changed = true;
        }
    }
    foreach (array_keys($codes) as $key) {
        if (!isset($companies[$key]) || !empty($groups[$key]['group'])) { unset($codes[$key]); $changed = true; }
    }
    if ($changed) update_option(ziggurat_bot_option($provider, 'company_discovery_codes'), $codes, false);
    ziggurat_rubika_schedule_discovery_poll(array_merge($codes, $partner_codes), $provider);
    return array_map(static function ($key, $name) use ($codes, $groups) {
        return ['key' => $key, 'name' => $name, 'group' => (string) ($groups[$key]['group'] ?? ''), 'code' => (string) ($codes[$key] ?? '')];
    }, array_keys($companies), array_values($companies));
}

function ziggurat_rubika_partner_discovery_rows($provider = null) {
    $provider = $provider ?? ziggurat_bot_provider();
    $partners = function_exists('zigurat_workflow_project_partners') ? zigurat_workflow_project_partners() : [];
    $groups = (array) get_option(ziggurat_bot_group_option($provider), []);
    $codes = (array) get_option(ziggurat_bot_option($provider, 'partner_discovery_codes'), []);
    $company_codes = (array) get_option(ziggurat_bot_option($provider, 'company_discovery_codes'), []);
    $used = array_flip(array_values(array_filter(array_merge($codes, $company_codes), 'is_string')));
    $rows = [];
    $changed = false;
    foreach ($partners as $partner) {
        $id = absint($partner['id'] ?? 0);
        if (!$id || !array_key_exists($id, $groups)) continue;
        $group = (string) ($groups[$id] ?? '');
        if ($group !== '') {
            if (isset($codes[$id])) { unset($codes[$id]); $changed = true; }
            continue;
        }
        if (!isset($codes[$id]) || !preg_match('/^[0-9]{6}$/D', (string) $codes[$id])) {
            do { $code = (string) wp_rand(100000, 999999); } while (isset($used[$code]));
            $codes[$id] = $code;
            $used[$code] = true;
            $changed = true;
        }
        $rows[$id] = ['id' => $id, 'name' => (string) ($partner['name'] ?? ''), 'group' => $group, 'code' => (string) $codes[$id]];
    }
    foreach (array_keys($codes) as $id) {
        if (!isset($rows[$id])) { unset($codes[$id]); $changed = true; }
    }
    if ($changed) update_option(ziggurat_bot_option($provider, 'partner_discovery_codes'), $codes, false);
    ziggurat_rubika_schedule_discovery_poll(array_merge($codes, $company_codes), $provider);
    return array_values($rows);
}

function ziggurat_rubika_schedule_discovery_poll($codes, $provider) {
    if ($codes && function_exists('ziggurat_bot_token') && ziggurat_bot_token($provider)
        && !wp_next_scheduled('ziggurat_rubika_poll_statements')) {
        wp_schedule_event(time(), 'ziggurat_statement_minute', 'ziggurat_rubika_poll_statements');
    }
}

function ziggurat_rubika_company_discovery_active($provider = null) {
    $provider = $provider ?? ziggurat_bot_provider();
    return (bool) get_option(ziggurat_bot_option($provider, 'company_discovery_codes'), [])
        || (bool) get_option(ziggurat_bot_option($provider, 'partner_discovery_codes'), []);
}

function ziggurat_rubika_company_discovery_command($text, $username, $codes) {
    if (!is_string($text) || strlen($text) > 256 || !preg_match('/^[A-Za-z0-9_]{1,64}$/D', $username)) return '';
    $text = trim(preg_replace('/[\s\x{200C}\x{200E}\x{200F}]+/u', ' ', $text) ?? '');
    if (!preg_match('/^@' . preg_quote($username, '/') . '\s+([0-9]{6})$/iD', $text, $match)) return '';
    foreach ((array) $codes as $key => $code) {
        if (hash_equals((string) $code, $match[1])) return (string) $key;
    }
    return '';
}

function ziggurat_rubika_handle_company_group_discovery($token, array $update, $username, $provider = 'rubika') {
    $message = $update['new_message'] ?? [];
    $group = $update['chat_id'] ?? '';
    $message_id = $message['message_id'] ?? '';
    if (($update['type'] ?? '') !== 'NewMessage' || ($message['sender_type'] ?? '') !== 'User' || !empty($message['is_edited'])
        || !is_string($group) || !ziggurat_bot_valid_group($provider, $group)
        || !is_string($message_id) || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $message_id)
        || (int) ($message['time'] ?? 0) < time() - 900) return false;
    $company_codes = (array) get_option(ziggurat_bot_option($provider, 'company_discovery_codes'), []);
    $partner_codes = (array) get_option(ziggurat_bot_option($provider, 'partner_discovery_codes'), []);
    $key = ziggurat_rubika_company_discovery_command($message['text'] ?? '', $username, $company_codes);
    $type = 'company';
    if ($key === '') {
        $key = ziggurat_rubika_company_discovery_command($message['text'] ?? '', $username, $partner_codes);
        $type = 'partner';
    }
    if ($key === '') return false;

    $seen = 'ziggurat_company_group_code_' . hash('sha256', $provider . ':' . $group . ':' . $message_id);
    if (get_transient($seen)) return true;
    $groups = $type === 'company'
        ? (array) get_option(ziggurat_bot_option($provider, 'company_groups'), [])
        : (array) get_option(ziggurat_bot_group_option($provider), []);
    $codes = $type === 'company' ? $company_codes : $partner_codes;
    if (!isset($codes[$key]) || !empty($type === 'company' ? ($groups[$key]['group'] ?? '') : ($groups[$key] ?? ''))) return false;
    $chat = ziggurat_rubika_statement_api($token, 'getChat', ['chat_id' => $group], $provider)['chat'] ?? [];
    if (($chat['chat_type'] ?? '') !== 'Group' || ($chat['chat_id'] ?? '') !== $group) return false;

    if ($type === 'company') {
        $groups[$key] = ['name' => (string) ($groups[$key]['name'] ?? ''), 'group' => $group];
        update_option(ziggurat_bot_option($provider, 'company_groups'), $groups, false);
        $name = (string) ($groups[$key]['name'] ?? '');
        $codes_option = ziggurat_bot_option($provider, 'company_discovery_codes');
    } else {
        $groups[(int) $key] = $group;
        update_option(ziggurat_bot_group_option($provider), $groups, false);
        $partner_names = array_column(function_exists('zigurat_workflow_project_partners') ? zigurat_workflow_project_partners() : [], 'name', 'id');
        $name = (string) ($partner_names[(int) $key] ?? '');
        $codes_option = ziggurat_bot_option($provider, 'partner_discovery_codes');
    }
    unset($codes[$key]);
    update_option($codes_option, $codes, false);
    set_transient($seen, 1, DAY_IN_SECONDS);
    $target = $type === 'company' ? 'شرکت' : 'نصاب';
    ziggurat_rubika_statement_api($token, 'sendMessage', [
        'chat_id' => $group,
        'reply_to_message_id' => $message_id,
        'text' => "گروه {$target} در تنظیمات بات ثبت شد." . ($name !== '' ? "\n{$target}: {$name}" : ''),
    ], $provider);
    return true;
}
