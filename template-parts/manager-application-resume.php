<?php
if (!defined('ABSPATH') || !zigurat_is_manager()) {
    return;
}

$application_id = isset($_GET['application_id']) && is_string($_GET['application_id'])
    ? absint($_GET['application_id'])
    : 0;
$application_nonce = isset($_GET['application_nonce']) && is_string($_GET['application_nonce'])
    ? sanitize_text_field(wp_unslash($_GET['application_nonce']))
    : '';
$application = $application_id ? get_post($application_id) : null;
$is_valid = $application
    && $application->post_type === 'partner_application'
    && $application->post_status === 'private'
    && wp_verify_nonce($application_nonce, 'zigurat_view_application_' . $application_id);

if (!$is_valid):
?>
    <div class="manager-resume-error">
        <h2>رزومه در دسترس نیست</h2>
        <p>پیوند مشاهده معتبر نیست یا منقضی شده است. لطفاً از فهرست درخواست‌ها دوباره روی «مشاهده رزومه» بزنید.</p>
        <a href="<?php echo esc_url(add_query_arg('manager-section', 'applications', home_url('/login/'))); ?>">بازگشت به درخواست‌ها</a>
    </div>
<?php
    return;
endif;

$meta = array();
foreach (array_keys(zigurat_application_fields()) as $field) {
    $meta[$field] = get_post_meta($application_id, '_application_' . $field, true);
}
$files = get_post_meta($application_id, '_application_files', true);
$files = is_array($files) ? $files : array();
$full_name = trim($meta['first_name'] . ' ' . $meta['last_name']);
$display_name = $meta['business_name'] ?: $full_name;
$photo = !empty($files['photo'][0]) && is_array($files['photo'][0]) ? $files['photo'][0] : null;
$photo_url = $photo ? zigurat_application_private_file_url($application_id, 'photo:0') : '';
$list_url = add_query_arg('manager-section', 'applications', home_url('/login/'));
$submitted_at = $meta['submitted_at'] ?: get_the_date('Y/m/d H:i', $application_id);
$edit_status = isset($_GET['application-edit']) && is_string($_GET['application-edit'])
    ? sanitize_key(wp_unslash($_GET['application-edit']))
    : '';
