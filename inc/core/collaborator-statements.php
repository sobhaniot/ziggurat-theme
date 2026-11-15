<?php
if (!defined('ABSPATH')) exit;

function zigurat_statement_partner($id)
{
    $post = get_post($id);
    return $post && $post->post_type === 'partner_application' && $post->post_status === 'private'
        && get_post_meta($id, '_application_application_type', true) === 'collaborator';
}

function zigurat_statement_manager_access()
{
    return current_user_can('manage_options');
}

function zigurat_statement_read_access()
{
    return zigurat_statement_manager_access() || current_user_can('zigurat_read_collaborator_statements');
}

function zigurat_statement_sync_access()
{
    return zigurat_statement_manager_access() || current_user_can('zigurat_sync_collaborator_statements');
}

add_action('edit_user_profile', static function ($user) {
    if (!current_user_can('manage_options') || !current_user_can('edit_user', $user->ID)) return;
    wp_nonce_field('zigurat_statement_sync_' . $user->ID, 'zigurat_statement_sync_nonce');
    ?>
    <h2>همگام‌سازی صورت‌حساب همکاران</h2>
    <label><input type="checkbox" name="zigurat_statement_sync_enabled" value="1"
        <?php checked(user_can($user, 'zigurat_sync_collaborator_statements')); ?>>
        اجازهٔ دریافت فهرست همکاران و ارسال صورت‌حساب از نرم‌افزار اکسل
    </label>
    <?php
});

add_action('edit_user_profile_update', static function ($user_id) {
    if (!current_user_can('manage_options') || !current_user_can('edit_user', $user_id)) return;
    $nonce = $_POST['zigurat_statement_sync_nonce'] ?? '';
    if (!is_string($nonce) || !wp_verify_nonce(wp_unslash($nonce), 'zigurat_statement_sync_' . $user_id)) return;
    $user = new WP_User($user_id);
    if (($_POST['zigurat_statement_sync_enabled'] ?? '') === '1') {
        $user->add_cap('zigurat_sync_collaborator_statements');
    } else {
        $user->remove_cap('zigurat_sync_collaborator_statements');
    }
});

add_action('rest_api_init', static function () {
    register_rest_route('zigurat-statements/v1', '/collaborators/(?P<id>[1-9]\d*)/pdf', array(
        'methods' => 'PUT',
        'permission_callback' => 'zigurat_statement_sync_access',
        'callback' => static function (WP_REST_Request $request) {
            $result = zigurat_statement_store_pdf((int) $request['id'], $request->get_body());
            return is_wp_error($result) ? $result : new WP_REST_Response(array('saved' => true), 200);
        },
    ));
    register_rest_route('zigurat-statements/v1', '/collaborators', array(
        'methods' => 'GET',
        'permission_callback' => 'zigurat_statement_sync_access',
        'callback' => static function () {
            return array('collaborators' => array_map(static function ($partner) {
                return array('id' => $partner['id'], 'name' => $partner['name']);
            }, zigurat_workflow_project_partners()));
        },
    ));
    register_rest_route('zigurat-statements/v1', '/rubika-project-group/(?P<group>[A-Za-z0-9_-]{1,128})', array(
        'methods' => 'GET',
        'permission_callback' => 'zigurat_statement_read_access',
        'callback' => 'zigurat_statement_rubika_group',
    ));
    register_rest_route('zigurat-statements/v1', '/collaborators/(?P<id>[1-9]\d*)/statement', array(
        array(
            'methods' => 'GET',
            'permission_callback' => 'zigurat_statement_read_access',
            'callback' => 'zigurat_statement_read',
        ),
        array(
            'methods' => 'PUT',
            'permission_callback' => 'zigurat_statement_manager_access',
            'callback' => 'zigurat_statement_write',
        ),
    ));
});

function zigurat_statement_rubika_group(WP_REST_Request $request)
{
    $group = (string) $request['group'];
    $matches = array_keys((array) get_option('ziggurat_rubika_groups', array()), $group, true);
    $id = count($matches) === 1 ? (int) $matches[0] : 0;
    if (!$id || !zigurat_statement_partner($id)) {
        return new WP_Error('zigurat_statement_group_missing', 'گروه به یک همکار یکتا متصل نیست.', array('status' => 404));
    }
    return new WP_REST_Response(array('collaborator_id' => $id), 200, array('Cache-Control' => 'private, no-store'));
}

function zigurat_statement_read(WP_REST_Request $request)
{
    $id = (int) $request['id'];
    $statement = zigurat_statement_partner($id) ? get_option('zigurat_statement_' . $id) : false;
    if (!is_array($statement)) {
        return new WP_Error('zigurat_statement_missing', 'صورت‌حساب در دسترس نیست.', array('status' => 404));
    }
    return new WP_REST_Response(array('statement' => $statement), 200, array('Cache-Control' => 'private, no-store'));
}

