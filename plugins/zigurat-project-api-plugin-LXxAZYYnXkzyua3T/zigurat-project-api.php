<?php
/**
 * Plugin Name: Zigurat Project API
 * Description: Authenticated API for the Ziggurat project manager.
 * Version: 1.2.2
 *
 * Routes reuse the existing project save and photo-storage functions so the
 * API and manager board keep the same validation, history, and private files.
 */

if (!defined('ABSPATH')) {
    exit;
}

function zigurat_manager_project_api_authorize()
{
    if (!function_exists('zigurat_is_manager') || !zigurat_is_manager()) {
        return new WP_Error(
            'zigurat_project_api_forbidden',
            'این حساب اجازه دسترسی به مدیریت پروژه‌ها را ندارد.',
            array('status' => is_user_logged_in() ? 403 : 401)
        );
    }
    return true;
}

function zigurat_manager_is_draft_agent($user)
{
    return $user->user_login === 'ziggurat_wp_agent'
        && in_array('contributor', $user->roles, true);
}

function zigurat_manager_draft_agent_upload_cap($capabilities, $required, $args, $user)
{
    if (in_array('upload_files', $required, true) && zigurat_manager_is_draft_agent($user)) {
        $capabilities['upload_files'] = true;
    }
    return $capabilities;
}
add_filter('user_has_cap', 'zigurat_manager_draft_agent_upload_cap', 10, 4);

function zigurat_manager_can_create_content_draft()
{
    return current_user_can('manage_options') || zigurat_manager_is_draft_agent(wp_get_current_user());
}

function zigurat_manager_project_api_register_routes()
{
    register_rest_route('zigurat-manager/v1', '/content-projects/draft/(?P<id>[1-9]\d*)', array(
        'methods' => 'PATCH',
        'callback' => 'zigurat_manager_update_content_draft',
        'permission_callback' => 'zigurat_manager_can_create_content_draft',
    ));

    register_rest_route('zigurat-manager/v1', '/content-projects/draft', array(
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'zigurat_manager_content_project_draft',
        'permission_callback' => 'zigurat_manager_can_create_content_draft',
    ));

    register_rest_route('zigurat-manager/v1', '/projects', array(
        array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'zigurat_manager_project_api_list',
            'permission_callback' => 'zigurat_manager_project_api_authorize',
            'args' => array(
                'stage' => array('type' => 'string', 'required' => false),
                'search' => array('type' => 'string', 'required' => false),
                'page' => array('type' => 'integer', 'default' => 1, 'minimum' => 1),
                'per_page' => array('type' => 'integer', 'default' => 100, 'minimum' => 1, 'maximum' => 100),
            ),
        ),
        array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'zigurat_manager_project_api_create',
            'permission_callback' => 'zigurat_manager_project_api_authorize',
        ),
    ));

    register_rest_route('zigurat-manager/v1', '/projects/(?P<id>\d+)', array(
        array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'zigurat_manager_project_api_get',
            'permission_callback' => 'zigurat_manager_project_api_authorize',
            'args' => array('id' => array('validate_callback' => static function ($value) { return absint($value) > 0; })),
        ),
        array(
            'methods' => 'PATCH',
            'callback' => 'zigurat_manager_project_api_update',
            'permission_callback' => 'zigurat_manager_project_api_authorize',
            'args' => array('id' => array('validate_callback' => static function ($value) { return absint($value) > 0; })),
        ),
    ));

    register_rest_route('zigurat-manager/v1', '/collaborators', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'zigurat_manager_project_api_collaborators',
        'permission_callback' => 'zigurat_manager_project_api_authorize',
    ));
}
add_action('rest_api_init', 'zigurat_manager_project_api_register_routes');

