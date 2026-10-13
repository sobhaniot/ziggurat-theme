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
                <p><?php echo esc_html(zigurat_t('footer_about')); ?></p>
            </div>
            <!-- خدمات -->
            <div class="footer-column">
                <h3>
                    <?php echo esc_html(zigurat_t('our_services')); ?>
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
                    <?php echo esc_html(zigurat_t('quick_access')); ?>
                </h3>
                <ul>
                    <li>
                        <a href="<?php echo esc_url(zigurat_localized_url(home_url('/'))); ?>">
                            <?php echo esc_html(zigurat_t('home')); ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url(get_post_type_archive_link('project')); ?>">
                            <?php echo esc_html(zigurat_t('projects')); ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url(zigurat_localized_url(home_url('/about/'))); ?>">
                            <?php echo esc_html(zigurat_t('about_us')); ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url(zigurat_localized_url(home_url('/contact/'))); ?>">
                            <?php echo esc_html(zigurat_t('contact')); ?>
                        </a>
                    </li>
                </ul>
            </div>
            <!-- تماس -->
            <div class="footer-column">
                <h3>
                    <?php echo esc_html(zigurat_t('contact_us')); ?>
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
                            <a href="<?php echo esc_url($location_url); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr(zigurat_t('view_location')); ?>">
                                📍 <?php echo esc_html($contact_details['address']); ?>
                            </a>
                        </li>
                    <?php elseif ($location_url): ?>
                        <li><a href="<?php echo esc_url($location_url); ?>" target="_blank" rel="noopener noreferrer">📍 <?php echo esc_html(zigurat_t('view_location')); ?></a></li>
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
            <?php echo esc_html(zigurat_t('rights')); ?>
        </p>
    </div>
</footer>
<?php wp_footer(); ?>
</body>

</html>
