<?php
function register_user($username, $password) {
    global $wpdb;
    $table_name = "zigurat_users";
    
    // هشینگ رمز عبور
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // وارد کردن داده‌ها به جدول
    $wpdb->insert(
        $table_name,
        array(
            'username' => $username,
            'password' => $hashed_password
        )
    );
}
?>



