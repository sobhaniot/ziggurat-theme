<?php
if (!defined('ABSPATH')) {
    exit;
}

function zigurat_site_languages()
{
    return array(
        'fa' => array('name' => 'فارسی', 'short' => 'FA', 'locale' => 'fa_IR', 'dir' => 'rtl'),
        'en' => array('name' => 'English', 'short' => 'EN', 'locale' => 'en_US', 'dir' => 'ltr'),
        'ar' => array('name' => 'العربية', 'short' => 'AR', 'locale' => 'ar', 'dir' => 'rtl'),
        'tr' => array('name' => 'Türkçe', 'short' => 'TR', 'locale' => 'tr_TR', 'dir' => 'ltr'),
    );
}

/** کلید سراسری فعال‌بودن قابلیت چندزبانه؛ برای حفظ رفتار فعلی به‌صورت پیش‌فرض روشن است. */
function zigurat_multilingual_enabled()
{
    return get_option('zigurat_multilingual_enabled', '1') === '1';
}

function zigurat_current_language()
{
    static $language = null;
    if ($language !== null) {
        return $language;
    }
    if (!zigurat_multilingual_enabled()) {
        $language = 'fa';
        return $language;
    }
    $languages = zigurat_site_languages();
    $requested = isset($_GET['lang']) && is_string($_GET['lang'])
        ? sanitize_key(wp_unslash($_GET['lang']))
        : '';
    $stored = isset($_COOKIE['zigurat_lang']) && is_string($_COOKIE['zigurat_lang'])
        ? sanitize_key(wp_unslash($_COOKIE['zigurat_lang']))
        : '';
    $language = isset($languages[$requested]) ? $requested : (isset($languages[$stored]) ? $stored : 'fa');
    return $language;
}

function zigurat_store_selected_language()
{
    if (!zigurat_multilingual_enabled() || headers_sent() || empty($_GET['lang']) || !is_string($_GET['lang'])) {
        return;
    }
    $language = sanitize_key(wp_unslash($_GET['lang']));
    if (!isset(zigurat_site_languages()[$language])) {
        return;
    }
    setcookie('zigurat_lang', $language, array(
        'expires'  => time() + YEAR_IN_SECONDS,
        'path'     => '/',
        'domain'   => defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '',
        'secure'   => is_ssl(),
        'httponly' => true,
        'samesite' => 'Lax',
    ));
    $_COOKIE['zigurat_lang'] = $language;
}
add_action('init', 'zigurat_store_selected_language', 1);

function zigurat_language_direction($language = '')
{
    $language = $language ?: zigurat_current_language();
    $languages = zigurat_site_languages();
    return $languages[$language]['dir'] ?? 'rtl';
}

function zigurat_language_url($language, $url = '')
{
    $url = $url ?: home_url(add_query_arg(array(), $GLOBALS['wp']->request ?? ''));
    $url = remove_query_arg('lang', $url);
    if (!zigurat_multilingual_enabled()) {
        return $url;
    }
    $language = isset(zigurat_site_languages()[$language]) ? $language : 'fa';
    return add_query_arg('lang', $language, $url);
}

function zigurat_localized_url($url)
{
    if (!zigurat_multilingual_enabled()) {
        return remove_query_arg('lang', $url);
    }
    $language = zigurat_current_language();
    return $language === 'fa' ? remove_query_arg('lang', $url) : add_query_arg('lang', $language, $url);
}

