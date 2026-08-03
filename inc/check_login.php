<?php
function check_login_cookies() {
    global $wpdb;

    $table_name = "zigurat_users";
    
    if (isset($_COOKIE['ziguser']) && isset($_COOKIE['zigpass'])) {
        $username = $_COOKIE['ziguser'];
        $password = $_COOKIE['zigpass'];


        $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE username = %s", $username));
        
        if ($user && $password == $user->password) {
            return true;
            echo "true";
        }
    }
    return false;
}
?>