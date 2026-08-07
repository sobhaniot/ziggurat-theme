<?php
if (!defined('ABSPATH')) {
    exit;
}

function zigurat_consultation_fields()
{
    return array(
        'signage'      => 'تابلوسازی و حروف برجسته',
        'printing'     => 'چاپ و تبلیغات محیطی',
        'renovation'   => 'بازسازی و دکوراسیون',
        'lighting'     => 'نورپردازی و برق',
        'facade'       => 'نما و کامپوزیت',
        'maintenance'  => 'تعمیر و نگهداری',
        'other'        => 'سایر موارد',
    );
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
    $redirect_with_status = static function ($status) use ($redirect_url) {
        wp_safe_redirect(add_query_arg('contact-status', $status, $redirect_url) . '#consultation-form');
        exit;
    };

    if (
        !isset($_POST['zigurat_contact_nonce']) ||
        !wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['zigurat_contact_nonce'])),
            'zigurat_send_contact_message'
        ) ||
        !empty($_POST['website'])
    ) {
        $redirect_with_status('error');
    }

    $remote_address = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
    $rate_key = 'zigurat_consultation_' . md5($remote_address);
    $attempts = (int) get_transient($rate_key);
    if ($attempts >= 5) {
        $redirect_with_status('limited');
    }

    $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    $phone = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
    $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
    $consultation_field = sanitize_key(wp_unslash($_POST['consultation_field'] ?? ''));
    $consultation_fields = zigurat_consultation_fields();

    if (!$name || !$phone || !isset($consultation_fields[$consultation_field])) {
        $redirect_with_status('invalid');
    }

    $field_label = $consultation_fields[$consultation_field];
    $subject = sprintf('درخواست مشاوره %s از %s', $field_label, $name);
    $body = "نام: {$name}\nشماره تماس: {$phone}\nزمینه مشاوره: {$field_label}\nایمیل: {$email}\n\nتوضیحات:\n" . ($message ?: '—');
    $headers = array('Content-Type: text/plain; charset=UTF-8');

    if ($email && is_email($email)) {
        $headers[] = "Reply-To: {$name} <{$email}>";
    }

    $contact_details = zigurat_get_contact_details();
    $recipient = is_email($contact_details['email'])
        ? $contact_details['email']
        : get_option('admin_email');
    $status = wp_mail($recipient, $subject, $body, $headers) ? 'sent' : 'error';
    set_transient($rate_key, $attempts + 1, HOUR_IN_SECONDS);
    $redirect_with_status($status);
}
add_action('init', 'zigurat_handle_contact_form');
