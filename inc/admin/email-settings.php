<?php
if (!defined('ABSPATH')) {
    exit;
}

function zigurat_smtp_settings_defaults()
{
    $admin_email = sanitize_email((string) get_option('admin_email'));
    return array(
        'enabled'    => '1',
        'host'       => 'smtp.mail.yahoo.com',
        'port'       => 465,
        'encryption' => 'ssl',
        'auth'       => '1',
        'username'   => $admin_email,
        'password'   => '',
        'from_email' => $admin_email,
        'from_name'  => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
    );
}

function zigurat_get_smtp_settings()
{
    $saved = get_option('zigurat_smtp_settings', array());
    return wp_parse_args(is_array($saved) ? $saved : array(), zigurat_smtp_settings_defaults());
}

function zigurat_smtp_encrypt_password($password)
{
    $password = (string) $password;
    if ($password === '' || !function_exists('openssl_encrypt')) {
        return '';
    }
    $cipher = 'aes-256-cbc';
    $iv_length = openssl_cipher_iv_length($cipher);
    try {
        $iv = random_bytes($iv_length);
    } catch (Exception $exception) {
        return '';
    }
    $key = hash('sha256', wp_salt('auth'), true);
    $encrypted = openssl_encrypt($password, $cipher, $key, OPENSSL_RAW_DATA, $iv);
    return $encrypted === false ? '' : 'enc:v1:' . base64_encode($iv . $encrypted);
}

function zigurat_smtp_decrypt_password($stored)
{
    $stored = (string) $stored;
    if (strpos($stored, 'enc:v1:') !== 0) {
        return $stored;
    }
    if (!function_exists('openssl_decrypt')) {
        return '';
    }
    $payload = base64_decode(substr($stored, 7), true);
    $cipher = 'aes-256-cbc';
    $iv_length = openssl_cipher_iv_length($cipher);
    if ($payload === false || strlen($payload) <= $iv_length) {
        return '';
    }
    $iv = substr($payload, 0, $iv_length);
    $encrypted = substr($payload, $iv_length);
    $key = hash('sha256', wp_salt('auth'), true);
    $password = openssl_decrypt($encrypted, $cipher, $key, OPENSSL_RAW_DATA, $iv);
    return $password === false ? '' : $password;
}

function zigurat_sanitize_smtp_settings($input)
{
    $input = is_array($input) ? $input : array();
    $current = zigurat_get_smtp_settings();
    $encryption = isset($input['encryption']) ? sanitize_key($input['encryption']) : 'ssl';
    if (!in_array($encryption, array('ssl', 'tls', 'none'), true)) {
        $encryption = 'ssl';
    }
    $port = isset($input['port']) ? absint($input['port']) : 465;
    if ($port < 1 || $port > 65535) {
        $port = 465;
    }
    $password = isset($input['password']) && is_string($input['password'])
        ? wp_unslash((string) $input['password'])
        : '';
    $stored_password = $password !== ''
        ? zigurat_smtp_encrypt_password($password)
        : (string) $current['password'];

    return array(
        'enabled'    => !empty($input['enabled']) ? '1' : '0',
        'host'       => sanitize_text_field($input['host'] ?? ''),
        'port'       => $port,
        'encryption' => $encryption,
        'auth'       => !empty($input['auth']) ? '1' : '0',
        'username'   => sanitize_text_field($input['username'] ?? ''),
        'password'   => $stored_password,
        'from_email' => sanitize_email($input['from_email'] ?? ''),
        'from_name'  => sanitize_text_field($input['from_name'] ?? ''),
    );
}

function zigurat_register_smtp_settings()
{
    register_setting(
        'zigurat_smtp_settings_group',
        'zigurat_smtp_settings',
        array('sanitize_callback' => 'zigurat_sanitize_smtp_settings')
    );
}
add_action('admin_init', 'zigurat_register_smtp_settings');

function zigurat_add_smtp_settings_page()
{
    add_options_page(
        'ارسال ایمیل سایت',
        'ارسال ایمیل سایت',
        'manage_options',
        'zigurat-email-settings',
        'zigurat_render_smtp_settings_page'
    );
}
add_action('admin_menu', 'zigurat_add_smtp_settings_page');

function zigurat_smtp_is_configured($settings = null)
{
    $settings = is_array($settings) ? $settings : zigurat_get_smtp_settings();
    $password = zigurat_smtp_decrypt_password($settings['password'] ?? '');
    if (($settings['enabled'] ?? '0') !== '1' || empty($settings['host']) || empty($settings['from_email'])) {
        return false;
    }
    return ($settings['auth'] ?? '0') !== '1'
        || (!empty($settings['username']) && $password !== '');
}

