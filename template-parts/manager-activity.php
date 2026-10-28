<?php
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options')) {
    echo '<p role="alert">فقط مدیرکل اجازهٔ مشاهدهٔ گزارش فعالیت‌ها را دارد.</p>';
    return;
}
nocache_headers();
if (get_option('zigurat_activity_schema') !== '1') {
    echo '<p role="alert">جدول گزارش فعالیت‌ها هنوز آماده نشده است؛ دسترسی ساخت جدول در پایگاه داده را بررسی کنید.</p>';
    return;
}
$page = max(1, absint($_GET['activity_page'] ?? 1));
$log = zigurat_activity_list($page);
$page = $log['page'];
?>
<section class="manager-activity" aria-labelledby="activity-title">
    <header class="manager-activity__heading"><h2 id="activity-title">گزارش فعالیت‌ها</h2></header>
    <form class="manager-activity__export-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="get" data-activity-export>
        <input type="hidden" name="action" value="zigurat_activity_export">
        <?php wp_nonce_field('zigurat_activity_export', '_wpnonce', false); ?>
        <label>از تاریخ (شمسی)<span class="manager-crm-datetime"><input name="date_from" data-crm-datetime data-date-only readonly inputmode="none" dir="ltr" placeholder="بدون محدودیت" aria-haspopup="dialog"><button type="button" data-crm-datetime-open aria-label="انتخاب تاریخ شروع"><?php get_template_part('template-parts/followup-calendar-icon'); ?></button></span></label>
        <label>تا تاریخ (شمسی)<span class="manager-crm-datetime"><input name="date_to" data-crm-datetime data-date-only readonly inputmode="none" dir="ltr" placeholder="بدون محدودیت" aria-haspopup="dialog"><button type="button" data-crm-datetime-open aria-label="انتخاب تاریخ پایان"><?php get_template_part('template-parts/followup-calendar-icon'); ?></button></span></label>
        <button type="submit" class="manager-activity__export">دریافت خروجی CSV (اکسل)</button>
        <button type="reset" class="manager-activity__clear">پاک‌کردن بازه</button>
    </form>
    <p class="manager-activity__range-error" role="alert" data-activity-range-error hidden></p>
    <p class="manager-activity__page-info">با خالی‌گذاشتن بازه، همهٔ سوابق خروجی گرفته می‌شوند. روز پایان به‌طور کامل و بر اساس ساعت تهران محاسبه می‌شود.</p>
    <?php get_template_part('template-parts/manager-followup-picker'); ?>
    <p>جدیدترین رویدادها در ابتدا نمایش داده می‌شوند. ثبت سوابق از زمان فعال‌شدن این بخش آغاز می‌شود.</p>
    <?php if (!$log['items']): ?><p>هنوز رویدادی ثبت نشده است.</p><?php endif; ?>
    <ol class="manager-activity__list">
    <?php foreach ($log['items'] as $entry): ?>
        <li><time><?php echo esc_html(zigurat_inventory_format_jalali_datetime($entry->created_at)); ?></time> — <strong><?php echo esc_html($entry->actor); ?></strong> — <?php echo esc_html($entry->message); ?></li>
    <?php endforeach; ?>
    </ol>
    <nav aria-label="صفحه‌های گزارش فعالیت">
    <?php echo paginate_links(array('base'=>str_replace('999999999', '%#%', esc_url(add_query_arg(array('manager-section'=>'activity','activity_page'=>999999999), zigurat_manager_login_url()))), 'current'=>$page, 'total'=>$log['pages'], 'prev_text'=>'جدیدتر', 'next_text'=>'قدیمی‌تر')); ?>
    </nav>
    <p class="manager-activity__page-info">صفحهٔ <?php echo esc_html(number_format_i18n($page)); ?> از <?php echo esc_html(number_format_i18n($log['pages'])); ?> — <?php echo esc_html(number_format_i18n($log['total'])); ?> رویداد؛ حداکثر ۵۰ رویداد در هر صفحه</p>
</section>
