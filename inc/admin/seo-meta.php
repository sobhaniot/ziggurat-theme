<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Expose the theme's SEO fields to authenticated REST clients such as n8n.
 * The capability check keeps these protected meta keys private from writers
 * who cannot edit the corresponding post.
 */
function zigurat_register_seo_rest_meta()
{
    foreach (array('page', 'project', 'article') as $post_type) {
        foreach (array('_zigurat_seo_title', '_zigurat_seo_description') as $meta_key) {
            register_post_meta($post_type, $meta_key, array(
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback'     => function ($allowed, $key, $post_id) {
                    return current_user_can('edit_post', $post_id);
                },
            ));
        }
    }
    foreach (array('project', 'article') as $post_type) {
        foreach (array(
            '_zigurat_seo_focus_keyword'    => 'sanitize_text_field',
            '_zigurat_seo_related_keywords' => 'sanitize_textarea_field',
            '_zigurat_seo_image_alt'        => 'sanitize_text_field',
        ) as $meta_key => $sanitize_callback) {
            register_post_meta($post_type, $meta_key, array(
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => $sanitize_callback,
                'auth_callback'     => function ($allowed, $key, $post_id) {
                    return current_user_can('edit_post', $post_id);
                },
            ));
        }
    }
}
add_action('init', 'zigurat_register_seo_rest_meta');

function zigurat_add_seo_meta_box()
{
    foreach (array('page', 'project', 'article') as $post_type) {
        add_meta_box('zigurat-seo-settings', 'تنظیمات سئو', 'zigurat_render_seo_meta_box', $post_type, 'normal', 'default');
    }
}
add_action('add_meta_boxes', 'zigurat_add_seo_meta_box');

function zigurat_project_seo_suggestions($post_id)
{
    $project_title = trim((string) get_the_title($post_id));
    $client = trim((string) get_post_meta($post_id, '_project_client', true));
    $city = trim((string) get_post_meta($post_id, '_project_city', true));
    $type = trim((string) get_post_meta($post_id, '_project_type', true));
    $activity = $type;
    if ($activity !== '' && !preg_match('/^(اجرا|اجرای|طراحی|ساخت|چاپ|بازسازی|نورپردازی)/u', $activity)) {
        $activity = 'اجرای ' . $activity;
    }
    $title_parts = array_filter(array($activity ?: $project_title, $client));
    $suggested_title = trim(implode(' ', $title_parts));
    if ($city !== '') {
        $suggested_title .= ' در ' . $city;
    }
    if ($suggested_title !== '') {
        $suggested_title .= ' | زیگورات';
        $suggested_title = wp_html_excerpt($suggested_title, 70, '…');
    }
    $focus_keyword = trim(($activity ?: $project_title) . ($city !== '' ? ' در ' . $city : ''));
    $subject = $client !== '' ? 'پروژه ' . $client : ($project_title !== '' ? 'پروژه ' . $project_title : 'این پروژه');
    $description = 'طراحی و اجرای ' . $subject;
    if ($city !== '') {
        $description .= ' در ' . $city;
    }
    if ($type !== '') {
        $description .= ' در زمینه ' . $type;
    }
    $description .= ' توسط زیگورات. تصاویر، مشخصات و جزئیات اجرای پروژه را مشاهده کنید.';
    $image_alt = trim(($activity ?: 'اجرای پروژه') . ($client !== '' ? ' ' . $client : '') . ($city !== '' ? ' در ' . $city : ''));
    return array(
        'title'       => $suggested_title,
        'description' => zigurat_seo_trim_description($description),
        'focus'       => $focus_keyword,
        'image_alt'   => $image_alt,
    );
}

function zigurat_article_seo_suggestions($post_id)
{
    $article_title = trim((string) get_the_title($post_id));
    $seo_title = $article_title !== '' ? wp_html_excerpt($article_title . ' | زیگورات', 70, '…') : '';
    $excerpt = trim((string) get_post_field('post_excerpt', $post_id));
    $content = trim((string) get_post_field('post_content', $post_id));
    $description = zigurat_seo_trim_description($excerpt ?: $content);
    if ($description === '' && $article_title !== '') {
        $description = zigurat_seo_trim_description('در این مطلب از زیگورات با ' . $article_title . '، نکات کاربردی و اطلاعات تخصصی مرتبط آشنا شوید.');
    }
    $categories = wp_get_post_terms($post_id, 'article_category', array('fields'=>'names'));
    $focus_keyword = $article_title;
    if (!$focus_keyword && !is_wp_error($categories) && $categories) {
        $focus_keyword = (string) reset($categories);
    }
    return array(
        'title'       => $seo_title,
        'description' => $description,
        'focus'       => $focus_keyword,
        'image_alt'   => $article_title,
    );
}

