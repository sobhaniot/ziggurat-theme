<?php
if (!defined('ABSPATH') || !zigurat_is_manager()) {
    exit;
}
$records = zigurat_crm_records();
$summary = zigurat_crm_summary($records);
$statuses = zigurat_crm_statuses();
$priorities = zigurat_crm_priorities();
$managers = zigurat_crm_managers();
?>
<section class="manager-crm" data-crm-root>
    <header class="manager-crm__heading">
        <div>
            <span>ارتباط با مشتری</span>
            <h2>پیگیری مشتریان</h2>
            <p>تماس‌ها، نتیجه مذاکره و زمان پیگیری بعدی را یکجا مدیریت کنید.</p>
        </div>
        <div class="manager-crm__heading-actions">
            <button type="button" class="manager-crm__push" data-crm-push><span aria-hidden="true">🔔</span><b>فعال‌کردن اعلان گوشی</b></button>
            <button type="button" class="manager-crm__new" data-crm-new><span aria-hidden="true">＋</span> مشتری جدید</button>
        </div>
    </header>

    <div class="manager-crm__notice" data-crm-notice role="status" aria-live="polite" hidden></div>

    <div class="manager-crm__summary" data-crm-summary>
        <button type="button" data-crm-quick-filter="overdue"><span>عقب‌افتاده</span><strong data-crm-count="overdue"><?php echo esc_html(number_format_i18n($summary['overdue'])); ?></strong></button>
        <button type="button" data-crm-quick-filter="today"><span>پیگیری امروز</span><strong data-crm-count="today"><?php echo esc_html(number_format_i18n($summary['today'])); ?></strong></button>
        <button type="button" data-crm-quick-filter="upcoming"><span>آینده</span><strong data-crm-count="upcoming"><?php echo esc_html(number_format_i18n($summary['upcoming'])); ?></strong></button>
        <button type="button" data-crm-quick-filter="active"><span>کل فعال</span><strong data-crm-count="active"><?php echo esc_html(number_format_i18n($summary['active'])); ?></strong></button>
    </div>

    <div class="manager-crm__toolbar">
        <label><span>جستجو</span><input type="search" data-crm-search placeholder="نام، شرکت، شماره یا موضوع"></label>
        <label><span>وضعیت</span><select data-crm-status-filter><option value="">همه وضعیت‌ها</option><?php foreach ($statuses as $key => $label): ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
        <label><span>نمایش</span><select data-crm-due-filter><option value="active">پیگیری‌های فعال</option><option value="overdue">عقب‌افتاده</option><option value="today">امروز</option><option value="upcoming">آینده</option><option value="all">همه مشتریان</option></select></label>
    </div>

    <div class="manager-crm__list" data-crm-list></div>
    <p class="manager-crm__empty" data-crm-empty hidden>موردی با این فیلتر پیدا نشد.</p>

    <dialog class="manager-crm-dialog" data-crm-editor>
        <form class="manager-crm-form" data-crm-form>
            <header><div><span>پرونده مشتری</span><h3 data-crm-form-title>مشتری جدید</h3></div><button type="button" data-crm-close aria-label="بستن"></button></header>
            <input type="hidden" name="customer_id" value="">
            <div class="manager-crm-form__grid">
                <label><span>عنوان</span><select name="prefix"><option value="">بدون عنوان</option><option value="شرکت">شرکت</option><option value="جناب آقای">جناب آقای</option><option value="سرکار خانم">سرکار خانم</option></select></label>
                <label><span>نام مخاطب</span><input type="text" name="name" autocomplete="name"></label>
                <label><span>نام شرکت</span><input type="text" name="company" autocomplete="organization"></label>
                <label><span>شماره تماس</span><input type="tel" name="phone" autocomplete="tel" required></label>
                <label class="is-wide"><span>موضوع مذاکره</span><input type="text" name="topic" placeholder="مثلاً تابلو شعبه جدید"></label>
                <label><span>وضعیت</span><select name="status"><?php foreach ($statuses as $key => $label): ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                <label><span>اولویت</span><select name="priority"><?php foreach ($priorities as $key => $label): ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                <label><span>مسئول پیگیری</span><select name="owner_id"><option value="0">همه مدیران</option><?php foreach ($managers as $manager): ?><option value="<?php echo (int) $manager['id']; ?>"><?php echo esc_html($manager['name']); ?></option><?php endforeach; ?></select></label>
                <label><span>زمان پیگیری بعدی</span><input type="datetime-local" name="next_at"></label>
                <label class="is-wide"><span>توضیحات ثابت</span><textarea name="notes" rows="3" placeholder="اطلاعاتی که باید همیشه در پرونده مشتری بماند"></textarea></label>
            </div>
            <section class="manager-crm-history" data-crm-history-wrap hidden><h4>تاریخچه ارتباط</h4><ol data-crm-history></ol></section>
            <footer><button type="submit" class="manager-crm-form__save">ذخیره مشتری</button><button type="button" data-crm-close>انصراف</button><button type="button" class="manager-crm-form__delete" data-crm-delete hidden>حذف</button></footer>
        </form>
    </dialog>

    <dialog class="manager-crm-dialog manager-crm-dialog--log" data-crm-log-dialog>
        <form class="manager-crm-form" data-crm-log-form>
            <header><div><span>ثبت تماس</span><h3 data-crm-log-title>نتیجه پیگیری</h3></div><button type="button" data-crm-log-close aria-label="بستن"></button></header>
            <input type="hidden" name="customer_id" value="">
            <div class="manager-crm-form__grid">
                <label class="is-wide"><span>نتیجه تماس یا مذاکره</span><textarea name="note" rows="5" required placeholder="چه صحبتی شد و اقدام بعدی چیست؟"></textarea></label>
                <label><span>وضعیت جدید</span><select name="status"><?php foreach ($statuses as $key => $label): ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                <label><span>پیگیری بعدی</span><input type="datetime-local" name="next_at"></label>
            </div>
            <footer><button type="submit" class="manager-crm-form__save">ثبت نتیجه تماس</button><button type="button" data-crm-log-close>انصراف</button></footer>
        </form>
    </dialog>

    <dialog class="manager-crm-confirm" data-crm-confirm>
        <form method="dialog"><div aria-hidden="true">!</div><h3>حذف مشتری؟</h3><p>تاریخچه تماس‌ها نیز برای همیشه حذف می‌شود.</p><footer><button value="cancel">انصراف</button><button value="confirm">حذف دائمی</button></footer></form>
    </dialog>
</section>
