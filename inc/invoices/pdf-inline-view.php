<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Stream a browser-built invoice PDF inline with its requested save name. */
function zigurat_invoice_pdf_inline_view()
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        wp_die('درخواست نامعتبر است.', 'خطا', array('response' => 405));
    }

    $nonce = isset($_POST['zigurat_pdf_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['zigurat_pdf_nonce']))
        : '';
    if (!wp_verify_nonce($nonce, 'zigurat_invoice_pdf_inline')) {
        wp_die('درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.', 'خطا', array('response' => 403));
    }

    $upload = $_FILES['zigurat_invoice_pdf'] ?? null;
    if (!is_array($upload) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        wp_die('دریافت PDF برای نمایش انجام نشد.', 'خطا', array('response' => 400));
    }

    $size = (int) ($upload['size'] ?? 0);
    $temporary_file = (string) ($upload['tmp_name'] ?? '');
    if ($size < 8 || $size > 20 * MB_IN_BYTES || !$temporary_file || !is_uploaded_file($temporary_file)) {
        wp_die('اندازه یا فایل PDF معتبر نیست.', 'خطا', array('response' => 400));
    }

    $handle = fopen($temporary_file, 'rb');
    $signature = $handle ? fread($handle, 5) : '';
    if ($handle) {
        fclose($handle);
    }
    if ($signature !== '%PDF-') {
        wp_die('فایل دریافت‌شده PDF معتبر نیست.', 'خطا', array('response' => 400));
    }

    $filename = sanitize_file_name(wp_unslash((string) ($upload['name'] ?? '')));
    $filename = preg_replace('/[\\\\\/:*?"<>|\x00-\x1F\x7F]+/u', '-', $filename);
    $filename = trim((string) $filename, " .\t\n\r\0\x0B");
    if ($filename === '' || strtolower((string) pathinfo($filename, PATHINFO_EXTENSION)) !== 'pdf') {
        $filename = 'zigurat-invoice.pdf';
    }

    $stream = fopen($temporary_file, 'rb');
    if (!$stream) {
        wp_die('خواندن PDF انجام نشد.', 'خطا', array('response' => 500));
    }

    while (ob_get_level()) {
        ob_end_clean();
    }
    nocache_headers();
    status_header(200);
    header('Content-Type: application/pdf');
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . $size);
    header('Content-Disposition: inline; filename="zigurat-invoice.pdf"; filename*=UTF-8\'\'' . rawurlencode($filename));
    fpassthru($stream);
    fclose($stream);
    exit;
}
add_action('admin_post_zigurat_invoice_pdf_inline', 'zigurat_invoice_pdf_inline_view');
add_action('admin_post_nopriv_zigurat_invoice_pdf_inline', 'zigurat_invoice_pdf_inline_view');
