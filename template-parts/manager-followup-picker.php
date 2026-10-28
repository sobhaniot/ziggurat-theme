<?php if (!defined('ABSPATH')) exit; ?>
    <dialog class="manager-crm-date-picker" data-crm-date-picker aria-label="انتخاب تاریخ و ساعت پیگیری">
        <div class="manager-crm-date-picker__panel">
            <header><button type="button" data-crm-date-prev aria-label="ماه قبل">‹</button><strong data-crm-date-title></strong><button type="button" data-crm-date-next aria-label="ماه بعد">›</button></header>
            <div class="manager-crm-date-picker__week"><span>ش</span><span>ی</span><span>د</span><span>س</span><span>چ</span><span>پ</span><span>ج</span></div>
            <div class="manager-crm-date-picker__days" data-crm-date-days></div>
            <div class="manager-crm-date-picker__time">
                <span>زمان پیگیری</span>
                <label>دقیقه<span class="manager-crm-time-wheel" data-crm-date-minute data-crm-time-wheel="minute"><button type="button" class="manager-crm-time-wheel__trigger" data-crm-time-wheel-toggle aria-label="انتخاب دقیقه" aria-haspopup="listbox" aria-expanded="false"><span data-crm-time-wheel-value>۰۰</span></button><span class="manager-crm-time-wheel__list" data-crm-time-wheel-list role="listbox" aria-label="دقیقه" hidden></span></span></label>
                <b>:</b>
                <label>ساعت<span class="manager-crm-time-wheel" data-crm-date-hour data-crm-time-wheel="hour"><button type="button" class="manager-crm-time-wheel__trigger" data-crm-time-wheel-toggle aria-label="انتخاب ساعت" aria-haspopup="listbox" aria-expanded="false"><span data-crm-time-wheel-value>۰۹</span></button><span class="manager-crm-time-wheel__list" data-crm-time-wheel-list role="listbox" aria-label="ساعت" hidden></span></span></label>
            </div>
            <footer><button type="button" data-crm-date-today>امروز</button><button type="button" data-crm-date-clear>پاک‌کردن</button><span></span><button type="button" data-crm-date-cancel>انصراف</button><button type="button" class="is-primary" data-crm-date-apply>تأیید زمان</button></footer>
        </div>
    </dialog>