function zigurat_render_seo_meta_box($post)
{
    wp_nonce_field('zigurat_save_seo_meta', 'zigurat_seo_nonce');
    $title = get_post_meta($post->ID, '_zigurat_seo_title', true);
    $description = get_post_meta($post->ID, '_zigurat_seo_description', true);
    $is_project = $post->post_type === 'project';
    $is_article = $post->post_type === 'article';
    $is_extended = $is_project || $is_article;
    $suggestions = $is_project ? zigurat_project_seo_suggestions($post->ID) : ($is_article ? zigurat_article_seo_suggestions($post->ID) : array());
    $focus_keyword = $is_extended ? get_post_meta($post->ID, '_zigurat_seo_focus_keyword', true) : '';
    $related_keywords = $is_extended ? get_post_meta($post->ID, '_zigurat_seo_related_keywords', true) : '';
    $image_alt = $is_extended ? get_post_meta($post->ID, '_zigurat_seo_image_alt', true) : '';
    $slug = $is_extended ? (string) $post->post_name : '';
    $base_url = $is_project
        ? home_url('/projects/')
        : ($is_article ? (get_post_type_archive_link('article') ?: home_url('/articles/')) : '');
    ?>
    <div class="zigurat-seo-editor <?php echo $is_extended ? 'is-' . esc_attr($post->post_type) : ''; ?>"<?php if ($is_extended): ?> data-content-type="<?php echo esc_attr($post->post_type); ?>" data-suggested-title="<?php echo esc_attr($suggestions['title']); ?>" data-suggested-description="<?php echo esc_attr($suggestions['description']); ?>" data-suggested-focus="<?php echo esc_attr($suggestions['focus']); ?>" data-suggested-alt="<?php echo esc_attr($suggestions['image_alt']); ?>" data-content-base-url="<?php echo esc_attr(trailingslashit($base_url)); ?>"<?php endif; ?>>
        <?php if ($is_extended): ?>
            <div class="zigurat-seo-intro"><div><strong><?php echo $is_project ? 'سئوی پروژه' : 'سئوی مطلب'; ?></strong><p><?php echo $is_project ? 'پیشنهادها از عنوان پروژه، کارفرما، شهر و نوع اجرا ساخته می‌شوند.' : 'پیشنهادها از عنوان، خلاصه و محتوای مطلب ساخته می‌شوند.'; ?> همه مقادیر قابل ویرایش هستند.</p></div><button type="button" class="button button-primary" data-zigurat-seo-generate>ساخت پیشنهاد خودکار</button></div>
            <div class="zigurat-seo-grid">
                <label><strong>عبارت کلیدی اصلی</strong><input id="zigurat-seo-focus" name="zigurat_seo_focus_keyword" type="text" class="widefat" value="<?php echo esc_attr($focus_keyword); ?>" placeholder="<?php echo esc_attr($is_project ? 'مثلاً اجرای تابلو کامپوزیت در لاهیجان' : 'مثلاً راهنمای انتخاب تابلو فروشگاهی'); ?>"><small>فقط راهنمای نگارش است و به‌صورت meta keywords منتشر نمی‌شود.</small></label>
                <label><strong>نامک انگلیسی صفحه</strong><span class="zigurat-seo-slug-control"><span><?php echo esc_html(trailingslashit($base_url)); ?></span><input id="zigurat-seo-slug" name="zigurat_seo_slug" type="text" dir="ltr" inputmode="url" value="<?php echo esc_attr($slug); ?>" placeholder="<?php echo esc_attr($is_project ? 'xpoint-lahijan-composite-sign' : 'store-sign-buying-guide'); ?>"></span><small>فقط حروف انگلیسی کوچک، عدد و خط تیره؛ پس از انتشار بی‌دلیل تغییر ندهید.</small></label>
                <label class="is-full"><strong>عبارت‌های مرتبط</strong><textarea id="zigurat-seo-related" name="zigurat_seo_related_keywords" class="widefat" rows="3" placeholder="<?php echo esc_attr($is_project ? 'تابلو سازی در لاهیجان، حروف برجسته، تابلو فروشگاهی' : 'تابلو سردر، حروف برجسته، طراحی تابلو تجاری'); ?>"><?php echo esc_textarea($related_keywords); ?></textarea><small>برای استفاده طبیعی در متن <?php echo $is_project ? 'پروژه' : 'مطلب'; ?>؛ این فهرست مستقیماً در صفحه یا متادیتا نمایش داده نمی‌شود.</small></label>
            </div>
        <?php endif; ?>
        <div class="zigurat-seo-grid">
            <label class="is-full"><strong>عنوان سئو</strong><input id="zigurat-seo-title" name="zigurat_seo_title" type="text" class="widefat" maxlength="70" value="<?php echo esc_attr($title); ?>" placeholder="اگر خالی باشد، عنوان استاندارد وردپرس استفاده می‌شود."><small><span data-seo-title-count>۰</span> نویسه؛ عنوانی روشن، منحصربه‌فرد و کوتاه بنویسید.</small></label>
            <label class="is-full"><strong>توضیحات متا</strong><textarea id="zigurat-seo-description" name="zigurat_seo_description" class="widefat" rows="3" maxlength="170" placeholder="خلاصه‌ای روشن و ترغیب‌کننده از محتوای صفحه"><?php echo esc_textarea($description); ?></textarea><small><span data-seo-description-count>۰</span> نویسه؛ حدود ۱۴۰ تا ۱۶۰ نویسه معمولاً مناسب است.</small></label>
            <?php if ($is_extended): ?><label class="is-full"><strong>متن جایگزین تصویر شاخص</strong><input id="zigurat-seo-image-alt" name="zigurat_seo_image_alt" type="text" class="widefat" value="<?php echo esc_attr($image_alt); ?>" placeholder="توصیف واقعی تصویر شاخص <?php echo $is_project ? 'پروژه' : 'مطلب'; ?>"><small>تصویر را توصیف کنید؛ عبارت کلیدی را فقط در صورت مرتبط‌بودن به تصویر استفاده کنید.</small></label><?php endif; ?>
        </div>
        <?php if ($is_extended): ?>
            <section class="zigurat-google-preview" aria-label="پیش‌نمایش تقریبی نتیجه گوگل"><span>پیش‌نمایش تقریبی نتیجه گوگل</span><cite data-seo-preview-url><?php echo esc_html(trailingslashit($base_url) . ($slug ?: ($is_project ? 'project-slug' : 'article-slug')) . '/'); ?></cite><strong data-seo-preview-title><?php echo esc_html($title ?: ($suggestions['title'] ?: get_the_title($post))); ?></strong><p data-seo-preview-description><?php echo esc_html($description ?: $suggestions['description']); ?></p></section>
            <p class="zigurat-seo-taxonomy-note"><?php echo $is_project ? 'برای دسته‌بندی پروژه از فیلدهای کارفرما، شهر، استان، نوع اجرا و باکس‌های «خدمات» و «متریال» استفاده کنید؛ نیازی به برچسب تکراری نیست.' : 'موضوع اصلی را در «دسته‌بندی مطالب» و عبارت‌های جزئی‌تر را در «برچسب‌های مطالب» ثبت کنید؛ برچسب‌های زیاد و تکراری نسازید.'; ?></p>
        <?php endif; ?>
    </div>
    <?php
}

