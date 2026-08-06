<?php
if (!defined('ABSPATH')) {
    exit;
}

/** نام قدیمی برای سازگاری قالب؛ اعتبارسنجی اکنون از نشست وردپرس انجام می‌شود. */
function check_login_cookies()
{
    return function_exists('zigurat_is_manager') && zigurat_is_manager();
}