function zigurat_statement_write(WP_REST_Request $request)
{
    $id = (int) $request['id'];
    if (!zigurat_statement_partner($id)) {
        return new WP_Error('zigurat_statement_partner_missing', 'همکار پیدا نشد.', array('status' => 404));
    }
    $input = $request->get_json_params();
    $invalid = new WP_Error('zigurat_statement_invalid', 'اطلاعات صورت‌حساب معتبر نیست.', array('status' => 400));
    $is_amount = static function ($value) {
        return is_string($value) && preg_match('/^-?(?:0|[1-9][0-9]{0,19})$/D', $value);
    };
    if (!is_array($input) || !in_array($input['currency'] ?? '', array('IRR', 'IRT'), true)
        || !$is_amount($input['opening_balance'] ?? null) || !is_array($input['rows'] ?? null)
        || array_values($input['rows']) !== $input['rows'] || count($input['rows']) > 5000) return $invalid;

    $rows = array();
    foreach ($input['rows'] as $row) {
        if (!is_array($row) || !is_string($row['date'] ?? null) || !is_string($row['description'] ?? null)
            || strlen($row['date']) > 80 || strlen($row['description']) > 2000) return $invalid;
        $entry = array(
            'date' => sanitize_text_field($row['date']),
            'description' => sanitize_text_field($row['description']),
        );
        foreach (array('debit', 'credit', 'balance') as $field) {
            if (!$is_amount($row[$field] ?? null)) return $invalid;
            $entry[$field] = $row[$field];
        }
        $rows[] = $entry;
    }
    $statement = array(
        'collaborator_id' => $id,
        'currency' => $input['currency'],
        'opening_balance' => $input['opening_balance'],
        'rows' => $rows,
        'synced_at' => gmdate('c'),
    );
    $key = 'zigurat_statement_' . $id;
    if (!update_option($key, $statement, false) && get_option($key) !== $statement) {
        return new WP_Error('zigurat_statement_save_failed', 'ذخیره صورت‌حساب انجام نشد.', array('status' => 500));
    }
    return new WP_REST_Response(array('synced_at' => $statement['synced_at'], 'row_count' => count($rows)), 200);
}

function zigurat_statement_store_pdf($id, $bytes)
{
    if (!zigurat_statement_partner($id)) {
        return new WP_Error('zigurat_statement_partner_missing', 'همکار پیدا نشد.', array('status' => 404));
    }
    if (strlen($bytes) > 5 * MB_IN_BYTES || strpos($bytes, '%PDF-') !== 0
        || strpos(substr($bytes, -1024), '%%EOF') === false) {
        return new WP_Error('zigurat_statement_invalid_pdf', 'یک فایل PDF معتبر تا ۵ مگابایت انتخاب کنید.', array('status' => 400));
    }
    $dir = zigurat_prepare_private_application_directory();
    if (is_wp_error($dir)) return $dir;
    $path = trailingslashit($dir) . wp_generate_uuid4() . '.pdf';
    $key = '_zigurat_statement_sample_pdf';
    $old = get_post_meta($id, $key, true);
    if (file_put_contents($path, $bytes) !== strlen($bytes)
        || !update_post_meta($id, $key, wp_slash(array('path' => $path, 'mime' => 'application/pdf')))) {
        if (is_file($path)) wp_delete_file($path);
        return new WP_Error('zigurat_statement_pdf_save_failed', 'ذخیرهٔ PDF انجام نشد.', array('status' => 500));
    }
    zigurat_delete_private_application_file($old);
    return true;
}

add_action('admin_post_zigurat_statement_upload_pdf', static function () {
    if (!zigurat_statement_manager_access()) wp_die('دسترسی مجاز نیست.', '', array('response' => 403));
    $id = absint($_POST['collaborator'] ?? 0);
    check_admin_referer('zigurat_statement_upload_pdf_' . $id);
    $file = $_FILES['statement_pdf'] ?? array();
    $tmp = $file['tmp_name'] ?? '';
    $bytes = is_string($tmp) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file($tmp)
        ? file_get_contents($tmp, false, null, 0, 5 * MB_IN_BYTES + 1)
        : false;
    $result = is_string($bytes) ? zigurat_statement_store_pdf($id, $bytes) : false;
    wp_safe_redirect(add_query_arg('statement-pdf', $result === true ? 'saved' : 'error', zigurat_application_resume_url($id)) . '#collaborator-statement');
    exit;
});

add_action('admin_post_zigurat_statement_pdf', static function () {
    if (!zigurat_statement_manager_access()) wp_die('دسترسی مجاز نیست.', '', array('response' => 403));
    $id = absint($_GET['collaborator'] ?? 0);
    check_admin_referer('zigurat_statement_pdf_' . $id);
    $path = zigurat_statement_partner($id)
        ? zigurat_resolve_private_application_file_path(get_post_meta($id, '_zigurat_statement_sample_pdf', true))
        : false;
    if (!$path) wp_die('فایل پیدا نشد.', '', array('response' => 404));
    nocache_headers();
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="collaborator-statement.pdf"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
});

add_action('before_delete_post', static function ($id, $post) {
    if ($post->post_type === 'partner_application') {
        delete_option('zigurat_statement_' . $id);
        zigurat_delete_private_application_file(get_post_meta($id, '_zigurat_statement_sample_pdf', true));
    }
}, 10, 2);
