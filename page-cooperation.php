<?php
/*
Template Name: Cooperation
*/
if (!defined('ABSPATH')) {
    exit;
}
get_header();

$status = isset($_GET['application-status']) && is_string($_GET['application-status'])
    ? sanitize_key(wp_unslash($_GET['application-status']))
    : '';
$selected_type = isset($_GET['type']) && $_GET['type'] === 'supplier' ? 'supplier' : 'collaborator';
$provinces = array(
    'آذربایجان شرقی', 'آذربایجان غربی', 'اردبیل', 'اصفهان', 'البرز', 'ایلام', 'بوشهر', 'تهران', 'چهارمحال و بختیاری',
    'خراسان جنوبی', 'خراسان رضوی', 'خراسان شمالی', 'خوزستان', 'زنجان', 'سمنان', 'سیستان و بلوچستان', 'فارس', 'قزوین',
    'قم', 'کردستان', 'کرمان', 'کرمانشاه', 'کهگیلویه و بویراحمد', 'گلستان', 'گیلان', 'لرستان', 'مازندران', 'مرکزی', 'هرمزگان', 'همدان', 'یزد'
);
?>
<main class="cooperation-page">
    <section class="cooperation-hero">
        <div class="container">
            <span class="cooperation-eyebrow">شبکه همکاری زیگورات در سراسر ایران</span>
            <h1>فرصت شغلی و همکاری با زیگورات</h1>
            <p>اگر نصاب، بنا، نقاش، برقکار، ام‌دی‌اف‌کار یا متخصص اجرای پروژه هستید، یا کارگاه و مجموعه تأمین‌کننده دارید، برای دریافت کار و همکاری بلندمدت درخواست خود را ثبت کنید.</p>
            <div class="cooperation-hero-actions">
                <a href="<?php echo esc_url(add_query_arg('type', 'collaborator', get_permalink()) . '#application-form'); ?>">ثبت‌نام همکار اجرایی</a>
                <a href="<?php echo esc_url(add_query_arg('type', 'supplier', get_permalink()) . '#application-form'); ?>">ثبت‌نام تأمین‌کننده</a>
            </div>
        </div>
    </section>

    <section class="cooperation-paths" aria-labelledby="cooperation-paths-title">
        <div class="container">
            <h2 id="cooperation-paths-title">کدام نوع همکاری برای شما مناسب است؟</h2>
            <div class="cooperation-path-grid">
                <article>
                    <span aria-hidden="true">🛠️</span>
                    <h3>همکار اجرایی و نیروی متخصص</h3>
                    <p>ویژه نصاب‌ها، بناها، نقاش‌ها، برقکارها، جوشکارها، ام‌دی‌اف‌کارها، نیروهای چاپ و سایر متخصصان اجرایی.</p>
                    <a href="<?php echo esc_url(add_query_arg('type', 'collaborator', get_permalink()) . '#application-form'); ?>">ثبت درخواست همکاری</a>
                </article>
                <article>
                    <span aria-hidden="true">🏭</span>
                    <h3>تأمین‌کننده و کارگاه تولیدی</h3>
                    <p>ویژه کارگاه‌های ام‌دی‌اف، شیشه سکوریت، چاپ، فلز، نورپردازی، تابلو و مجموعه‌های تأمین کالا و خدمات.</p>
                    <a href="<?php echo esc_url(add_query_arg('type', 'supplier', get_permalink()) . '#application-form'); ?>">ثبت‌نام تأمین‌کننده</a>
                </article>
            </div>
        </div>
    </section>

    <section class="application-section" id="application-form">
        <div class="container application-layout">
            <div class="application-intro">
                <span>فرم محرمانه</span>
                <h2><?php echo $selected_type === 'supplier' ? 'ثبت اطلاعات تأمین‌کننده' : 'ثبت اطلاعات همکار اجرایی'; ?></h2>
                <p>اطلاعات شما عمومی نمی‌شود. همکاران و تأمین‌کنندگان به اطلاعات یکدیگر دسترسی ندارند و فقط مدیران زیگورات درخواست را بررسی می‌کنند.</p>
                <ul>
                    <li>امکان انتخاب همکاری در شهرهای مشخص یا سراسر ایران</li>
                    <li>بارگذاری امن عکس، کارت ملی و حداکثر ۵ نمونه‌کار</li>
                    <li>بررسی مستقیم توسط مدیر و تماس در صورت وجود پروژه مناسب</li>
                </ul>
            </div>
            <div class="application-card">
                <?php if ($status === 'sent'): ?>
                    <div class="application-notice success" role="status">درخواست شما با موفقیت ثبت شد. پس از بررسی با شما تماس می‌گیریم.</div>
                <?php elseif ($status === 'limited'): ?>
                    <div class="application-notice error" role="alert">تعداد درخواست‌های این اتصال بیش از حد مجاز است. لطفاً بعداً دوباره تلاش کنید.</div>
                <?php elseif ($status === 'upload-error'): ?>
                    <div class="application-notice error" role="alert">فایل‌ها کامل بارگذاری نشدند. فرمت JPG، PNG، WEBP یا PDF و حداکثر حجم ۵ مگابایت را رعایت کنید.</div>
                <?php elseif ($status === 'invalid' || $status === 'error'): ?>
                    <div class="application-notice error" role="alert">ثبت درخواست انجام نشد. لطفاً همه فیلدهای ضروری را تکمیل و دوباره تلاش کنید.</div>
                <?php endif; ?>
                <form class="application-form" method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('zigurat_partner_application', 'zigurat_application_nonce'); ?>
                    <input type="hidden" name="zigurat_partner_application" value="1">
                    <input type="hidden" name="application_type" value="<?php echo esc_attr($selected_type); ?>">
                    <div class="application-honeypot" aria-hidden="true">
                        <label for="company-website">وب‌سایت شرکت</label>
                        <input id="company-website" type="text" name="company_website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="application-row">
                        <label>نام *<input type="text" name="first_name" autocomplete="given-name" required></label>
                        <label>نام خانوادگی *<input type="text" name="last_name" autocomplete="family-name" required></label>
                    </div>
                    <?php if ($selected_type === 'supplier'): ?><label class="business-name-field">نام مجموعه یا کارگاه<input type="text" name="business_name" autocomplete="organization"></label><?php endif; ?>
                    <div class="application-row">
                        <label>شماره تماس *<input type="tel" name="phone" inputmode="tel" autocomplete="tel" required></label>
                        <label>ایمیل<input type="email" name="email" autocomplete="email"></label>
                    </div>
                    <div class="application-row">
                        <label><span class="profession-label"><?php echo $selected_type === 'supplier' ? 'حوزه تأمین یا تولید *' : 'زمینه شغلی *'; ?></span><input type="text" name="profession" list="zigurat-professions" placeholder="<?php echo esc_attr($selected_type === 'supplier' ? 'مثلاً شیشه سکوریت' : 'مثلاً برقکار'); ?>" required></label>
                        <label>سابقه فعالیت (سال)<input type="number" name="experience_years" min="0" max="70"></label>
                    </div>
                    <datalist id="zigurat-professions">
                        <option value="نصاب تابلو"><option value="بنا و بازسازی"><option value="نقاش ساختمان"><option value="برقکار">
                        <option value="ام‌دی‌اف‌کار"><option value="جوشکار"><option value="چاپ و تبلیغات"><option value="شیشه سکوریت">
                        <option value="نورپردازی"><option value="تأمین ورق و متریال"><option value="حمل و نصب">
                    </datalist>
                    <div class="application-row">
                        <label>استان محل سکونت/فعالیت *
                            <select name="province" required>
                                <option value="">انتخاب استان</option>
                                <?php foreach ($provinces as $province): ?><option value="<?php echo esc_attr($province); ?>"><?php echo esc_html($province); ?></option><?php endforeach; ?>
                            </select>
                        </label>
                        <label>شهر محل سکونت/فعالیت *<input type="text" name="city" required></label>
                    </div>
                    <label>شهرهای قابل همکاری<textarea name="work_cities" rows="3" placeholder="نام شهرها را با ویرگول جدا کنید؛ مثلاً تهران، کرج، قم"></textarea></label>
                    <label class="application-checkbox"><input type="checkbox" name="nationwide" value="1"> امکان اعزام و همکاری در سراسر ایران را دارم</label>
                    <label>توضیحات تکمیلی<textarea name="description" rows="4" placeholder="ابزار، تجهیزات، ظرفیت تولید، خودرو یا شرایط همکاری خود را بنویسید."></textarea></label>

                    <div class="application-files">
                        <label>عکس متقاضی یا مجموعه *<input type="file" name="applicant_photo" accept="image/jpeg,image/png,image/webp" required><small>JPG، PNG یا WEBP؛ حداکثر ۵ مگابایت</small></label>
                        <?php if ($selected_type !== 'supplier'): ?>
                            <label>عکس کارت ملی *<input type="file" name="national_card" accept="image/jpeg,image/png,image/webp" required><small>این فایل فقط برای مدیر قابل مشاهده است.</small></label>
                        <?php endif; ?>
                        <label>نمونه‌کارها *<input type="file" name="portfolio[]" accept="image/jpeg,image/png,image/webp,application/pdf" multiple required><small>حداکثر ۵ فایل تصویر یا PDF، هر فایل تا ۵ مگابایت</small></label>
                    </div>
                    <label class="application-checkbox privacy-consent"><input type="checkbox" name="privacy_consent" value="1" required> با ذخیره و بررسی محرمانه اطلاعات برای ارزیابی همکاری موافقم. *</label>
                    <button type="submit">ثبت امن درخواست همکاری</button>
                </form>
            </div>
        </div>
    </section>

    <section class="cooperation-seo-content">
        <div class="container">
            <h2>پیدا کردن کار و پروژه اجرایی در سراسر ایران</h2>
            <p>زیگورات برای اجرای پروژه‌های تابلوسازی، چاپ، بازسازی، دکوراسیون، برق، رنگ، ام‌دی‌اف، شیشه و خدمات وابسته به شبکه‌ای از نیروهای متخصص و تأمین‌کنندگان معتبر نیاز دارد. ثبت‌نام در این صفحه به معنی استخدام قطعی نیست؛ اطلاعات شما در بانک همکاران خصوصی ثبت می‌شود تا هنگام وجود پروژه مناسب با شما تماس بگیریم.</p>
            <div class="cooperation-faq">
                <details><summary>آیا اطلاعات من برای سایر افراد نمایش داده می‌شود؟</summary><p>خیر. هیچ فهرست عمومی از متقاضیان وجود ندارد و اطلاعات و فایل‌ها فقط در اختیار مدیران سایت است.</p></details>
                <details><summary>اگر در چند شهر فعالیت می‌کنم چه بنویسم؟</summary><p>شهرها را در فیلد شهرهای قابل همکاری با ویرگول جدا کنید یا گزینه همکاری در سراسر ایران را انتخاب کنید.</p></details>
                <details><summary>تأمین‌کنندگان چه مجموعه‌هایی می‌توانند ثبت‌نام کنند؟</summary><p>کارگاه‌های ام‌دی‌اف، شیشه سکوریت، چاپ، فلز، نورپردازی، تابلو، رنگ و سایر تأمین‌کنندگان مرتبط می‌توانند درخواست بدهند.</p></details>
            </div>
        </div>
    </section>
</main>
<?php get_footer(); ?>
