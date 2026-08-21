<?php
/* Template Name: Invoices */
if (!defined('ABSPATH')) { exit; }
zigurat_require_manager();

$brand = isset($_REQUEST['invoice_brand']) ? sanitize_key(wp_unslash($_REQUEST['invoice_brand'])) : (isset($_REQUEST['brand']) ? sanitize_key(wp_unslash($_REQUEST['brand'])) : '');
$view = isset($_REQUEST['invoice_view']) ? sanitize_key(wp_unslash($_REQUEST['invoice_view'])) : (isset($_REQUEST['view']) ? sanitize_key(wp_unslash($_REQUEST['view'])) : '');
$document_type = isset($_REQUEST['invoice_type']) ? sanitize_key(wp_unslash($_REQUEST['invoice_type'])) : (isset($_REQUEST['type']) ? sanitize_key(wp_unslash($_REQUEST['type'])) : 'proforma');
$edit_id = isset($_REQUEST['invoice_edit']) ? absint($_REQUEST['invoice_edit']) : (isset($_REQUEST['edit']) ? absint($_REQUEST['edit']) : 0);
$from_proforma_id = isset($_REQUEST['invoice_from_proforma'])
    ? absint($_REQUEST['invoice_from_proforma'])
    : absint($_REQUEST['source_proforma_id'] ?? 0);
$branch_from_id = isset($_REQUEST['invoice_branch_from'])
    ? absint($_REQUEST['invoice_branch_from'])
    : absint($_REQUEST['branch_source_id'] ?? 0);
