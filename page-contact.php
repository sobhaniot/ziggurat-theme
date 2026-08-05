<?php
get_header();

$contact_status = isset($_GET['contact-status'])
    ? sanitize_key(wp_unslash($_GET['contact-status']))
    : '';
$contact_details = zigurat_get_contact_details();
$phone_url = preg_replace('/[^0-9+]/', '', $contact_details['phone']);
?>

<main class="contact-page">
    <?php while (have_posts()) : ?>
        <?php the_post(); ?>
        <section class="contact-hero">
            <div class="container">
                <span class="contact-eyebrow">زیگورات</span>
                <h1><?php the_title(); ?></h1>
                <div class="contact-intro">
                    <?php
                    if (get_the_content()) {
                        the_content();
                    } else {
                        echo '<p>برای مشاوره، دریافت قیمت و شروع پروژه با ما در ارتباط باشید.</p>';
                    }
                    ?>
                </div>
            </div>
        </section>

        <section class="contact-details">
            <div class="container contact-layout">
                <aside class="contact-information">
                    <h2>راه‌های ارتباطی</h2>
                    <a class="contact-detail" href="tel:<?php echo esc_attr($phone_url); ?>">
                        <span>تلفن</span>
                        <strong><?php echo esc_html($contact_details['phone']); ?></strong>
                    </a>
                    <a class="contact-detail" href="mailto:<?php echo esc_attr($contact_details['email']); ?>">
                        <span>ایمیل</span>
                        <strong><?php echo esc_html($contact_details['email']); ?></strong>
                    </a>
                    <div class="contact-detail">
                        <span>نشانی</span>
                        <strong><?php echo esc_html($contact_details['address']); ?></strong>
                    </div>
                </aside>

                <div class="contact-form-card">
                    <h2>ارسال پیام</h2>
                    <?php if ($contact_status === 'sent') : ?>
                        <p class="contact-notice success">پیام شما با موفقیت ارسال شد. به‌زودی با شما تماس می‌گیریم.</p>
                    <?php elseif ($contact_status === 'invalid') : ?>
                        <p class="contact-notice error">لطفاً نام و متن پیام را وارد کنید.</p>
                    <?php elseif ($contact_status === 'error') : ?>
                        <p class="contact-notice error">ارسال پیام انجام نشد. لطفاً دوباره تلاش کنید یا تماس بگیرید.</p>
                    <?php endif; ?>
                    <form method="post" class="contact-form">
                        <?php wp_nonce_field('zigurat_send_contact_message', 'zigurat_contact_nonce'); ?>
                        <input type="hidden" name="zigurat_contact_form" value="1">
                        <div class="contact-honeypot" aria-hidden="true">
                            <label for="website">وب‌سایت</label>
                            <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                        </div>
                        <label for="contact-name">نام و نام خانوادگی *</label>
                        <input id="contact-name" name="name" type="text" required>
                        <label for="contact-phone">شماره تماس</label>
                        <input id="contact-phone" name="phone" type="tel">
                        <label for="contact-email">ایمیل</label>
                        <input id="contact-email" name="email" type="email">
                        <label for="contact-message">پیام شما *</label>
                        <textarea id="contact-message" name="message" rows="6" required></textarea>
                        <button type="submit">ارسال پیام</button>
                    </form>
                </div>
            </div>
        </section>
    <?php endwhile; ?>
</main>

<?php get_footer(); ?>
