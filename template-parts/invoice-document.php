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
?>
<article class="invoice-document invoice-document--<?php echo esc_attr($invoice->brand); ?> <?php echo $invoice->status === 'draft' ? 'is-draft' : ''; ?>" dir="rtl">
    <?php if ($invoice->status === 'draft'): ?><div class="invoice-watermark">پیش‌نویس</div><?php endif; ?>
    <header class="invoice-document__header">
        <div class="invoice-brand">
            <?php if ($logo_url): ?><img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($brand_title); ?>"><?php endif; ?>
            <strong><?php echo esc_html($brand_title); ?></strong>
        </div>
        <h1><?php echo esc_html(zigurat_invoice_document_label($invoice->document_type)); ?></h1>
        <dl><div><dt>شماره سریال:</dt><dd><?php echo esc_html(zigurat_invoice_format_number($invoice->document_number)); ?></dd></div><div><dt>تاریخ:</dt><dd><?php echo esc_html($invoice->issue_date); ?></dd></div></dl>
    </header>

    <section class="invoice-party">
        <h2>مشخصات فروشنده</h2>
        <div class="invoice-party__grid">
            <div class="is-wide"><strong>نام شخص حقیقی/حقوقی:</strong> <?php echo esc_html($seller['name'] ?? ''); ?></div>
            <div><strong>شماره اقتصادی:</strong> <?php echo esc_html($seller['economic_no'] ?? ''); ?></div>
            <div><strong>شماره ثبت/شماره ملی:</strong> <?php echo esc_html($seller['national_id'] ?? ''); ?></div>
            <div><strong>استان:</strong> <?php echo esc_html($seller['province'] ?? ''); ?></div>
            <div><strong>شهرستان:</strong> <?php echo esc_html($seller['county'] ?? ''); ?></div>
            <div><strong>شهر:</strong> <?php echo esc_html($seller['city'] ?? ''); ?></div>
            <div><strong>کدپستی:</strong> <?php echo esc_html($seller['postal_code'] ?? ''); ?></div>
            <div class="is-wide"><strong>نشانی:</strong> <?php echo esc_html($seller['address'] ?? ''); ?></div>
            <div><strong>تلفن:</strong> <?php echo esc_html($seller['phone'] ?? ''); ?></div>
        </div>
    </section>

    <section class="invoice-party">
        <h2>مشخصات خریدار</h2>
        <div class="invoice-party__grid">
            <div class="is-wide"><strong>نام شخص حقیقی/حقوقی:</strong> <?php echo esc_html($invoice->customer_name); ?></div>
            <div><strong>شماره اقتصادی:</strong> <?php echo esc_html($invoice->customer_economic_no); ?></div>
            <div><strong>شماره ثبت/شماره ملی:</strong> <?php echo esc_html($invoice->customer_national_id); ?></div>
            <div><strong>استان:</strong> <?php echo esc_html($invoice->customer_province); ?></div>
            <div><strong>شهرستان:</strong> <?php echo esc_html($invoice->customer_county); ?></div>
            <div><strong>شهر:</strong> <?php echo esc_html($invoice->customer_city); ?></div>
            <div><strong>کدپستی:</strong> <?php echo esc_html($invoice->customer_postal_code); ?></div>
            <div class="is-wide"><strong>نشانی:</strong> <?php echo esc_html($invoice->customer_address); ?></div>
            <div><strong>تلفن:</strong> <?php echo esc_html($invoice->customer_phone); ?></div>
        </div>
    </section>

    <?php if ($invoice->subject): ?><div class="invoice-subject"><strong>موضوع:</strong> <?php echo esc_html($invoice->subject); ?></div><?php endif; ?>
    <table class="invoice-lines">
        <thead><tr><th>ردیف</th><th>شرح کالا یا خدمت</th><th>مقدار</th><th>واحد</th><th>مبلغ واحد (ریال)</th><th>تخفیف</th><th>جمع کل (ریال)</th></tr></thead>
        <tbody>
        <?php foreach ($invoice->items as $index => $item): ?>
            <tr><td><?php echo (int) ($index + 1); ?></td><td><?php echo nl2br(esc_html($item->description)); ?></td><td><?php echo esc_html(rtrim(rtrim(number_format((float)$item->quantity, 3, '.', ''), '0'), '.')); ?></td><td><?php echo esc_html($item->unit); ?></td><td><?php echo esc_html(number_format_i18n($item->unit_price)); ?></td><td><?php echo esc_html(number_format_i18n($item->discount)); ?></td><td><?php echo esc_html(number_format_i18n($item->line_total)); ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="invoice-bottom">
        <div class="invoice-notes"><h2>توضیحات</h2><p><?php echo nl2br(esc_html($invoice->notes ?: '—')); ?></p><?php if ($invoice->payment_info): ?><h2>اطلاعات پرداخت</h2><p><?php echo nl2br(esc_html($invoice->payment_info)); ?></p><?php endif; ?></div>
        <dl class="invoice-totals">
            <div><dt>جمع اقلام:</dt><dd><?php echo esc_html(zigurat_invoice_format_money($invoice->subtotal)); ?></dd></div>
            <div><dt>تخفیف:</dt><dd><?php echo esc_html(zigurat_invoice_format_money($invoice->discount)); ?></dd></div>
            <div><dt>هزینه حمل و بسته‌بندی:</dt><dd><?php echo esc_html(zigurat_invoice_format_money($invoice->shipping)); ?></dd></div>
            <div><dt>مالیات ارزش افزوده <?php echo esc_html(rtrim(rtrim(number_format((float) $invoice->tax_rate, 2, '.', ''), '0'), '.')); ?>٪:</dt><dd><?php echo esc_html(zigurat_invoice_format_money($invoice->tax_amount)); ?></dd></div>
            <div class="is-grand"><dt>جمع کل:</dt><dd><?php echo esc_html(zigurat_invoice_format_money($invoice->grand_total)); ?></dd></div>
            <div><dt>پرداختی:</dt><dd><?php echo esc_html(zigurat_invoice_format_money($invoice->paid_amount)); ?></dd></div>
            <div><dt>مانده:</dt><dd><?php echo esc_html(zigurat_invoice_format_money($invoice->balance)); ?></dd></div>
        </dl>
    </div>
    <footer class="invoice-signatures"><div class="invoice-signature invoice-signature--seller"><span>مهر و امضای فروشنده</span><?php if ($stamp_url): ?><img src="<?php echo esc_url($stamp_url); ?>" class="invoice-stamp-image" alt="مهر فروشنده" loading="eager" decoding="sync"><?php endif; ?></div><div class="invoice-signature"><span>مهر و امضای خریدار</span></div></footer>
</article>
