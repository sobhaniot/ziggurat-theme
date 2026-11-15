<?php
/**
 * Plugin Name: Ziggurat Rubika Renderer Diagnostics
 * Version: 1.0.0
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_management_page('بررسی ساخت برگه اجرا', 'بررسی ساخت برگه اجرا', 'manage_options', 'ziggurat-render-check', function () {
        if (!current_user_can('manage_options')) return;
        echo '<div class="wrap"><h1>بررسی ساخت برگه اجرا</h1><form method="post">';
        wp_nonce_field('ziggurat_render_check');
        submit_button('بررسی امکان اجرای مرورگر روی هاست');
        echo '</form>';
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo '</div>'; return; }
        check_admin_referer('ziggurat_render_check');
        $paths = ['/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome', '/usr/bin/google-chrome-stable', '/usr/local/bin/chromium', '/opt/google/chrome/chrome', '/snap/bin/chromium'];
        if (defined('ZIGGURAT_RENDER_BROWSER')) array_unshift($paths, ZIGGURAT_RENDER_BROWSER);
        echo '<pre>' . esc_html('OS: ' . PHP_OS_FAMILY . "\nproc_open: " . (function_exists('proc_open') ? 'available' : 'disabled') . "\n");
        foreach (array_unique($paths) as $path) {
            $status = 'not accessible';
            if (@is_executable($path)) {
                $status = 'executable';
                if (function_exists('proc_open')) {
                    $process = @proc_open([$path, '--version'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                    if (is_resource($process)) {
                        fclose($pipes[0]);
                        $deadline = microtime(true) + 3;
                        do {
                            $running = proc_get_status($process);
                            if (!$running['running']) break;
                            usleep(50000);
                        } while (microtime(true) < $deadline);
                        $status = !$running['running'] && $running['exitcode'] === 0 ? 'version command passed' : 'version command failed';
                        if ($running['running']) proc_terminate($process, 9);
                        fclose($pipes[1]);
                        fclose($pipes[2]);
                        proc_close($process);
                    } else {
                        $status = 'process launch failed';
                    }
                }
            }
            echo esc_html($path . ': ' . $status . "\n");
        }
        echo '</pre></div>';
    });
});
