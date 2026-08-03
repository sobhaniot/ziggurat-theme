<?php
if (!defined('ABSPATH')) {
    exit;
}
/**
 * بررسی ورود کاربر انبار
 */
function zigurat_inventory_login()
{
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        return;
    }
    if (
        !isset($_POST['zig_inventory_login'])
    ) {
        return;
    }
    global $wpdb;
    $username = sanitize_text_field(
        $_POST['log']
    );
    $password = sanitize_text_field(
        $_POST['pwd']
    );
    $table_name = 'zigurat_users';
    $user = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE username = %s",
            $username
        )
    );
    if (
        $user &&
        $password === $user->password
    ) {
        setcookie(
            'zigpass',
            $password,
            time() + (86400 * 30),
            "/"
        );
        setcookie(
            'ziguser',
            $username,
            time() + (86400 * 30),
            "/"
        );
        wp_redirect(
            home_url("/")
        );
        exit;
    } else {
        set_transient(
            'zig_login_error',
            'نام کاربری یا رمز عبور اشتباه است.',
            30
        );
    }
}
add_action(
    'init',
    'zigurat_inventory_login'
);
