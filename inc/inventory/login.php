<?php
if (!defined('ABSPATH')) {
    exit;
}

function zigurat_user_can_access_manager_panel($user = null)
{
    if (!$user) {
        if (!is_user_logged_in()) {
            return false;
        }
        $user = wp_get_current_user();
    }
    if (!($user instanceof WP_User) || !$user->exists()) {
        return false;
    }

    return user_can($user, 'manage_options') || in_array('contributor', (array) $user->roles, true);
}

function zigurat_is_manager()
{
    return zigurat_user_can_access_manager_panel();
}

function zigurat_is_panel_contributor($user = null)
{
    $user = $user ?: wp_get_current_user();
    return $user instanceof WP_User
        && $user->exists()
        && in_array('contributor', (array) $user->roles, true)
        && !user_can($user, 'manage_options');
}

function zigurat_manager_login_url()
{
    return home_url('/login/');
}

function zigurat_require_manager()
{
    if (!zigurat_is_manager()) {
        wp_safe_redirect(zigurat_manager_login_url());
        exit;
    }
}

/** مشارکت‌کننده پس از ورود وردپرس نیز فقط وارد پنل اختصاصی سایت می‌شود. */
function zigurat_contributor_login_redirect($redirect_to, $requested_redirect_to, $user)
{
    return !is_wp_error($user) && zigurat_is_panel_contributor($user)
        ? zigurat_manager_login_url()
        : $redirect_to;
}
add_filter('login_redirect', 'zigurat_contributor_login_redirect', 20, 3);

/** دسترسی مستقیم مشارکت‌کننده به پیشخوان وردپرس مسدود است. */
function zigurat_keep_contributor_out_of_wp_admin()
{
    if (!zigurat_is_panel_contributor() || wp_doing_ajax()) {
        return;
    }
    global $pagenow;
    if (in_array($pagenow, array('admin-post.php', 'admin-ajax.php'), true)) {
        return;
    }
    wp_safe_redirect(zigurat_manager_login_url());
    exit;
}
add_action('admin_init', 'zigurat_keep_contributor_out_of_wp_admin', 1);

/** نوار مدیریت وردپرس در صفحات سایت برای مشارکت‌کننده نمایش داده نمی‌شود. */
function zigurat_hide_admin_bar_for_panel_contributor($show)
{
    return zigurat_is_panel_contributor() ? false : $show;
}
add_filter('show_admin_bar', 'zigurat_hide_admin_bar_for_panel_contributor', 20);
