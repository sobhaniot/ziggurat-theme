<?php
if (!defined('ABSPATH') || !zigurat_is_manager()) {
    return;
}
$calculator = isset($_GET['calculator']) && is_string($_GET['calculator'])
    ? sanitize_key(wp_unslash($_GET['calculator']))
    : '';
$pricing_url = add_query_arg('manager-section', 'pricing', zigurat_manager_login_url());
?>
<section class="manager-pricing" aria-labelledby="manager-pricing-title">
    <div class="manager-pricing__toolbar no-print">
        <a href="<?php echo esc_url($calculator ? $pricing_url : zigurat_manager_login_url()); ?>"><?php echo $calculator ? 'بازگشت به محصولات' : 'بازگشت به پنل مدیران'; ?></a>
    </div>

    <?php if ($calculator !== 'lightbox'): ?>
        <header class="manager-pricing__heading">
            <span>ابزارهای برآورد قیمت</span>
            <h2 id="manager-pricing-title">محاسبه قیمت</h2>
            <p>محصول موردنظر را انتخاب کنید. محاسبه‌گرهای دیگر بعداً به این بخش اضافه می‌شوند.</p>
        </header>
        <div class="manager-pricing-products">
            <a href="<?php echo esc_url(add_query_arg(array('manager-section'=>'pricing','calculator'=>'lightbox'), zigurat_manager_login_url())); ?>">
                <span aria-hidden="true">▣</span>
                <strong>محاسبه قیمت لایت‌باکس</strong>
                <small>محاسبه براساس متر محیط یا مترمربع، هزینه‌های جانبی و سود</small>
            </a>
        </div>
    <?php else:
        $settings = zigurat_get_lightbox_pricing_settings();
        $last_costs = zigurat_get_lightbox_last_costs();
        $pricing_status = isset($_GET['pricing-status']) ? sanitize_key(wp_unslash($_GET['pricing-status'])) : '';
    ?>
        <header class="manager-pricing__heading">
            <span>برآورد محصول</span>
            <h2 id="manager-pricing-title">محاسبه قیمت لایت‌باکس</h2>
            <p>اگر یکی از اضلاع کمتر از ۱٫۵ متر باشد، متر محیط مبناست؛ در غیر این صورت قیمت براساس مترمربع محاسبه می‌شود.</p>
        </header>

        <?php if ($pricing_status === 'saved'): ?><div class="manager-pricing-notice is-success" role="status">نرخ‌های پایه با موفقیت ذخیره شدند.</div><?php elseif ($pricing_status): ?><div class="manager-pricing-notice is-error" role="alert">ذخیره نرخ‌ها انجام نشد؛ دوباره تلاش کنید.</div><?php endif; ?>

        <div class="manager-pricing-layout">
            <aside class="manager-pricing-rates">
                <h3>تنظیم نرخ‌های پایه</h3>
                <p>نرخ‌ها به ریال ذخیره می‌شوند و در محاسبات بعدی باقی می‌مانند.</p>
                <form method="post">
                    <?php wp_nonce_field('zigurat_save_lightbox_rates', 'zigurat_pricing_nonce'); ?>
                    <input type="hidden" name="manager-section" value="pricing">
                    <input type="hidden" name="calculator" value="lightbox">
                    <input type="hidden" name="zigurat_save_lightbox_rates" value="1">
                    <label>قیمت هر متر محیط (ریال)
                        <input name="perimeter_rate" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $settings['perimeter_rate']); ?>" required>
                    </label>
                    <label>قیمت هر مترمربع (ریال)
                        <input name="square_rate" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $settings['square_rate']); ?>" required>
                    </label>
                    <label>نرخ PVC بر اساس مبنای محاسبه (ریال)
                        <input name="pvc_rate" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $settings['pvc_rate']); ?>" required>
                    </label>
                    <button type="submit">ذخیره نرخ‌های پایه</button>
                </form>
                <?php if (!empty($settings['updated_at'])): ?><small class="manager-pricing-rates__updated">آخرین به‌روزرسانی: <?php echo esc_html($settings['updated_at']); ?></small><?php endif; ?>
            </aside>

            <form class="manager-lightbox-calculator" data-lightbox-calculator data-perimeter-rate="<?php echo esc_attr((int) $settings['perimeter_rate']); ?>" data-square-rate="<?php echo esc_attr((int) $settings['square_rate']); ?>" data-pvc-rate="<?php echo esc_attr((int) $settings['pvc_rate']); ?>" data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-costs-nonce="<?php echo esc_attr(wp_create_nonce('zigurat_lightbox_last_costs')); ?>">
                <div class="manager-lightbox-fields">
                    <label>طول لایت‌باکس (متر) *<input name="length" type="text" inputmode="decimal" placeholder="مثلاً ۲.۴" required></label>
                    <label>عرض لایت‌باکس (متر) *<input name="width" type="text" inputmode="decimal" placeholder="مثلاً ۱.۸" required></label>
                    <label>هزینه نصب (ریال)<input name="installation" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $last_costs['installation']); ?>"></label>
                    <label>هزینه ایاب و ذهاب (ریال)<input name="travel" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $last_costs['travel']); ?>"></label>
                    <label>هزینه لوازم (ریال)<input name="supplies" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $last_costs['supplies']); ?>"></label>
                    <label>هزینه ترانس (ریال)<input name="transformer" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $last_costs['transformer']); ?>"></label>
                    <label>درصد سود<input name="profit_percent" type="text" inputmode="decimal" value="0" placeholder="مثلاً ۲۵"></label>
                    <label class="manager-pricing-check"><input name="use_pvc" type="checkbox" value="1"><span>استفاده از PVC</span></label>
                </div>
                <small class="manager-pricing-autosave">آخرین مبلغ واردشده در هزینه‌های جانبی به‌صورت خودکار ذخیره می‌شود.</small>
                <div class="manager-pricing-formula"><strong>فرمول:</strong> قیمت پایه + هزینه PVC (در صورت انتخاب) + نصب + ایاب‌وذهاب + لوازم + ترانس؛ سپس درصد سود به جمع هزینه‌ها اضافه می‌شود.</div>
                <div class="manager-pricing-error" data-pricing-error role="alert" hidden></div>
                <button class="manager-pricing-calculate" type="submit">محاسبه قیمت نهایی</button>

                <section class="manager-pricing-result" data-pricing-result aria-live="polite">
                    <div><span>روش محاسبه</span><strong data-price-method>—</strong></div>
                    <div><span>مقدار مبنا</span><strong data-price-measure>—</strong></div>
                    <div><span>نرخ مبنا</span><strong data-price-rate>۰ ریال</strong></div>
                    <div><span>قیمت پایه محصول</span><strong data-price-base>۰ ریال</strong></div>
                    <div><span>هزینه PVC</span><strong data-price-pvc>۰ ریال</strong></div>
                    <div><span>جمع هزینه‌های جانبی</span><strong data-price-extras>۰ ریال</strong></div>
                    <div><span>جمع قبل از سود</span><strong data-price-subtotal>۰ ریال</strong></div>
                    <div><span>مبلغ سود</span><strong data-price-profit>۰ ریال</strong></div>
                    <div class="manager-pricing-result__final"><span>قیمت نهایی</span><strong data-price-final>۰ ریال</strong></div>
                </section>
            </form>
        </div>
    <?php endif; ?>
</section>
