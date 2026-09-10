<?php
if (!defined('ABSPATH') || !zigurat_is_manager()) {
    return;
}

$view = isset($_GET['letter-view']) ? sanitize_key(wp_unslash($_GET['letter-view'])) : 'list';
if (!in_array($view, array('list', 'new', 'edit', 'settings'), true) || ($view === 'settings' && !current_user_can('manage_options'))) {
    $view = 'list';
}
$type_filter = isset($_GET['letter-type']) ? sanitize_key(wp_unslash($_GET['letter-type'])) : '';
if (!in_array($type_filter, array('official', 'unofficial'), true)) {
    $type_filter = '';
}
$letter_id = isset($_GET['letter-id']) ? absint($_GET['letter-id']) : 0;
$editing_letter = $view === 'edit' ? zigurat_letter_get($letter_id) : null;
if ($view === 'edit' && !$editing_letter) {
    $view = 'list';
}
$status = isset($_GET['letter-status']) ? sanitize_key(wp_unslash($_GET['letter-status'])) : '';
$base_url = zigurat_letters_page_url();
?>
<section class="manager-letters" aria-labelledby="manager-letters-title">
    <div class="manager-letters__toolbar no-print">
        <a href="<?php echo esc_url(zigurat_manager_login_url()); ?>">بازگشت به پنل مدیران</a>
        <div>
            <a class="<?php echo $view === 'list' ? 'is-active' : ''; ?>" href="<?php echo esc_url($base_url); ?>">فهرست نامه‌ها</a>
            <a class="<?php echo $view === 'new' && $type_filter === 'official' ? 'is-active' : ''; ?>" href="<?php echo esc_url(zigurat_letters_page_url(array('letter-view' => 'new', 'letter-type' => 'official'))); ?>">نامه رسمی</a>
            <a class="<?php echo $view === 'new' && $type_filter === 'unofficial' ? 'is-active' : ''; ?>" href="<?php echo esc_url(zigurat_letters_page_url(array('letter-view' => 'new', 'letter-type' => 'unofficial'))); ?>">نامه غیررسمی</a>
            <?php if (current_user_can('manage_options')): ?><a class="<?php echo $view === 'settings' ? 'is-active' : ''; ?>" href="<?php echo esc_url(zigurat_letters_page_url(array('letter-view' => 'settings'))); ?>">تنظیمات نامه‌ها</a><?php endif; ?>
        </div>
    </div>

    <header class="manager-letters__heading">
        <div><span>نامه‌نگاری سازمانی</span><h2 id="manager-letters-title"><?php echo $view === 'list' ? 'نامه‌های ثبت‌شده' : ($view === 'settings' ? 'تنظیمات نامه‌ها' : ($editing_letter ? 'ویرایش نامه' : zigurat_letter_type_label($type_filter))); ?></h2></div>
        <p><?php echo $view === 'list' ? 'نامه‌های رسمی زیگورات و غیررسمی دیاموند را ذخیره، ویرایش و روی برگه A4 چاپ کنید.' : ($view === 'settings' ? 'تصویر مهر مورد استفاده در نامه‌های رسمی و غیررسمی را تعیین کنید.' : 'اطلاعات و متن را وارد کنید؛ پیش‌نمایش برگه هم‌زمان به‌روزرسانی می‌شود.'); ?></p>
    </header>

    <?php if ($status): ?>
        <?php
        $messages = array(
            'saved' => array('is-success', 'نامه با موفقیت ذخیره شد.'),
            'duplicate' => array('is-error', 'این شماره قبلاً برای همین نوع نامه استفاده شده است.'),
            'required' => array('is-error', 'نام گیرنده، موضوع و متن نامه الزامی است.'),
            'missing' => array('is-error', 'نامه موردنظر پیدا نشد.'),
            'error' => array('is-error', 'ذخیره نامه انجام نشد؛ دوباره تلاش کنید.'),
            'settings-saved' => array('is-success', 'تنظیمات مهر نامه‌ها ذخیره شد.'),
            'settings-error' => array('is-error', isset($_GET['letter-error']) ? sanitize_text_field(wp_unslash($_GET['letter-error'])) : 'ذخیره تنظیمات انجام نشد.'),
        );
        $message = $messages[$status] ?? null;
        ?>
        <?php if ($message): ?><div class="manager-letters__notice <?php echo esc_attr($message[0]); ?>" role="status"><?php echo esc_html($message[1]); ?></div><?php endif; ?>
    <?php endif; ?>

    <?php if ($view === 'settings'): ?>
        <form class="manager-letter-settings" method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('zigurat_save_letter_settings', 'zigurat_letter_settings_nonce'); ?>
            <input type="hidden" name="manager-section" value="letters">
            <input type="hidden" name="zigurat_save_letter_settings" value="1">
            <div class="manager-letter-settings__grid">
                <?php foreach (array('official' => 'نامه رسمی زیگورات', 'unofficial' => 'نامه غیررسمی دیاموند') as $settings_type => $settings_label): ?>
                    <?php
                    $letter_settings = zigurat_letter_get_settings($settings_type);
                    $stamp_layout = zigurat_letter_stamp_layout($settings_type);
                    $settings_stamp_id = absint($letter_settings['stamp_id'] ?? 0);
                    $settings_stamp_url = $settings_stamp_id ? wp_get_attachment_image_url($settings_stamp_id, 'full') : '';
                    $stamp_editor_id = 'letter-stamp-editor-' . $settings_type;
                    $stamp_brand_defaults = zigurat_letter_brand_defaults($settings_type);
                    $stamp_mark_url = get_template_directory_uri() . '/assets/images/' . ($settings_type === 'official' ? 'zigurat-logo.svg' : 'diamond-cyberpunk.png');
                    ?>
                    <fieldset>
                        <legend><?php echo esc_html($settings_label); ?></legend>
                        <?php if ($settings_stamp_url): ?><div class="manager-letter-stamp-preview"><img src="<?php echo esc_url($settings_stamp_url); ?>" alt="مهر تنظیم‌شده"><label><input type="checkbox" name="letter_<?php echo esc_attr($settings_type); ?>_remove_stamp" value="1"> حذف مهر فعلی</label></div><?php else: ?><p class="manager-letter-settings__empty">هنوز مهری تعیین نشده است.</p><?php endif; ?>
                        <label class="manager-letter-file-field">انتخاب تصویر مهر<input type="file" name="letter_<?php echo esc_attr($settings_type); ?>_stamp_file" accept="image/png,image/jpeg,image/webp,image/gif"></label>
                        <small>برای نتیجه بهتر، تصویر PNG یا WebP با پس‌زمینه شفاف انتخاب کنید.</small>
                        <div class="manager-letter-stamp-layout">
                            <div><strong>اندازه و جای مهر در نامه</strong><small>مهر را روی نمونه A4 بکشید و برای تغییر اندازه، دستگیره گوشه آن را جابه‌جا کنید.</small></div>
                            <input type="hidden" name="letter_<?php echo esc_attr($settings_type); ?>_stamp_size_mm" value="<?php echo esc_attr($stamp_layout['size_mm']); ?>">
                            <input type="hidden" name="letter_<?php echo esc_attr($settings_type); ?>_stamp_x_percent" value="<?php echo esc_attr($stamp_layout['x_percent']); ?>">
                            <input type="hidden" name="letter_<?php echo esc_attr($settings_type); ?>_stamp_bottom_mm" value="<?php echo esc_attr($stamp_layout['bottom_mm']); ?>">
                            <button type="button" class="manager-letter-stamp-editor-open" data-letter-stamp-editor-open="<?php echo esc_attr($stamp_editor_id); ?>"><span>تنظیم دیداری مهر</span><small>باز کردن نمونه نامه خالی</small></button>
                        </div>

                        <dialog class="letter-stamp-editor-dialog" id="<?php echo esc_attr($stamp_editor_id); ?>" data-letter-stamp-editor data-letter-type="<?php echo esc_attr($settings_type); ?>">
                            <div class="letter-stamp-editor-dialog__header">
                                <div><strong>تنظیم مهر <?php echo esc_html($settings_label); ?></strong><small>مهر را جابه‌جا کنید یا دستگیره طلایی را برای تغییر اندازه بکشید.</small></div>
                                <span>A4 عمودی</span>
                                <button type="button" data-letter-stamp-editor-close aria-label="بستن">×</button>
                            </div>
                            <div class="letter-stamp-editor-viewport">
                                <article class="letter-page letter-page--<?php echo esc_attr($settings_type); ?> letter-stamp-editor-paper" dir="rtl">
                                    <header class="letterhead">
                                        <div class="letterhead__mark"><img<?php echo $settings_type === 'unofficial' ? ' class="letterhead__diamond-logo"' : ''; ?> src="<?php echo esc_url($stamp_mark_url); ?>" alt="نشان مجموعه"></div>
                                        <div class="letterhead__titles"><strong><?php echo esc_html($stamp_brand_defaults['header_title']); ?></strong><span><?php echo esc_html($stamp_brand_defaults['header_subtitle']); ?></span></div>
                                        <div class="letterhead__meta"><span>شماره: <b>۰۰۱</b></span><span>تاریخ: <b><?php echo esc_html(zigurat_letter_persian_digits(zigurat_letter_today())); ?></b></span><span>پیوست: <b>ندارد</b></span></div>
                                    </header>
                                    <section class="letter-page__content letter-stamp-editor-content">
                                        <div class="letter-page__intro"><div class="letter-page__recipient">به: <strong>نام گیرنده</strong></div><div class="letter-page__subject">موضوع: <strong>نمونه نامه</strong></div><p class="letter-page__greeting">با سلام و احترام</p></div>
                                        <div class="letter-stamp-editor-body" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span></div>
                                        <div class="letter-page__signature" data-letter-stamp-stage>
                                            <span>با احترام</span><strong>نام امضاکننده</strong>
                                            <div class="letter-stamp-editor-object" data-letter-stamp-object tabindex="0" role="application" aria-label="مهر قابل جابه‌جایی" style="--letter-stamp-editor-size:<?php echo esc_attr($stamp_layout['size_mm']); ?>mm;--letter-stamp-editor-x:<?php echo esc_attr($stamp_layout['x_percent']); ?>%;--letter-stamp-editor-bottom:<?php echo esc_attr($stamp_layout['bottom_mm']); ?>mm;">
                                                <?php if ($settings_stamp_url): ?><img src="<?php echo esc_url($settings_stamp_url); ?>" alt="مهر" draggable="false"><?php else: ?><span class="letter-stamp-editor-placeholder">نمونه مهر</span><?php endif; ?>
                                                <button type="button" data-letter-stamp-resize aria-label="تغییر اندازه مهر"></button>
                                            </div>
                                        </div>
                                    </section>
                                    <footer class="letterfoot"><span><?php echo esc_html($stamp_brand_defaults['header_title']); ?></span><p>اطلاعات تماس مجموعه</p><b>صفحه ۱ از ۱</b></footer>
                                </article>
                            </div>
                            <div class="letter-stamp-editor-dialog__footer">
                                <div><span>اندازه: <b data-letter-stamp-size-output><?php echo esc_html(zigurat_letter_persian_digits($stamp_layout['size_mm'])); ?></b> میلی‌متر</span><span>فاصله از پایین: <b data-letter-stamp-bottom-output><?php echo esc_html(zigurat_letter_persian_digits($stamp_layout['bottom_mm'])); ?></b> میلی‌متر</span><span class="letter-stamp-editor-save-status" data-letter-stamp-save-status role="status" hidden></span></div>
                                <div><button type="button" data-letter-stamp-reset>بازنشانی</button><button type="button" data-letter-stamp-editor-accept>تأیید و ذخیره جای مهر</button></div>
                            </div>
                        </dialog>
                    </fieldset>
                <?php endforeach; ?>
            </div>
            <button type="submit">ذخیره تنظیمات مهر</button>
        </form>
    <?php elseif ($view === 'list'): ?>
        <?php
        $page_number = max(1, isset($_GET['letter-page']) ? absint($_GET['letter-page']) : 1);
        $query_args = array(
            'post_type' => 'zig_letter',
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'paged' => $page_number,
            'orderby' => 'modified',
            'order' => 'DESC',
        );
        if ($type_filter) {
            $query_args['meta_query'] = array(array('key' => '_zigurat_letter_type', 'value' => $type_filter));
        }
        $letters = new WP_Query($query_args);
        ?>
        <div class="manager-letter-list__filters no-print">
            <a class="<?php echo $type_filter === '' ? 'is-active' : ''; ?>" href="<?php echo esc_url($base_url); ?>">همه نامه‌ها</a>
            <a class="<?php echo $type_filter === 'official' ? 'is-active' : ''; ?>" href="<?php echo esc_url(zigurat_letters_page_url(array('letter-type' => 'official'))); ?>">رسمی زیگورات</a>
            <a class="<?php echo $type_filter === 'unofficial' ? 'is-active' : ''; ?>" href="<?php echo esc_url(zigurat_letters_page_url(array('letter-type' => 'unofficial'))); ?>">غیررسمی دیاموند</a>
        </div>
        <?php if ($letters->have_posts()): ?>
            <div class="manager-letter-list-wrap">
                <table class="manager-letter-list">
                    <thead><tr><th>شماره</th><th>تاریخ</th><th>نوع</th><th>گیرنده</th><th>موضوع</th><th>عملیات</th></tr></thead>
                    <tbody>
                    <?php while ($letters->have_posts()): $letters->the_post(); $row = zigurat_letter_get(get_the_ID()); ?>
                        <tr>
                            <td dir="ltr"><?php echo esc_html($row->number); ?></td>
                            <td><?php echo esc_html($row->issue_date); ?></td>
                            <td><span class="manager-letter-type manager-letter-type--<?php echo esc_attr($row->type); ?>"><?php echo esc_html($row->type === 'official' ? 'رسمی' : 'غیررسمی'); ?></span></td>
                            <td><?php echo esc_html($row->recipient); ?></td>
                            <td><?php echo esc_html($row->subject); ?></td>
                            <td><div class="manager-letter-list__actions"><a href="<?php echo esc_url(zigurat_letters_page_url(array('letter-view' => 'edit', 'letter-id' => $row->id))); ?>">ویرایش</a><a target="_blank" rel="noopener" href="<?php echo esc_url(zigurat_letters_page_url(array('letter-view' => 'print', 'letter-id' => $row->id))); ?>">مشاهده و چاپ</a></div></td>
                        </tr>
                    <?php endwhile; wp_reset_postdata(); ?>
                    </tbody>
                </table>
            </div>
            <?php if ($letters->max_num_pages > 1): ?>
                <nav class="manager-letter-pagination no-print" aria-label="صفحه‌بندی نامه‌ها">
                    <?php for ($page = 1; $page <= (int) $letters->max_num_pages; $page++): ?>
                        <a class="<?php echo $page === $page_number ? 'is-active' : ''; ?>" href="<?php echo esc_url(zigurat_letters_page_url(array_filter(array('letter-type' => $type_filter, 'letter-page' => $page)))); ?>"><?php echo esc_html(number_format_i18n($page)); ?></a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="manager-letter-empty"><strong>هنوز نامه‌ای ثبت نشده است.</strong><p>برای شروع، یکی از گزینه‌های نامه رسمی یا غیررسمی را انتخاب کنید.</p></div>
        <?php endif; ?>
    <?php else: ?>
        <?php
        $type = $editing_letter ? $editing_letter->type : ($type_filter === 'unofficial' ? 'unofficial' : 'official');
        $manager = wp_get_current_user();
        $brand_defaults = zigurat_letter_brand_defaults($type);
        $letter_settings = zigurat_letter_get_settings($type);
        $defaults = (object) array(
            'id' => 0,
            'type' => $type,
            'number' => '',
            'issue_date' => zigurat_letter_today(),
            'recipient' => '',
            'subject' => '',
            'attachment' => 'ندارد',
            'greeting' => 'با سلام و احترام',
            'body' => '',
            'signer_name' => 'با احترام',
            'signer_title' => trim((string) $manager->display_name) ?: (string) $manager->user_login,
            'stamp_id' => absint($letter_settings['stamp_id'] ?? 0),
            'include_stamp' => false,
            'header_title' => $brand_defaults['header_title'],
            'header_subtitle' => $brand_defaults['header_subtitle'],
            'font_size' => $brand_defaults['font_size'],
        );
        $form_letter = $editing_letter ?: $defaults;
        ?>
        <div class="manager-letter-editor-layout" data-letter-editor data-manager-name="<?php echo esc_attr(trim((string) $manager->display_name) ?: (string) $manager->user_login); ?>">
            <form class="manager-letter-form manager-letter-form--<?php echo esc_attr($type); ?>" method="post">
                <?php wp_nonce_field('zigurat_save_letter', 'zigurat_letter_nonce'); ?>
                <input type="hidden" name="manager-section" value="letters">
                <input type="hidden" name="zigurat_save_letter" value="1">
                <input type="hidden" name="letter_id" value="<?php echo (int) $form_letter->id; ?>">
                <input type="hidden" name="letter_type" value="<?php echo esc_attr($type); ?>">
                <div class="manager-letter-form__type manager-letter-form__type--<?php echo esc_attr($type); ?>"><span><?php echo $type === 'official' ? 'سربرگ زیگورات' : 'سربرگ دیاموند'; ?></span><strong><?php echo esc_html(zigurat_letter_type_label($type)); ?></strong></div>
                <fieldset class="manager-letter-letterhead-fields">
                    <legend>تنظیم نوشته‌های سربرگ</legend>
                    <div class="manager-letter-fields">
                        <label>عنوان سربرگ<input name="letter_header_title" value="<?php echo esc_attr($form_letter->header_title); ?>" data-letter-source="header_title"></label>
                        <label>زیرعنوان سربرگ<input name="letter_header_subtitle" value="<?php echo esc_attr($form_letter->header_subtitle); ?>" data-letter-source="header_subtitle"></label>
                        <label>اندازه فونت متن (پوینت)<input type="number" name="letter_font_size" value="<?php echo esc_attr($form_letter->font_size); ?>" min="9" max="22" step="0.5" inputmode="decimal" data-letter-font-size></label>
                    </div>
                </fieldset>
                <div class="manager-letter-fields">
                    <label>شماره نامه<input name="letter_number" value="<?php echo esc_attr($form_letter->number); ?>" placeholder="در صورت خالی‌بودن خودکار ساخته می‌شود" data-letter-source="number"></label>
                    <label>تاریخ شمسی<input name="letter_date" value="<?php echo esc_attr($form_letter->issue_date); ?>" inputmode="numeric" required data-letter-source="date"></label>
                    <label>پیوست<input name="letter_attachment" value="<?php echo esc_attr($form_letter->attachment); ?>" data-letter-source="attachment"></label>
                    <label class="is-full">گیرنده نامه<input name="letter_recipient" value="<?php echo esc_attr($form_letter->recipient); ?>" placeholder="مثلاً مدیریت محترم شرکت..." required data-letter-source="recipient"></label>
                    <label class="is-full">موضوع<input name="letter_subject" value="<?php echo esc_attr($form_letter->subject); ?>" required data-letter-source="subject"></label>
                    <label class="is-full">عبارت آغازین<input name="letter_greeting" value="<?php echo esc_attr($form_letter->greeting); ?>" data-letter-source="greeting"></label>
                </div>
                <label class="manager-letter-body-label">متن نامه</label>
                <?php
                wp_editor($form_letter->body, 'zigurat_letter_body_editor', array(
                    'textarea_name' => 'letter_body',
                    'textarea_rows' => 16,
                    'media_buttons' => false,
                    'quicktags' => true,
                    'teeny' => false,
                    'tinymce' => array(
                        'toolbar1' => 'undo redo | formatselect | bold italic underline | alignright aligncenter alignleft alignjustify | bullist numlist | removeformat',
                        'toolbar2' => '',
                        'directionality' => 'rtl',
                    ),
                ));
                ?>
                <div class="manager-letter-fields manager-letter-fields--signature">
                    <label>عبارت پایانی<input name="letter_signer_name" value="<?php echo esc_attr($form_letter->signer_name); ?>" data-letter-source="signer_name"></label>
                    <label>نام امضاکننده<input name="letter_signer_title" value="<?php echo esc_attr($form_letter->signer_title); ?>" data-letter-source="signer_title"></label>
                </div>
                <?php if (!empty($form_letter->stamp_id)): ?>
                    <div class="manager-letter-stamp-toggle">
                        <label class="manager-letter-switch"><input type="checkbox" name="include_stamp" value="1" data-letter-stamp-toggle aria-label="مهردار بودن نامه" <?php checked(!empty($form_letter->include_stamp)); ?>><span aria-hidden="true"></span></label>
                        <div><strong>نامه مهردار باشد</strong><small>فقط با کلیک روی کلید، درج مهر در نسخه چاپی روشن یا خاموش می‌شود.</small></div>
                    </div>
                <?php else: ?>
                    <p class="manager-letter-no-stamp">برای مهردار کردن نامه، ابتدا از «تنظیمات نامه‌ها» تصویر مهر را ثبت کنید.</p>
                <?php endif; ?>
                <div class="manager-letter-form__actions">
                    <button type="submit">ذخیره نامه</button>
                    <?php if ($editing_letter): ?><a target="_blank" rel="noopener" href="<?php echo esc_url(zigurat_letters_page_url(array('letter-view' => 'print', 'letter-id' => $editing_letter->id))); ?>">مشاهده و چاپ</a><?php endif; ?>
                </div>
            </form>
            <aside class="manager-letter-preview">
                <div class="manager-letter-preview__heading"><div><strong>پیش‌نمایش A4</strong><small>نمای چاپی نامه</small></div><span>A4 عمودی</span></div>
                <div class="manager-letter-preview__scroll">
                    <?php get_template_part('template-parts/letter-document', null, array('letter' => $form_letter, 'preview' => true)); ?>
                </div>
                <div class="manager-letter-overflow" data-letter-overflow hidden>متن از فضای قابل چاپ دو صفحه بیشتر شده است؛ متن را کوتاه‌تر یا اندازه فونت را کمتر کنید.</div>
            </aside>
        </div>
    <?php endif; ?>
</section>
