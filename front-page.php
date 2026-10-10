<?php
get_header();
?>

<main id="site-main">
    <?php foreach (zigurat_get_home_section_order() as $home_section_key) : ?>
        <?php
        $home_sections = zigurat_home_section_definitions();
        if (!isset($home_sections[$home_section_key])) {
            continue;
        }
        get_template_part($home_sections[$home_section_key]['template']);
        ?>
    <?php endforeach; ?>

</main>

<?php
get_footer();
?>
