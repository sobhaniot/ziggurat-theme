<?php
if (!defined('ABSPATH') || !zigurat_is_manager()) {
    return;
}
$settings = zigurat_get_letter_pricing_settings();
$last_values = zigurat_get_letter_last_values();
$saved_estimate_page = function_exists('zigurat_get_pricing_estimate_page')
    ? zigurat_get_pricing_estimate_page(1, 10)
    : array('records' => array(), 'total' => 0, 'page' => 1, 'pages' => 1);
$saved_estimates = $saved_estimate_page['records'];
$edge_types = array(
    'swedish' => 'لبه سوئدی',
    'plastic' => 'لبه پلاستیک',
    'channelium' => 'لبه چلنیوم',
    'metal' => 'لبه فلزی',
);
$smd_types = array(
    'block' => 'SMD بلوکی',
    'lens' => 'SMD لنزدار',
    'roll' => 'SMD رولوکی',
);
$transformer_types = array(
    '400' => '۴۰۰ وات',
    '300' => '۳۰۰ وات',
    '200' => '۲۰۰ وات',
    '120' => '۱۲۰ وات',
    '100' => '۱۰۰ وات',
    '60' => '۶۰ وات',
);
?>
<header class="manager-pricing__heading">
    <span>برآورد حروف برجسته</span>
    <h2 id="manager-pricing-title">محاسبه قیمت حروف و چیدمان ورق</h2>
    <p>فایل SVG خروجی Corel را با اندازه واقعی تحلیل کنید. قطعات از پایین ورق چیده می‌شوند و هزینه رویه فقط از روی مستطیل واقعی مصرف، پرت داخل آن، متر محیط و نرخ‌های ساخت محاسبه می‌شود.</p>
</header>

<div class="manager-pricing-letter-guide">
    <strong>آماده‌سازی فایل در Corel</strong>
    <span>تمام نوشته‌ها را به Curve تبدیل کنید، اندازه صفحه را واقعی بگذارید و سپس با فرمت SVG خروجی بگیرید. دورخط مشکی برش اصلی و <em class="manager-pricing-double-color">قرمز</em> مسیر قطعه دوبل است؛ رنگ داخل هر شکل نیز رنگ پلکسی آن محسوب می‌شود. مسیر <em class="manager-pricing-double-color">قرمز</em>ِ داخل قطعه مشکی از سطح آن کم می‌شود تا پلکسی اصلی به‌صورت نوار برش بخورد و قطعه رنگی داخل فقط یک بار به‌عنوان رویه محاسبه می‌شود.</span>
</div>

