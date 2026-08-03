<?php
/*
Template Name: Login
*/

get_header();

include_once("inc/check_login.php");

if (check_login_cookies()) {
    include "page-main.php";
} else {
    // پردازش فرم ورود
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        global $wpdb;
        $username = sanitize_text_field($_POST["log"]);
        $password = sanitize_text_field($_POST["pwd"]);

        // دریافت اطلاعات کاربر از دیتابیس
        $table_name = "zigurat_users";
        $user = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE username = %s",
                $username
            )
        );

        // بررسی صحت رمز عبور هش شده
        if ($user && $password === $user->password) {
            
            wp_redirect(home_url("/"));
            exit();
        } else {
            $error_message = "نام کاربری یا رمز عبور اشتباه است.";
        }
    } ?>
    <div id="content">
        <div class="login-form-container">
            <h2>ورود</h2>
            <?php if (isset($error_message)) {
                echo '<div id="login-response"><p>' .
                    $error_message .
                    "</p></div>";
            } ?>
            <form id="loginform" action="" method="post">
                <p>
                    <label for="user_login">نام کاربری<br />
                    <input type="text" name="log" id="user_login" class="input" value="" size="20" /></label>
                </p>
                <p>
                    <label for="user_pass">رمز عبور<br />
                    <input type="password" name="pwd" id="user_pass" class="input" value="" size="20" /></label>
                </p>
                <p class="submit">
                    <input type="button" id="login-submit" class="button button-primary" value="ورود" />
                </p>
            </form>
        </div>
    </div>

    <script>
        jQuery(document).ready(function($) {
            $('#login-submit').on('click', function() {
                var username = $('#user_login').val();
                var password = $('#user_pass').val();
                
                // هش کردن رمز عبور
                var hashedPassword = CryptoJS.SHA256(password).toString();
                console.log(hashedPassword);
                
                function setCookie(name, value, days) {
                    var expires = "";
                    if (days) {
                        var date = new Date();
                        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                        expires = "; expires=" + date.toUTCString();
                    }
                    document.cookie = name + "=" + (value || "") + expires + "; path=/";
                }

                setCookie('zigpass', hashedPassword, 30);
                setCookie('ziguser', username, 30);

                
                // قرار دادن هش در فرم برای ارسال به سرور
                $('<input>').attr({
                    type: 'hidden',
                    name: 'pwd',
                    value: hashedPassword
                }).appendTo('#loginform');

                // ارسال فرم
                $('#loginform').submit();
            });
        });
    </script>

    <?php
}
?>

<?php get_footer(); ?>
