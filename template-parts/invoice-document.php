<?php
if (!empty($args['invoice'])) {
    $invoice = $args['invoice'];
}
if (!defined('ABSPATH') || empty($invoice)) {
    return;
}
$brand_title = $invoice->brand === 'official' ? 'زیگورات' : 'فروشگاه دیاموند';
$seller = (array) $invoice->seller;
$logo_id = $invoice->brand === 'official' ? absint(get_theme_mod('custom_logo')) : 0;
$logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';
$stamp_id = !empty($seller['include_stamp']) ? absint($seller['stamp_id'] ?? 0) : 0;
$stamp_url = $stamp_id ? wp_get_attachment_image_url($stamp_id, 'full') : '';
$stamp_layout = function_exists('zigurat_invoice_stamp_layout')
    ? zigurat_invoice_stamp_layout($invoice->brand)
    : array('size_mm' => $invoice->brand === 'official' ? 46 : 40, 'x_percent' => 75, 'position' => 'right', 'bottom_mm' => 0);
$stamp_style = sprintf(
    '--invoice-stamp-size:%smm;--invoice-stamp-x:%s%%;--invoice-stamp-bottom:%smm;',
    rtrim(rtrim(number_format((float) $stamp_layout['size_mm'], 2, '.', ''), '0'), '.'),
    rtrim(rtrim(number_format((float) $stamp_layout['x_percent'], 2, '.', ''), '0'), '.'),
    rtrim(rtrim(number_format((float) $stamp_layout['bottom_mm'], 2, '.', ''), '0'), '.')
);
$seller_fields = array(
    array('نام شخص حقیقی/حقوقی', $seller['name'] ?? '', 'is-wide'),
    array('شماره اقتصادی', $seller['economic_no'] ?? '', ''),
    array('شماره ثبت/شماره ملی', $seller['national_id'] ?? '', ''),
    array('استان', $seller['province'] ?? '', ''),
    array('شهرستان', $seller['county'] ?? '', ''),
    array('شهر', $seller['city'] ?? '', ''),
    array('کدپستی', $seller['postal_code'] ?? '', ''),
    array('نشانی', $seller['address'] ?? '', 'is-wide'),
    array('تلفن', $seller['phone'] ?? '', ''),
);
$customer_fields = array(
    array('نام شخص حقیقی/حقوقی', $invoice->customer_name, 'is-wide'),
    array('شماره اقتصادی', $invoice->customer_economic_no, ''),
    array('شماره ثبت/شماره ملی', $invoice->customer_national_id, ''),
    array('استان', $invoice->customer_province, ''),
    array('شهرستان', $invoice->customer_county, ''),
    array('شهر', $invoice->customer_city, ''),
    array('کدپستی', $invoice->customer_postal_code, ''),
    array('نشانی', $invoice->customer_address, 'is-wide'),
    array('تلفن', $invoice->customer_phone, ''),
);
$render_party_fields = static function ($fields) {
    foreach ($fields as $field) {
        $value = trim((string) $field[1]);
        printf(
            '<div%s><strong>%s:</strong> %s</div>',
            $field[2] !== '' ? ' class="' . esc_attr($field[2]) . '"' : '',
            esc_html($field[0]),
            esc_html($value)
        );
    }
};
$rate_label = static function ($rate) {
    return rtrim(rtrim(number_format((float) $rate, 2, '.', ''), '0'), '.');
};
$show_discount = (int) $invoice->discount !== 0;
$show_shipping = (int) $invoice->shipping !== 0;
$show_overhead = (float) ($invoice->overhead_rate ?? 0) !== 0.0 || (int) ($invoice->overhead_amount ?? 0) !== 0;
$show_insurance = $invoice->brand === 'official' && ((float) ($invoice->insurance_rate ?? 0) !== 0.0 || (int) ($invoice->insurance_amount ?? 0) !== 0);
$show_tax = (float) ($invoice->tax_rate ?? 0) !== 0.0 || (int) $invoice->tax_amount !== 0;
$show_grand_total = (int) $invoice->grand_total !== (int) $invoice->subtotal;
$show_paid = (int) $invoice->paid_amount !== 0;
?>
<article class="invoice-document invoice-document--<?php echo esc_attr($invoice->brand); ?> <?php echo $invoice->status === 'draft' ? 'is-draft' : ''; ?>" dir="rtl">
    <?php if ($invoice->status === 'draft'): ?><div class="invoice-watermark">پیش‌نویس</div><?php endif; ?>
    <?php if ($stamp_url): ?><div class="invoice-first-page-stamp" style="<?php echo esc_attr($stamp_style); ?>" aria-hidden="true"><img src="<?php echo esc_url($stamp_url); ?>" alt="" loading="eager" decoding="sync"></div><?php endif; ?>
    <header class="invoice-document__header">
        <div class="invoice-brand">
            <?php if ($logo_url): ?><img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($brand_title); ?>"><?php endif; ?>
            <strong><?php echo esc_html($brand_title); ?></strong>
        </div>
        <h1><?php echo esc_html(zigurat_invoice_document_label($invoice->document_type)); ?></h1>
        <dl><div><dt>شماره سریال:</dt><dd><bdi class="invoice-number" dir="ltr"><?php echo esc_html(zigurat_invoice_object_number($invoice)); ?></bdi></dd></div><div><dt>تاریخ:</dt><dd><?php echo esc_html($invoice->issue_date); ?></dd></div></dl>
    </header>
    <?php if ($invoice->document_type === 'invoice' && ($invoice->tax_subject ?? 'original') === 'correction'): ?><div class="invoice-document-status"><span>اصلاحیه فاکتور شماره <?php $reference = zigurat_invoice_get($invoice->reference_invoice_id ?? 0); ?><bdi dir="ltr"><?php echo esc_html($reference ? zigurat_invoice_object_number($reference) : '—'); ?></bdi></span></div><?php endif; ?>

    <section class="invoice-party invoice-party--seller">
        <h2>مشخصات فروشنده</h2>
        <div class="invoice-party__grid"><?php $render_party_fields($seller_fields); ?></div>
    </section>

    <section class="invoice-party invoice-party--buyer">
        <h2>مشخصات خریدار</h2>
        <div class="invoice-party__grid"><?php $render_party_fields($customer_fields); ?></div>
    </section>

    <?php if ($invoice->subject): ?><div class="invoice-subject"><strong>موضوع:</strong> <?php echo esc_html($invoice->subject); ?></div><?php endif; ?>
    <table class="invoice-lines">
        <thead><tr class="invoice-lines-page-spacer" aria-hidden="true"><th colspan="7"></th></tr><tr><th>ردیف</th><th>شرح کالا یا خدمت</th><th>مقدار</th><th>واحد</th><th>مبلغ واحد (ریال)</th><th>تخفیف</th><th>جمع کل (ریال)</th></tr></thead>
        <tbody>
        <?php foreach ($invoice->items as $index => $item): ?>
            <tr><td><?php echo (int) ($index + 1); ?></td><td><span class="invoice-line-description"><?php echo esc_html($item->description); ?></span></td><td><?php echo esc_html(rtrim(rtrim(number_format((float)$item->quantity, 3, '.', ''), '0'), '.')); ?></td><td><?php echo esc_html($item->unit); ?></td><td><?php echo esc_html(number_format_i18n($item->unit_price)); ?></td><td><?php echo esc_html(number_format_i18n($item->discount)); ?></td><td><?php echo esc_html(number_format_i18n($item->line_total)); ?></td></tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr class="invoice-lines-page-spacer" aria-hidden="true"><td colspan="7"></td></tr></tfoot>
    </table>
    <div class="invoice-bottom">
        <div class="invoice-notes"><h2>توضیحات</h2><p><?php echo esc_html($invoice->notes ?: '—'); ?></p><?php if ($invoice->payment_info): ?><h2>اطلاعات پرداخت</h2><p><?php echo esc_html($invoice->payment_info); ?></p><?php endif; ?></div>
        <dl class="invoice-totals">
            <div><dt>جمع اقلام:</dt><dd><?php echo esc_html(zigurat_invoice_format_money($invoice->subtotal)); ?></dd></div>
            <?php if ($show_discount): ?><div><dt>تخفیف:</dt><dd><?php echo esc_html(zigurat_invoice_format_money($invoice->discount)); ?></dd></div><?php endif; ?>
            <?php if ($show_shipping): ?><div><dt>هزینه حمل و بسته‌بندی:</dt><dd><?php echo esc_html(zigurat_invoice_format_money($invoice->shipping)); ?></dd></div><?php endif; ?>
            <?php if ($show_overhead): ?><div><dt>بالاسری/سود پیمانکار <?php echo esc_html($rate_label($invoice->overhead_rate ?? 0)); ?>٪:</dt><dd><?php echo esc_html(zigurat_invoice_format_money($invoice->overhead_amount ?? 0)); ?></dd></div><?php endif; ?>
            <?php if ($show_insurance): ?><div><dt>بیمه <?php echo esc_html($rate_label($invoice->insurance_rate ?? 0)); ?>٪:</dt><dd><?php echo esc_html(zigurat_invoice_format_money($invoice->insurance_amount ?? 0)); ?></dd></div><?php endif; ?>
            <?php if ($show_tax): ?><div><dt>مالیات ارزش افزوده <?php echo esc_html($rate_label($invoice->tax_rate)); ?>٪:</dt><dd><?php echo esc_html(zigurat_invoice_format_money($invoice->tax_amount)); ?></dd></div><?php endif; ?>
            <?php if ($show_grand_total): ?><div><dt>جمع کل:</dt><dd><?php echo esc_html(zigurat_invoice_format_money($invoice->grand_total)); ?></dd></div><?php endif; ?>
            <?php if ($show_paid): ?><div><dt>پرداختی:</dt><dd><?php echo esc_html(zigurat_invoice_format_money($invoice->paid_amount)); ?></dd></div><?php endif; ?>
            <div class="is-payable"><dt>قابل پرداخت:</dt><dd><?php echo esc_html(zigurat_invoice_format_money($invoice->balance)); ?></dd></div>
        </dl>
    </div>
    <footer class="invoice-signatures"><div class="invoice-signature invoice-signature--seller invoice-signature--stamp-<?php echo esc_attr($stamp_layout['position']); ?>" style="<?php echo esc_attr($stamp_style); ?>"><span>مهر و امضای فروشنده</span><?php if ($stamp_url): ?><img src="<?php echo esc_url($stamp_url); ?>" class="invoice-stamp-image" alt="مهر فروشنده" loading="eager" decoding="sync"><?php endif; ?></div><div class="invoice-signature"><span>مهر و امضای خریدار</span></div></footer>
</article>