function zigurat_translation_catalog()
{
    return array(
        'en' => array(
            'home' => 'Home', 'projects' => 'Projects', 'digital_catalog' => 'Digital Catalog', 'articles' => 'Insights', 'downloads' => 'Downloads', 'cooperation' => 'Work With Us', 'contact' => 'Contact',
            'manager_panel' => 'Managers Panel', 'open_menu' => 'Open menu', 'language' => 'Language', 'primary_menu' => 'Primary navigation',
            'hero_title' => 'Signage Design, Manufacturing and Commercial Interior Execution', 'view_projects' => 'View Projects', 'request_consultation' => 'Request a Consultation',
            'services_title' => 'Ziggurat Services', 'services_subtitle' => 'Design, manufacturing and installation of professional signage', 'view_service_projects' => 'View %s projects',
            'latest_projects' => 'Latest Projects', 'latest_projects_subtitle' => 'A selection of projects delivered by Ziggurat', 'no_projects' => 'No projects have been published yet.',
            'read_more' => 'Read More', 'latest_articles' => 'Latest Insights', 'latest_articles_subtitle' => 'News, ideas and specialist content from Ziggurat', 'continue_reading' => 'Continue Reading', 'no_articles' => 'No articles are available yet.',
            'clients_title' => 'Selected Clients', 'clients_subtitle' => 'The trust of established brands is our greatest asset.',
            'activity_eyebrow' => 'Projects Across Iran', 'activity_title' => 'Ziggurat Activity Map', 'activity_text' => 'Highlighted provinces show where Ziggurat projects have been delivered. Select a province to view its projects.', 'active_provinces' => '%s active provinces', 'map_hint' => 'Move over the map to view the number of projects.', 'has_projects' => 'With projects', 'no_registered_projects' => 'No registered projects', 'projects_registered' => '%s registered projects; select to view.', 'province_empty' => 'No project has been registered for this province yet.',
            'footer_about' => 'Ziggurat designs, manufactures and installs professional signage and commercial interiors.', 'our_services' => 'Our Services', 'quick_access' => 'Quick Access', 'about_us' => 'About Us', 'contact_us' => 'Contact Us', 'view_location' => 'View location on map', 'rights' => 'Ziggurat — All rights reserved.',
        ),
        'ar' => array(
            'home' => 'الرئيسية', 'projects' => 'المشاريع', 'digital_catalog' => 'الكتالوج الرقمي', 'articles' => 'المقالات', 'downloads' => 'التنزيلات', 'cooperation' => 'التعاون معنا', 'contact' => 'اتصل بنا',
            'manager_panel' => 'لوحة المديرين', 'open_menu' => 'فتح القائمة', 'language' => 'اللغة', 'primary_menu' => 'القائمة الرئيسية',
            'hero_title' => 'تصميم وتصنيع وتنفيذ اللوحات الإعلانية والديكور التجاري', 'view_projects' => 'مشاهدة المشاريع', 'request_consultation' => 'طلب استشارة',
            'services_title' => 'خدمات زيگورات', 'services_subtitle' => 'تصميم وتصنيع وتركيب اللوحات الإعلانية باحتراف', 'view_service_projects' => 'مشاهدة مشاريع %s',
            'latest_projects' => 'أحدث المشاريع', 'latest_projects_subtitle' => 'مجموعة من المشاريع التي نفذتها زيگورات', 'no_projects' => 'لم تُنشر مشاريع بعد.',
            'read_more' => 'اقرأ المزيد', 'latest_articles' => 'أحدث المقالات', 'latest_articles_subtitle' => 'أخبار وأفكار ومحتوى متخصص من زيگورات', 'continue_reading' => 'متابعة القراءة', 'no_articles' => 'لا توجد مقالات للعرض.',
            'clients_title' => 'بعض عملائنا', 'clients_subtitle' => 'ثقة العلامات التجارية المرموقة هي أهم رصيد لنا.',
            'activity_eyebrow' => 'مشاريع في أنحاء إيران', 'activity_title' => 'نطاق عمل زيگورات', 'activity_text' => 'توضح المحافظات المميزة أماكن تنفيذ مشاريع زيگورات. اختر محافظة لمشاهدة مشاريعها.', 'active_provinces' => '%s محافظة نشطة', 'map_hint' => 'حرّك المؤشر فوق الخريطة لمعرفة عدد المشاريع.', 'has_projects' => 'توجد مشاريع', 'no_registered_projects' => 'لا توجد مشاريع مسجلة', 'projects_registered' => '%s مشروع مسجل؛ اختر للمشاهدة.', 'province_empty' => 'لم يُسجل مشروع في هذه المحافظة بعد.',
            'footer_about' => 'زيگورات متخصصة في تصميم وتصنيع وتنفيذ اللوحات الإعلانية والديكور التجاري باحتراف.', 'our_services' => 'خدماتنا', 'quick_access' => 'روابط سريعة', 'about_us' => 'من نحن', 'contact_us' => 'تواصل معنا', 'view_location' => 'عرض الموقع على الخريطة', 'rights' => 'زيگورات — جميع الحقوق محفوظة.',
        ),
        'tr' => array(
            'home' => 'Ana Sayfa', 'projects' => 'Projeler', 'digital_catalog' => 'Dijital Katalog', 'articles' => 'Yazılar', 'downloads' => 'İndirmeler', 'cooperation' => 'Bizimle Çalışın', 'contact' => 'İletişim',
            'manager_panel' => 'Yönetici Paneli', 'open_menu' => 'Menüyü aç', 'language' => 'Dil', 'primary_menu' => 'Ana menü',
            'hero_title' => 'Tabela Tasarımı, Üretimi ve Ticari Dekorasyon Uygulamaları', 'view_projects' => 'Projeleri Gör', 'request_consultation' => 'Danışmanlık İste',
            'services_title' => 'Ziggurat Hizmetleri', 'services_subtitle' => 'Profesyonel tabela tasarımı, üretimi ve montajı', 'view_service_projects' => '%s projelerini görüntüle',
            'latest_projects' => 'Son Projeler', 'latest_projects_subtitle' => 'Ziggurat tarafından tamamlanan projelerden bir seçki', 'no_projects' => 'Henüz proje yayınlanmadı.',
            'read_more' => 'Devamını Oku', 'latest_articles' => 'Son Yazılar', 'latest_articles_subtitle' => 'Ziggurat’tan haberler, fikirler ve uzman içerikleri', 'continue_reading' => 'Okumaya Devam Et', 'no_articles' => 'Gösterilecek yazı bulunmuyor.',
            'clients_title' => 'Bazı Müşterilerimiz', 'clients_subtitle' => 'Saygın markaların güveni en büyük değerimizdir.',
            'activity_eyebrow' => 'İran Genelindeki Projeler', 'activity_title' => 'Ziggurat Faaliyet Alanı', 'activity_text' => 'Vurgulanan eyaletler Ziggurat projelerinin uygulandığı yerleri gösterir. Projeleri görmek için bir eyalet seçin.', 'active_provinces' => '%s aktif eyalet', 'map_hint' => 'Proje sayısını görmek için haritanın üzerine gelin.', 'has_projects' => 'Proje mevcut', 'no_registered_projects' => 'Kayıtlı proje yok', 'projects_registered' => '%s kayıtlı proje; görüntülemek için seçin.', 'province_empty' => 'Bu eyalet için henüz proje kaydedilmedi.',
            'footer_about' => 'Ziggurat; profesyonel tabela ve ticari iç mekân tasarımı, üretimi ve uygulaması yapar.', 'our_services' => 'Hizmetlerimiz', 'quick_access' => 'Hızlı Erişim', 'about_us' => 'Hakkımızda', 'contact_us' => 'İletişim', 'view_location' => 'Haritada konumu gör', 'rights' => 'Ziggurat — Tüm hakları saklıdır.',
        ),
    );
}