function zigurat_manager_update_content_draft(WP_REST_Request $request)
{
    $url = $request->get_url_params();
    $post = get_post((int) $url['id']);
    if (!$post || $post->post_type !== 'project' || $post->post_status !== 'draft'
        || (!current_user_can('manage_options') && (int) $post->post_author !== get_current_user_id())) {
        return new WP_Error('zigurat_content_draft_not_found', 'پیش‌نویس قابل ویرایش پیدا نشد.', array('status' => 404));
    }

    $input = $request->get_json_params();
    if (!is_array($input) || array_keys($input) !== array('content') || !is_string($input['content'])) {
        return new WP_Error('zigurat_content_draft_invalid', 'فقط متن پروژه را در فیلد content بفرستید.', array('status' => 400));
    }
    $content = wp_kses_post($input['content']);
    if (trim(wp_strip_all_tags($content)) === '') {
        return new WP_Error('zigurat_content_draft_empty', 'متن پروژه نباید خالی باشد.', array('status' => 400));
    }

    $result = wp_update_post(wp_slash(array('ID' => $post->ID, 'post_content' => $content)), true);
    if (is_wp_error($result)) {
        return $result;
    }
    $saved = get_post($result);
    return rest_ensure_response(array('id' => $saved->ID, 'status' => $saved->post_status, 'content' => $saved->post_content));
}

function zigurat_manager_content_project_draft(WP_REST_Request $request)
{
    if (!zigurat_manager_can_create_content_draft()) {
        return new WP_Error('zigurat_content_project_forbidden', 'اجازه ساخت پیش‌نویس پروژه را ندارید.', array('status' => 403));
    }

    $input = $request->get_json_params();
    if (!is_array($input)) {
        $input = $request->get_body_params();
    }
    foreach ($input as $field => $value) {
        if ($field !== 'gallery' && !is_string($value)) {
            return new WP_Error('zigurat_content_project_invalid_field', 'مقدار فیلد باید متن باشد.', array('status' => 400, 'field' => $field));
        }
    }

    $gallery = $input['gallery'] ?? array();
    if (!is_array($gallery)) {
        return new WP_Error('zigurat_content_project_invalid_gallery', 'گالری باید آرایه‌ای از شناسه‌های تصاویر باشد.', array('status' => 400));
    }
    foreach ($gallery as $attachment_id) {
        if (!is_int($attachment_id) || $attachment_id <= 0 || !wp_attachment_is_image($attachment_id) || !current_user_can('edit_post', $attachment_id)) {
            return new WP_Error('zigurat_content_project_invalid_gallery', 'شناسهٔ تصویر معتبر یا قابل استفاده نیست.', array('status' => 400));
        }
    }
    $gallery = array_unique($gallery);
    $title = sanitize_text_field($input['title'] ?? '');
    if ($title === '') {
        return new WP_Error('zigurat_content_project_title_required', 'عنوان پروژه الزامی است.', array('status' => 400));
    }

    $post_id = wp_insert_post(wp_slash(array(
        'post_type' => 'project',
        'post_status' => 'draft',
        'post_title' => $title,
        'post_name' => sanitize_title((string) ($input['slug'] ?? $title)),
        'post_content' => wp_kses_post((string) ($input['content'] ?? '')),
        'post_excerpt' => sanitize_textarea_field((string) ($input['excerpt'] ?? '')),
    )), true);
    if (is_wp_error($post_id)) {
        return $post_id;
    }

    $meta_fields = array(
        '_project_city' => 'sanitize_text_field',
        '_project_neighborhood' => 'sanitize_text_field',
        '_project_province' => 'sanitize_text_field',
        '_project_client' => 'sanitize_text_field',
        '_project_date' => 'sanitize_text_field',
        '_project_type' => 'sanitize_text_field',
        '_project_duration' => 'sanitize_text_field',
        '_zigurat_seo_title' => 'sanitize_text_field',
        '_zigurat_seo_description' => 'sanitize_textarea_field',
        '_zigurat_seo_focus_keyword' => 'sanitize_text_field',
        '_zigurat_seo_related_keywords' => 'sanitize_textarea_field',
        '_zigurat_seo_image_alt' => 'sanitize_text_field',
    );
    foreach ($meta_fields as $key => $sanitizer) {
        $field = ltrim($key, '_');
        if (isset($input[$field]) && is_scalar($input[$field])) {
            update_post_meta($post_id, $key, call_user_func($sanitizer, (string) $input[$field]));
        }
    }

    if ($gallery) {
        update_post_meta($post_id, '_project_gallery', implode(',', $gallery));
        set_post_thumbnail($post_id, $gallery[0]);
    }

    zigurat_sync_project_taxonomies($post_id, true);

    $post = get_post($post_id);
    return new WP_REST_Response(array(
        'id' => $post_id,
        'status' => $post->post_status,
        'gallery' => array_values(array_map('intval', array_filter(explode(',', (string) get_post_meta($post_id, '_project_gallery', true))))),
        'featured_media' => (int) get_post_thumbnail_id($post_id),
        'edit_url' => get_edit_post_link($post_id, 'raw'),
        'link' => get_permalink($post_id),
    ), 201);
}

