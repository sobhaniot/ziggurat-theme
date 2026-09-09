<?php
if (!defined('ABSPATH') || !current_user_can('manage_options')) {
    return;
}
$brand = isset($args['brand']) && in_array($args['brand'], array('official','unofficial'), true)
    ? $args['brand']
    : 'official';
$list_error = $args['list_error'] ?? null;
$search = isset($_GET['trash_search']) ? sanitize_text_field(wp_unslash($_GET['trash_search'])) : '';
$page = isset($_GET['trash_page']) ? max(1, absint($_GET['trash_page'])) : 1;
$trash = zigurat_invoice_trash_list(array(
    'brand' => $brand,
    'search' => $search,
    'page' => $page,
));
?>
<div class="invoice-list-heading invoice-trash-heading">
    <div><h2>سطل زباله فاکتورها</h2><p>نسخه کامل فاکتورهای حذف‌شده در این بخش نگهداری می‌شود و در گزارش‌های مالی محاسبه نمی‌شود.</p></div>
    <div class="invoice-list-heading__actions no-print"><strong><?php echo esc_html(number_format_i18n($trash['total'])); ?> سند</strong></div>
</div>
<?php if ($list_error): ?>
    <div class="invoice-notice is-error" role="alert"><?php echo esc_html($list_error->get_error_message()); ?></div>
<?php elseif (!empty($_GET['invoice-status']) && $_GET['invoice-status'] === 'restored'): ?>
    <div class="invoice-notice is-success">فاکتور با شماره اصلی بازیابی شد.</div>
<?php elseif (!empty($_GET['invoice-status']) && $_GET['invoice-status'] === 'restored-renumbered'): ?>
    <div class="invoice-notice is-success">فاکتور با شماره جدید <bdi class="invoice-number" dir="ltr"><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['restored-number'] ?? ''))); ?></bdi> بازیابی شد.</div>
<?php elseif (!empty($_GET['invoice-status']) && $_GET['invoice-status'] === 'permanently-deleted'): ?>
    <div class="invoice-notice is-success">فاکتور برای همیشه از سطل زباله حذف شد.</div>
<?php endif; ?>

<form class="invoice-list-filters invoice-trash-filters no-print" method="get">
    <input type="hidden" name="invoice_brand" value="<?php echo esc_attr($brand); ?>">
    <input type="hidden" name="invoice_view" value="trash">
    <label>جستجو<input type="search" name="trash_search" value="<?php echo esc_attr($search); ?>" placeholder="شماره، خریدار یا موضوع"></label>
    <button type="submit">جستجو</button>
    <?php if ($search !== ''): ?><a href="<?php echo esc_url(zigurat_invoice_page_url(array('brand'=>$brand,'view'=>'trash'))); ?>">حذف فیلتر</a><?php endif; ?>
</form>

<div class="invoice-list-table-wrap">
    <table class="invoice-list-table invoice-trash-table">
        <thead><tr><th>شماره قبلی</th><th>نوع سند</th><th>تاریخ سند</th><th>خریدار</th><th>موضوع</th><th>جمع کل</th><th>حذف‌شده توسط</th><th>زمان حذف</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php if ($trash['items']): foreach ($trash['items'] as $row): ?>
            <?php
            $restore_conflict = zigurat_invoice_number_is_in_use($row->brand, $row->document_type, $row->document_number, $row->number_suffix);
            $suggested_number = $restore_conflict ? zigurat_invoice_format_number(zigurat_invoice_next_number($row->brand, $row->document_type)) : '';
            ?>
            <tr>
                <td><strong><bdi class="invoice-number" dir="ltr"><?php echo esc_html(zigurat_invoice_format_number($row->document_number, $row->number_suffix)); ?></bdi></strong></td>
                <td><?php echo esc_html(zigurat_invoice_document_label($row->document_type)); ?></td>
                <td><?php echo esc_html($row->issue_date); ?></td>
                <td><?php echo esc_html($row->customer_name ?: '—'); ?></td>
                <td><?php echo esc_html($row->subject ?: '—'); ?></td>
                <td><?php echo esc_html(zigurat_invoice_format_money($row->grand_total)); ?></td>
                <td><?php echo esc_html($row->deleted_by_name ?: 'مدیر کل'); ?></td>
                <td><?php echo esc_html(function_exists('zigurat_inventory_format_jalali_datetime') ? zigurat_inventory_format_jalali_datetime($row->deleted_at) : $row->deleted_at); ?></td>
                <td>
                    <a href="<?php echo esc_url(zigurat_invoice_page_url(array('view'=>'trash_print','id'=>$row->id))); ?>" target="_blank" rel="noopener">چاپ</a>
                    <form class="invoice-trash-restore-form" method="post" data-invoice-restore-form data-restore-conflict="<?php echo $restore_conflict ? '1' : '0'; ?>" data-invoice-number="<?php echo esc_attr(zigurat_invoice_format_number($row->document_number, $row->number_suffix)); ?>" data-new-number="<?php echo esc_attr($suggested_number); ?>">
                        <input type="hidden" name="restore_invoice" value="1">
                        <input type="hidden" name="restore_with_new_number" value="0">
                        <input type="hidden" name="trash_id" value="<?php echo (int) $row->id; ?>">
                        <?php wp_nonce_field('zigurat_restore_invoice_' . $row->id, 'invoice_restore_nonce'); ?>
                        <button type="submit">بازیابی</button>
                    </form>
                    <form class="invoice-delete-form" method="post" data-invoice-delete-form data-delete-mode="permanent" data-invoice-number="<?php echo esc_attr(zigurat_invoice_format_number($row->document_number, $row->number_suffix)); ?>">
                        <input type="hidden" name="delete_invoice_permanently" value="1">
                        <input type="hidden" name="trash_id" value="<?php echo (int) $row->id; ?>">
                        <?php wp_nonce_field('zigurat_permanently_delete_invoice_' . $row->id, 'invoice_permanent_delete_nonce'); ?>
                        <button type="submit">حذف دائمی</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="9">سطل زباله خالی است.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($trash['pages'] > 1): ?>
    <nav class="invoice-pagination no-print" aria-label="صفحه‌بندی سطل زباله">
        <?php echo wp_kses_post(paginate_links(array(
            'base' => esc_url_raw(add_query_arg('trash_page', '%#%', zigurat_invoice_page_url(array('brand'=>$brand,'view'=>'trash','trash_search'=>$search)))),
            'format' => '',
            'current' => $trash['page'],
            'total' => $trash['pages'],
            'prev_text' => 'قبلی',
            'next_text' => 'بعدی',
            'type' => 'plain',
        ))); ?>
    </nav>
<?php endif; ?>
