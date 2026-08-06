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
?>
<div class="manager-resume-page">
    <div class="manager-applications__toolbar no-print">
        <a href="<?php echo esc_url($list_url); ?>">بازگشت به درخواست‌ها</a>
        <button type="button" onclick="window.print()">چاپ رزومه</button>
    </div>

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
            <div><small>محل فعالیت</small><strong><?php echo esc_html(trim($meta['province'] . '، ' . $meta['city'], '، ') ?: '—'); ?></strong></div>
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

            <section class="manager-resume__section manager-resume__section--wide">
                <h3>مدارک و فایل‌ها</h3>
                <div class="manager-resume__documents">
                    <?php foreach ((array) ($files['national_card'] ?? array()) as $index => $file): ?>
                        <a href="<?php echo esc_url(zigurat_application_private_file_url($application_id, 'national_card:' . $index)); ?>" target="_blank" rel="noopener">
                            <span>کارت ملی</span>
                            <small><?php echo esc_html($file['name'] ?? 'مشاهده فایل'); ?></small>
                        </a>
                    <?php endforeach; ?>
                    <?php if (empty($files['national_card'])): ?><span>مدرک هویتی در دسترس نیست.</span><?php endif; ?>
                </div>
            </section>

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
