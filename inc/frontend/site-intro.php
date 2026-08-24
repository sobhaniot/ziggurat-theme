<?php
if (!defined('ABSPATH')) {
    exit;
}

function zigurat_should_show_site_intro()
{
    if (
        is_admin()
        || wp_doing_ajax()
        || is_feed()
        || is_embed()
        || is_preview()
        || is_customize_preview()
        || !is_front_page()
        || (function_exists('zigurat_is_private_management_page') && zigurat_is_private_management_page())
    ) {
        return false;
    }
    return true;
}

function zigurat_site_intro_boot_script()
{
    if (!zigurat_should_show_site_intro()) {
        return;
    }
    ?>
    <script id="zigurat-site-intro-boot">
    (function(){
      try {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        document.documentElement.classList.add('zigurat-intro-pending');
        window.ziguratIntroFailsafe = window.setTimeout(function(){ document.documentElement.classList.remove('zigurat-intro-pending'); }, 6500);
      } catch (error) {}
    }());
    </script>
    <?php
}
add_action('wp_head', 'zigurat_site_intro_boot_script', 0);

function zigurat_render_site_intro()
{
    if (!zigurat_should_show_site_intro()) {
        return;
    }
    $logo_file = get_template_directory() . '/assets/images/zigurat-logo.svg';
    if (!is_readable($logo_file)) {
        return;
    }
    ?>
    <div id="zigurat-site-intro" class="zigurat-site-intro" role="status" aria-label="در حال نمایش لوگوی زیگورات">
        <div class="zigurat-site-intro__stage" aria-hidden="true">
            <?php include $logo_file; ?>
            <span class="zigurat-site-intro__shine"></span>
        </div>
        <p>زیگورات</p>
        <button type="button" data-intro-skip>ورود سریع به سایت</button>
    </div>
    <?php
}
add_action('wp_body_open', 'zigurat_render_site_intro', 1);
