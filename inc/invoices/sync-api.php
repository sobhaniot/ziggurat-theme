<?php
/**
 * REST bridge used by the local Ziggurat Accounting desktop application.
 *
 * The bridge deliberately exposes only issued invoices. Invoice totals are
 * read-only; the only writable field is the subject of an unlocked invoice.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Application Passwords are normally unavailable over plain HTTP. The live
// site currently uses HTTP, so this project-specific bridge explicitly enables
// them. Remove these filters after the site is migrated to HTTPS.
add_filter('wp_is_application_passwords_supported', '__return_true');
add_filter('wp_is_application_passwords_available', '__return_true');

function zigurat_invoice_sync_authorize()
{
    if (!is_user_logged_in()) {
        return new WP_Error(
            'zigurat_sync_auth_required',
            'نام کاربری یا رمز برنامه وردپرس معتبر نیست.',
            array('status' => 401)
        );
    }

    if (function_exists('zigurat_user_can_access_manager_panel') && zigurat_user_can_access_manager_panel()) {
        return true;
    }

    return new WP_Error(
        'zigurat_sync_forbidden',
        'این کاربر اجازه دسترسی به فاکتورها را ندارد.',
        array('status' => 403)
    );
}

function zigurat_invoice_sync_register_routes()
{
    register_rest_route('zigurat-sync/v1', '/invoices', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'zigurat_invoice_sync_get_invoices',
        'permission_callback' => 'zigurat_invoice_sync_authorize',
    ));

    register_rest_route('zigurat-sync/v1', '/invoices/(?P<id>\d+)', array(
        'methods' => 'PATCH',
        'callback' => 'zigurat_invoice_sync_update_invoice',
        'permission_callback' => 'zigurat_invoice_sync_authorize',
        'args' => array(
            'id' => array(
                'required' => true,
                'validate_callback' => static function ($value) {
                    return absint($value) > 0;
                },
            ),
            'subject' => array(
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ),
        ),
    ));
}
add_action('rest_api_init', 'zigurat_invoice_sync_register_routes');

function zigurat_invoice_sync_get_invoices()
{
    global $wpdb;

    $table = zigurat_invoices_table_name();
    $rows = $wpdb->get_results(
        "SELECT id, brand, document_type, document_number, number_suffix,
                subject, grand_total, payment_status, paid_amount, status,
                locked_at, locked_reason
         FROM {$table}
         WHERE document_type = 'invoice' AND status = 'issued'
         ORDER BY document_number ASC, number_suffix ASC, id ASC"
    );

    $invoices = array_map(static function ($invoice) {
        return array(
            'id' => (int) $invoice->id,
            'brand' => (string) $invoice->brand,
            'document_type' => 'invoice',
            'number' => zigurat_invoice_object_number($invoice),
            'project' => (string) $invoice->subject,
            'amount' => (int) $invoice->grand_total,
            'locked' => zigurat_invoice_is_locked($invoice),
        );
    }, is_array($rows) ? $rows : array());

    return rest_ensure_response(array(
        'invoices' => $invoices,
        'count' => count($invoices),
        'generated_at' => gmdate('c'),
    ));
}

function zigurat_invoice_sync_update_invoice(WP_REST_Request $request)
{
    global $wpdb;

    $invoice_id = absint($request['id']);
    $invoice = zigurat_invoice_get($invoice_id);
    if (!$invoice || $invoice->document_type !== 'invoice' || $invoice->status !== 'issued') {
        return new WP_Error(
            'zigurat_sync_invoice_not_found',
            'فاکتور قطعی پیدا نشد.',
            array('status' => 404)
        );
    }

    if (zigurat_invoice_is_locked($invoice)) {
        return new WP_Error(
            'zigurat_sync_invoice_locked',
            'فاکتور تسویه یا قفل شده است و از برنامه قابل تغییر نیست.',
            array('status' => 409)
        );
    }

    $subject = sanitize_text_field((string) $request->get_param('subject'));
    if ($subject === '') {
        return new WP_Error(
            'zigurat_sync_invalid_subject',
            'نام پروژه نمی‌تواند خالی باشد.',
            array('status' => 400)
        );
    }

    $updated = $wpdb->update(
        zigurat_invoices_table_name(),
        array(
            'subject' => $subject,
            'updated_by' => get_current_user_id(),
            'updated_at' => current_time('mysql', true),
        ),
        array('id' => $invoice_id),
        array('%s', '%d', '%s'),
        array('%d')
    );

    if ($updated === false) {
        return new WP_Error(
            'zigurat_sync_database_error',
            'نام پروژه در سایت ذخیره نشد.',
            array('status' => 500)
        );
    }

    return rest_ensure_response(array(
        'updated' => true,
        'id' => $invoice_id,
        'subject' => $subject,
    ));
}
