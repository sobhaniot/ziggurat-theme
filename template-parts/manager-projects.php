<?php
if (!defined('ABSPATH') || !zigurat_is_manager()) {
    exit;
}

$stages = zigurat_workflow_project_stages();
$records = zigurat_workflow_project_records();
// The archive is loaded on demand after the board is visible. Loading every
// trashed project (and its complete history) here made the initial page slow.
$archived_records = array();
$active_collaborators = zigurat_workflow_active_collaborators($records);
$grouped = array_fill_keys(array_keys($stages), array());
$today = function_exists('zigurat_invoice_today_jalali') ? zigurat_invoice_today_jalali() : '';
$due_count = 0;
$ready_invoice_count = 0;
foreach ($records as $record) {
    $grouped[$record['stage']][] = $record;
    if ($record['stage'] !== 'delivery' && $record['followup'] !== '' && $today !== '' && strcmp($record['followup'], $today) <= 0) {
        $due_count++;
    }
    if ($record['stage'] === 'delivery' && !$record['invoice_id']) {
        $ready_invoice_count++;
    }
}
$proformas = zigurat_workflow_project_recent_documents('proforma');
$invoices = zigurat_workflow_project_recent_documents('invoice');
$partners = zigurat_workflow_project_partners();
$stage_classes = array(
    'lead' => 'is-lead',
    'proforma' => 'is-proforma',
    'approval' => 'is-approval',
    'production' => 'is-production',
    'delivery' => 'is-delivery',
    'payment' => 'is-payment',
);
?>
<section class="manager-projects" data-project-board>
    <header class="manager-projects__heading">
        <div>
            <span>مدیریت یکپارچه فروش و اجرا</span>
            <h2>مدیریت پروژه‌ها</h2>
            <p>مسیر هر مشتری را از اولین پیگیری تا تحویل و صدور فاکتور نهایی مدیریت کنید.</p>
        </div>
        <div class="manager-projects__heading-actions"><button type="button" class="manager-projects__archive-jump" data-project-archive-jump>دیدن آرشیو</button><button type="button" class="manager-projects__new" data-project-new><span aria-hidden="true">＋</span> پروژه جدید</button></div>
    </header>

    <div class="manager-projects__notice" data-project-notice role="status" aria-live="polite" hidden></div>

    <div class="manager-projects__summary">
        <div><span>پروژه‌های فعال</span><strong><?php echo esc_html(number_format_i18n(count($records))); ?></strong><small>پروژه</small></div>
        <div><span>نیازمند پیگیری</span><strong class="<?php echo $due_count ? 'is-alert' : ''; ?>"><?php echo esc_html(number_format_i18n($due_count)); ?></strong><small>مورد</small></div>
        <div><span>در مرحله پیش‌فاکتور</span><strong data-project-proforma-count><?php echo esc_html(number_format_i18n(count($grouped['proforma']))); ?></strong><small>پروژه</small></div>
        <div><span>آماده صدور فاکتور</span><strong><?php echo esc_html(number_format_i18n($ready_invoice_count)); ?></strong><small>پروژه</small></div>
    </div>

    <div class="manager-project-board" data-project-columns aria-label="مراحل پروژه‌ها">
        <?php foreach ($stages as $stage_key => $stage_label): ?>
            <section class="manager-project-column <?php echo esc_attr($stage_classes[$stage_key]); ?>" data-project-stage="<?php echo esc_attr($stage_key); ?>">
                <header>
                    <h3><?php echo esc_html($stage_label); ?></h3>
                    <span data-project-stage-count><?php echo esc_html(number_format_i18n(count($grouped[$stage_key]))); ?></span>
                </header>
                <div class="manager-project-column__cards" data-project-card-list>
                    <?php foreach ($grouped[$stage_key] as $record): ?>
                        <?php
                        $is_due = $record['stage'] !== 'delivery'
                            && $record['followup'] !== ''
                            && $today !== ''
                            && strcmp($record['followup'], $today) <= 0;
                        ?>
                        <article class="manager-project-card" draggable="true" data-project-card data-project-id="<?php echo (int) $record['id']; ?>" data-project-current-stage="<?php echo esc_attr($record['stage']); ?>" data-project-proforma-id="<?php echo (int) $record['proforma_id']; ?>" data-project-invoice-id="<?php echo (int) $record['invoice_id']; ?>" data-project-conversion-url="<?php echo esc_url($record['conversion_url']); ?>">
                            <div class="manager-project-card__top">
                                <span class="manager-project-card__grip" aria-label="دستگیره جابه‌جایی" title="برای جابه‌جایی بکشید">⠿</span>
                                <button type="button" class="manager-project-card__edit" data-project-edit aria-label="ویرایش <?php echo esc_attr($record['title']); ?>">
                                    <strong><?php echo esc_html($record['title']); ?></strong>
                                </button>
                            </div>
                            <?php if ($record['client']): ?>
                                <div class="manager-project-card__client"><b><?php echo esc_html($record['client']); ?></b></div>
                            <?php endif; ?>
                            <div class="manager-project-card__meta">
                                <?php if ($record['followup']): ?><span class="<?php echo $is_due ? 'is-due' : ''; ?>">پیگیری: <bdi dir="ltr"><?php echo esc_html($record['followup']); ?></bdi></span><?php endif; ?>
                            </div>
                            <?php if ($record['work_items']): ?>
                                <div class="manager-project-card__work">
                                    <?php foreach (array_slice($record['work_items'], 0, 3) as $work_item): ?>
                                        <span><b><?php echo esc_html($work_item['title']); ?>:</b> <?php echo esc_html($work_item['partner_names'] ? implode('، ', $work_item['partner_names']) : 'همکار تعیین نشده'); ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($record['work_items']) > 3): ?><small>+ <?php echo esc_html(number_format_i18n(count($record['work_items']) - 3)); ?> بخش دیگر</small><?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($record['proforma_url'] || $record['invoice_url']): ?>
                                <div class="manager-project-card__documents">
                                    <?php if ($record['proforma_url']): ?><a href="<?php echo esc_url($record['proforma_url']); ?>">مشاهده پیش‌فاکتور</a><?php endif; ?>
                                    <?php if ($record['invoice_url']): ?><a href="<?php echo esc_url($record['invoice_url']); ?>" data-project-invoice-link>مشاهده فاکتور</a><?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                    <p class="manager-project-column__empty" data-project-empty <?php echo $grouped[$stage_key] ? 'hidden' : ''; ?>>پروژه‌ای در این مرحله نیست.</p>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

    <p class="manager-projects__guide"><span aria-hidden="true">↔</span> کارت را بکشید و در ستون جدید رها کنید؛ انتقال به «تحویل و فاکتور» فاکتور را خودکار می‌سازد و پس از تسویه، پروژه بایگانی می‌شود. در موبایل کارت را کمی نگه دارید و سپس بکشید.</p>

    <section id="manager-project-archive" class="manager-project-archive" aria-labelledby="manager-project-archive-title" data-project-archive hidden>
        <header class="manager-project-archive__heading">
            <div><span>سوابق تکمیل‌شده</span><h3 id="manager-project-archive-title">آرشیو زمانی پروژه‌ها</h3></div>
            <label>نمایش بازه
                <select data-project-archive-range>
                    <option value="10d" selected>۱۰ روز اخیر</option>
                    <option value="30d">۳۰ روز اخیر</option>
                    <option value="3m">۳ ماه اخیر</option>
                    <option value="6m">۶ ماه اخیر</option>
                    <option value="year">امسال</option>
                    <option value="all">همه سوابق</option>
                </select>
            </label>
            <strong data-project-archive-count>۰ پروژه بایگانی‌شده</strong>
        </header>
        <div data-project-archive-content>
            <p class="manager-project-archive__loading">برای نمایش آرشیو، کمی صبر کنید…</p>
        </div>
    </section>

    <section class="manager-active-collaborators" aria-labelledby="manager-active-collaborators-title">
        <header>
            <div>
                <span>نمایش براساس نیروی اجرایی</span>
                <h3 id="manager-active-collaborators-title">همکاران فعال</h3>
                <p>پروژه حتی بعد از تکمیل زیر نام همکار باقی می‌ماند و فقط پس از دریافت کارکرد، دستی از این فهرست خارج می‌شود.</p>
            </div>
            <strong><span data-active-collaborator-count><?php echo esc_html(number_format_i18n(count($active_collaborators))); ?></span> همکار فعال</strong>
        </header>
        <div class="manager-active-collaborators__columns" data-active-collaborator-columns>
            <?php foreach ($active_collaborators as $collaborator): ?>
                <article class="manager-active-collaborator" data-active-collaborator data-assignment-key="<?php echo esc_attr($collaborator['key']); ?>">
                    <header>
                        <div><h4><?php echo esc_html($collaborator['name']); ?></h4></div>
                        <b data-active-project-count><?php echo esc_html(number_format_i18n(count($collaborator['projects']))); ?></b>
                    </header>
                    <div data-active-project-list>
                        <?php foreach ($collaborator['projects'] as $active_project): ?>
                            <div class="manager-active-project" data-active-project data-project-id="<?php echo (int) $active_project['id']; ?>">
                                <strong><?php echo esc_html($active_project['title']); ?></strong>
                                <?php if ($active_project['client']): ?><span>مشتری: <?php echo esc_html($active_project['client']); ?></span><?php endif; ?>
                                <span>نقش: <?php echo esc_html(implode('، ', $active_project['roles'])); ?></span>
                                <span class="manager-active-project__stage"><?php echo esc_html($active_project['stage_label']); ?></span>
                                <button type="button" data-close-active-assignment>کارکرد گرفته شد؛ حذف از فعال‌ها</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
            <p class="manager-active-collaborators__empty" data-active-collaborator-empty <?php echo $active_collaborators ? 'hidden' : ''; ?>>هنوز همکاری به بخش‌های اجرایی پروژه‌ها اختصاص داده نشده است.</p>
        </div>
    </section>

    <dialog class="manager-project-dialog" data-project-dialog aria-labelledby="manager-project-dialog-title">
        <form class="manager-project-form" data-project-form autocomplete="off">
            <header>
                <div><span data-project-dialog-eyebrow>پروژه جدید</span><h3 id="manager-project-dialog-title" data-project-dialog-title>ثبت پروژه</h3></div>
                <button type="button" data-project-close aria-label="بستن پنجره">×</button>
            </header>
            <input type="hidden" name="project_id" value="">
            <div class="manager-project-form__grid">
                <label class="is-wide">نام پروژه *<input type="text" name="title" required maxlength="180" placeholder="مثلاً تابلو چلنیوم شعبه ولیعصر"></label>
                <label>نام مشتری<input type="text" name="client" maxlength="180"></label>
                <label>شماره تماس<input type="tel" name="phone" maxlength="50" dir="ltr"></label>
                <label>مرحله پروژه<select name="stage"><?php foreach ($stages as $key => $label): ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                <label>تاریخ پیگیری بعدی<input type="text" name="followup" inputmode="numeric" dir="ltr" placeholder="1405/07/20" pattern="[0-9۰-۹]{4}/[0-9۰-۹]{1,2}/[0-9۰-۹]{1,2}"></label>
                <label class="is-wide">اتصال به پیش‌فاکتور<select name="proforma_id"><option value="0">بدون پیش‌فاکتور</option><?php foreach ($proformas as $document): ?><option value="<?php echo (int) $document->id; ?>"><?php echo esc_html(zigurat_workflow_project_document_label($document)); ?></option><?php endforeach; ?></select></label>
                <label class="is-wide">اتصال به فاکتور نهایی<select name="invoice_id"><option value="0">بدون فاکتور نهایی</option><?php foreach ($invoices as $document): ?><option value="<?php echo (int) $document->id; ?>"><?php echo esc_html(zigurat_workflow_project_document_label($document)); ?></option><?php endforeach; ?></select></label>
                <label class="is-wide">یادداشت‌ها<textarea name="notes" rows="4" maxlength="3000" placeholder="نتیجه تماس‌ها، توافق‌ها و نکات اجرایی پروژه"></textarea></label>
            </div>
            <section class="manager-project-work-editor">
                <header>
                    <div><h4>بخش‌های اجرایی پروژه</h4><p>مثلاً بنایی، نقاشی، برق یا نصب را اضافه و برای هر بخش یک یا چند همکار انتخاب کنید.</p></div>
                    <button type="button" data-project-work-add><span aria-hidden="true">＋</span> افزودن بخش</button>
                </header>
                <div class="manager-project-work-editor__list" data-project-work-list></div>
                <p class="manager-project-work-editor__empty" data-project-work-empty>هنوز بخش اجرایی اضافه نشده است.</p>
            </section>
            <div class="manager-project-form__result" data-project-form-result role="status" aria-live="polite" hidden></div>
            <section class="manager-project-history" data-project-history hidden>
                <h4>سوابق تغییر مرحله</h4>
                <ol data-project-history-list></ol>
            </section>
            <footer>
                <button type="button" class="manager-project-form__archive" data-project-archive hidden>لغو و بایگانی پروژه</button>
                <span></span>
                <button type="button" class="manager-project-form__cancel" data-project-close>انصراف</button>
                <button type="submit" class="manager-project-form__save">ذخیره پروژه</button>
            </footer>
        </form>
        <template data-project-work-template>
            <article class="manager-project-work-row" data-project-work-row>
                <input type="hidden" data-work-field="id">
                <label>عنوان بخش<input type="text" data-work-field="title" maxlength="100" placeholder="مثلاً بنایی یا نقاشی"></label>
                <div class="manager-project-partner-picker">
                    <span>همکاران این بخش</span>
                    <details data-partner-picker>
                        <summary data-partner-summary>انتخاب همکاران</summary>
                        <div>
                            <?php if ($partners): ?>
                                <?php foreach ($partners as $partner): ?>
                                    <label>
                                        <input type="checkbox" value="<?php echo (int) $partner['id']; ?>" data-partner-checkbox>
                                        <span><b><?php echo esc_html($partner['name']); ?></b><small><?php echo esc_html(implode(' — ', array_filter(array($partner['profession'], $partner['location'])))); ?></small></span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <label class="manager-project-partner-picker__other">
                                <input type="checkbox" value="other" data-partner-other-toggle>
                                <span><b>سایر</b><small>همکاری که هنوز در پنل همکاران ثبت نشده است</small></span>
                            </label>
                            <input type="text" maxlength="120" placeholder="نام همکار را وارد کنید" data-partner-other-input hidden>
                        </div>
                    </details>
                </div>
                <button type="button" class="manager-project-work-row__remove" data-project-work-remove aria-label="حذف این بخش">حذف</button>
            </article>
        </template>
    </dialog>
</section>
