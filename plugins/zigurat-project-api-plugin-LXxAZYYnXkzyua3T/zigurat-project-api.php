<?php
/**
 * Plugin Name: Zigurat Project API
 * Description: Authenticated API for the Ziggurat project manager.
 * Version: 1.1.0
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

function zigurat_manager_project_api_register_routes()
{
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
