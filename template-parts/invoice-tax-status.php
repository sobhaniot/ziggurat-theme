<?php
if (!defined('ABSPATH') || empty($args['invoice'])) return;
$tax_invoice = $args['invoice'];
$tax_return_url = $args['return_url'] ?? '';
if ($tax_invoice->document_type !== 'invoice') { echo '—'; return; }
$tax_quarter = zigurat_invoice_submission_quarter($tax_invoice->tax_submission_quarter ?? 0);
$tax_editable = $tax_invoice->status === 'issued' && !in_array(($tax_invoice->tax_status ?? 'not_submitted'), array('confirmed','corrected','voided'), true);
if ($tax_editable):
    $tax_state = ($tax_invoice->tax_status ?? '') === 'submitted' ? 'submitted' : 'not_submitted';
?>
<div class="invoice-status-quick" data-status-quick data-status-kind="tax" data-current-status="<?php echo esc_attr($tax_state); ?>" data-submission-quarter="<?php echo $tax_quarter; ?>" data-invoice-number="<?php echo esc_attr(zigurat_invoice_object_number($tax_invoice)); ?>">
    <button type="button" class="invoice-tax-badge invoice-tax-badge--<?php echo esc_attr($tax_state); ?>" data-status-menu-toggle aria-expanded="false"><?php echo $tax_state === 'submitted' ? 'ثبت‌شده' : 'ثبت‌نشده'; ?></button>
    <div class="invoice-status-quick__menu" data-status-menu hidden><button type="button" data-status-option="not_submitted" <?php disabled($tax_state,'not_submitted'); ?>>ثبت‌نشده</button><button type="button" data-status-option="submitted"><?php echo $tax_state === 'submitted' ? 'تغییر فصل ثبت' : 'ثبت‌شده'; ?></button></div>
    <form method="post" data-status-quick-form><input type="hidden" name="set_invoice_tax_status" value="1"><input type="hidden" name="invoice_id" value="<?php echo (int) $tax_invoice->id; ?>"><input type="hidden" name="tax_status_target" value=""><input type="hidden" name="tax_submission_quarter" value="<?php echo $tax_quarter; ?>"><input type="hidden" name="confirm_tax_submission" value="0"><input type="hidden" name="invoice_return_url" value="<?php echo esc_url($tax_return_url); ?>"><?php wp_nonce_field('zigurat_invoice_tax_quick_' . $tax_invoice->id, 'invoice_tax_quick_nonce'); ?></form>
</div>
<?php else: ?><span class="invoice-tax-badge invoice-tax-badge--<?php echo esc_attr($tax_invoice->tax_status ?? 'not_submitted'); ?>"><?php echo esc_html(zigurat_invoice_tax_status_label($tax_invoice->tax_status ?? 'not_submitted')); ?></span><?php endif; ?>
<?php if (in_array(($tax_invoice->tax_status ?? ''), array('submitted','confirmed','corrected','voided'), true)): ?><small class="invoice-tax-quarter"><?php echo esc_html($tax_quarter ? zigurat_invoice_tax_quarter_label($tax_quarter) : 'فصل مشخص نشده'); ?></small><?php endif; ?>
