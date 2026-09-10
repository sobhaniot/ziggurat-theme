<?php
if (!defined('ABSPATH')) {
    exit;
}

$letter = $args['letter'] ?? null;
if (!$letter) {
    return;
}

$type = ($letter->type ?? '') === 'unofficial' ? 'unofficial' : 'official';
$brand_defaults = zigurat_letter_brand_defaults($type);
$number = trim((string) ($letter->number ?? ''));
$issue_date = trim((string) ($letter->issue_date ?? ''));
$recipient = trim((string) ($letter->recipient ?? ''));
$subject = trim((string) ($letter->subject ?? ''));
$attachment = trim((string) ($letter->attachment ?? ''));
$greeting = trim((string) ($letter->greeting ?? ''));
$body = (string) ($letter->body ?? '');
$signer_name = trim((string) ($letter->signer_name ?? ''));
$signer_title = trim((string) ($letter->signer_title ?? ''));
$stamp_id = absint($letter->stamp_id ?? 0);
$include_stamp = !empty($letter->include_stamp) && $stamp_id;
$stamp_url = $stamp_id ? wp_get_attachment_image_url($stamp_id, 'full') : '';
$stamp_layout = function_exists('zigurat_letter_stamp_layout')
    ? zigurat_letter_stamp_layout($type)
    : array('size_mm' => 34, 'x_percent' => 50, 'bottom_mm' => 0);
$stamp_style = sprintf(
    '--letter-stamp-size:%smm;--letter-stamp-x:%s%%;--letter-stamp-bottom:%smm;',
    rtrim(rtrim(number_format((float) $stamp_layout['size_mm'], 1, '.', ''), '0'), '.'),
    rtrim(rtrim(number_format((float) $stamp_layout['x_percent'], 1, '.', ''), '0'), '.'),
    rtrim(rtrim(number_format((float) $stamp_layout['bottom_mm'], 1, '.', ''), '0'), '.')
);
$header_title = trim((string) ($letter->header_title ?? $brand_defaults['header_title']));
$header_subtitle = trim((string) ($letter->header_subtitle ?? $brand_defaults['header_subtitle']));
$font_size = (float) ($letter->font_size ?? $brand_defaults['font_size']);
$font_size = max(9, min(22, $font_size));
$body_html = $body !== '' ? wpautop(wp_kses_post($body)) : '<p>متن نامه در این قسمت قرار می‌گیرد.</p>';
$body_html = zigurat_letter_persian_digits_html($body_html);

$brand_settings = function_exists('zigurat_invoice_get_brand_settings')
    ? zigurat_invoice_get_brand_settings($type)
    : array();
$seller = isset($brand_settings['seller']) && is_array($brand_settings['seller'])
    ? $brand_settings['seller']
    : array();
$brand_phone = trim((string) ($seller['phone'] ?? ''));
$brand_address = trim((string) ($seller['address'] ?? ''));
$logo_url = get_template_directory_uri() . '/assets/images/zigurat-logo.svg';
?>
<div class="letter-document letter-document--<?php echo esc_attr($type); ?>" data-letter-document style="--letter-body-size: <?php echo esc_attr($font_size); ?>pt;">
    <template data-letter-body-source><?php echo $body_html; ?></template>

    <?php for ($page_number = 1; $page_number <= 2; $page_number++): ?>
        <article class="letter-page letter-page--<?php echo esc_attr($type); ?>" data-letter-page="<?php echo (int) $page_number; ?>"<?php echo $page_number === 2 ? ' hidden' : ''; ?>>
            <header class="letterhead">
                <div class="letterhead__mark">
                    <?php if ($type === 'official'): ?>
                        <img src="<?php echo esc_url($logo_url); ?>" alt="نشان زیگورات">
                    <?php else: ?>
                        <img
                            class="letterhead__diamond-logo"
                            src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/diamond-cyberpunk.png'); ?>"
                            alt="نشان خطی دیاموند"
                        >
                    <?php endif; ?>
                </div>
                <div class="letterhead__titles">
                    <strong data-letter-preview="header_title"><?php echo esc_html(zigurat_letter_persian_digits($header_title)); ?></strong>
                    <span data-letter-preview="header_subtitle"><?php echo esc_html(zigurat_letter_persian_digits($header_subtitle)); ?></span>
                </div>
                <div class="letterhead__meta">
                    <span>شماره: <b data-letter-preview="number"><?php echo esc_html(zigurat_letter_persian_digits($number ?: 'پس از ذخیره')); ?></b></span>
                    <span>تاریخ: <b data-letter-preview="date"><?php echo esc_html(zigurat_letter_persian_digits($issue_date ?: zigurat_letter_today())); ?></b></span>
                    <span>پیوست: <b data-letter-preview="attachment"><?php echo esc_html(zigurat_letter_persian_digits($attachment ?: 'ندارد')); ?></b></span>
                </div>
            </header>

            <section class="letter-page__content" data-letter-content-page="<?php echo (int) $page_number; ?>">
                <?php if ($page_number === 1): ?>
                    <div class="letter-page__intro">
                        <div class="letter-page__recipient">به: <strong data-letter-preview="recipient"><?php echo esc_html(zigurat_letter_persian_digits($recipient ?: 'نام گیرنده')); ?></strong></div>
                        <div class="letter-page__subject">موضوع: <strong data-letter-preview="subject"><?php echo esc_html(zigurat_letter_persian_digits($subject ?: 'موضوع نامه')); ?></strong></div>
                        <p class="letter-page__greeting" data-letter-preview="greeting"><?php echo esc_html(zigurat_letter_persian_digits($greeting ?: 'با سلام و احترام')); ?></p>
                    </div>
                <?php else: ?>
                    <div class="letter-page__continuation">ادامه نامه شماره <strong data-letter-preview="number"><?php echo esc_html(zigurat_letter_persian_digits($number ?: 'پس از ذخیره')); ?></strong></div>
                <?php endif; ?>

                <div class="letter-page__body" data-letter-body-page="<?php echo (int) $page_number; ?>"><?php echo $page_number === 1 ? $body_html : ''; ?></div>
                <div class="letter-page__signature" data-letter-signature style="<?php echo esc_attr($stamp_style); ?>">
                    <span data-letter-preview="signer_name"><?php echo esc_html(zigurat_letter_persian_digits($signer_name ?: 'عبارت پایانی')); ?></span>
                    <strong data-letter-preview="signer_title"><?php echo esc_html(zigurat_letter_persian_digits($signer_title ?: 'نام امضاکننده')); ?></strong>
                    <?php if ($stamp_url): ?><img class="letter-page__stamp" src="<?php echo esc_url($stamp_url); ?>" alt="مهر" data-letter-stamp<?php echo $include_stamp ? '' : ' hidden'; ?>><?php endif; ?>
                </div>
            </section>

            <footer class="letterfoot">
                <span data-letter-preview="header_title"><?php echo esc_html(zigurat_letter_persian_digits($header_title)); ?></span>
                <p><?php echo esc_html(zigurat_letter_persian_digits($brand_phone)); ?><?php echo $brand_phone && $brand_address ? ' — ' : ''; ?><?php echo esc_html(zigurat_letter_persian_digits($brand_address)); ?></p>
                <b data-letter-page-counter><?php echo esc_html('صفحه ' . zigurat_letter_persian_digits($page_number)); ?></b>
            </footer>
        </article>
    <?php endfor; ?>
</div>
