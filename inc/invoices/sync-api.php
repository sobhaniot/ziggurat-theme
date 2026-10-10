<?php
/**
 * REST bridge used by the local Ziggurat Accounting desktop application.
 *
 * The bridge deliberately exposes only issued invoices. Invoice totals remain
 * read-only; the desktop application may only synchronize internal settlement
 * data (deductions and paid amount).
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
            'net_after_deductions' => array(
                'required' => true,
                'validate_callback' => static function ($value) {
                    return is_numeric(zigurat_invoice_normalize_digits($value)) && (float) zigurat_invoice_normalize_digits($value) >= 0;
                },
            ),
            'paid_amount' => array(
                'required' => true,
                'validate_callback' => static function ($value) {
                    return is_numeric(zigurat_invoice_normalize_digits($value)) && (float) zigurat_invoice_normalize_digits($value) >= 0;
                },
            ),
            'deduction_note' => array(
                'required' => false,
                'sanitize_callback' => 'sanitize_textarea_field',
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
                subject, customer_name, issue_date, grand_total, deduction_amount,
                deduction_note, payment_status, paid_amount, balance, status,
                tax_status, locked_at, locked_reason
         FROM {$table}
         WHERE document_type = 'invoice' AND status = 'issued'
         ORDER BY document_number ASC, number_suffix ASC, id ASC"
    );

    $invoices = array_map(static function ($invoice) {
        $accounting = zigurat_invoice_accounting_totals(
            $invoice->grand_total,
            $invoice->deduction_amount,
            $invoice->paid_amount,
            'invoice'
        );
        return array(
            'id' => (int) $invoice->id,
            'brand' => (string) $invoice->brand,
            'document_type' => 'invoice',
            'number' => zigurat_invoice_object_number($invoice),
            'project' => (string) $invoice->subject,
            'subject' => (string) $invoice->subject,
            'customer_name' => (string) $invoice->customer_name,
            'issue_date' => (string) $invoice->issue_date,
            'amount' => (int) $invoice->grand_total,
            'net_after_deductions' => (int) $accounting['net_payable'],
            'deduction_amount' => (int) $accounting['deduction_amount'],
            'deduction_note' => (string) $invoice->deduction_note,
            'paid_amount' => (int) $invoice->paid_amount,
            'balance' => (int) $accounting['balance'],
            'payment_status' => (string) $accounting['payment_status'],
            'tax_status' => (string) $invoice->tax_status,
            'tax_registered' => in_array(
                (string) $invoice->tax_status,
                array('submitted', 'confirmed', 'corrected', 'voided'),
                true
            ),
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

    $grand_total = (int) $invoice->grand_total;
    $net_after_deductions = zigurat_invoice_money($request->get_param('net_after_deductions'));
    $paid_amount = zigurat_invoice_money($request->get_param('paid_amount'));
    if ($net_after_deductions > $grand_total) {
        return new WP_Error(
            'zigurat_sync_invalid_net_amount',
            'مبلغ پس از کسورات نمی‌تواند بیشتر از مبلغ کل فاکتور باشد.',
            array('status' => 400)
        );
    }
    $deduction_amount = $grand_total - $net_after_deductions;
    $deduction_note = sanitize_textarea_field((string) $request->get_param('deduction_note'));
    $accounting = zigurat_invoice_accounting_totals($grand_total, $deduction_amount, $paid_amount, 'invoice');
    $now = current_time('mysql', true);
    $settled = $accounting['payment_status'] === 'settled';
    $update = array(
        'deduction_amount' => $accounting['deduction_amount'],
        'deduction_note' => $deduction_note,
        'paid_amount' => $accounting['paid_amount'],
        'balance' => $accounting['balance'],
        'payment_status' => $accounting['payment_status'],
        'settled_at' => $settled ? ($invoice->settled_at ?: $now) : null,
        'updated_by' => get_current_user_id(),
        'updated_at' => $now,
    );
    if ($settled) {
        $update['locked_at'] = $invoice->locked_at ?: $now;
        $update['locked_reason'] = 'settled';
    } elseif (($invoice->locked_reason ?? '') === 'settled') {
        $update['locked_at'] = null;
        $update['locked_reason'] = '';
    }
    $updated = $wpdb->update(
        zigurat_invoices_table_name(),
        $update,
        array('id' => $invoice_id),
    );

    if ($updated === false) {
        return new WP_Error(
            'zigurat_sync_database_error',
            'اطلاعات کسورات و پرداخت در سایت ذخیره نشد.',
            array('status' => 500)
        );
    }

    $updated_invoice = zigurat_invoice_get($invoice_id);
    if ($updated_invoice) {
        do_action('zigurat_invoice_saved', $updated_invoice, $invoice);
    }

    return rest_ensure_response(array(
        'updated' => true,
        'id' => $invoice_id,
        'net_after_deductions' => $accounting['net_payable'],
        'deduction_amount' => $accounting['deduction_amount'],
        'paid_amount' => $accounting['paid_amount'],
        'balance' => $accounting['balance'],
        'payment_status' => $accounting['payment_status'],
    ));
}
