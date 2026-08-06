<div class="manager-actions">
    <a class="manager-action" href="<?php echo esc_url(home_url('/inventory-list/')); ?>">
        <span class="manager-action__icon" aria-hidden="true">📦</span>
        <strong>انبارداری</strong>
        <small>موجودی، ورود و خروج کالا</small>
    </a>
    <a class="manager-action" href="<?php echo esc_url(home_url('/inventory-transactions/')); ?>">
        <span class="manager-action__icon" aria-hidden="true">📊</span>
        <strong>گزارش حساب</strong>
        <small>گزارش و چاپ گردش ثبت‌شده</small>
    </a>
    <a class="manager-action" href="<?php echo esc_url(add_query_arg('manager-section', 'applications', home_url('/login/'))); ?>">
        <span class="manager-action__icon" aria-hidden="true">🤝</span>
        <strong>درخواست‌های همکاری</strong>
        <small>مشاهده همکاران و تأمین‌کنندگان ثبت‌نام‌شده</small>
    </a>
</div>
