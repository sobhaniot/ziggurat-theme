<?php
$contact_details = zigurat_get_contact_details();
$contact_phones = zigurat_get_contact_phones($contact_details);
$location_url = zigurat_get_contact_location_url($contact_details);
?>
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <!-- درباره شرکت -->
            <div class="footer-column footer-about">
                <div class="footer-logo">
                    <?php
                    if (has_custom_logo()) {
                        zigurat_site_logo('lazy');
                    } else {
                        echo get_bloginfo('name');
                    }
                    ?>
                </div>
                <p>
                    زیگورات؛ طراحی، ساخت و اجرای
                    تابلوهای تبلیغاتی و دکوراسیون
                    تجاری با اجرای حرفه‌ای.
                </p>
            </div>
            <!-- خدمات -->
            <div class="footer-column">
                <h3>
                    خدمات ما
                </h3>
                <ul>
                    <?php
                    $footer_services = get_posts(array(
                        'post_type'      => 'service',
                        'post_status'    => 'publish',
                        'posts_per_page' => 4,
                        'orderby'        => array('menu_order' => 'ASC', 'title' => 'ASC'),
                        'no_found_rows'  => true,
                    ));
                    $services_page = get_page_by_path('services');
                    $services_url = $services_page ? get_permalink($services_page) : home_url('/services/');
                    foreach ($footer_services as $footer_service):
                    ?>
                        <li><a href="<?php echo esc_url($services_url . '#service-' . $footer_service->ID); ?>"><?php echo esc_html(get_the_title($footer_service)); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <!-- لینک ها -->
            <div class="footer-column">
                <h3>
                    دسترسی سریع
                </h3>
                <ul>
                    <li>
                        <a href="<?php echo esc_url(home_url('/')); ?>">
                            خانه
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url(get_post_type_archive_link('project')); ?>">
                            پروژه‌ها
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url(home_url('/about/')); ?>">
                            درباره ما
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url(home_url('/contact/')); ?>">
                            تماس با ما
                        </a>
                    </li>
                </ul>
            </div>
            <!-- تماس -->
            <div class="footer-column">
                <h3>
                    ارتباط با ما
                </h3>
                <ul class="footer-contact">
                    <?php foreach ($contact_phones as $phone): ?>
                        <li>
                            <a href="tel:<?php echo esc_attr(zigurat_contact_phone_url($phone)); ?>">
                                📞 <?php echo esc_html($phone); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!empty($contact_details['address'])): ?>
                        <li>
                            <a href="<?php echo esc_url($location_url); ?>" target="_blank" rel="noopener noreferrer" title="مشاهده لوکیشن روی نقشه">
                                📍 <?php echo esc_html($contact_details['address']); ?>
                            </a>
                        </li>
                    <?php elseif ($location_url): ?>
                        <li><a href="<?php echo esc_url($location_url); ?>" target="_blank" rel="noopener noreferrer">📍 مشاهده لوکیشن روی نقشه</a></li>
                    <?php endif; ?>
                    <?php if (!empty($contact_details['email'])): ?>
                        <li>
                            <a href="mailto:<?php echo esc_attr($contact_details['email']); ?>">
                                ✉ <?php echo esc_html($contact_details['email']); ?>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
                <?php zigurat_render_social_links('social-links--footer'); ?>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <p>
            © <?php echo date('Y'); ?>
            زیگورات - تمامی حقوق محفوظ است
        </p>
    </div>
</footer>
<?php wp_footer(); ?>
</body>

</html>
