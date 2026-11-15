<?php
// Run from the live WordPress installation: wp eval-file /path/to/announce.php
if (!defined('WP_CLI') || !WP_CLI) exit;

if (!function_exists('ziggurat_bot_token') || !function_exists('ziggurat_rubika_statement_partner')
    || !function_exists('ziggurat_rubika_statement_api') || !get_option('ziggurat_rubika_statements_enabled')) {
    WP_CLI::error('The Rubika statement feature must be active.');
}

$token = ziggurat_bot_token('rubika');
if (!$token) WP_CLI::error('The Rubika bot token is unavailable.');

$partners = array_column(zigurat_workflow_project_partners(), 'name', 'id');
$groups = (array) get_option(ziggurat_bot_group_option('rubika'), []);
$failed = 0;
$sent = 0;
foreach ($groups as $id => $group) {
    if (!is_string($group) || !preg_match('/^g0[A-Za-z0-9_-]{1,126}$/D', $group)
        || ziggurat_rubika_statement_partner($group) !== (int) $id || empty($partners[$id])) {
        WP_CLI::warning('Skipped an invalid or shared Rubika group.');
        ++$failed;
        continue;
    }
    $name = $partners[$id];
    $key = 'ziggurat_statement_intro_' . hash('sha256', $id . ':' . $group);
    if (!add_option($key, 'in_progress', '', false)) {
        WP_CLI::log('Already sent or pending verification: ' . $name);
        continue;
    }
    $result = ziggurat_rubika_statement_api($token, 'sendMessage', [
        'chat_id' => $group,
        'text' => "آقای {$name}، سلام. برای دریافت آخرین صورت‌حساب خود، در همین گروه پیام «/حساب» یا «/صورت حساب» را بفرستید. بات زیگورات فایل PDF صورت‌حساب را در پاسخ به همان پیام ارسال می‌کند.",
    ]);
    if (empty($result['message_id'])) {
        // An ambiguous API result may still have delivered the message.
        WP_CLI::warning('Delivery could not be confirmed: ' . $name);
        ++$failed;
        continue;
    }
    update_option($key, 'sent', false);
    WP_CLI::log('Sent: ' . $name);
    ++$sent;
}
WP_CLI::log(sprintf('Confirmed: %d; issues: %d', $sent, $failed));
if ($failed) WP_CLI::halt(1);