function zigurat_configure_phpmailer($phpmailer)
{
    $settings = zigurat_get_smtp_settings();
    if (!zigurat_smtp_is_configured($settings)) {
        return;
    }
    $phpmailer->isSMTP();
    $phpmailer->Host = $settings['host'];
    $phpmailer->Port = (int) $settings['port'];
    $phpmailer->SMTPAuth = $settings['auth'] === '1';
    $phpmailer->Username = $settings['username'];
    $phpmailer->Password = zigurat_smtp_decrypt_password($settings['password']);
    $phpmailer->SMTPSecure = $settings['encryption'] === 'none' ? '' : $settings['encryption'];
    $phpmailer->SMTPAutoTLS = $settings['encryption'] !== 'none';
    $phpmailer->Timeout = 15;
    $phpmailer->CharSet = 'UTF-8';
}
add_action('phpmailer_init', 'zigurat_configure_phpmailer');

function zigurat_smtp_mail_from($email)
{
    $settings = zigurat_get_smtp_settings();
    return zigurat_smtp_is_configured($settings) ? $settings['from_email'] : $email;
}
add_filter('wp_mail_from', 'zigurat_smtp_mail_from');

function zigurat_smtp_mail_from_name($name)
{
    $settings = zigurat_get_smtp_settings();
    return zigurat_smtp_is_configured($settings) && $settings['from_name'] !== '' ? $settings['from_name'] : $name;
}
add_filter('wp_mail_from_name', 'zigurat_smtp_mail_from_name');

function zigurat_handle_smtp_test_email()
{
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی غیرمجاز.', 403);
    }
    check_admin_referer('zigurat_smtp_test_email');
    $recipient = isset($_POST['test_email']) && is_string($_POST['test_email'])
        ? sanitize_email(wp_unslash($_POST['test_email']))
        : '';
    $redirect = admin_url('options-general.php?page=zigurat-email-settings');
    if (!$recipient || !zigurat_smtp_is_configured()) {
        set_transient('zigurat_smtp_test_' . get_current_user_id(), array(
            'success' => false,
            'message' => 'ابتدا SMTP را کامل کنید، رمز برنامه را وارد و تنظیمات را ذخیره کنید.',
        ), MINUTE_IN_SECONDS);
        wp_safe_redirect($redirect);
        exit;
    }

    $mail_error = null;
    $failure_listener = static function ($error) use (&$mail_error) {
        $mail_error = $error;
    };
    add_action('wp_mail_failed', $failure_listener);
    $sent = wp_mail(
        $recipient,
        'آزمایش ارسال ایمیل سایت زیگورات',
        "این ایمیل آزمایشی با موفقیت از سایت زیگورات ارسال شده است.\n\n" . home_url('/'),
        array('Content-Type: text/plain; charset=UTF-8')
    );
    remove_action('wp_mail_failed', $failure_listener);

    $message = $sent
        ? 'ایمیل آزمایشی ارسال شد؛ صندوق ورودی و پوشه Spam را بررسی کنید.'
        : ($mail_error instanceof WP_Error ? $mail_error->get_error_message() : 'ارسال ایمیل انجام نشد. اطلاعات SMTP را بررسی کنید.');
    set_transient('zigurat_smtp_test_' . get_current_user_id(), array(
        'success' => (bool) $sent,
        'message' => $message,
    ), MINUTE_IN_SECONDS);
    wp_safe_redirect($redirect);
    exit;
}
add_action('admin_post_zigurat_smtp_test_email', 'zigurat_handle_smtp_test_email');