function zigurat_manager_project_api_list(WP_REST_Request $request)
{
    $stages = zigurat_workflow_project_stages();
    $stage = sanitize_key((string) $request->get_param('stage'));
    if ($stage !== '' && !isset($stages[$stage])) {
        return new WP_Error('zigurat_project_api_invalid_stage', 'مرحله پروژه معتبر نیست.', array('status' => 400));
    }

    $search = sanitize_text_field((string) $request->get_param('search'));
    $page = max(1, absint($request->get_param('page')));
    $per_page = min(100, max(1, absint($request->get_param('per_page')) ?: 100));
    $records = zigurat_workflow_project_records();
    $records = array_values(array_filter($records, static function ($record) use ($stage, $search) {
        if ($stage !== '' && $record['stage'] !== $stage) {
            return false;
        }
        if ($search !== '') {
            $haystack = implode(' ', array($record['title'], $record['client'], $record['notes']));
            if (function_exists('mb_stripos') ? mb_stripos($haystack, $search) === false : stripos($haystack, $search) === false) {
                return false;
            }
        }
        return true;
    }));

    $total = count($records);
    return rest_ensure_response(array(
        'projects' => array_slice($records, ($page - 1) * $per_page, $per_page),
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'pages' => (int) ceil($total / $per_page),
        'stages' => $stages,
    ));
}

function zigurat_manager_project_api_get(WP_REST_Request $request)
{
    $record = zigurat_workflow_project_record(absint($request['id']));
    if (!$record || !empty($record['archived'])) {
        return new WP_Error('zigurat_project_api_not_found', 'پروژه فعال پیدا نشد.', array('status' => 404));
    }
    return rest_ensure_response(array('project' => $record));
}

function zigurat_manager_project_api_collaborators()
{
    return rest_ensure_response(array('collaborators' => zigurat_workflow_project_partners()));
}

function zigurat_manager_project_api_request_data(WP_REST_Request $request)
{
    $data = $request->get_json_params();
    if (!is_array($data)) {
        $data = $request->get_body_params();
    }
    return is_array($data) ? $data : array();
}

