<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <!-- درباره شرکت -->
            <div class="footer-column footer-about">
                <div class="footer-logo">
                    <?php
                    if (has_custom_logo()) {
                        the_custom_logo();
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
                    <li>
                        <a href="#">
                            حروف برجسته
                        </a>
                    </li>
                    <li>
                        <a href="#">
                            تابلو کامپوزیت
                        </a>
                    </li>
                    <li>
                        <a href="#">
                            دکوراسیون داخلی
                        </a>
                    </li>
                    <li>
                        <a href="#">
                            طراحی و اجرا
                        </a>
                    </li>
                </ul>
            </div>
            <!-- لینک ها -->
            <div class="footer-column">
                <h3>
                    دسترسی سریع
                </h3>
                <ul>
                    <li>
                        <a href="<?php echo home_url(); ?>">
                            خانه
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo home_url('/projects'); ?>">
                            پروژه‌ها
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo home_url('/about'); ?>">
                            درباره ما
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo home_url('/contact'); ?>">
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
                    <li>
                        <a href="tel:09125606941">
                            📞 09125606941
                        </a>
                    </li>
                    <li>
                        📍 تهران، ایران
                    </li>
                    <li>
                        <a href="mailto:zigguratcorporation@gmail.com">
                            ✉ zigguratcorporation@gmail.com
                        </a>
                    </li>
                </ul>
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
