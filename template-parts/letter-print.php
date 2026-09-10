<?php
if (!defined('ABSPATH') || !isset($letter) || !$letter) {
    exit;
}
$print_title = implode('-', array_filter(array(
    trim((string) $letter->number),
    trim((string) $letter->recipient),
    trim((string) $letter->subject),
)));
$print_title = $print_title !== '' ? $print_title : 'نامه';
$css_path = get_template_directory() . '/assets/css/letters.css';
$css_url = get_template_directory_uri() . '/assets/css/letters.css';
$js_path = get_template_directory() . '/assets/js/letters.js';
$js_url = get_template_directory_uri() . '/assets/js/letters.js';
$back_url = zigurat_letters_page_url(array('letter-view' => 'edit', 'letter-id' => $letter->id));
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo esc_html($print_title); ?></title>
    <link rel="stylesheet" href="<?php echo esc_url(add_query_arg('ver', is_file($css_path) ? filemtime($css_path) : null, $css_url)); ?>">
    <script defer src="<?php echo esc_url(add_query_arg('ver', is_file($js_path) ? filemtime($js_path) : null, $js_url)); ?>"></script>
</head>
<body class="letter-print-page" data-print-filename="<?php echo esc_attr($print_title); ?>">
    <div class="letter-print-toolbar no-print">
        <a href="<?php echo esc_url($back_url); ?>">بازگشت به ویرایش</a>
        <button type="button" onclick="window.print()">چاپ / ذخیره PDF</button>
        <span class="letter-print-toolbar__warning" data-letter-print-overflow hidden>متن بیشتر از دو صفحه است؛ پیش از چاپ اندازه فونت یا متن را اصلاح کنید.</span>
    </div>
    <main class="letter-print-sheet">
        <?php get_template_part('template-parts/letter-document', null, array('letter' => $letter)); ?>
    </main>
</body>
</html>