function zigurat_manager_project_api_save(WP_REST_Request $request, $project_id = 0)
{
    $existing = $project_id ? zigurat_workflow_project_record($project_id) : null;
    if ($project_id && (!$existing || !empty($existing['archived']))) {
        return new WP_Error('zigurat_project_api_not_found', 'پروژه فعال پیدا نشد.', array('status' => 404));
    }

    $input = zigurat_manager_project_api_request_data($request);
    $allowed = array('title', 'client', 'phone', 'stage', 'followup', 'repeat_daily', 'work_items', 'proforma_ids', 'invoice_ids', 'notes');
    $input = array_intersect_key($input, array_fill_keys($allowed, true));
    foreach (array('title', 'client', 'phone', 'stage', 'followup', 'notes') as $field) {
        if (array_key_exists($field, $input) && !is_scalar($input[$field])) {
            return new WP_Error('zigurat_project_api_invalid_field', 'مقدار یکی از فیلدهای متنی معتبر نیست.', array('status' => 400));
        }
    }
    foreach (array('work_items', 'proforma_ids', 'invoice_ids') as $field) {
        if (array_key_exists($field, $input) && !is_array($input[$field])) {
            return new WP_Error('zigurat_project_api_invalid_field', 'فهرست بخش‌ها یا اسناد باید آرایه باشد.', array('status' => 400));
        }
    }
    if (array_key_exists('repeat_daily', $input) && !in_array($input['repeat_daily'], array(true, false, 0, 1, '0', '1'), true)) {
        return new WP_Error('zigurat_project_api_invalid_field', 'مقدار یادآوری روزانه باید بولی باشد.', array('status' => 400));
    }
    foreach (array('proforma_ids', 'invoice_ids') as $field) {
        foreach ((array) ($input[$field] ?? array()) as $document_id) {
            if (!is_scalar($document_id) || !preg_match('/^\d+$/', (string) $document_id)) {
                return new WP_Error('zigurat_project_api_invalid_document', 'شناسه سند معتبر نیست.', array('status' => 400));
            }
        }
    }
    foreach ((array) ($input['work_items'] ?? array()) as $work_item) {
        if (!is_array($work_item)
            || (isset($work_item['title']) && !is_scalar($work_item['title']))
            || (isset($work_item['other_partner']) && !is_scalar($work_item['other_partner']))
            || (isset($work_item['partner_ids']) && !is_array($work_item['partner_ids']))) {
            return new WP_Error('zigurat_project_api_invalid_work_item', 'ساختار یکی از بخش‌های اجرایی معتبر نیست.', array('status' => 400));
        }
        foreach ((array) ($work_item['partner_ids'] ?? array()) as $partner_id) {
            if (!is_scalar($partner_id) || !preg_match('/^\d+$/', (string) $partner_id)) {
                return new WP_Error('zigurat_project_api_invalid_partner', 'شناسه همکار معتبر نیست.', array('status' => 400));
            }
        }
    }
    $data = $existing ? array(
        'title' => $existing['title'],
        'client' => $existing['client'],
        'phone' => $existing['phone'],
        'stage' => $existing['stage'],
        'followup' => $existing['followup'],
        'repeat_daily' => $existing['repeat_daily'],
        'repeat_daily_present' => 1,
        'work_items' => $existing['work_items'],
        'proforma_ids' => $existing['proforma_ids'],
        'invoice_ids' => $existing['invoice_ids'],
        'notes' => $existing['notes'],
    ) : array(
        'title' => '',
        'client' => '',
        'phone' => '',
        'stage' => 'lead',
        'followup' => '',
        'repeat_daily' => 0,
        'repeat_daily_present' => 1,
        'work_items' => array(),
        'proforma_ids' => array(),
        'invoice_ids' => array(),
        'notes' => '',
    );
    $data = array_merge($data, $input);

    $stages = zigurat_workflow_project_stages();
    if (!isset($stages[$data['stage']])) {
        return new WP_Error('zigurat_project_api_invalid_stage', 'مرحله پروژه معتبر نیست.', array('status' => 400));
    }
    $invoice_ids = zigurat_workflow_project_valid_document_ids($data['invoice_ids'], 'invoice');
    $proforma_ids = zigurat_workflow_project_valid_document_ids($data['proforma_ids'], 'proforma');
    if ($data['stage'] === 'payment' && !$invoice_ids) {
        return new WP_Error('zigurat_project_api_invoice_required', 'برای مرحله انتظار پرداخت، پروژه باید فاکتور نهایی داشته باشد.', array('status' => 409));
    }
    if ($data['stage'] === 'delivery' && !$invoice_ids && $proforma_ids) {
        return new WP_Error('zigurat_project_api_invoice_required', 'ابتدا فاکتور نهایی را از پیش‌فاکتور بسازید و سپس مرحله پروژه را تغییر دهید.', array('status' => 409));
    }

    if ($project_id) {
        $data['project_id'] = $project_id;
    }
    $photos = zigurat_project_handoff_upload_photos($request->get_file_params());
    if (is_wp_error($photos)) return $photos;
    // The existing workflow save function expects slashed form values and
    // performs its own field-specific unslashing and sanitization.
    $record = zigurat_workflow_project_save_from_request(wp_slash($data));
    if (is_wp_error($record)) {
        foreach ($photos as $photo) zigurat_project_handoff_remove_photo($photo);
        return new WP_Error('zigurat_project_api_save_failed', $record->get_error_message(), array('status' => $record->get_error_code() === 'forbidden' ? 403 : 400));
    }

    if (!zigurat_project_handoff_save_photos($record['id'], $photos)) {
        return new WP_Error('zigurat_project_api_photo_save_failed', 'ذخیره عکس پروژه انجام نشد.', array('status' => 500));
    }
    $record = zigurat_workflow_project_record($record['id']);
    return rest_ensure_response(array('project' => $record));
}

function zigurat_manager_project_api_create(WP_REST_Request $request)
{
    $response = zigurat_manager_project_api_save($request);
    if (is_wp_error($response)) {
        return $response;
    }
    $response->set_status(201);
    return $response;
}

function zigurat_manager_project_api_update(WP_REST_Request $request)
{
    return zigurat_manager_project_api_save($request, absint($request['id']));
}
