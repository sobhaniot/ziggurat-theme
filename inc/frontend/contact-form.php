<?php
if (!defined('ABSPATH')) {
    exit;
}

function zigurat_handle_contact_form()
{
    if (
        !isset($_SERVER['REQUEST_METHOD']) ||
        $_SERVER['REQUEST_METHOD'] !== 'POST' ||
        !isset($_POST['zigurat_contact_form'])
    ) {
        return;
    }

    $redirect_url = wp_get_referer() ?: home_url('/contact/');

    if (
        !isset($_POST['zigurat_contact_nonce']) ||
        !wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['zigurat_contact_nonce'])),
            'zigurat_send_contact_message'
        ) ||
        !empty($_POST['website'])
    ) {
        wp_safe_redirect(add_query_arg('contact-status', 'error', $redirect_url));
        exit;
    }

    $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    $phone = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
    $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));

    if (!$name || !$message) {
        wp_safe_redirect(add_query_arg('contact-status', 'invalid', $redirect_url));
        exit;
    }

    $subject = sprintf('پیام جدید از %s', $name);
    $body = "نام: {$name}\nشماره تماس: {$phone}\nایمیل: {$email}\n\nپیام:\n{$message}";
    $headers = array('Content-Type: text/plain; charset=UTF-8');

    if ($email && is_email($email)) {
        $headers[] = "Reply-To: {$name} <{$email}>";
    }

    $contact_details = zigurat_get_contact_details();
    $recipient = is_email($contact_details['email'])
        ? $contact_details['email']
        : get_option('admin_email');
    $status = wp_mail($recipient, $subject, $body, $headers) ? 'sent' : 'error';
    wp_safe_redirect(add_query_arg('contact-status', $status, $redirect_url));
    exit;
}
add_action('init', 'zigurat_handle_contact_form');