function zigurat_t($key, $arguments = array())
{
    $persian = array(
        'home' => 'خانه', 'projects' => 'پروژه‌ها', 'digital_catalog' => 'کاتالوگ دیجیتال', 'articles' => 'مطالب', 'downloads' => 'دانلودها', 'cooperation' => 'همکاری با ما', 'contact' => 'تماس با ما',
        'manager_panel' => 'پنل مدیران', 'open_menu' => 'باز کردن منو', 'language' => 'زبان', 'primary_menu' => 'منوی اصلی', 'hero_title' => 'طراحی و اجرای تابلوهای تبلیغاتی و دکوراسیون تجاری', 'view_projects' => 'مشاهده پروژه‌ها', 'request_consultation' => 'درخواست مشاوره',
        'services_title' => 'خدمات زیگورات', 'services_subtitle' => 'طراحی، ساخت و اجرای تابلوهای تبلیغاتی', 'view_service_projects' => 'مشاهده پروژه‌های %s', 'latest_projects' => 'آخرین پروژه‌ها', 'latest_projects_subtitle' => 'بخشی از پروژه‌های اجرا شده توسط زیگورات', 'no_projects' => 'هنوز پروژه‌ای ثبت نشده است.',
        'read_more' => 'بیشتر بخوانید', 'latest_articles' => 'آخرین مطالب', 'latest_articles_subtitle' => 'جدیدترین اخبار، ایده‌ها و مطالب تخصصی زیگورات', 'continue_reading' => 'ادامه مطلب', 'no_articles' => 'مطلبی برای نمایش وجود ندارد.',
        'clients_title' => 'برخی از مشتریان ما', 'clients_subtitle' => 'اعتماد برندهای معتبر، بزرگ‌ترین سرمایه ماست.', 'activity_eyebrow' => 'پروژه‌ها در سراسر ایران', 'activity_title' => 'گستره فعالیت زیگورات', 'activity_text' => 'استان‌های طلایی محل اجرای پروژه‌های ثبت‌شده زیگورات هستند. روی هر استان فعال بزنید تا پروژه‌های همان استان را ببینید.', 'active_provinces' => '%s استان فعال', 'map_hint' => 'برای مشاهده تعداد پروژه، نشانگر را روی نقشه ببرید.', 'has_projects' => 'دارای پروژه', 'no_registered_projects' => 'بدون پروژه ثبت‌شده', 'projects_registered' => '%s پروژه ثبت‌شده؛ برای مشاهده کلیک کنید.', 'province_empty' => 'هنوز پروژه‌ای برای این استان ثبت نشده است.',
        'footer_about' => 'زیگورات؛ طراحی، ساخت و اجرای تابلوهای تبلیغاتی و دکوراسیون تجاری با اجرای حرفه‌ای.', 'our_services' => 'خدمات ما', 'quick_access' => 'دسترسی سریع', 'about_us' => 'درباره ما', 'contact_us' => 'ارتباط با ما', 'view_location' => 'مشاهده لوکیشن روی نقشه', 'rights' => 'زیگورات - تمامی حقوق محفوظ است',
    );
    $language = zigurat_current_language();
    $catalog = zigurat_translation_catalog();
    $text = $language === 'fa' ? ($persian[$key] ?? $key) : ($catalog[$language][$key] ?? ($persian[$key] ?? $key));
    return $arguments ? vsprintf($text, $arguments) : $text;
}

