<?php
if (!defined('ABSPATH')) {
    exit;
}
/**
 * ساخت Cookie های سیستم انبارداری
 */
function zigurat_inventory_cookies()
{
    $cookie_lifetime = time() + (86400 * 30);
    if (!isset($_COOKIE['zigpass'])) {
        setcookie(
            'zigpass',
            'ff',
            $cookie_lifetime,
            "/"
        );
    }
    if (!isset($_COOKIE['ziguser'])) {
        setcookie(
            'ziguser',
            'unknown',
            $cookie_lifetime,
            "/"
        );
    }
}
add_action(
    'init',
    'zigurat_inventory_cookies'
);