$form_error = null;
$settings_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_invoice_settings'])) {
    $nonce = isset($_POST['invoice_settings_nonce']) ? sanitize_text_field(wp_unslash($_POST['invoice_settings_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'zigurat_save_invoice_settings')) {
        $settings_error = new WP_Error('invalid_nonce', 'درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.');
    } else {
        $settings_saved = zigurat_invoice_save_initial_settings($_POST);
        if (is_wp_error($settings_saved)) {
            $settings_error = $settings_saved;
        } else {
            wp_safe_redirect(zigurat_invoice_page_url(array('view'=>'settings','settings-status'=>'saved')));
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_invoice'])) {
    $nonce = isset($_POST['invoice_nonce']) ? sanitize_text_field(wp_unslash($_POST['invoice_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'zigurat_save_invoice')) {
        $form_error = new WP_Error('invalid_nonce', 'درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.');
    } else {
        $saved = zigurat_invoice_save($_POST);
        if (is_wp_error($saved)) {
            $form_error = $saved;
        } else {
            wp_safe_redirect(zigurat_invoice_page_url(array('brand'=>$saved->brand,'view'=>'form','type'=>$saved->document_type,'edit'=>$saved->id,'invoice-status'=>'saved')));
            exit;
        }
    }
}

if ($view === 'export') {
    $export_nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
    if (!wp_verify_nonce($export_nonce, 'zigurat_invoice_export')) {
        wp_die('درخواست خروجی معتبر نیست.', 'خطا', array('response'=>403));
    }
    zigurat_invoice_download_xlsx(array(
        'brand' => $brand,
        'type' => isset($_GET['filter_type']) ? sanitize_key(wp_unslash($_GET['filter_type'])) : '',
        'status' => isset($_GET['filter_status']) ? sanitize_key(wp_unslash($_GET['filter_status'])) : '',
        'search' => isset($_GET['invoice_search']) ? sanitize_text_field(wp_unslash($_GET['invoice_search'])) : '',
    ));
}

if ($view === 'print') {
    $invoice = zigurat_invoice_get(isset($_GET['invoice_id']) ? absint($_GET['invoice_id']) : (isset($_GET['id']) ? absint($_GET['id']) : 0));
    if (!$invoice) {
        wp_die('فاکتور پیدا نشد.', 'خطا', array('response'=>404));
    }
    $print_subject = trim((string) ($invoice->subject ?: zigurat_invoice_document_label($invoice->document_type)));
    $print_subject = preg_replace('/[\\\\\/:*?"<>|]+/u', '-', $print_subject);
    $print_number = str_replace('/', '-', zigurat_invoice_object_number($invoice));
    $print_title = $print_number . '. ' . $print_subject;
    remove_action('wp_head', '_wp_render_title_tag', 1);
    ?><!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php echo esc_html($print_title); ?></title><?php wp_head(); ?><script>window.ziguratInvoicePrintTitle=<?php echo wp_json_encode($print_title, JSON_UNESCAPED_UNICODE); ?>;document.title=window.ziguratInvoicePrintTitle;window.addEventListener('beforeprint',function(){document.title=window.ziguratInvoicePrintTitle;});function ziguratPrintInvoice(){document.title=window.ziguratInvoicePrintTitle;window.requestAnimationFrame(function(){window.setTimeout(function(){window.print();},100);});}</script></head><body class="invoice-print-body" data-print-filename="<?php echo esc_attr($print_title); ?>"><div class="invoice-print-toolbar no-print"><a href="<?php echo esc_url(zigurat_invoice_page_url(array('brand'=>$invoice->brand,'view'=>'list'))); ?>">بازگشت به فهرست</a><a href="<?php echo esc_url(zigurat_invoice_page_url(array('brand'=>$invoice->brand,'view'=>'form','type'=>$invoice->document_type,'edit'=>$invoice->id))); ?>">اصلاح فاکتور</a><button type="button" onclick="ziguratPrintInvoice()">چاپ / ذخیره PDF</button></div><main class="invoice-print-sheet"><?php get_template_part('template-parts/invoice-document', null, array('invoice'=>$invoice)); ?></main><?php wp_footer(); ?></body></html><?php
    exit;
}

$editing_invoice = $edit_id ? zigurat_invoice_get($edit_id) : null;
$source_proforma = null;
$branch_source = null;
if ($editing_invoice) {
    $brand = $editing_invoice->brand;
    $document_type = $editing_invoice->document_type;
} elseif ($from_proforma_id) {
    $candidate_proforma = zigurat_invoice_get($from_proforma_id);
    if ($candidate_proforma && $candidate_proforma->document_type === 'proforma') {
        $previous_conversion = zigurat_invoice_get_conversion($from_proforma_id);
        if ($previous_conversion && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_safe_redirect(zigurat_invoice_page_url(array(
                'brand' => $previous_conversion->brand,
                'view' => 'form',
                'type' => 'invoice',
                'edit' => $previous_conversion->id,
                'invoice-status' => 'already-converted',
            )));
            exit;
        }
        $source_proforma = $candidate_proforma;
        $brand = $source_proforma->brand;
        $document_type = 'invoice';
        $view = 'form';
    } elseif (!$form_error) {
        $form_error = new WP_Error('invalid_source_proforma', 'پیش‌فاکتور انتخاب‌شده معتبر نیست.');
    }
} elseif ($branch_from_id) {
    $candidate_branch = zigurat_invoice_get($branch_from_id);
    $branch_root = $candidate_branch && !empty($candidate_branch->parent_invoice_id)
        ? zigurat_invoice_get($candidate_branch->parent_invoice_id)
        : $candidate_branch;
    if ($candidate_branch && $branch_root && !empty($branch_root->allow_branches)) {
        $branch_source = $candidate_branch;
        $brand = $candidate_branch->brand;
        $document_type = $candidate_branch->document_type;
        $view = 'form';
    } elseif (!$form_error) {
        $form_error = new WP_Error('invalid_branch', 'امکان ایجاد انشعاب برای این سند فعال نیست.');
    }
}
if (!in_array($brand, array('official','unofficial'), true)) { $brand = ''; }
if (!in_array($document_type, array('proforma','invoice'), true)) { $document_type = 'proforma'; }
if ($brand && $view === '') { $view = 'list'; }

get_header();
?>
<main class="invoice-admin-page <?php echo $brand === 'unofficial' ? 'invoice-admin-page--unofficial' : ''; ?>"><div class="container">
    <div class="invoice-admin-top no-print"><a href="<?php echo esc_url(zigurat_manager_login_url()); ?>">بازگشت به پنل مدیران</a><?php if ($brand || $view === 'settings'): ?><a href="<?php echo esc_url(zigurat_invoice_page_url()); ?>">انتخاب نوع فاکتور</a><?php endif; ?><?php if (current_user_can('manage_options')): ?><a class="<?php echo $view === 'settings' ? 'is-active' : ''; ?>" href="<?php echo esc_url(zigurat_invoice_page_url(array('view'=>'settings'))); ?>">تنظیمات اولیه فاکتورها</a><?php endif; ?></div>

    <?php if ($view === 'settings' && current_user_can('manage_options')): ?>
        <?php
        $seller_labels = array('name'=>'نام فروشنده','national_id'=>'شماره ملی/ثبت','economic_no'=>'شماره اقتصادی','province'=>'استان','county'=>'شهرستان','city'=>'شهر','postal_code'=>'کدپستی','phone'=>'تلفن');
        $brand_settings = array('official'=>zigurat_invoice_get_brand_settings('official'),'unofficial'=>zigurat_invoice_get_brand_settings('unofficial'));
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            foreach (array('official','unofficial') as $settings_brand) {
                foreach (array_keys($brand_settings[$settings_brand]['seller']) as $settings_key) {
                    if (isset($_POST['setting_'.$settings_brand.'_'.$settings_key])) $brand_settings[$settings_brand]['seller'][$settings_key]=wp_unslash($_POST['setting_'.$settings_brand.'_'.$settings_key]);
                }
                if (isset($_POST['setting_'.$settings_brand.'_payment_info'])) $brand_settings[$settings_brand]['payment_info']=wp_unslash($_POST['setting_'.$settings_brand.'_payment_info']);
                if (isset($_POST['setting_'.$settings_brand.'_notes'])) $brand_settings[$settings_brand]['notes']=wp_unslash($_POST['setting_'.$settings_brand.'_notes']);
                if (isset($_POST['setting_'.$settings_brand.'_tax_rate'])) $brand_settings[$settings_brand]['tax_rate']=wp_unslash($_POST['setting_'.$settings_brand.'_tax_rate']);
            }
        }
        ?>
        <section class="invoice-workspace invoice-settings-page">
            <div class="invoice-admin-heading"><span>تنظیمات مدیر کل</span><h1>تنظیمات اولیه فاکتورها</h1><p>این اطلاعات به‌عنوان مقدار پیش‌فرض در سندهای جدید قرار می‌گیرند و اطلاعات سندهای قبلی را تغییر نمی‌دهند.</p></div>
            <?php if ($settings_error): ?><div class="invoice-notice is-error" role="alert"><?php echo esc_html($settings_error->get_error_message()); ?></div><?php elseif (!empty($_GET['settings-status']) && $_GET['settings-status']==='saved'): ?><div class="invoice-notice is-success">تنظیمات اولیه فاکتورها ذخیره شد.</div><?php endif; ?>
            <form class="invoice-editor invoice-settings-form" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('zigurat_save_invoice_settings','invoice_settings_nonce'); ?><input type="hidden" name="save_invoice_settings" value="1">
                <div class="invoice-settings-grid">
                    <?php foreach (array('official'=>'زیگورات — فاکتور رسمی','unofficial'=>'فروشگاه دیاموند — فاکتور غیررسمی') as $settings_brand=>$settings_title): ?>
                        <fieldset><legend><?php echo esc_html($settings_title); ?></legend><div class="invoice-fields invoice-fields--2">
                            <?php foreach ($seller_labels as $settings_key=>$settings_label): ?><label><?php echo esc_html($settings_label); ?><input name="setting_<?php echo esc_attr($settings_brand.'_'.$settings_key); ?>" value="<?php echo esc_attr($brand_settings[$settings_brand]['seller'][$settings_key]??''); ?>" <?php echo $settings_key==='name'?'required':''; ?>></label><?php endforeach; ?>
                            <label class="is-full">نشانی<textarea name="setting_<?php echo esc_attr($settings_brand); ?>_address" rows="3"><?php echo esc_textarea($brand_settings[$settings_brand]['seller']['address']??''); ?></textarea></label>
                            <label class="is-full">توضیحات پیش‌فرض فاکتور<textarea name="setting_<?php echo esc_attr($settings_brand); ?>_notes" rows="4"><?php echo esc_textarea($brand_settings[$settings_brand]['notes'] ?? ''); ?></textarea></label>
                            <label class="is-full">اطلاعات پرداخت پیش‌فرض<textarea name="setting_<?php echo esc_attr($settings_brand); ?>_payment_info" rows="5"><?php echo esc_textarea($brand_settings[$settings_brand]['payment_info']); ?></textarea></label>
                            <div class="invoice-stamp-setting is-full">
                                <label>تصویر مهر فروشنده (PNG شفاف پیشنهاد می‌شود)<input type="file" name="setting_<?php echo esc_attr($settings_brand); ?>_stamp_file" accept="image/png,image/jpeg,image/webp"></label>
                                <input type="hidden" name="setting_<?php echo esc_attr($settings_brand); ?>_stamp_id" value="<?php echo absint($brand_settings[$settings_brand]['stamp_id'] ?? 0); ?>">
                                <?php if (!empty($brand_settings[$settings_brand]['stamp_id'])): ?>
                                    <div class="invoice-stamp-preview"><?php echo wp_get_attachment_image(absint($brand_settings[$settings_brand]['stamp_id']), 'thumbnail'); ?><label><input type="checkbox" name="setting_<?php echo esc_attr($settings_brand); ?>_remove_stamp" value="1"> حذف مهر فعلی</label></div>
                                <?php endif; ?>
                            </div>
                            <label>مالیات پیش‌فرض (%)<input type="number" min="0" max="100" step="0.01" name="setting_<?php echo esc_attr($settings_brand); ?>_tax_rate" value="<?php echo esc_attr($brand_settings[$settings_brand]['tax_rate']); ?>"></label>
                        </div></fieldset>
                    <?php endforeach; ?>
                </div>
                <fieldset><legend>شماره بعدی اسناد</legend><p class="invoice-settings-help">عددی را وارد کنید که سند بعدی با آن ثبت شود. برای جلوگیری از شماره تکراری، این عدد نمی‌تواند از آخرین شماره ثبت‌شده کوچک‌تر یا مساوی باشد.</p><div class="invoice-fields invoice-fields--4">
                    <?php foreach (array('official_proforma'=>'پیش‌فاکتور رسمی','official_invoice'=>'فاکتور رسمی','unofficial_proforma'=>'پیش‌فاکتور غیررسمی','unofficial_invoice'=>'فاکتور غیررسمی') as $number_key=>$number_label): [$number_brand,$number_type]=explode('_',$number_key,2); $number_value=zigurat_invoice_next_number($number_brand,$number_type); if(isset($_POST['next_'.$number_key]))$number_value=wp_unslash($_POST['next_'.$number_key]); ?><label><?php echo esc_html($number_label); ?><input type="number" min="1" step="1" name="next_<?php echo esc_attr($number_key); ?>" value="<?php echo esc_attr($number_value); ?>" required></label><?php endforeach; ?>
                </div></fieldset>
                <div class="invoice-editor-actions"><button type="submit">ذخیره تنظیمات اولیه</button></div>
            </form>
        </section>
    <?php elseif (!$brand): ?>
        <section class="invoice-brand-picker" aria-labelledby="invoice-section-title"><div class="invoice-admin-heading"><span>صدور آنلاین اسناد فروش</span><h1 id="invoice-section-title">بخش فاکتور</h1><p>نوع فاکتور را انتخاب کنید. شماره هر سند هنگام اولین ذخیره به‌صورت خودکار و غیرتکراری ثبت می‌شود.</p></div><div class="invoice-brand-cards"><a href="<?php echo esc_url(zigurat_invoice_page_url(array('brand'=>'official','view'=>'list'))); ?>"><span>رسمی</span><strong>فاکتور رسمی زیگورات</strong><small>با مشخصات ثبتی، مالیات ارزش افزوده و اطلاعات پرداخت</small></a><a href="<?php echo esc_url(zigurat_invoice_page_url(array('brand'=>'unofficial','view'=>'list'))); ?>"><span>غیررسمی</span><strong>فاکتور غیررسمی دیاموند</strong><small>مناسب پیش‌فاکتور و فاکتورهای فروشگاهی</small></a></div></section>
    <?php else: ?>
        <section class="invoice-workspace">
            <div class="invoice-admin-heading"><span><?php echo esc_html($brand === 'official' ? 'زیگورات' : 'دیاموند'); ?></span><h1><?php echo esc_html(zigurat_invoice_brand_label($brand)); ?></h1></div>
            <nav class="invoice-tabs no-print"><a class="<?php echo $view==='form'&&$document_type==='proforma'?'is-active':''; ?>" href="<?php echo esc_url(zigurat_invoice_page_url(array('brand'=>$brand,'view'=>'form','type'=>'proforma'))); ?>">ثبت پیش‌فاکتور</a><a class="<?php echo $view==='form'&&$document_type==='invoice'?'is-active':''; ?>" href="<?php echo esc_url(zigurat_invoice_page_url(array('brand'=>$brand,'view'=>'form','type'=>'invoice'))); ?>">ثبت فاکتور</a><a class="<?php echo $view==='list'?'is-active':''; ?>" href="<?php echo esc_url(zigurat_invoice_page_url(array('brand'=>$brand,'view'=>'list'))); ?>">نمایش لیست فاکتورها</a></nav>

            <?php if ($view === 'form'): ?>
                <?php
                $form_source = $editing_invoice ?: $source_proforma;
                $seller = $form_source ? (array)$form_source->seller : zigurat_invoice_default_seller($brand);
                $current_brand_settings = zigurat_invoice_get_brand_settings($brand);
                $seller_stamp_id = absint($seller['stamp_id'] ?? ($current_brand_settings['stamp_id'] ?? 0));
                $include_stamp = $_SERVER['REQUEST_METHOD']==='POST' ? !empty($_POST['include_stamp']) : ($form_source && !empty($seller['include_stamp']));
                $field = static function ($key, $default='') use ($editing_invoice, $source_proforma, $branch_source) {
                    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST[$key])) return wp_unslash($_POST[$key]);
                    if ($editing_invoice && isset($editing_invoice->$key)) return $editing_invoice->$key;
                    if ($branch_source && strpos($key, 'customer_') === 0 && isset($branch_source->$key)) return $branch_source->$key;
                    if ($source_proforma && !in_array($key, array('issue_date','status'), true) && isset($source_proforma->$key)) return $source_proforma->$key;
                    return $default;
                };
                if ($_SERVER['REQUEST_METHOD']==='POST') {
                    foreach ($seller as $seller_key=>$seller_value) if (isset($_POST['seller_'.$seller_key])) $seller[$seller_key]=wp_unslash($_POST['seller_'.$seller_key]);
                }
                if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['item_description']) && is_array($_POST['item_description'])) {
                    $form_items=array(); foreach($_POST['item_description'] as $i=>$description) $form_items[]=(object)array('description'=>wp_unslash($description),'quantity'=>wp_unslash($_POST['item_quantity'][$i]??1),'unit'=>wp_unslash($_POST['item_unit'][$i]??''),'unit_price'=>wp_unslash($_POST['item_unit_price'][$i]??0),'discount'=>wp_unslash($_POST['item_discount'][$i]??0));
                } else { $form_items=$form_source?$form_source->items:array((object)array('description'=>'','quantity'=>1,'unit'=>'عدد','unit_price'=>0,'discount'=>0)); }
                ?>
                <?php if ($form_error): ?><div class="invoice-notice is-error" role="alert"><?php echo esc_html($form_error->get_error_message()); ?></div><?php elseif ($branch_source): ?><div class="invoice-notice is-branch">در حال ساخت فرعی بعدی از سند شماره <bdi class="invoice-number" dir="ltr"><?php echo esc_html(zigurat_invoice_object_number($branch_source)); ?></bdi> هستید. فقط مشخصات مشتری منتقل شده و سایر فیلدهای فاکتور برای ثبت اطلاعات جدید خالی هستند.</div><?php elseif ($source_proforma): ?><div class="invoice-notice is-success">اطلاعات پیش‌فاکتور شماره <bdi class="invoice-number" dir="ltr"><?php echo esc_html(zigurat_invoice_object_number($source_proforma)); ?></bdi> منتقل شد. تاریخ و اطلاعات را بررسی کنید؛ شماره فاکتور فقط پس از کلیک روی ذخیره ساخته می‌شود.</div><?php elseif (!empty($_GET['invoice-status']) && $_GET['invoice-status']==='already-converted'): ?><div class="invoice-notice is-success">این فاکتور قبلاً از پیش‌فاکتور انتخاب‌شده ساخته شده است.</div><?php elseif (!empty($_GET['invoice-status']) && $_GET['invoice-status']==='saved'): ?><div class="invoice-notice is-success">فاکتور ذخیره شد. اکنون می‌توانید آن را چاپ، اصلاح یا در صورت فعال‌بودن انشعاب، فرعی بعدی را ایجاد کنید.</div><?php endif; ?>
                <form class="invoice-editor" method="post" data-invoice-editor data-invoice-unsaved="<?php echo $form_error ? '1' : '0'; ?>">
                    <?php wp_nonce_field('zigurat_save_invoice','invoice_nonce'); ?><input type="hidden" name="save_invoice" value="1"><input type="hidden" name="invoice_id" value="<?php echo $editing_invoice?(int)$editing_invoice->id:0; ?>"><input type="hidden" name="invoice_form_brand" value="<?php echo esc_attr($brand); ?>"><input type="hidden" name="invoice_form_document_type" value="<?php echo esc_attr($document_type); ?>"><?php if ($source_proforma): ?><input type="hidden" name="source_proforma_id" value="<?php echo (int) $source_proforma->id; ?>"><?php endif; ?><?php if ($branch_source): ?><input type="hidden" name="branch_source_id" value="<?php echo (int) $branch_source->id; ?>"><?php endif; ?>
                    <?php $header_number = $editing_invoice ? zigurat_invoice_object_number($editing_invoice) : ($branch_source ? zigurat_invoice_format_number($branch_source->document_number, zigurat_invoice_next_branch_suffix($branch_source)) : 'خودکار پس از ذخیره'); ?>
                    <div class="invoice-editor__title"><div><span><?php echo $editing_invoice?'اصلاح سند':($branch_source?'انشعاب جدید':($source_proforma?'تبدیل پیش‌فاکتور به فاکتور':'سند جدید')); ?></span><h2><?php echo esc_html(zigurat_invoice_document_label($document_type)); ?></h2></div><strong data-invoice-number data-base-number="<?php echo esc_attr($editing_invoice && empty($editing_invoice->parent_invoice_id) ? zigurat_invoice_format_number($editing_invoice->document_number) : ''); ?>">شماره: <span data-invoice-number-value class="<?php echo ($editing_invoice || $branch_source) ? 'invoice-number' : ''; ?>" <?php echo ($editing_invoice || $branch_source) ? 'dir="ltr"' : ''; ?>><?php echo esc_html($header_number); ?></span></strong></div>
                    <?php $branch_checked = $_SERVER['REQUEST_METHOD']==='POST' ? !empty($_POST['allow_branches']) : ($editing_invoice ? !empty($editing_invoice->allow_branches) : ($branch_source ? true : ($source_proforma && !empty($source_proforma->allow_branches)))); ?>
                    <fieldset><legend>اطلاعات سند</legend><div class="invoice-fields invoice-fields--4"><label>تاریخ شمسی *<input name="issue_date" value="<?php echo esc_attr($field('issue_date',zigurat_invoice_today_jalali())); ?>" pattern="[0-9۰-۹]{4}/[0-9۰-۹]{2}/[0-9۰-۹]{2}" required></label><label>وضعیت<select name="status"><option value="issued" <?php selected($field('status','issued'),'issued'); ?>>صادرشده</option><option value="draft" <?php selected($field('status','issued'),'draft'); ?>>پیش‌نویس</option></select></label><label class="is-wide">موضوع<input name="subject" value="<?php echo esc_attr($field('subject')); ?>" placeholder="مثلاً اجرای تابلو یا دکوراسیون آشپزخانه"></label></div><?php if (!$branch_source && (!$editing_invoice || empty($editing_invoice->parent_invoice_id))): ?><?php $branch_example_number = $editing_invoice ? zigurat_invoice_format_number($editing_invoice->document_number) : '200'; ?><label class="invoice-branch-toggle"><input type="checkbox" name="allow_branches" value="1" <?php checked($branch_checked); ?>><span><strong>این سند شماره پسونددار دارد</strong><small>با فعال‌کردن این گزینه، شماره همین سند به‌شکل <?php echo esc_html($branch_example_number . '/1'); ?> ذخیره می‌شود و فرعی بعدی <?php echo esc_html($branch_example_number . '/2'); ?> خواهد بود.</small></span></label><?php else: ?><div class="invoice-branch-status">این سند یک نسخه فرعی است و امکان ساخت فرعی بعدی را دارد.</div><?php endif; ?></fieldset>
                    <details class="invoice-seller-settings"><summary>مشخصات فروشنده (<?php echo esc_html($brand==='official'?'زیگورات':'دیاموند'); ?>)</summary><div class="invoice-fields invoice-fields--3"><?php foreach(array('name'=>'نام فروشنده','national_id'=>'شماره ملی/ثبت','economic_no'=>'شماره اقتصادی','province'=>'استان','county'=>'شهرستان','city'=>'شهر','postal_code'=>'کدپستی','phone'=>'تلفن') as $key=>$label): ?><label><?php echo esc_html($label); ?><input name="seller_<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($seller[$key]??''); ?>"></label><?php endforeach; ?><label class="is-full">نشانی<textarea name="seller_address" rows="2"><?php echo esc_textarea($seller['address']??''); ?></textarea></label></div></details>
                    <fieldset><legend>مشخصات خریدار</legend><div class="invoice-fields invoice-fields--3"><label class="is-wide">نام شخص حقیقی/حقوقی *<input name="customer_name" value="<?php echo esc_attr($field('customer_name')); ?>" required></label><label>شماره ملی/ثبت<input name="customer_national_id" value="<?php echo esc_attr($field('customer_national_id')); ?>"></label><label>شماره اقتصادی<input name="customer_economic_no" value="<?php echo esc_attr($field('customer_economic_no')); ?>"></label><label>استان<input name="customer_province" value="<?php echo esc_attr($field('customer_province')); ?>"></label><label>شهرستان<input name="customer_county" value="<?php echo esc_attr($field('customer_county')); ?>"></label><label>شهر<input name="customer_city" value="<?php echo esc_attr($field('customer_city')); ?>"></label><label>کدپستی<input name="customer_postal_code" value="<?php echo esc_attr($field('customer_postal_code')); ?>"></label><label>تلفن<input name="customer_phone" value="<?php echo esc_attr($field('customer_phone')); ?>"></label><label class="is-full">نشانی<textarea name="customer_address" rows="2"><?php echo esc_textarea($field('customer_address')); ?></textarea></label></div></fieldset>
                    <fieldset><legend>ردیف‌های کالا و خدمات</legend><div class="invoice-items-wrap"><table class="invoice-items-editor"><thead><tr><th>شرح *</th><th>مقدار *</th><th>واحد</th><th>مبلغ واحد</th><th>تخفیف ردیف</th><th>جمع</th><th></th></tr></thead><tbody data-invoice-items><?php foreach($form_items as $item): ?><tr><td><textarea name="item_description[]" rows="2" required><?php echo esc_textarea($item->description); ?></textarea></td><td><input name="item_quantity[]" type="number" min="0" step="1" value="<?php echo esc_attr($item->quantity); ?>" required></td><td><input name="item_unit[]" value="<?php echo esc_attr($item->unit); ?>"></td><td><input name="item_unit_price[]" type="text" inputmode="numeric" value="<?php echo esc_attr($item->unit_price); ?>" data-money required></td><td><input name="item_discount[]" type="text" inputmode="numeric" value="<?php echo esc_attr($item->discount); ?>" data-money></td><td data-line-total>۰</td><td><button type="button" data-remove-item aria-label="حذف ردیف">×</button></td></tr><?php endforeach; ?></tbody></table></div><button class="invoice-add-row" type="button" data-add-item>افزودن ردیف</button></fieldset>
                    <div class="invoice-editor-bottom"><fieldset><legend>توضیحات و پرداخت</legend><label>توضیحات<textarea name="notes" rows="5"><?php echo esc_textarea($field('notes',zigurat_invoice_default_notes($brand))); ?></textarea></label><label>اطلاعات پرداخت<textarea name="payment_info" rows="5"><?php echo esc_textarea($field('payment_info',zigurat_invoice_default_payment_info($brand))); ?></textarea></label><?php if ($seller_stamp_id): ?><input type="hidden" name="seller_stamp_id" value="<?php echo (int) $seller_stamp_id; ?>"><label class="invoice-stamp-toggle"><input type="checkbox" name="include_stamp" value="1" <?php checked($include_stamp); ?>> درج مهر فروشنده در فاکتور چاپی</label><?php endif; ?></fieldset><fieldset><legend>محاسبات</legend><div class="invoice-fields"><label>تخفیف کلی<input name="discount" type="text" inputmode="numeric" value="<?php echo esc_attr($field('discount',0)); ?>" data-money></label><label>حمل و بسته‌بندی<input name="shipping" type="text" inputmode="numeric" value="<?php echo esc_attr($field('shipping',0)); ?>" data-money></label><label>ضریب بالاسری/سود پیمانکار (%)<input name="overhead_rate" type="number" min="0" max="100" step="0.01" value="<?php echo esc_attr($field('overhead_rate',0)); ?>"></label><?php if ($brand === 'official'): ?><label>ضریب بیمه (%)<input name="insurance_rate" type="number" min="0" max="100" step="0.01" value="<?php echo esc_attr($field('insurance_rate',0)); ?>"></label><?php endif; ?><label>مالیات ارزش افزوده (%)<input name="tax_rate" type="number" min="0" max="100" step="0.01" value="<?php echo esc_attr($field('tax_rate',zigurat_invoice_default_tax_rate($brand))); ?>"></label><label>پرداختی<input name="paid_amount" type="text" inputmode="numeric" value="<?php echo esc_attr($field('paid_amount',0)); ?>" data-money></label></div><dl class="invoice-live-totals"><div><dt>جمع اقلام</dt><dd data-subtotal>۰ ریال</dd></div><div><dt>بالاسری/سود پیمانکار</dt><dd data-overhead>۰ ریال</dd></div><?php if ($brand === 'official'): ?><div><dt>بیمه</dt><dd data-insurance>۰ ریال</dd></div><?php endif; ?><div><dt>مالیات</dt><dd data-tax>۰ ریال</dd></div><div><dt>جمع کل</dt><dd data-grand-total>۰ ریال</dd></div><div><dt>مانده</dt><dd data-balance>۰ ریال</dd></div></dl></fieldset></div>
                    <div class="invoice-editor-actions no-print"><button type="submit">ذخیره <?php echo esc_html(zigurat_invoice_document_label($document_type)); ?></button><?php if($editing_invoice): ?><a href="<?php echo esc_url(zigurat_invoice_page_url(array('view'=>'print','id'=>$editing_invoice->id))); ?>" target="_blank" rel="noopener">مشاهده و چاپ</a><?php if(zigurat_invoice_can_branch($editing_invoice)): ?><a class="invoice-branch-action" href="<?php echo esc_url(zigurat_invoice_page_url(array('brand'=>$editing_invoice->brand,'view'=>'form','type'=>$editing_invoice->document_type,'branch_from'=>$editing_invoice->id))); ?>">ایجاد فرعی بعدی</a><?php endif; ?><?php endif; ?></div>
                </form>
            <?php else: ?>
                <?php
                $list_type=isset($_GET['filter_type'])?sanitize_key(wp_unslash($_GET['filter_type'])):''; $list_status=isset($_GET['filter_status'])?sanitize_key(wp_unslash($_GET['filter_status'])):''; $list_search=isset($_GET['invoice_search'])?sanitize_text_field(wp_unslash($_GET['invoice_search'])):''; $list_page=isset($_GET['invoice_page'])?max(1,absint($_GET['invoice_page'])):1;
                $invoice_list=zigurat_invoice_list(array('brand'=>$brand,'type'=>$list_type,'status'=>$list_status,'search'=>$list_search,'page'=>$list_page));
                ?>
                <?php $excel_url=wp_nonce_url(zigurat_invoice_page_url(array('brand'=>$brand,'view'=>'export','filter_type'=>$list_type,'filter_status'=>$list_status,'invoice_search'=>$list_search)),'zigurat_invoice_export'); ?>
                <div class="invoice-list-heading"><h2>لیست فاکتورها</h2><div class="invoice-list-heading__actions no-print"><strong><?php echo esc_html(number_format_i18n($invoice_list['total'])); ?> سند</strong><a class="invoice-excel-button" href="<?php echo esc_url($excel_url); ?>">خروجی اکسل</a></div></div>
                <form class="invoice-list-filters no-print" method="get"><input type="hidden" name="invoice_brand" value="<?php echo esc_attr($brand); ?>"><input type="hidden" name="invoice_view" value="list"><label>نوع سند<select name="filter_type"><option value="">همه</option><option value="proforma" <?php selected($list_type,'proforma'); ?>>پیش‌فاکتور</option><option value="invoice" <?php selected($list_type,'invoice'); ?>>فاکتور</option></select></label><label>وضعیت<select name="filter_status"><option value="">همه</option><option value="issued" <?php selected($list_status,'issued'); ?>>صادرشده</option><option value="draft" <?php selected($list_status,'draft'); ?>>پیش‌نویس</option></select></label><label>جستجو<input type="search" name="invoice_search" value="<?php echo esc_attr($list_search); ?>" placeholder="شماره، خریدار یا موضوع"></label><button type="submit">اعمال فیلتر</button><?php if($list_type||$list_status||$list_search): ?><a href="<?php echo esc_url(zigurat_invoice_page_url(array('brand'=>$brand,'view'=>'list'))); ?>">حذف فیلتر</a><?php endif; ?></form>
                <div class="invoice-list-table-wrap">
                    <table class="invoice-list-table">
                        <thead><tr><th>شماره</th><th>نوع سند</th><th>تاریخ</th><th>خریدار</th><th>موضوع</th><th>جمع کل</th><th>مانده</th><th>وضعیت</th><th>عملیات</th></tr></thead>
                        <tbody>
                        <?php if ($invoice_list['items']): foreach ($invoice_list['items'] as $row): ?>
                            <?php $conversion = $row->document_type === 'proforma' ? zigurat_invoice_get_conversion($row->id) : null; ?>
                            <tr class="invoice-list-row--<?php echo esc_attr($row->document_type); ?> <?php echo !empty($row->number_suffix) ? 'invoice-list-row--branch' : ''; ?>">
                                <td><strong><bdi class="invoice-number" dir="ltr"><?php echo esc_html(zigurat_invoice_object_number($row)); ?></bdi></strong></td>
                                <td><?php echo esc_html(zigurat_invoice_document_label($row->document_type)); ?></td>
                                <td><?php echo esc_html($row->issue_date); ?></td>
                                <td><?php echo esc_html($row->customer_name); ?></td>
                                <td><?php echo esc_html($row->subject ?: '—'); ?></td>
                                <td><?php echo esc_html(zigurat_invoice_format_money($row->grand_total)); ?></td>
                                <td><?php echo esc_html(zigurat_invoice_format_money($row->balance)); ?></td>
                                <td><span class="invoice-status invoice-status--<?php echo esc_attr($row->status); ?>"><?php echo esc_html(zigurat_invoice_status_label($row->status)); ?></span></td>
                                <td>
                                    <a href="<?php echo esc_url(zigurat_invoice_page_url(array('brand'=>$row->brand,'view'=>'form','type'=>$row->document_type,'edit'=>$row->id))); ?>">اصلاح</a>
                                    <a href="<?php echo esc_url(zigurat_invoice_page_url(array('view'=>'print','id'=>$row->id))); ?>" target="_blank" rel="noopener">چاپ</a>
                                    <?php if (empty($row->parent_invoice_id) && zigurat_invoice_can_branch($row)): ?>
                                        <a class="invoice-branch-action" href="<?php echo esc_url(zigurat_invoice_page_url(array('brand'=>$row->brand,'view'=>'form','type'=>$row->document_type,'branch_from'=>$row->id))); ?>">ایجاد انشعاب</a>
                                    <?php endif; ?>
                                    <?php if ($row->document_type === 'proforma' && !$conversion): ?>
                                        <a class="invoice-convert-action" href="<?php echo esc_url(zigurat_invoice_page_url(array('brand'=>$row->brand,'view'=>'form','type'=>'invoice','from_proforma'=>$row->id))); ?>">تبدیل به فاکتور</a>
                                    <?php elseif ($conversion): ?>
                                        <a class="invoice-converted-link" href="<?php echo esc_url(zigurat_invoice_page_url(array('brand'=>$conversion->brand,'view'=>'form','type'=>'invoice','edit'=>$conversion->id))); ?>">فاکتور <bdi class="invoice-number" dir="ltr"><?php echo esc_html(zigurat_invoice_object_number($conversion)); ?></bdi></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="9">هنوز فاکتوری ثبت نشده است.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($invoice_list['pages'] > 1): ?>
                    <nav class="invoice-pagination no-print" aria-label="صفحه‌بندی فاکتورها">
                        <?php echo wp_kses_post(paginate_links(array(
                            'base'      => esc_url_raw(add_query_arg('invoice_page', '%#%', zigurat_invoice_page_url(array('brand'=>$brand,'view'=>'list','filter_type'=>$list_type,'filter_status'=>$list_status,'invoice_search'=>$list_search)))),
                            'format'    => '',
                            'current'   => $invoice_list['page'],
                            'total'     => $invoice_list['pages'],
                            'prev_text' => 'قبلی',
                            'next_text' => 'بعدی',
                            'type'      => 'plain',
                        ))); ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div></main>
<?php get_footer(); ?>
