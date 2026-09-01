<?php
/*
Template Name: Manager Panel
*/
if (!defined('ABSPATH')) {
    exit;
}

$login_error = '';
$manager_section = isset($_REQUEST['manager-section']) && is_string($_REQUEST['manager-section'])
    ? sanitize_key(wp_unslash($_REQUEST['manager-section']))
    : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['zigurat_manager_login'])) {
    $remote_address = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
    $rate_key = 'zigurat_login_' . md5($remote_address);
    $attempts = (int) get_transient($rate_key);

    if ($attempts >= 5) {
        $login_error = 'تعداد تلاش‌ها بیش از حد مجاز است. لطفاً ۱۵ دقیقه دیگر دوباره تلاش کنید.';
    } elseif (
        !isset($_POST['zigurat_manager_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['zigurat_manager_nonce'])), 'zigurat_manager_login')
    ) {
        $login_error = 'درخواست ورود معتبر نیست. صفحه را تازه‌سازی و دوباره تلاش کنید.';
    } else {
        $credentials = array(
            'user_login'    => isset($_POST['log']) ? sanitize_user(wp_unslash($_POST['log'])) : '',
            'user_password' => isset($_POST['pwd']) ? (string) wp_unslash($_POST['pwd']) : '',
            'remember'      => !empty($_POST['rememberme']),
        );
        $user = wp_signon($credentials, is_ssl());

        if (is_wp_error($user) || !zigurat_user_can_access_manager_panel($user)) {
            if (!is_wp_error($user)) {
                wp_logout();
            }
            set_transient($rate_key, $attempts + 1, 15 * MINUTE_IN_SECONDS);
            $login_error = 'نام کاربری یا رمز عبور صحیح نیست، یا این حساب مجوز ورود به پنل را ندارد.';
        } else {
            delete_transient($rate_key);
            wp_safe_redirect(home_url('/login/'));
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['zigurat_save_lightbox_rates']) && zigurat_is_manager()) {
    $pricing_nonce = isset($_POST['zigurat_pricing_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['zigurat_pricing_nonce']))
        : '';
    $pricing_status = 'invalid';
    if (wp_verify_nonce($pricing_nonce, 'zigurat_save_lightbox_rates')) {
        $pricing_result = zigurat_save_lightbox_pricing_settings($_POST);
        $pricing_status = is_wp_error($pricing_result) ? $pricing_result->get_error_code() : 'saved';
    }
    wp_safe_redirect(add_query_arg(array(
        'manager-section' => 'pricing',
        'calculator' => 'lightbox',
        'pricing-status' => sanitize_key($pricing_status),
    ), zigurat_manager_login_url()));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['zigurat_save_composite_rates']) && zigurat_is_manager()) {
    $pricing_nonce = isset($_POST['zigurat_pricing_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['zigurat_pricing_nonce']))
        : '';
    $pricing_status = 'invalid';
    if (wp_verify_nonce($pricing_nonce, 'zigurat_save_composite_rates')) {
        $pricing_result = zigurat_save_composite_pricing_settings($_POST);
        $pricing_status = is_wp_error($pricing_result) ? $pricing_result->get_error_code() : 'saved';
    }
    wp_safe_redirect(add_query_arg(array(
        'manager-section' => 'pricing',
        'calculator' => 'composite',
        'pricing-status' => sanitize_key($pricing_status),
    ), zigurat_manager_login_url()));
    exit;
}

get_header();
?>
<main class="manager-area">
    <div class="container">
        <?php if (zigurat_is_manager()): ?>
            <?php
            $current_manager = wp_get_current_user();
            $current_manager_name = trim((string) $current_manager->display_name);
            if ($current_manager_name === '') {
                $current_manager_name = $current_manager->user_login;
            }
            $manager_unread_applications = function_exists('zigurat_application_unread_count')
                ? zigurat_application_unread_count($current_manager->ID)
                : 0;
            ?>
            <section class="manager-panel" aria-labelledby="manager-panel-title">
                <div class="manager-panel__header">
                    <div>
                        <span>دسترسی مدیریت</span>
                        <h1 id="manager-panel-title">پنل مدیران</h1>
                    </div>
                    <div class="manager-panel__account">
                        <?php if ($manager_unread_applications): ?>
                            <a class="manager-panel__notification" href="<?php echo esc_url(add_query_arg('manager-section', 'applications', zigurat_manager_login_url())); ?>" aria-label="<?php echo esc_attr($manager_unread_applications . ' درخواست همکاری جدید'); ?>">
                                <span aria-hidden="true">🔔</span>
                                <strong><?php echo esc_html(number_format_i18n($manager_unread_applications)); ?> درخواست جدید</strong>
                            </a>
                        <?php endif; ?>
                        <div class="manager-panel__user">
                            <span>کاربر واردشده</span>
                            <strong><?php echo esc_html($current_manager_name); ?></strong>
                        </div>
                        <a class="manager-logout" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">خروج امن</a>
                    </div>
                </div>
                <?php
                if ($manager_section === 'applications') {
                    get_template_part('template-parts/manager-applications');
                } elseif ($manager_section === 'application-detail') {
                    get_template_part('template-parts/manager-application-resume');
                } elseif ($manager_section === 'views') {
                    get_template_part('template-parts/manager-views');
                } elseif ($manager_section === 'pricing') {
                    get_template_part('template-parts/manager-pricing');
                } else {
                    get_template_part('page-main');
                }
                ?>
            </section>
        <?php else: ?>
            <section class="manager-login" aria-labelledby="manager-login-title">
                <h1 id="manager-login-title">ورود به پنل مدیران</h1>
                <p>برای ادامه از حساب کاربری مدیر یا مشارکت‌کننده وردپرس استفاده کنید.</p>
                <?php if ($login_error): ?>
                    <div class="manager-message manager-message--error" role="alert"><?php echo esc_html($login_error); ?></div>
                <?php endif; ?>
                <form method="post" autocomplete="on">
                    <?php wp_nonce_field('zigurat_manager_login', 'zigurat_manager_nonce'); ?>
                    <label for="user_login">نام کاربری</label>
                    <input type="text" name="log" id="user_login" autocomplete="username" required>
                    <label for="user_pass">رمز عبور</label>
                    <div class="manager-password-field">
                        <input type="password" name="pwd" id="user_pass" autocomplete="current-password" required>
                        <button class="manager-password-toggle" type="button" aria-controls="user_pass" aria-pressed="false" aria-label="نمایش رمز عبور">
                            <svg class="manager-password-toggle__show" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="M2.2 12s3.5-6 9.8-6 9.8 6 9.8 6-3.5 6-9.8 6-9.8-6-9.8-6Z" fill="none" stroke="currentColor" stroke-width="1.8"/>
                                <circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/>
                            </svg>
                            <svg class="manager-password-toggle__hide" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="M3 3l18 18M10.6 6.2A10 10 0 0 1 12 6c6.3 0 9.8 6 9.8 6a16 16 0 0 1-3 3.7M6.2 6.2C3.6 8 2.2 12 2.2 12s3.5 6 9.8 6a10 10 0 0 0 3-.4M9.9 9.9a3 3 0 0 0 4.2 4.2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </div>
                    <label class="manager-remember">
                        <input type="checkbox" name="rememberme" value="1">
                        مرا به خاطر بسپار
                    </label>
                    <input type="hidden" name="zigurat_manager_login" value="1">
                    <button type="submit">ورود امن</button>
                </form>
                <a class="manager-lost-password" href="<?php echo esc_url(wp_lostpassword_url(home_url('/login/'))); ?>">رمز عبور را فراموش کرده‌اید؟</a>
            </section>
        <?php endif; ?>
    </div>
</main>
<?php get_footer(); ?>
