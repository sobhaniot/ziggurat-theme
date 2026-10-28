<?php
if (!defined('ABSPATH')) { exit; }

// Issued only after the server accepts the existing contact email operation.
function zigurat_guardian_issue_lead_receipt()
{
    $token = bin2hex(random_bytes(16));
    set_transient('zig_guardian_receipt_' . $token, 1, 600);
    setcookie('zig_guardian_lead', $token, array('expires' => time() + 600, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax'));
}

function zigurat_guardian_consume_lead_receipt($token)
{
    if (!is_string($token) || !preg_match('/^[a-f0-9]{32}$/D', $token)) { return ''; }
    if (!get_transient('zig_guardian_receipt_' . $token)) { return ''; }
    if (!delete_transient('zig_guardian_receipt_' . $token)) { return ''; }
    return $token;
}

function zigurat_guardian_analytics_public()
{
    if (is_admin() || wp_parse_url(home_url('/'), PHP_URL_HOST) !== 'ziggurat.eleknow.com' ||
        (function_exists('zigurat_is_private_management_page') && zigurat_is_private_management_page()) || is_page_template('page-login.php')) { return false; }
    return true;
}

function zigurat_guardian_capture_receipt()
{
    if (!zigurat_guardian_analytics_public()) { return; }
    $GLOBALS['zigurat_guardian_receipt'] = zigurat_guardian_consume_lead_receipt($_COOKIE['zig_guardian_lead'] ?? '');
    if ($GLOBALS['zigurat_guardian_receipt'] !== '') { setcookie('zig_guardian_lead', '', array('expires' => time() - 3600, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax')); }
}
add_action('template_redirect', 'zigurat_guardian_capture_receipt');

function zigurat_guardian_enqueue_analytics()
{
    if (!zigurat_guardian_analytics_public()) { return; }
    $path = get_template_directory() . '/assets/js/guardian-analytics.js';
    wp_enqueue_script('zigurat-guardian-analytics', get_template_directory_uri() . '/assets/js/guardian-analytics.js', array(), filemtime($path), true);
    $receipt = $GLOBALS['zigurat_guardian_receipt'] ?? '';
    wp_add_inline_script('zigurat-guardian-analytics', 'window.zigguratGuardianAnalytics=' . wp_json_encode(array('leadReceipt' => $receipt, 'consentRequired' => true)) . ';', 'before');
}
add_action('wp_enqueue_scripts', 'zigurat_guardian_enqueue_analytics');