function zigurat_filter_language_attributes($output)
{
    if (is_admin()) {
        return $output;
    }
    $language = zigurat_current_language();
    $output = preg_replace('/lang=("|\')[^"\']+("|\')/i', 'lang="' . esc_attr($language) . '"', $output);
    if (preg_match('/dir=("|\')[^"\']+("|\')/i', $output)) {
        $output = preg_replace('/dir=("|\')[^"\']+("|\')/i', 'dir="' . esc_attr(zigurat_language_direction($language)) . '"', $output);
    } else {
        $output .= ' dir="' . esc_attr(zigurat_language_direction($language)) . '"';
    }
    return $output;
}
add_filter('language_attributes', 'zigurat_filter_language_attributes');

function zigurat_language_body_classes($classes)
{
    $language = zigurat_current_language();
    $classes[] = 'site-language-' . $language;
    $classes[] = zigurat_language_direction($language) === 'ltr' ? 'site-direction-ltr' : 'site-direction-rtl';
    return $classes;
}
add_filter('body_class', 'zigurat_language_body_classes');

function zigurat_translate_menu_items($items)
{
    $translations = array('خانه' => 'home', 'پروژه‌ها' => 'projects', 'کاتالوگ دیجیتال' => 'digital_catalog', 'مطالب' => 'articles', 'دانلودها' => 'downloads', 'همکاری با ما' => 'cooperation', 'تماس با ما' => 'contact');
    foreach ($items as $item) {
        $plain_title = trim(wp_strip_all_tags($item->title));
        if (isset($translations[$plain_title])) {
            $item->title = zigurat_t($translations[$plain_title]);
        }
        if (!empty($item->url)) {
            $item->url = zigurat_localized_url($item->url);
        }
    }
    return $items;
}
add_filter('wp_nav_menu_objects', 'zigurat_translate_menu_items');

