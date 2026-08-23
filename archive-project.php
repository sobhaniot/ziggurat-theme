<?php
get_header();
?>
<section class="projects-archive">
    <div class="container">
        <div class="projects-header">
            <h1>
                پروژه‌های زیگورات
            </h1>
            <p>
                نمونه‌ای از پروژه‌های اجرا شده در زمینه تابلو،
                دکوراسیون و تبلیغات محیطی
            </p>
        </div>
        <form class="project-filters" method="get" action="<?php echo esc_url(get_post_type_archive_link('project')); ?>" data-auto-filter-form>
            <?php
            $filter_taxonomies = array(
                'project_client'    => 'همه کارفرماها',
                'project_city'      => 'همه شهرها',
                'project_province'  => 'همه استان‌ها',
                'project_sign_type' => 'همه انواع فعالیت/اجرا',
            );
            foreach ($filter_taxonomies as $taxonomy => $placeholder):
                $terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => true));
                $selected = isset($_GET[$taxonomy]) && is_string($_GET[$taxonomy])
                    ? sanitize_title(wp_unslash($_GET[$taxonomy]))
                    : '';
            ?>
                <label>
                    <span class="screen-reader-text"><?php echo esc_html($placeholder); ?></span>
                    <select name="<?php echo esc_attr($taxonomy); ?>">
                        <option value=""><?php echo esc_html($placeholder); ?></option>
                        <?php if (!is_wp_error($terms)): foreach ($terms as $term): ?>
                            <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($selected, $term->slug); ?>>
                                <?php echo esc_html($term->name); ?>
                            </option>
                        <?php endforeach; endif; ?>
                    </select>
                </label>
            <?php endforeach; ?>
            <button type="submit">نمایش پروژه‌ها</button>
            <?php if (array_filter(array_intersect_key($_GET, $filter_taxonomies))): ?>
                <a href="<?php echo esc_url(get_post_type_archive_link('project')); ?>">حذف فیلترها</a>
            <?php endif; ?>
        </form>
        <div class="projects-grid">
            <?php
            if (have_posts()) :
                while (have_posts()) :
                    the_post();
                    $city = zigurat_get_project_term_name(get_the_ID(), 'project_city', '_project_city');
                    $province = zigurat_get_project_term_name(get_the_ID(), 'project_province', '_project_province');
                    $client = zigurat_get_project_term_name(get_the_ID(), 'project_client', '_project_client');
                    $type = zigurat_get_project_term_name(get_the_ID(), 'project_sign_type', '_project_type');
            ?>
                    <div class="project-card">
                        <a href="<?php the_permalink(); ?>">
                            <?php
                            if (has_post_thumbnail()) {
                                the_post_thumbnail('large');
                            }
                            ?>
                            <div class="project-card-content">
                                <div class="project-card-meta"><?php echo esc_html(number_format_i18n(zigurat_get_project_views(get_the_ID()))); ?> بازدید</div>
                                <h2>
                                    <?php the_title(); ?>
                                </h2>
                                <?php if ($city): ?>
                                    <span>
                                        📍 <?php echo esc_html($city); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($province): ?>
                                    <span>استان: <?php echo esc_html($province); ?></span>
                                <?php endif; ?>
                                <?php if ($client): ?>
                                    <span>کارفرما: <?php echo esc_html($client); ?></span>
                                <?php endif; ?>
                                <?php if ($type): ?>
                                    <span>
                                        🛠 <?php echo esc_html($type); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                <?php
                endwhile;
            else:
                ?>
                <p>
                    هنوز پروژه‌ای ثبت نشده است.
                </p>
            <?php
            endif;
            ?>
        </div>
        <?php
        the_posts_pagination(array(
            'mid_size'  => 2,
            'prev_text' => 'قبلی',
            'next_text' => 'بعدی',
        ));
        ?>
    </div>
</section>
<?php
get_footer();
?>