function zigurat_render_smtp_settings_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }
    $settings = zigurat_get_smtp_settings();
    $configured = zigurat_smtp_is_configured($settings);
    $test_status = get_transient('zigurat_smtp_test_' . get_current_user_id());
    delete_transient('zigurat_smtp_test_' . get_current_user_id());
    ?>
    <div class="wrap" dir="rtl">
        <h1>تنظیمات ارسال ایمیل سایت</h1>
        <div class="notice <?php echo $configured ? 'notice-success' : 'notice-warning'; ?> inline">
            <p><strong><?php echo $configured ? 'SMTP فعال و آماده ارسال است.' : 'SMTP هنوز فعال نیست؛ رمز برنامه Yahoo را وارد و ذخیره کنید.'; ?></strong></p>
        </div>
        <?php if (is_array($test_status)): ?>
            <div class="notice <?php echo !empty($test_status['success']) ? 'notice-success' : 'notice-error'; ?> is-dismissible"><p><?php echo esc_html($test_status['message'] ?? ''); ?></p></div>
        <?php endif; ?>
        <p>مقادیر مناسب Yahoo از قبل قرار گرفته‌اند. رمز معمولی ایمیل را وارد نکنید؛ از بخش امنیت حساب Yahoo یک App Password بسازید.</p>

        <form action="options.php" method="post" autocomplete="off">
            <?php settings_fields('zigurat_smtp_settings_group'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">فعال‌سازی SMTP</th>
                    <td><label><input name="zigurat_smtp_settings[enabled]" type="checkbox" value="1" <?php checked($settings['enabled'], '1'); ?>> ارسال همه ایمیل‌های سایت از SMTP</label></td>
                </tr>
                <tr><th scope="row"><label for="zigurat-smtp-host">سرور SMTP</label></th><td><input class="regular-text ltr" id="zigurat-smtp-host" name="zigurat_smtp_settings[host]" type="text" value="<?php echo esc_attr($settings['host']); ?>" required></td></tr>
                <tr><th scope="row"><label for="zigurat-smtp-port">پورت</label></th><td><input id="zigurat-smtp-port" name="zigurat_smtp_settings[port]" type="number" min="1" max="65535" value="<?php echo esc_attr($settings['port']); ?>" required></td></tr>
                <tr>
                    <th scope="row"><label for="zigurat-smtp-encryption">رمزگذاری اتصال</label></th>
                    <td><select id="zigurat-smtp-encryption" name="zigurat_smtp_settings[encryption]"><option value="ssl" <?php selected($settings['encryption'], 'ssl'); ?>>SSL (پیشنهادی برای Yahoo)</option><option value="tls" <?php selected($settings['encryption'], 'tls'); ?>>TLS</option><option value="none" <?php selected($settings['encryption'], 'none'); ?>>بدون رمزگذاری</option></select></td>
                </tr>
                <tr><th scope="row">احراز هویت</th><td><label><input name="zigurat_smtp_settings[auth]" type="checkbox" value="1" <?php checked($settings['auth'], '1'); ?>> نام کاربری و رمز لازم است</label></td></tr>
                <tr><th scope="row"><label for="zigurat-smtp-username">نام کاربری</label></th><td><input class="regular-text ltr" id="zigurat-smtp-username" name="zigurat_smtp_settings[username]" type="text" value="<?php echo esc_attr($settings['username']); ?>" autocomplete="username"></td></tr>
                <tr>
                    <th scope="row"><label for="zigurat-smtp-password">App Password</label></th>
                    <td><input class="regular-text ltr" id="zigurat-smtp-password" name="zigurat_smtp_settings[password]" type="password" value="" autocomplete="new-password" placeholder="<?php echo $settings['password'] !== '' ? 'رمز ذخیره شده؛ برای حفظ آن خالی بگذارید' : 'رمز برنامه Yahoo را وارد کنید'; ?>"><p class="description">رمز پس از ذخیره با کلید امنیتی همین وردپرس رمزنگاری می‌شود.</p></td>
                </tr>
                <tr><th scope="row"><label for="zigurat-smtp-from-email">ایمیل فرستنده</label></th><td><input class="regular-text ltr" id="zigurat-smtp-from-email" name="zigurat_smtp_settings[from_email]" type="email" value="<?php echo esc_attr($settings['from_email']); ?>" required></td></tr>
                <tr><th scope="row"><label for="zigurat-smtp-from-name">نام فرستنده</label></th><td><input class="regular-text" id="zigurat-smtp-from-name" name="zigurat_smtp_settings[from_name]" type="text" value="<?php echo esc_attr($settings['from_name']); ?>" required></td></tr>
            </table>
            <?php submit_button('ذخیره تنظیمات ایمیل'); ?>
        </form>

        <hr>
        <h2>ارسال آزمایشی</h2>
        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
            <input type="hidden" name="action" value="zigurat_smtp_test_email">
            <?php wp_nonce_field('zigurat_smtp_test_email'); ?>
            <input class="regular-text ltr" name="test_email" type="email" value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>" required>
            <?php submit_button('ارسال ایمیل آزمایشی', 'secondary', 'submit', false); ?>
        </form>
    </div>
    <?php
}