function zigurat_save_seo_meta($post_id)
{
    if (
        !isset($_POST['zigurat_seo_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['zigurat_seo_nonce'])), 'zigurat_save_seo_meta')
        || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        || wp_is_post_revision($post_id)
        || !current_user_can('edit_post', $post_id)
    ) {
        return;
    }
    foreach (array('title', 'description') as $key) {
        $field = 'zigurat_seo_' . $key;
        $value = isset($_POST[$field]) ? sanitize_text_field(wp_unslash($_POST[$field])) : '';
        if ($value === '') {
            delete_post_meta($post_id, '_zigurat_seo_' . $key);
        } else {
            update_post_meta($post_id, '_zigurat_seo_' . $key, $value);
        }
    }
    if (!in_array(get_post_type($post_id), array('project', 'article'), true)) {
        return;
    }
    $content_fields = array(
        'focus_keyword'    => 'sanitize_text_field',
        'related_keywords' => 'sanitize_textarea_field',
        'image_alt'        => 'sanitize_text_field',
    );
    foreach ($content_fields as $key => $sanitize_callback) {
        $field = 'zigurat_seo_' . $key;
        $value = isset($_POST[$field]) ? call_user_func($sanitize_callback, wp_unslash($_POST[$field])) : '';
        if ($value === '') {
            delete_post_meta($post_id, '_zigurat_seo_' . $key);
        } else {
            update_post_meta($post_id, '_zigurat_seo_' . $key, $value);
        }
    }
    if (isset($_POST['zigurat_seo_slug'])) {
        $raw_slug = strtolower(trim((string) wp_unslash($_POST['zigurat_seo_slug'])));
        $slug = trim((string) preg_replace('/-+/', '-', preg_replace('/[^a-z0-9-]+/', '-', $raw_slug)), '-');
        if ($slug !== '' && $slug !== get_post_field('post_name', $post_id)) {
            remove_action('save_post', 'zigurat_save_seo_meta');
            wp_update_post(array('ID'=>$post_id, 'post_name'=>$slug));
            add_action('save_post', 'zigurat_save_seo_meta');
        }
    }
}
add_action('save_post', 'zigurat_save_seo_meta');
