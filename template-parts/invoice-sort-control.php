<?php
if (!defined('ABSPATH')) return;
$selected_sort = zigurat_invoice_normalize_sort($args['sort'] ?? 'default');
?>
<label>مرتب‌سازی<select name="sort"><?php foreach (zigurat_invoice_sort_options() as $sort_key => $sort_label): ?><option value="<?php echo esc_attr($sort_key); ?>" <?php selected($selected_sort, $sort_key); ?>><?php echo esc_html($sort_label); ?></option><?php endforeach; ?></select></label>