<div class="manager-pricing-layout manager-pricing-layout--letters">
    <aside class="manager-pricing-rates">
        <h3>ورق و نرخ‌های پایه</h3>
        <p>ابعاد به میلی‌متر و قیمت‌ها به ریال هستند. تغییرات به‌صورت خودکار ذخیره می‌شوند.</p>
        <form data-letter-rates-form data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-rates-nonce="<?php echo esc_attr(wp_create_nonce('zigurat_letter_rates')); ?>">
            <label>عرض ورق رویه (میلی‌متر)<input name="sheet_width_mm" type="text" inputmode="decimal" value="<?php echo esc_attr($settings['sheet_width_mm']); ?>"><small>برای پلکسی و ورق فلزی ۰٫۷ مشترک است.</small></label>
            <label>ارتفاع ورق رویه (میلی‌متر)<input name="sheet_height_mm" type="text" inputmode="decimal" value="<?php echo esc_attr($settings['sheet_height_mm']); ?>"><small>برای پلکسی و ورق فلزی ۰٫۷ مشترک است.</small></label>
            <label>قیمت پلکسی هر مترمربع (ریال)<input name="plexi_sqm_rate" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $settings['plexi_sqm_rate']); ?>"><small>در مساحت مستطیل مصرف پلکسی ضرب می‌شود.</small></label>
            <label>قیمت ورق فلزی ۰٫۷ هر مترمربع (ریال)<input name="metal_sheet_07_sqm_rate" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $settings['metal_sheet_07_sqm_rate']); ?>"><small>ابعاد و مقدار مصرف آن برابر ورق پلکسی در نظر گرفته می‌شود.</small></label>
                    <label>اجرت دوبل هر متر مسیر قرمز (ریال)<input name="double_layer_labor_rate" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $settings['double_layer_labor_rate']); ?>"><small>مسیر قرمز یک بار داخل پلکسی اصلی و یک بار برای قطعه رویی برش می‌خورد؛ قطعه داخلی هم‌رنگ جداگانه محاسبه نمی‌شود.</small></label>

            <label>نوع لبه برای تنظیم نرخ
                <select name="active_edge_type" data-letter-edge-rate-select>
                    <?php foreach ($edge_types as $key => $label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($settings['active_edge_type'], $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
                </select>
            </label>
            <?php foreach ($edge_types as $key => $label): ?>
                <div class="manager-letter-rate-panel" data-letter-edge-rate-panel="<?php echo esc_attr($key); ?>" <?php echo $settings['active_edge_type'] === $key ? '' : 'hidden'; ?>>
                    <strong><?php echo esc_html($label); ?></strong>
                    <label>قیمت هر متر لبه (ریال)<input name="edge_<?php echo esc_attr($key); ?>_material_rate" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $settings['edge_' . $key . '_material_rate']); ?>"></label>
                    <label>اجرت ساخت هر متر (ریال)<input name="edge_<?php echo esc_attr($key); ?>_labor_rate" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $settings['edge_' . $key . '_labor_rate']); ?>"></label>
                    <?php if ($key === 'metal'): ?>
                        <label>رنگ کوره‌ای هر مترمربع (ریال)<input name="metal_powder_coating_rate" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $settings['metal_powder_coating_rate']); ?>"><small>فقط هنگام انتخاب لبه فلزی و براساس مساحت واقعی رویه محاسبه می‌شود.</small></label>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <label>قیمت PVC هر مترمربع (ریال)<input name="pvc_rate" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $settings['pvc_rate']); ?>"></label>
            <label>اجرت برش پلکسی هر متر مسیر (ریال)<input name="plexi_cut_rate" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $settings['plexi_cut_rate']); ?>"></label>
            <label>اجرت برش PVC هر متر محیط (ریال)<input name="pvc_cut_rate" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $settings['pvc_cut_rate']); ?>"></label>
            <label>هزینه چسب هر متر محیط (ریال)<input name="glue_rate" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $settings['glue_rate']); ?>"></label>

            <label>نوع SMD برای تنظیم نرخ
                <select name="active_smd_type" data-letter-smd-rate-select>
                    <?php foreach ($smd_types as $key => $label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($settings['active_smd_type'], $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
                </select>
            </label>
            <?php foreach ($smd_types as $key => $label): ?>
                <div class="manager-letter-rate-panel" data-letter-smd-rate-panel="<?php echo esc_attr($key); ?>" <?php echo $settings['active_smd_type'] === $key ? '' : 'hidden'; ?>>
                    <strong><?php echo esc_html($label); ?></strong>
                    <label><?php echo $key === 'roll' ? 'قیمت هر متر رولوکی با اجرت نصب (ریال)' : 'قیمت هر واحد با اجرت نصب (ریال)'; ?><input name="smd_<?php echo esc_attr($key); ?>_rate" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $settings['smd_' . $key . '_rate']); ?>"></label>
                    <?php if ($key !== 'roll'): ?>
                        <label>تعداد واحد در هر مترمربع رویه<input name="smd_<?php echo esc_attr($key); ?>_units_per_square_meter" type="text" inputmode="decimal" value="<?php echo esc_attr($settings['smd_' . $key . '_units_per_square_meter']); ?>"><small>برای هر نوع SMD جداگانه ذخیره می‌شود.</small></label>
                    <?php endif; ?>
                    <?php if ($key === 'block'): ?>
                        <label>طول هر بلوک (میلی‌متر)<input name="smd_block_width_mm" type="text" inputmode="decimal" value="<?php echo esc_attr($settings['smd_block_width_mm']); ?>"></label>
                        <label>عرض هر بلوک (میلی‌متر)<input name="smd_block_height_mm" type="text" inputmode="decimal" value="<?php echo esc_attr($settings['smd_block_height_mm']); ?>"></label>
                        <label>تعداد چیپ روی هر بلوک<input name="smd_block_led_count" type="text" inputmode="numeric" value="<?php echo esc_attr((int) $settings['smd_block_led_count']); ?>"><small>پیش‌فرض بازار برای مدل سه‌چیپ: ۷۵ × ۱۵ میلی‌متر.</small></label>
                    <?php elseif ($key === 'lens'): ?>
                        <label>طول هر بلوک لنزدار (میلی‌متر)<input name="smd_lens_width_mm" type="text" inputmode="decimal" value="<?php echo esc_attr($settings['smd_lens_width_mm']); ?>"></label>
                        <label>عرض هر بلوک لنزدار (میلی‌متر)<input name="smd_lens_height_mm" type="text" inputmode="decimal" value="<?php echo esc_attr($settings['smd_lens_height_mm']); ?>"></label>
                        <label>تعداد لنز روی هر بلوک<input name="smd_lens_led_count" type="text" inputmode="numeric" value="<?php echo esc_attr((int) $settings['smd_lens_led_count']); ?>"><small>پیش‌فرض مدل سه‌لنز: ۷۵ × ۱۵ میلی‌متر.</small></label>
                    <?php elseif ($key === 'roll'): ?>
                        <label>عرض نوار رولوکی (میلی‌متر)<input name="smd_roll_strip_width_mm" type="text" inputmode="decimal" value="<?php echo esc_attr($settings['smd_roll_strip_width_mm']); ?>"></label>
                        <label>توان مصرفی هر متر (وات)<input name="smd_roll_watts_per_meter" type="text" inputmode="decimal" value="<?php echo esc_attr($settings['smd_roll_watts_per_meter']); ?>"></label>
                        <label>طول هر رول (متر)<input name="smd_roll_length_m" type="text" inputmode="decimal" value="<?php echo esc_attr($settings['smd_roll_length_m']); ?>"></label>
                        <label>گام مجاز برش (میلی‌متر)<input name="smd_roll_cut_interval_mm" type="text" inputmode="decimal" value="<?php echo esc_attr($settings['smd_roll_cut_interval_mm']); ?>"><small>طول هر قطعه به مضرب این عدد رو به بالا گرد می‌شود.</small></label>
                        <label>فاصله امن نوار از دیواره (میلی‌متر)<input name="smd_roll_wall_clearance_mm" type="text" inputmode="decimal" value="<?php echo esc_attr($settings['smd_roll_wall_clearance_mm']); ?>"><small>محور نوار پس از کسر عرض خود نوار، حداقل این فاصله را از لبه حفظ می‌کند.</small></label>
                        <label>حداکثر فاصله نوارهای رولوکی از هم (میلی‌متر)<input name="smd_roll_row_spacing_mm" type="text" inputmode="decimal" value="<?php echo esc_attr($settings['smd_roll_row_spacing_mm']); ?>"><small>حداکثر فاصله مرکز تا مرکز نوارهاست؛ تعداد ردیف‌ها با توجه به عرض هر قسمت از حرف خودکار تعیین می‌شود.</small></label>
                        <label>پرت خرید رولوکی (درصد)<input name="smd_roll_waste_percent" type="text" inputmode="decimal" value="<?php echo esc_attr($settings['smd_roll_waste_percent']); ?>"></label>
                        <label>رزرو توان ترانس (درصد)<input name="smd_roll_transformer_reserve_percent" type="text" inputmode="decimal" value="<?php echo esc_attr($settings['smd_roll_transformer_reserve_percent']); ?>"><small>برای جلوگیری از کارکرد ترانس در حداکثر توان.</small></label>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <label>ترانس برای تنظیم قیمت و ظرفیت
                <select name="active_transformer_type" data-letter-transformer-rate-select>
                    <?php foreach ($transformer_types as $watts => $label): ?><option value="<?php echo esc_attr($watts); ?>" <?php selected($settings['active_transformer_type'], $watts); ?>>ترانس <?php echo esc_html($label); ?></option><?php endforeach; ?>
                </select>
            </label>
            <?php foreach ($transformer_types as $watts => $label): ?>
                <div class="manager-letter-rate-panel" data-letter-transformer-rate-panel="<?php echo esc_attr($watts); ?>" <?php echo $settings['active_transformer_type'] === $watts ? '' : 'hidden'; ?>>
                    <strong>ترانس <?php echo esc_html($label); ?></strong>
                    <label>قیمت ترانس <?php echo esc_html($label); ?> (ریال)<input name="transformer_<?php echo esc_attr($watts); ?>_rate" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $settings['transformer_' . $watts . '_rate']); ?>"></label>
                    <label>ظرفیت ترانس <?php echo esc_html($label); ?> (تعداد SMD)<input name="transformer_<?php echo esc_attr($watts); ?>_capacity" type="text" inputmode="numeric" value="<?php echo esc_attr((int) $settings['transformer_' . $watts . '_capacity']); ?>"><small>این ظرفیت برای بلوکی و لنزدار است؛ رولوکی با وات مصرفی محاسبه می‌شود.</small></label>
                </div>
            <?php endforeach; ?>
            <label>فاصله قطعات از هم (میلی‌متر)<input name="cut_gap_mm" type="text" inputmode="decimal" value="<?php echo esc_attr($settings['cut_gap_mm']); ?>"></label>
            <label>حاشیه امن دور ورق (میلی‌متر)<input name="sheet_margin_mm" type="text" inputmode="decimal" value="<?php echo esc_attr($settings['sheet_margin_mm']); ?>"></label>
            <small class="manager-pricing-rates__status" data-letter-rates-status role="status">تغییر نرخ‌ها به‌صورت خودکار ذخیره می‌شود.</small>
        </form>
        <?php if (!empty($settings['updated_at'])): ?><small class="manager-pricing-rates__updated">آخرین به‌روزرسانی: <?php echo esc_html($settings['updated_at']); ?></small><?php endif; ?>
    </aside>

    <form class="manager-letter-calculator" data-letter-calculator
        data-sheet-width-mm="<?php echo esc_attr($settings['sheet_width_mm']); ?>"
        data-sheet-height-mm="<?php echo esc_attr($settings['sheet_height_mm']); ?>"
        data-cut-gap-mm="<?php echo esc_attr($settings['cut_gap_mm']); ?>"
        data-sheet-margin-mm="<?php echo esc_attr($settings['sheet_margin_mm']); ?>"
        data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
        data-values-nonce="<?php echo esc_attr(wp_create_nonce('zigurat_letter_last_values')); ?>"
        data-estimates-nonce="<?php echo esc_attr(wp_create_nonce('zigurat_pricing_estimates')); ?>"
        data-letter-worker-url="<?php echo esc_url(get_template_directory_uri() . '/assets/js/letter-calculator-worker.js?ver=' . (is_file(get_template_directory() . '/assets/js/letter-calculator-worker.js') ? filemtime(get_template_directory() . '/assets/js/letter-calculator-worker.js') : time())); ?>">

        <label class="manager-letter-upload" data-letter-upload>
            <input name="letter_svg" type="file" accept="image/svg+xml,.svg" required>
            <span aria-hidden="true">SVG</span>
            <strong>فایل را اینجا رها کنید یا برای انتخاب کلیک کنید</strong>
            <small data-letter-file-name>فقط فایل SVG حداکثر ۵ مگابایت</small>
        </label>
        <figure class="manager-letter-file-preview" data-letter-file-preview hidden>
            <div><img data-letter-source-image alt=""></div>
            <figcaption>
                <strong>پیش‌نمایش فایل انتخاب‌شده</strong>
                <small data-letter-source-details></small>
            </figcaption>
        </figure>

        <div class="manager-letter-fields">
            <label>عرض واقعی کل طرح (میلی‌متر) *<input name="design_width_mm" type="text" inputmode="decimal" placeholder="از فایل خوانده می‌شود" required></label>
            <label>ارتفاع واقعی کل طرح (میلی‌متر) *<input name="design_height_mm" type="text" inputmode="decimal" placeholder="از فایل خوانده می‌شود" required></label>
            <label>نوع لبه
                <select name="edge_type" data-letter-edge-type>
                    <?php foreach ($edge_types as $key => $label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($last_values['edge_type'], $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
                </select>
                <small data-letter-edge-rate-summary></small>
            </label>
            <label>نوع SMD
                <select name="smd_type" data-letter-smd-type>
                    <option value="none" <?php selected($last_values['smd_type'], 'none'); ?>>بدون SMD</option>
                    <?php foreach ($smd_types as $key => $label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($last_values['smd_type'], $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
                </select>
                <small data-letter-smd-rate-summary></small>
            </label>
            <label>روش محاسبه نصب
                <select name="installation_mode" data-letter-installation-mode>
                    <option value="fixed" <?php selected($last_values['installation_mode'], 'fixed'); ?>>هزینه نصب کلی</option>
                    <option value="perimeter" <?php selected($last_values['installation_mode'], 'perimeter'); ?>>براساس متر محیط حروف</option>
                </select>
            </label>
            <label><span data-letter-installation-input-label>هزینه نصب کلی (ریال)</span><input name="installation" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $last_values['installation']); ?>"></label>
            <label>هزینه ایاب و ذهاب (ریال)<input name="travel" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $last_values['travel']); ?>"></label>
            <label>تعداد چیدمان آزمایشی
                <select name="layout_trials">
                    <option value="5" <?php selected((int) $last_values['layout_trials'], 5); ?>>۵ بار — سریع</option>
                    <option value="10" <?php selected((int) $last_values['layout_trials'], 10); ?>>۱۰ بار — متعادل</option>
                    <option value="20" <?php selected((int) $last_values['layout_trials'], 20); ?>>۲۰ بار — دقیق‌تر</option>
                    <option value="30" <?php selected((int) $last_values['layout_trials'], 30); ?>>۳۰ بار — بیشترین بررسی</option>
                </select>
                <small>تعداد بیشتر ممکن است چیدمان بهتری پیدا کند، اما زمان تحلیل را افزایش می‌دهد.</small>
            </label>
            <label>هزینه سیم و لوازم مصرفی (ریال)<input name="wire_supplies" type="text" inputmode="numeric" data-money-input value="<?php echo esc_attr((int) $last_values['wire_supplies']); ?>"></label>
            <label>درصد سود<input name="profit_percent" type="text" inputmode="decimal" value="<?php echo esc_attr($last_values['profit_percent']); ?>"></label>
            <label>درصد بیمه و مالیات<input name="insurance_tax_percent" type="text" inputmode="decimal" value="<?php echo esc_attr($last_values['insurance_tax_percent']); ?>" placeholder="اگر لازم نیست صفر بگذارید"></label>
            <div class="manager-pricing-check">
                <input id="letter-use-transformer" name="use_transformer" type="checkbox" value="1" <?php checked(!empty($last_values['use_transformer'])); ?> aria-label="استفاده از ترانس">
                <span><strong>استفاده از ترانس</strong><small>در صورت خاموش‌بودن، پیشنهاد و هزینه ترانس در محاسبه منظور نمی‌شود.</small></span>
            </div>
        </div>

        <small class="manager-pricing-autosave">آخرین هزینه‌های جانبی، درصدها و وضعیت استفاده از ترانس به‌صورت خودکار ذخیره می‌شوند.</small>
        <div class="manager-pricing-formula"><strong>مبنای برآورد:</strong> مسیر مشکی یک‌بار و مسیر <em class="manager-pricing-double-color">قرمز</em> دوبار (برش داخلی پلکسی اصلی و برش قطعه رویی) محاسبه می‌شوند. قطعه داخلیِ هم‌رنگ پلکسی اصلی دوباره به مصرف ورق اضافه نمی‌شود. SMD بلوکی و لنزدار از سطح کامل داخل مسیر مشکی و رولوکی از مسیر میانی قابل نصب داخل حروف محاسبه می‌شود. طول رولوکی با گام برش و پرت خرید گرد می‌شود و ترانس آن براساس وات مصرفی و رزرو توان پیشنهاد می‌شود. چیدمان هر رنگ پلکسی جداگانه و همیشه با چرخش بهینه انجام می‌شود و PVC نیز محاسبه خواهد شد. برای لبه فلزی، رویه ورق فلزی ۰٫۷ و رنگ کوره‌ای براساس مترمربعِ مساحت واقعی رویه محاسبه می‌شوند.</div>
        <div class="manager-pricing-error" data-letter-error role="alert" hidden></div>
        <button class="manager-pricing-calculate" type="submit" data-letter-calculate>تحلیل فایل و چیدمان ورق</button>
        <small class="manager-letter-runtime-note">طرح‌های معمولی طی چند ثانیه تحلیل می‌شوند؛ مرحله جاری و زمان سپری‌شده هنگام پردازش نمایش داده خواهد شد.</small>
        <div class="manager-letter-progress" data-letter-progress role="status" hidden><span></span><i data-letter-progress-color aria-hidden="true" hidden></i><strong>مرحله ۱ از ۴: خواندن فایل SVG</strong></div>

        <section class="manager-letter-analysis" data-letter-analysis hidden aria-live="polite">
            <div class="manager-letter-analysis__summary">
                <div><span>ابعاد واقعی طرح</span><strong data-letter-design-size>—</strong></div>
                <div><span>تعداد قطعات مستقل</span><strong data-letter-parts>—</strong></div>
                <div><span>مساحت واقعی رویه</span><strong data-letter-area>—</strong></div>
                <div><span>سطح مبنای محاسبه SMD</span><strong data-letter-lighting-area>—</strong></div>
                <div><span>مجموع محیط برداری</span><strong data-letter-perimeter>—</strong></div>
                <div><span>مسیر دوبل</span><strong data-letter-special-paths>—</strong></div>
                <div><span>تعداد ورق موردنیاز</span><strong data-letter-sheets>—</strong></div>
                <div><span>مصرف و پرت ورق رویه</span><strong data-letter-waste>—</strong></div>
            </div>

            <div class="manager-letter-materials" data-letter-materials hidden></div>

            <div class="manager-letter-unplaced" data-letter-unplaced hidden></div>

            <div class="manager-letter-smd-preview" data-letter-smd-preview hidden>
                <header>
                    <div><strong>چیدمان تقریبی SMD روی حروف</strong><small>برای مدل‌های بلوکی محل هر واحد و برای رولوکی مسیر نواری قابل نصب نمایش داده می‌شود.</small></div>
                    <span data-letter-smd-layout-summary>—</span>
                </header>
                <div data-letter-smd-canvas></div>
            </div>

            <div class="manager-letter-preview">
                <header><div><strong>پیش‌نمایش چیدمان</strong><small data-letter-accuracy></small></div><span data-letter-utilization>—</span></header>
                <div class="manager-letter-sheets" data-letter-sheet-preview></div>
            </div>

            <section class="manager-pricing-result manager-pricing-result--letters">
                <div><span>هزینه مصرف پلکسی</span><strong data-letter-plexi-cost>۰ ریال</strong></div>
                <div><span>هزینه ورق فلزی ۰٫۷</span><strong data-letter-metal-sheet-cost>محاسبه نشده</strong></div>
                <div><span>هزینه رنگ کوره‌ای</span><strong data-letter-powder-coating-cost>محاسبه نمی‌شود</strong></div>
                <div><span data-letter-edge-label>قیمت لبه</span><strong data-letter-edge-cost>۰ ریال</strong></div>
                <div><span data-letter-edge-labor-label>اجرت ساخت لبه</span><strong data-letter-build-cost>۰ ریال</strong></div>
                <div><span>برش پلکسی</span><strong data-letter-plexi-cut-cost>۰ ریال</strong></div>
                <div><span>هزینه PVC</span><strong data-letter-pvc-cost>محاسبه نشده</strong></div>
                <div><span>برش PVC</span><strong data-letter-pvc-cut-cost>محاسبه نشده</strong></div>
                <div><span>هزینه چسب</span><strong data-letter-glue-cost>۰ ریال</strong></div>
                <div><span>اجرت ساخت دوبل</span><strong data-letter-double-labor-cost>محاسبه نمی‌شود</strong></div>
                <div><span data-letter-smd-label>هزینه SMD با نصب</span><strong data-letter-led-cost>محاسبه نشده</strong></div>
                <div><span>هزینه ترانس</span><strong data-letter-transformer-cost>محاسبه نشده</strong></div>
                <div class="manager-pricing-result__extra"><span>هزینه نصب</span><strong data-letter-installation-cost>۰ ریال</strong></div>
                <div class="manager-pricing-result__extra"><span>هزینه ایاب و ذهاب</span><strong data-letter-travel-cost>۰ ریال</strong></div>
                <div class="manager-pricing-result__extra"><span>هزینه سیم و لوازم مصرفی</span><strong data-letter-wire-supplies>۰ ریال</strong></div>
                <div class="manager-pricing-result__summary"><span>جمع هزینه‌های جانبی</span><strong data-letter-extras>۰ ریال</strong></div>
                <div class="manager-pricing-result__summary"><span>جمع هزینه پایه</span><strong data-letter-base>۰ ریال</strong></div>
                <div class="manager-pricing-result__summary"><span>مبلغ سود</span><strong data-letter-profit>۰ ریال</strong></div>
                <div class="manager-pricing-result__summary"><span>مبلغ بیمه و مالیات</span><strong data-letter-insurance-tax>۰ ریال</strong></div>
                <div class="manager-pricing-result__final"><span>قیمت تقریبی ساخت حروف</span><div><strong data-letter-final>۰ ریال</strong><small data-letter-unit-price>۰ ریال به‌ازای هر متر</small></div></div>
            </section>

            <section class="manager-pricing-estimate-editor no-print" data-letter-estimate-editor>
                <div>
                    <label>نام پروژه برای ذخیره این محاسبه
                        <input name="estimate_project_name" type="text" maxlength="191" placeholder="مثلاً تابلو فروشگاه یانا">
                    </label>
                    <input name="estimate_id" type="hidden" value="0">
                    <small data-letter-estimate-mode>به‌عنوان یک برآورد جدید ذخیره می‌شود.</small>
                </div>
                <div class="manager-pricing-estimate-editor__actions">
                    <button type="button" data-letter-estimate-save>ذخیره محاسبه</button>
                    <button type="button" data-letter-estimate-new hidden>ایجاد نسخه جدید</button>
                    <button type="button" data-letter-estimate-print>چاپ / ذخیره PDF</button>
                </div>
                <p data-letter-estimate-status role="status"></p>
            </section>
        </section>
    </form>
</div>

<section class="manager-pricing-estimates no-print" data-letter-estimates data-current-page="<?php echo esc_attr($saved_estimate_page['page']); ?>" data-total-pages="<?php echo esc_attr($saved_estimate_page['pages']); ?>">
    <header>
        <div><span>سوابق قیمت‌گذاری</span><h3>برآوردهای ذخیره‌شده</h3></div>
        <strong data-letter-estimate-count><?php echo esc_html(number_format_i18n($saved_estimate_page['total'])); ?> مورد</strong>
    </header>
    <div class="manager-pricing-estimates__toolbar">
        <label>جستجو در نام پروژه
            <input type="search" data-letter-estimate-search placeholder="نام پروژه را بنویسید…" autocomplete="off">
        </label>
        <button type="button" data-letter-estimate-search-clear hidden>پاک‌کردن جستجو</button>
    </div>
    <div class="manager-pricing-estimates__list" data-letter-estimate-list>
        <?php if (!$saved_estimates): ?>
            <p class="manager-pricing-estimates__empty">هنوز محاسبه‌ای ذخیره نشده است.</p>
        <?php else: foreach ($saved_estimates as $estimate): ?>
            <article data-estimate-id="<?php echo esc_attr($estimate['id']); ?>">
                <div><strong><?php echo esc_html($estimate['project_name']); ?></strong><small>آخرین تغییر: <?php echo esc_html($estimate['modified']); ?></small></div>
                <b><?php echo esc_html(number_format_i18n($estimate['final_price'])); ?> ریال<small><?php echo esc_html(number_format_i18n($estimate['perimeter_m'], 1)); ?> متر · <?php echo esc_html(number_format_i18n($estimate['unit_price'])); ?> ریال/متر</small></b>
                <div class="manager-pricing-estimates__actions">
                    <button type="button" data-letter-estimate-load="<?php echo esc_attr($estimate['id']); ?>">بازکردن و ویرایش</button>
                    <button type="button" data-letter-estimate-print-saved="<?php echo esc_attr($estimate['id']); ?>">چاپ / PDF</button>
                </div>
            </article>
        <?php endforeach; endif; ?>
    </div>
    <p class="manager-pricing-estimates__status" data-letter-estimate-list-status role="status"></p>
    <nav class="manager-pricing-estimates__pagination" aria-label="صفحه‌بندی برآوردهای ذخیره‌شده">
        <button type="button" data-letter-estimate-page="prev" <?php disabled($saved_estimate_page['page'] <= 1); ?>>صفحه قبل</button>
        <span data-letter-estimate-page-label>صفحه <?php echo esc_html(number_format_i18n($saved_estimate_page['page'])); ?> از <?php echo esc_html(number_format_i18n($saved_estimate_page['pages'])); ?></span>
        <button type="button" data-letter-estimate-page="next" <?php disabled($saved_estimate_page['page'] >= $saved_estimate_page['pages']); ?>>صفحه بعد</button>
    </nav>
</section>
