<?php
if (!defined('ABSPATH')) return;
$tax_filter = zigurat_invoice_tax_filter($args['filter'] ?? '', $args['quarter'] ?? 0);
?>
<label>سامانه مؤدیان<select name="filter_tax_status" <?php disabled(!empty($args['disabled'])); ?>><option value="">همه</option><option value="not_submitted" <?php selected($tax_filter['value'], 'not_submitted'); ?>>ثبت‌نشده</option><option value="submitted" <?php selected($tax_filter['value'], 'submitted'); ?>>ثبت‌شده‌ها</option><?php for ($quarter=1; $quarter<=4; ++$quarter): ?><option value="quarter_<?php echo $quarter; ?>" <?php selected($tax_filter['value'], 'quarter_'.$quarter); ?>><?php echo esc_html(zigurat_invoice_tax_quarter_label($quarter)); ?></option><?php endfor; ?></select></label>