?>
<div class="manager-resume-page">
    <div class="manager-applications__toolbar no-print">
        <a href="<?php echo esc_url($list_url); ?>">بازگشت به درخواست‌ها</a>
        <button type="button" onclick="window.print()">چاپ رزومه</button>
        <?php if (current_user_can('manage_options')): ?>
            <button type="button" class="manager-application-edit-toggle" aria-expanded="false" aria-controls="manager-application-edit-form" onclick="var panel=document.getElementById('manager-application-edit-form'); var open=panel.hidden; panel.hidden=!open; this.setAttribute('aria-expanded', open ? 'true' : 'false'); if(open){panel.scrollIntoView({behavior:'smooth', block:'start'});}">ویرایش رزومه</button>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('این رزومه به زباله‌دان منتقل شود؟');">
                <input type="hidden" name="action" value="zigurat_delete_partner_application">
                <input type="hidden" name="application_id" value="<?php echo esc_attr($application_id); ?>">
                <input type="hidden" name="redirect_to" value="<?php echo esc_url($list_url); ?>">
                <?php wp_nonce_field('zigurat_delete_partner_application_' . $application_id); ?>
                <button class="manager-application-delete" type="submit">حذف رزومه</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($edit_status === 'saved'): ?>
        <div class="manager-resume-notice manager-resume-notice--success" role="status">تغییرات رزومه با موفقیت ذخیره شد.</div>
    <?php elseif ($edit_status === 'too-many-files'): ?>
        <div class="manager-resume-notice manager-resume-notice--error" role="alert">مجموع نمونه‌کارها نمی‌تواند بیشتر از ۵ فایل باشد.</div>
    <?php elseif ($edit_status === 'upload-error'): ?>
        <div class="manager-resume-notice manager-resume-notice--error" role="alert">بارگذاری فایل انجام نشد. فرمت و سقف ۵ مگابایت را بررسی کنید.</div>
    <?php elseif ($edit_status === 'invalid'): ?>
        <div class="manager-resume-notice manager-resume-notice--error" role="alert">فیلدهای ضروری رزومه را کامل کنید.</div>
    <?php endif; ?>

    <?php if (current_user_can('manage_options')): ?>
        <section id="manager-application-edit-form" class="manager-application-edit no-print" <?php echo $edit_status && $edit_status !== 'saved' ? '' : 'hidden'; ?>>
            <div class="manager-application-edit__heading">
                <div><span>دسترسی مدیر کل</span><h2>ویرایش رزومه و فایل‌ها</h2></div>
                <button type="button" onclick="document.getElementById('manager-application-edit-form').hidden=true; document.querySelector('.manager-application-edit-toggle').setAttribute('aria-expanded','false');">بستن</button>
            </div>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="manager-application-edit__form">
                <input type="hidden" name="action" value="zigurat_update_partner_application">
                <input type="hidden" name="application_id" value="<?php echo esc_attr($application_id); ?>">
                <?php wp_nonce_field('zigurat_update_partner_application_' . $application_id); ?>

                <div class="manager-application-edit__grid">
                    <label>نوع درخواست<select name="application_type"><option value="collaborator" <?php selected($meta['application_type'], 'collaborator'); ?>>همکار اجرایی</option><option value="supplier" <?php selected($meta['application_type'], 'supplier'); ?>>تأمین‌کننده</option></select></label>
                    <label>نام *<input type="text" name="first_name" value="<?php echo esc_attr($meta['first_name']); ?>" required></label>
                    <label>نام خانوادگی *<input type="text" name="last_name" value="<?php echo esc_attr($meta['last_name']); ?>" required></label>
                    <label>نام مجموعه/کارگاه<input type="text" name="business_name" value="<?php echo esc_attr($meta['business_name']); ?>"></label>
                    <label>شماره تماس *<input type="tel" name="phone" value="<?php echo esc_attr($meta['phone']); ?>" required></label>
                    <label>ایمیل<input type="email" name="email" value="<?php echo esc_attr($meta['email']); ?>"></label>
                    <label>زمینه فعالیت *<input type="text" name="profession" value="<?php echo esc_attr($meta['profession']); ?>" required></label>
                    <label>سابقه فعالیت (سال)<input type="number" name="experience_years" min="0" max="70" value="<?php echo esc_attr($meta['experience_years']); ?>"></label>
                    <label>استان *<select name="province" required><option value="">انتخاب استان</option><?php foreach (zigurat_application_provinces() as $province): ?><option value="<?php echo esc_attr($province); ?>" <?php selected($meta['province'], $province); ?>><?php echo esc_html($province); ?></option><?php endforeach; ?></select></label>
                    <label>شهر *<input type="text" name="city" value="<?php echo esc_attr($meta['city']); ?>" required></label>
                    <label class="manager-application-edit__wide">شهرهای قابل همکاری<textarea name="work_cities" rows="2"><?php echo esc_textarea($meta['work_cities']); ?></textarea></label>
                    <label class="manager-application-edit__wide">توضیحات<textarea name="description" rows="5"><?php echo esc_textarea($meta['description']); ?></textarea></label>
                    <label class="manager-application-edit__check"><input type="checkbox" name="nationwide" value="1" <?php checked($meta['nationwide'], '1'); ?>> امکان همکاری سراسر ایران</label>
                </div>

                <div class="manager-application-existing-files">
                    <h3>فایل‌های فعلی</h3>
                    <?php foreach (array('photo' => 'عکس متقاضی/مجموعه', 'national_card' => 'کارت ملی', 'portfolio' => 'نمونه‌کار') as $group => $label): ?>
                        <?php foreach ((array) ($files[$group] ?? array()) as $index => $file):
                            $file_url = zigurat_application_private_file_url($application_id, $group . ':' . $index);
                            $is_image = strpos((string) ($file['mime'] ?? ''), 'image/') === 0;
                        ?>
                            <label class="manager-application-existing-file">
                                <?php if ($is_image): ?><img src="<?php echo esc_url($file_url); ?>" alt=""><?php else: ?><span>PDF</span><?php endif; ?>
                                <small><?php echo esc_html($label . ' — ' . ($file['name'] ?? 'فایل')); ?></small>
                                <b><input type="checkbox" name="remove_application_files[]" value="<?php echo esc_attr($group . ':' . $index); ?>"> حذف این فایل</b>
                            </label>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>

                <div class="application-files manager-application-edit__uploads">
                    <div class="application-dropzone" data-upload-zone><label>جایگزینی عکس متقاضی/مجموعه<span class="application-dropzone__prompt">عکس جدید را رها یا انتخاب کنید</span><input type="file" name="edit_applicant_photo" accept="image/jpeg,image/png,image/webp" data-upload-input data-max-files="1"><small>در صورت انتخاب، جای عکس فعلی را می‌گیرد.</small></label><div class="application-upload-preview" data-upload-preview></div></div>
                    <div class="application-dropzone" data-upload-zone><label>جایگزینی کارت ملی<span class="application-dropzone__prompt">عکس جدید را رها یا انتخاب کنید</span><input type="file" name="edit_national_card" accept="image/jpeg,image/png,image/webp" data-upload-input data-max-files="1"></label><div class="application-upload-preview" data-upload-preview></div></div>
                    <div class="application-dropzone" data-upload-zone><label>افزودن نمونه‌کار جدید<span class="application-dropzone__prompt">چند فایل را باهم یا در چند مرحله انتخاب کنید</span><input type="file" name="edit_portfolio[]" accept="image/jpeg,image/png,image/webp,application/pdf" data-upload-input data-append-files="1" data-max-files="5" multiple><small>مجموع فایل‌های باقی‌مانده و جدید حداکثر ۵ عدد است.</small></label><div class="application-upload-preview" data-upload-preview></div></div>
                </div>
                <button class="manager-application-edit__save" type="submit">ذخیره تغییرات رزومه</button>
            </form>
        </section>
    <?php endif; ?>

    <article class="manager-resume" aria-labelledby="application-resume-title">
        <header class="manager-resume__header">
            <div class="manager-resume__photo">
                <?php if ($photo_url): ?>
                    <img src="<?php echo esc_url($photo_url); ?>" alt="عکس <?php echo esc_attr($display_name); ?>">
                <?php else: ?>
                    <span aria-hidden="true"><?php echo esc_html(function_exists('mb_substr') ? mb_substr($display_name ?: 'ز', 0, 1) : substr($display_name ?: 'Z', 0, 1)); ?></span>
                <?php endif; ?>
            </div>
            <div class="manager-resume__identity">
                <span class="manager-resume__eyebrow">رزومه همکاری با زیگورات</span>
                <h2 id="application-resume-title"><?php echo esc_html($display_name ?: 'متقاضی همکاری'); ?></h2>
                <?php if ($meta['business_name'] && $full_name): ?>
                    <p class="manager-resume__person">نماینده: <?php echo esc_html($full_name); ?></p>
                <?php endif; ?>
                <div class="manager-resume__badges">
                    <span><?php echo esc_html(zigurat_application_type_label($meta['application_type'])); ?></span>
                    <span><?php echo esc_html($meta['profession'] ?: 'زمینه فعالیت ثبت نشده'); ?></span>
                </div>
            </div>
            <div class="manager-resume__reference">
                <strong>شماره درخواست</strong>
                <span>#<?php echo esc_html(number_format_i18n($application_id)); ?></span>
                <small>ثبت: <?php echo esc_html($submitted_at); ?></small>
            </div>
        </header>

        <div class="manager-resume__contact-strip">
            <div><small>شماره تماس</small><strong class="ltr-cell"><?php echo esc_html($meta['phone'] ?: '—'); ?></strong></div>
            <div><small>ایمیل</small><strong class="ltr-cell"><?php echo esc_html($meta['email'] ?: '—'); ?></strong></div>
            <div><small>محل فعالیت</small><strong><?php echo esc_html(zigurat_application_location_label($meta['province'], $meta['city']) ?: '—'); ?></strong></div>
        </div>

        <div class="manager-resume__grid">
            <section class="manager-resume__section manager-resume__section--wide">
                <h3>معرفی و توضیحات</h3>
                <p><?php echo nl2br(esc_html($meta['description'] ?: 'توضیحی ثبت نشده است.')); ?></p>
            </section>

            <section class="manager-resume__section">
                <h3>اطلاعات حرفه‌ای</h3>
                <dl>
                    <div><dt>نوع همکاری</dt><dd><?php echo esc_html(zigurat_application_type_label($meta['application_type'])); ?></dd></div>
                    <div><dt>زمینه فعالیت</dt><dd><?php echo esc_html($meta['profession'] ?: '—'); ?></dd></div>
                    <div><dt>سابقه فعالیت</dt><dd><?php echo $meta['experience_years'] !== '' ? esc_html(number_format_i18n((int) $meta['experience_years']) . ' سال') : '—'; ?></dd></div>
                    <div><dt>نام مجموعه/کارگاه</dt><dd><?php echo esc_html($meta['business_name'] ?: '—'); ?></dd></div>
                </dl>
            </section>

            <section class="manager-resume__section">
                <h3>محدوده همکاری</h3>
                <dl>
                    <div><dt>استان</dt><dd><?php echo esc_html($meta['province'] ?: '—'); ?></dd></div>
                    <div><dt>شهر</dt><dd><?php echo esc_html($meta['city'] ?: '—'); ?></dd></div>
                    <div><dt>شهرهای قابل اعزام</dt><dd><?php echo nl2br(esc_html($meta['work_cities'] ?: '—')); ?></dd></div>
                    <div><dt>اعزام سراسر ایران</dt><dd><?php echo $meta['nationwide'] ? 'بله' : 'خیر'; ?></dd></div>
                </dl>
            </section>

            <?php if ($meta['application_type'] !== 'supplier' || !empty($files['national_card'])): ?>
                <section class="manager-resume__section manager-resume__section--wide">
                    <h3>مدارک و فایل‌ها</h3>
                    <div class="manager-resume__documents">
                        <?php foreach ((array) ($files['national_card'] ?? array()) as $index => $file):
                            $card_url = zigurat_application_private_file_url($application_id, 'national_card:' . $index);
                            $card_is_image = strpos((string) ($file['mime'] ?? ''), 'image/') === 0;
                        ?>
                            <a class="manager-resume__document-card" href="<?php echo esc_url($card_url); ?>" target="_blank" rel="noopener">
                                <?php if ($card_is_image): ?>
                                    <img src="<?php echo esc_url($card_url); ?>" alt="تصویر کارت ملی <?php echo esc_attr($display_name); ?>">
                                <?php else: ?>
                                    <span class="manager-resume__file-icon">PDF</span>
                                <?php endif; ?>
                                <strong>کارت ملی</strong>
                                <small><?php echo esc_html($file['name'] ?? 'مشاهده فایل'); ?></small>
                            </a>
                        <?php endforeach; ?>
                        <?php if (empty($files['national_card'])): ?><span>مدرک هویتی در دسترس نیست.</span><?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (!empty($files['portfolio']) && is_array($files['portfolio'])): ?>
                <section class="manager-resume__section manager-resume__section--wide manager-resume__portfolio-section">
                    <h3>نمونه‌کارها</h3>
                    <div class="manager-resume__portfolio">
                        <?php foreach ($files['portfolio'] as $index => $file):
                            $file_url = zigurat_application_private_file_url($application_id, 'portfolio:' . $index);
                            $is_image = isset($file['mime']) && strpos((string) $file['mime'], 'image/') === 0;
                        ?>
                            <a href="<?php echo esc_url($file_url); ?>" target="_blank" rel="noopener">
                                <?php if ($is_image): ?>
                                    <img src="<?php echo esc_url($file_url); ?>" alt="نمونه‌کار <?php echo esc_attr($index + 1); ?>">
                                <?php else: ?>
                                    <span class="manager-resume__file-icon">PDF</span>
                                <?php endif; ?>
                                <small><?php echo esc_html($file['name'] ?? ('نمونه‌کار ' . ($index + 1))); ?></small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>

        <footer class="manager-resume__footer">
            <span><?php echo esc_html(get_bloginfo('name')); ?></span>
            <span>این رزومه از درخواست خصوصی ثبت‌شده در سایت ایجاد شده است.</span>
        </footer>
    </article>
</div>