function zigurat_localize_public_link($url)
{
    if (is_admin() || zigurat_current_language() === 'fa') {
        return $url;
    }
    return zigurat_localized_url($url);
}
add_filter('page_link', 'zigurat_localize_public_link');
add_filter('post_link', 'zigurat_localize_public_link');
add_filter('post_type_link', 'zigurat_localize_public_link');
add_filter('post_type_archive_link', 'zigurat_localize_public_link');
add_filter('term_link', 'zigurat_localize_public_link');

function zigurat_render_hreflang_links()
{
    if (!zigurat_multilingual_enabled() || is_admin() || is_page('login') || is_page_template('page-login.php')) {
        return;
    }
    $scheme = is_ssl() ? 'https://' : 'http://';
    $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
    $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
    if ($host === '') {
        return;
    }
    $current_url = $scheme . $host . $uri;
    foreach (zigurat_site_languages() as $code => $language) {
        printf("<link rel=\"alternate\" hreflang=\"%s\" href=\"%s\">\n", esc_attr(str_replace('_', '-', $language['locale'])), esc_url(zigurat_language_url($code, $current_url)));
    }
    printf("<link rel=\"alternate\" hreflang=\"x-default\" href=\"%s\">\n", esc_url(zigurat_language_url('fa', $current_url)));
}
add_action('wp_head', 'zigurat_render_hreflang_links', 3);

function zigurat_render_language_switcher()
{
    if (!zigurat_multilingual_enabled()) {
        return;
    }
    $current = zigurat_current_language();
    $languages = zigurat_site_languages();
    ?>
    <details class="site-language-switcher">
        <summary aria-label="<?php echo esc_attr(zigurat_t('language')); ?>"><span><?php echo esc_html($languages[$current]['short']); ?></span></summary>
        <ul>
            <?php foreach ($languages as $code => $language): ?>
                <li><a href="<?php echo esc_url(zigurat_language_url($code)); ?>" lang="<?php echo esc_attr($code); ?>" dir="<?php echo esc_attr($language['dir']); ?>"<?php echo $code === $current ? ' aria-current="true"' : ''; ?>><?php echo esc_html($language['name']); ?></a></li>
            <?php endforeach; ?>
        </ul>
    </details>
    <?php
}

function zigurat_translated_post_value($post_id, $field, $fallback)
{
    $language = zigurat_current_language();
    if ($language === 'fa' || !$post_id) {
        return $fallback;
    }
    $translated = get_post_meta($post_id, '_zigurat_' . $field . '_' . $language, true);
    return trim((string) $translated) !== '' ? $translated : $fallback;
}

function zigurat_translate_post_title($title, $post_id = 0)
{
    return is_admin() ? $title : zigurat_translated_post_value($post_id, 'title', $title);
}
add_filter('the_title', 'zigurat_translate_post_title', 20, 2);

function zigurat_translate_post_excerpt($excerpt, $post = null)
{
    $post_id = is_object($post) ? $post->ID : get_the_ID();
    return is_admin() ? $excerpt : zigurat_translated_post_value($post_id, 'excerpt', $excerpt);
}
add_filter('get_the_excerpt', 'zigurat_translate_post_excerpt', 20, 2);

function zigurat_translate_post_content($content)
{
    return is_admin() ? $content : zigurat_translated_post_value(get_the_ID(), 'content', $content);
}
add_filter('the_content', 'zigurat_translate_post_content', 20);

