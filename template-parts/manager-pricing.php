<?php
if (!defined('ABSPATH') || !zigurat_is_manager()) {
    return;
}
$calculator = isset($_GET['calculator']) && is_string($_GET['calculator'])
    ? sanitize_key(wp_unslash($_GET['calculator']))
    : '';
if (!in_array($calculator, array('', 'lightbox', 'composite', 'flexi'), true)) {
    $calculator = '';
}
$pricing_url = add_query_arg('manager-section', 'pricing', zigurat_manager_login_url());
?>
<section class="manager-pricing" aria-labelledby="manager-pricing-title">
    <div class="manager-pricing__toolbar no-print">
        <a href="<?php echo esc_url($calculator ? $pricing_url : zigurat_manager_login_url()); ?>"><?php echo $calculator ? 'بازگشت به محصولات' : 'بازگشت به پنل مدیران'; ?></a>
    </div>

    <?php if ($calculator === ''): ?>
        <header class="manager-pricing__heading">
            <span>ابزارهای برآورد قیمت</span>
            <h2 id="manager-pricing-title">محاسبه قیمت</h2>
            <p>محصول موردنظر را انتخاب کنید تا نرخ‌ها، ابعاد و هزینه‌های مربوط به همان محصول محاسبه شوند.</p>
        </header>
        <div class="manager-pricing-products">
            <a href="<?php echo esc_url(add_query_arg(array('manager-section'=>'pricing','calculator'=>'lightbox'), zigurat_manager_login_url())); ?>">
                <span aria-hidden="true">▣</span>
                <strong>محاسبه قیمت لایت‌باکس</strong>
                <small>محاسبه براساس متر محیط یا مترمربع، هزینه‌های جانبی و سود</small>
            </a>
            <a href="<?php echo esc_url(add_query_arg(array('manager-section'=>'pricing','calculator'=>'composite'), zigurat_manager_login_url())); ?>">
                <span aria-hidden="true">▦</span>
                <strong>محاسبه قیمت تابلو کامپوزیت</strong>
                <small>محاسبه آهن، کامپوزیت، نصاب و لوازم به‌ازای مترمربع همراه با کرایه، سود، بیمه و مالیات</small>
            </a>
            <a href="<?php echo esc_url(add_query_arg(array('manager-section'=>'pricing','calculator'=>'flexi'), zigurat_manager_login_url())); ?>">
                <span aria-hidden="true">▤</span>
                <strong>محاسبه قیمت فلکسی</strong>
                <small>محاسبه رول فلکسی و پرت، سپری، مغزی، کلیپس، کاور، نصب و آهن‌کشی همراه با تودلی‌ها</small>
            </a>
        </div>
    <?php elseif ($calculator === 'lightbox'):
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
                <form data-pricing-rates-form="lightbox" data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-rates-nonce="<?php echo esc_attr(wp_create_nonce('zigurat_lightbox_rates')); ?>">
                    <label>قیمت هر متر محیط (ریال)
                        <input name="perimeter_rate" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $settings['perimeter_rate']); ?>" required>
                    </label>
                    <label>قیمت هر مترمربع (ریال)
                        <input name="square_rate" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $settings['square_rate']); ?>" required>
                    </label>
                    <label>نرخ PVC بر اساس مبنای محاسبه (ریال)
                        <input name="pvc_rate" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $settings['pvc_rate']); ?>" required>
                    </label>
                    <small class="manager-pricing-rates__status" data-pricing-rates-status role="status">تغییر نرخ‌ها به‌صورت خودکار ذخیره می‌شود.</small>
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
                    <label class="manager-pricing-check"><input name="use_pvc" type="checkbox" value="1"><span><strong>استفاده از PVC</strong><small>با فعال‌شدن این گزینه، نرخ PVC به مبنای محاسبه اضافه می‌شود.</small></span></label>
                </div>
                <small class="manager-pricing-autosave">آخرین مبلغ واردشده در هزینه‌های جانبی به‌صورت خودکار ذخیره می‌شود.</small>
                <div class="manager-pricing-formula"><strong>فرمول:</strong> قیمت پایه + هزینه PVC (در صورت انتخاب) + نصب + ایاب‌وذهاب + لوازم + ترانس؛ سپس درصد سود به جمع هزینه‌ها اضافه می‌شود.</div>
                <div class="manager-pricing-error" data-pricing-error role="alert" hidden></div>
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
    <?php elseif ($calculator === 'composite'):
        $settings = zigurat_get_composite_pricing_settings();
        $last_values = zigurat_get_composite_last_values();
        $pricing_status = isset($_GET['pricing-status']) ? sanitize_key(wp_unslash($_GET['pricing-status'])) : '';
    ?>
        <header class="manager-pricing__heading">
            <span>برآورد محصول</span>
            <h2 id="manager-pricing-title">محاسبه قیمت تابلو کامپوزیت</h2>
            <p>هزینه‌های آهن، کامپوزیت، نصاب و لوازم مصرفی براساس مساحت محاسبه می‌شوند؛ سپس کرایه، سود و در صورت نیاز بیمه و مالیات به‌ترتیب اعمال می‌شوند.</p>
        </header>

        <?php if ($pricing_status === 'saved'): ?><div class="manager-pricing-notice is-success" role="status">نرخ‌های پایه کامپوزیت با موفقیت ذخیره شدند.</div><?php elseif ($pricing_status): ?><div class="manager-pricing-notice is-error" role="alert">ذخیره نرخ‌ها انجام نشد؛ دوباره تلاش کنید.</div><?php endif; ?>

        <div class="manager-pricing-layout">
            <aside class="manager-pricing-rates">
                <h3>تنظیم نرخ‌های هر مترمربع</h3>
                <p>این نرخ‌ها به ریال ذخیره می‌شوند و تا زمان ویرایش بعدی باقی می‌مانند.</p>
                <form data-pricing-rates-form="composite" data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-rates-nonce="<?php echo esc_attr(wp_create_nonce('zigurat_composite_rates')); ?>">
                    <label>قیمت آهن هر مترمربع (ریال)
                        <input name="iron_rate" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $settings['iron_rate']); ?>" required>
                    </label>
                    <label>قیمت کامپوزیت هر مترمربع (ریال)
                        <input name="composite_rate" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $settings['composite_rate']); ?>" required>
                    </label>
                    <label>دستمزد نصاب هر مترمربع (ریال)
                        <input name="installer_rate" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $settings['installer_rate']); ?>" required>
                    </label>
                    <label>لوازم مصرفی هر مترمربع (ریال)
                        <input name="supplies_rate" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $settings['supplies_rate']); ?>" required>
                    </label>
                    <small class="manager-pricing-rates__status" data-pricing-rates-status role="status">تغییر نرخ‌ها به‌صورت خودکار ذخیره می‌شود.</small>
                </form>
                <?php if (!empty($settings['updated_at'])): ?><small class="manager-pricing-rates__updated">آخرین به‌روزرسانی: <?php echo esc_html($settings['updated_at']); ?></small><?php endif; ?>
            </aside>

            <form class="manager-composite-calculator" data-composite-calculator data-iron-rate="<?php echo esc_attr((int) $settings['iron_rate']); ?>" data-composite-rate="<?php echo esc_attr((int) $settings['composite_rate']); ?>" data-installer-rate="<?php echo esc_attr((int) $settings['installer_rate']); ?>" data-supplies-rate="<?php echo esc_attr((int) $settings['supplies_rate']); ?>" data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-values-nonce="<?php echo esc_attr(wp_create_nonce('zigurat_composite_last_values')); ?>">
                <div class="manager-composite-fields">
                    <label>طول تابلو (متر) *<input name="length" type="text" inputmode="decimal" placeholder="مثلاً ۶.۵" required></label>
                    <label>ارتفاع تابلو (متر) *<input name="width" type="text" inputmode="decimal" placeholder="مثلاً ۱.۲" required></label>
                    <label>کرایه (ریال)<input name="freight" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $last_values['freight']); ?>"></label>
                    <label>هزینه آهن‌کشی جهت مهار تابلو (ریال)<input name="bracing_cost" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $last_values['bracing_cost']); ?>"></label>
                    <label>درصد سود<input name="profit_percent" type="text" inputmode="decimal" value="<?php echo esc_attr($last_values['profit_percent']); ?>" placeholder="مثلاً ۲۵"></label>

                    <label>درصد بیمه
                        <input name="insurance_percent" type="text" inputmode="decimal" value="<?php echo esc_attr($last_values['insurance_percent']); ?>" placeholder="اگر لازم نیست صفر بگذارید">
                    </label>
                    <label>درصد مالیات
                        <input name="tax_percent" type="text" inputmode="decimal" value="<?php echo esc_attr($last_values['tax_percent']); ?>" placeholder="اگر لازم نیست صفر بگذارید">
                    </label>
                </div>
                <small class="manager-pricing-autosave">آخرین کرایه، هزینه آهن‌کشی مهار، درصد سود، بیمه و مالیات به‌صورت خودکار ذخیره می‌شوند.</small>
                <div class="manager-pricing-formula"><strong>ترتیب محاسبه:</strong> هزینه‌های متری + کرایه + آهن‌کشی جهت مهار؛ سپس سود و در صورت واردکردن درصد، بیمه و مالیات.</div>
                <div class="manager-pricing-error" data-composite-error role="alert" hidden></div>
                <button class="manager-pricing-calculate" type="submit">محاسبه قیمت نهایی</button>
                <section class="manager-pricing-result manager-pricing-result--composite" data-composite-result aria-live="polite">
                    <div><span>مساحت کل</span><strong data-composite-area>—</strong></div>
                    <div><span>هزینه آهن</span><strong data-composite-iron>۰ ریال</strong></div>
                    <div><span>هزینه کامپوزیت</span><strong data-composite-sheet>۰ ریال</strong></div>
                    <div><span>دستمزد نصاب</span><strong data-composite-installer>۰ ریال</strong></div>
                    <div><span>لوازم مصرفی</span><strong data-composite-supplies>۰ ریال</strong></div>
                    <div><span>کرایه</span><strong data-composite-freight>۰ ریال</strong></div>
                    <div><span>آهن‌کشی جهت مهار تابلو</span><strong data-composite-bracing>۰ ریال</strong></div>
                    <div><span>جمع هزینه پایه</span><strong data-composite-base>۰ ریال</strong></div>
                    <div><span>مبلغ سود</span><strong data-composite-profit>۰ ریال</strong></div>
                    <div><span>مبلغ بیمه</span><strong data-composite-insurance>۰ ریال</strong></div>
                    <div><span>مبلغ مالیات</span><strong data-composite-tax>۰ ریال</strong></div>
                    <div><span>قیمت نهایی هر مترمربع</span><strong data-composite-unit>۰ ریال</strong></div>
                    <div class="manager-pricing-result__final"><span>قیمت نهایی</span><strong data-composite-final>۰ ریال</strong></div>
                </section>
            </form>
        </div>
    <?php elseif ($calculator === 'flexi'):
        $settings = zigurat_get_flexi_pricing_settings();
        $last_values = zigurat_get_flexi_last_values();
        $iron_types = zigurat_flexi_iron_types();
        $bracing_iron_types = zigurat_flexi_bracing_iron_types();
        if (!isset($iron_types[$last_values['iron_type']])) {
            $last_values['iron_type'] = 'custom';
        }
        if (!isset($bracing_iron_types[$last_values['bracing_iron_type']])) {
            $last_values['bracing_iron_type'] = 'custom';
        }
    ?>
        <header class="manager-pricing__heading">
            <span>برآورد محصول</span>
            <h2 id="manager-pricing-title">محاسبه قیمت تابلو فلکسی</h2>
            <p>ابعاد خرید واقعی رول و شاخه‌ها رو به بالا گرد می‌شوند تا پرت فلکسی، سپری، نوار مغزی و کاور در قیمت نهایی محاسبه شود. آهن دور قاب و تودلی‌های لازم نیز براساس ابعاد و نوع قوطی محاسبه می‌شوند.</p>
        </header>

        <div class="manager-pricing-layout manager-pricing-layout--flexi">
            <aside class="manager-pricing-rates">
                <h3>نرخ‌های پایه و ابعاد بازار</h3>
                <p>همه نرخ‌ها بعد از تغییر به‌صورت خودکار ذخیره می‌شوند.</p>
                <form data-pricing-rates-form="flexi" data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-rates-nonce="<?php echo esc_attr(wp_create_nonce('zigurat_flexi_rates')); ?>">
                    <label>قیمت فلکسی هر مترمربع (ریال)<input name="flex_rate" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $settings['flex_rate']); ?>"></label>
                    <label>عرض رول‌های موجود بازار (متر)<input name="roll_widths" type="text" inputmode="decimal" dir="ltr" value="<?php echo esc_attr($settings['roll_widths']); ?>" placeholder="1, 1.5, 2, 2.5, 3, 3.2"><small>عرض‌ها را با ویرگول جدا کنید.</small></label>
                    <label>قیمت هر متر سپری (ریال)<input name="separator_rate" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $settings['separator_rate']); ?>"></label>
                    <label>قیمت هر شاخه نوار مغزی ۱٫۸ متری (ریال)<input name="core_branch_rate" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $settings['core_branch_rate']); ?>"></label>
                    <label>قیمت هر متر چسب دوطرفه (ریال)<input name="tape_rate" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $settings['tape_rate']); ?>"></label>
                    <label>قیمت هر کلیپس (ریال)<input name="clip_rate" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $settings['clip_rate']); ?>"></label>
                    <label>قیمت هر شاخه کاور ۲٫۵ متری (ریال)<input name="cover_branch_rate" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $settings['cover_branch_rate']); ?>"></label>
                    <label>دستمزد نصاب هر مترمربع (ریال)<input name="installer_rate" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $settings['installer_rate']); ?>"></label>
                    <label>قیمت روز هر کیلو آهن (ریال)<input name="iron_price_per_kg" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $settings['iron_price_per_kg']); ?>"></label>
                    <small class="manager-pricing-rates__status" data-pricing-rates-status role="status">تغییر نرخ‌ها به‌صورت خودکار ذخیره می‌شود.</small>
                </form>
                <?php if (!empty($settings['updated_at'])): ?><small class="manager-pricing-rates__updated">آخرین به‌روزرسانی: <?php echo esc_html($settings['updated_at']); ?></small><?php endif; ?>
            </aside>

            <form class="manager-flexi-calculator" data-flexi-calculator
                data-flex-rate="<?php echo esc_attr((int) $settings['flex_rate']); ?>"
                data-roll-widths="<?php echo esc_attr($settings['roll_widths']); ?>"
                data-separator-rate="<?php echo esc_attr((int) $settings['separator_rate']); ?>"
                data-core-branch-rate="<?php echo esc_attr((int) $settings['core_branch_rate']); ?>"
                data-tape-rate="<?php echo esc_attr((int) $settings['tape_rate']); ?>"
                data-clip-rate="<?php echo esc_attr((int) $settings['clip_rate']); ?>"
                data-cover-branch-rate="<?php echo esc_attr((int) $settings['cover_branch_rate']); ?>"
                data-installer-rate="<?php echo esc_attr((int) $settings['installer_rate']); ?>"
                data-iron-price-per-kg="<?php echo esc_attr((int) $settings['iron_price_per_kg']); ?>"
                data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
                data-values-nonce="<?php echo esc_attr(wp_create_nonce('zigurat_flexi_last_values')); ?>">
                <div class="manager-flexi-fields">
                    <label>طول تابلو (متر) *<input name="length" type="text" inputmode="decimal" placeholder="مثلاً ۶.۵" required></label>
                    <label>ارتفاع تابلو (متر) *<input name="width" type="text" inputmode="decimal" placeholder="مثلاً ۱.۲" required></label>
                    <label>اضافه چاپ فلکسی از هر طرف
                        <select name="flex_margin_cm">
                            <?php foreach (array(7, 8, 9, 10) as $margin_cm): ?><option value="<?php echo esc_attr($margin_cm); ?>" <?php selected((float) $last_values['flex_margin_cm'], $margin_cm); ?>><?php echo esc_html(number_format_i18n($margin_cm)); ?> سانتی‌متر</option><?php endforeach; ?>
                        </select>
                    </label>
                    <label>نوع آهن فریم تابلو
                        <select name="iron_type">
                            <?php foreach ($iron_types as $type_key => $type_data): ?><option value="<?php echo esc_attr($type_key); ?>" data-branch-weight="<?php echo esc_attr($type_data['branch_weight']); ?>" <?php selected($last_values['iron_type'], $type_key); ?>><?php echo esc_html($type_data['label']); ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <label>وزن شاخه ۶ متری آهن فریم (کیلوگرم)<input name="iron_branch_weight" type="text" inputmode="decimal" value="<?php echo esc_attr($last_values['iron_branch_weight']); ?>"><small>وزن پیشنهادی با انتخاب نوع قوطی وارد می‌شود و قابل ویرایش است.</small></label>
                    <label>نوع آهن مهار تابلو
                        <select name="bracing_iron_type">
                            <?php foreach ($bracing_iron_types as $type_key => $type_data): ?><option value="<?php echo esc_attr($type_key); ?>" data-branch-weight="<?php echo esc_attr($type_data['branch_weight']); ?>" <?php selected($last_values['bracing_iron_type'], $type_key); ?>><?php echo esc_html($type_data['label']); ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <label>طول آهن مهار موردنیاز (متر)<input name="bracing_iron_length" type="text" inputmode="decimal" placeholder="مثلاً ۱۲.۵"><small>در صورت نیاز نداشتن به مهار، این فیلد را خالی بگذارید.</small></label>
                    <label>وزن شاخه ۶ متری آهن مهار (کیلوگرم)<input name="bracing_iron_branch_weight" type="text" inputmode="decimal" value="<?php echo esc_attr($last_values['bracing_iron_branch_weight']); ?>"></label>
                    <label>کرایه (ریال)<input name="freight" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $last_values['freight']); ?>"></label>
                    <label>درصد سود<input name="profit_percent" type="text" inputmode="decimal" value="<?php echo esc_attr($last_values['profit_percent']); ?>"></label>
                    <label>درصد بیمه<input name="insurance_percent" type="text" inputmode="decimal" value="<?php echo esc_attr($last_values['insurance_percent']); ?>" placeholder="اگر لازم نیست صفر بگذارید"></label>
                    <label>درصد مالیات<input name="tax_percent" type="text" inputmode="decimal" value="<?php echo esc_attr($last_values['tax_percent']); ?>" placeholder="اگر لازم نیست صفر بگذارید"></label>
                </div>
                <small class="manager-pricing-autosave">مقدار اضافه چاپ فلکسی، نوع و وزن شاخه آهن فریم و مهار، کرایه، سود، بیمه و مالیات به‌صورت خودکار ذخیره می‌شوند. طول مهار مخصوص همین محاسبه است.</small>
                <div class="manager-pricing-formula"><strong>منطق آهن‌کشی:</strong> برای طول بیشتر از ۱٫۵ متر، تودلی‌ها با فاصله حداکثر یک متر و برای ارتفاع بیشتر از ۲ متر با فاصله حداکثر دو متر محاسبه می‌شوند. آهن مهار از طول واردشده محاسبه می‌شود؛ فریم و مهار هر دو به شاخه‌های ۶ متری رو به بالا گرد می‌شوند.</div>
                <div class="manager-pricing-error" data-flexi-error role="alert" hidden></div>
                <button class="manager-pricing-calculate" type="submit">محاسبه قیمت فلکسی</button>
                <section class="manager-pricing-result manager-pricing-result--flexi" data-flexi-result aria-live="polite">
                    <div><span>مساحت واقعی تابلو</span><strong data-flexi-area>—</strong></div>
                    <div><span>محیط تابلو</span><strong data-flexi-perimeter>—</strong></div>
                    <div><span>چاپ فلکسی</span><strong data-flexi-plan>—</strong></div>
                    <div><span>سپری و پرت آن</span><strong data-flexi-separator>—</strong></div>
                    <div><span>نوار مغزی و پرت آن</span><strong data-flexi-core>—</strong></div>
                    <div><span>چسب دوطرفه</span><strong data-flexi-tape>—</strong></div>
                    <div><span>کلیپس هر ۱۵ سانتی‌متر</span><strong data-flexi-clips>—</strong></div>
                    <div><span>کاور و پرت آن</span><strong data-flexi-cover>—</strong></div>
                    <div><span>هزینه نصاب</span><strong data-flexi-installer>۰ ریال</strong></div>
                    <div><span>تودلی‌های فریم</span><strong data-flexi-braces>—</strong></div>
                    <div><span>آهن فریم تابلو</span><strong data-flexi-iron>—</strong></div>
                    <div><span>مصرف آهن فریم در هر مترمربع</span><strong data-flexi-iron-unit>—</strong></div>
                    <div><span>آهن مهار تابلو</span><strong data-flexi-bracing-iron>در نظر گرفته نشده</strong></div>
                    <div><span>کرایه</span><strong data-flexi-freight>۰ ریال</strong></div>
                    <div><span>جمع هزینه پایه</span><strong data-flexi-base>۰ ریال</strong></div>
                    <div><span>مبلغ سود</span><strong data-flexi-profit>۰ ریال</strong></div>
                    <div><span>مبلغ بیمه</span><strong data-flexi-insurance>۰ ریال</strong></div>
                    <div><span>مبلغ مالیات</span><strong data-flexi-tax>۰ ریال</strong></div>
                    <div><span>قیمت نهایی هر مترمربع</span><strong data-flexi-unit>۰ ریال</strong></div>
                    <div class="manager-pricing-result__final"><span>قیمت نهایی تابلو فلکسی</span><strong data-flexi-final>۰ ریال</strong></div>
                </section>
            </form>
        </div>
    <?php endif; ?>
</section>
