<?php
// File: rest-command-handler.php - تنفيذ أوامر الذكاء الصناعي عبر REST API

if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
    register_rest_route('wpai/v1', '/execute', [
        'methods' => 'POST',
        'callback' => 'wpai_handle_command',
        'permission_callback' => function ($request) {
            return is_ssl() && wpai_verify_api_key($request);
        },
    ]);

    register_rest_route('wpai/v1', '/commands', [
        'methods' => 'GET',
        'callback' => 'wpai_get_available_commands',
        'permission_callback' => function($request) {
            return wpai_verify_api_key($request);
        }
    ]);
});


function wpai_verify_api_key($request) {
    $api_key   = $request->get_header('X-WPAI-API-KEY');
    $stored_key = get_option('wpai_global_api_key');

    // إذا لم يتم تعيين مفتاح عام، نتخطى التحقق للسماح بالطلبات
    if (empty($stored_key)) {
        wpai_debug_log('wpai_verify_api_key: bypassed - no global key set');
        return true;
    }

    $result = $api_key && hash_equals($stored_key, $api_key);
    wpai_debug_log($result ? 'wpai_verify_api_key: success' : 'wpai_verify_api_key: failed');
    return $result;
}

function wpai_handle_command(WP_REST_Request $request) {
    $command = sanitize_text_field($request->get_param('command'));
    $params = $request->get_param('params');

    wpai_debug_log('wpai_handle_command received', '[command=' . $command . ' params=' . wp_json_encode($params) . ']');

    $allowed_commands = [
        'create_post', 'create_page', 'inject_css',
        'update_option', 'install_plugin', 'optimize_site', 'create_menu'
    ];

    if (!in_array($command, $allowed_commands)) {
        wpai_debug_log('wpai_handle_command: invalid command', '[command=' . $command . ']');
        return new WP_Error('invalid_command', 'الأمر غير مسموح به', ['status' => 403]);
    }

    $result = wpai_execute_command($command, $params);
    if (is_wp_error($result)) {
        wpai_debug_log('wpai_handle_command result error', '[error=' . $result->get_error_message() . ']');
    } else {
        wpai_debug_log('wpai_handle_command result', '[result=' . wp_json_encode($result) . ']');
    }
    wpai_log_action($command, $params, $result);
    return rest_ensure_response($result);
}

function wpai_execute_command($command, $params) {
    wpai_debug_log('wpai_execute_command', '[command=' . $command . ' params=' . wp_json_encode($params) . ']');
    switch ($command) {
        case 'create_post':
            return wpai_create_post($params);
        case 'create_page':
            return wpai_create_page($params);
        case 'inject_css':
            return wpai_inject_css($params);
        case 'update_option':
            return wpai_update_option($params);
        case 'install_plugin':
            return wpai_install_plugin($params);
        case 'optimize_site':
            return wpai_optimize_site($params);
        case 'create_menu':
            return wpai_create_menu($params);
        default:
            return new WP_Error('unknown_command', 'الأمر غير معروف', ['status' => 400]);
    }
}

function wpai_create_post($params) {
    if (empty($params['title']) || empty($params['content'])) {
        return new WP_Error('missing_params', 'العنوان والمحتوى مطلوبان', ['status' => 400]);
    }
    $post_id = wp_insert_post([
        'post_title'   => sanitize_text_field($params['title']),
        'post_content' => wp_kses_post($params['content']),
        'post_status'  => sanitize_text_field($params['status'] ?? 'draft'),
        'post_type'    => sanitize_text_field($params['type'] ?? 'post')
    ]);
    return [
        'success' => !is_wp_error($post_id),
        'post_id' => $post_id,
        'message' => 'تم إنشاء المنشور بنجاح'
    ];
}

function wpai_create_page($params) {
    if (empty($params['title'])) {
        return new WP_Error('missing_params', 'عنوان الصفحة مطلوب', ['status' => 400]);
    }
    $page_id = wp_insert_post([
        'post_title'   => sanitize_text_field($params['title']),
        'post_content' => wp_kses_post($params['content'] ?? ''),
        'post_status'  => sanitize_text_field($params['status'] ?? 'publish'),
        'post_type'    => 'page'
    ]);
    return [
        'success' => !is_wp_error($page_id),
        'page_id' => $page_id,
        'message' => 'تم إنشاء الصفحة بنجاح'
    ];
}

function wpai_inject_css($params) {
    if (empty($params['css'])) {
        return new WP_Error('missing_params', 'كود CSS مطلوب', ['status' => 400]);
    }
    $css = sanitize_textarea_field($params['css']);
    $handle = sanitize_key($params['handle'] ?? 'custom-ai-css');
    update_option('ai_custom_css_' . $handle, $css);
    return ['success' => true, 'message' => 'تم إضافة CSS بنجاح'];
}

function wpai_update_option($params) {
    $protected_options = ['siteurl', 'home', 'admin_email', 'new_admin_email', 'wpai_global_api_key', 'users_can_register'];
    if (in_array($params['key'], $protected_options)) {
        return new WP_Error('protected_option', 'هذا الإعداد محمي ولا يمكن تعديله', ['status' => 403]);
    }
    $key = sanitize_key($params['key']);
    $value = maybe_serialize($params['value']);
    update_option($key, $value);
    return ['success' => true, 'message' => 'تم تحديث الإعداد بنجاح'];
}

function wpai_install_plugin($params) {
    if (empty($params['plugin_slug'])) {
        return new WP_Error('missing_params', 'معرف الإضافة مطلوب', ['status' => 400]);
    }
    require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $api = plugins_api('plugin_information', ['slug' => $params['plugin_slug']]);
    if (is_wp_error($api)) {
        return ['success' => false, 'message' => 'لم يتم العثور على الإضافة: ' . $params['plugin_slug']];
    }
    $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
    $install = $upgrader->install($api->download_link);
    if ($install === true) {
        activate_plugin($upgrader->plugin_info());
        return ['success' => true, 'message' => 'تم تثبيت وتفعيل الإضافة'];
    }
    return ['success' => false, 'message' => 'فشل تثبيت الإضافة'];
}

function wpai_optimize_site($params) {
    if (function_exists('wp_cache_flush')) wp_cache_flush();
    $report = [];
    $updates = get_site_transient('update_core');
    if (isset($updates->updates[0])) {
        $report['core_update'] = 'يتوفر تحديث: ' . $updates->updates[0]->version;
    }
    $all_plugins = get_plugins();
    $active_plugins = get_option('active_plugins', []);
    $inactive_plugins = array_diff_key($all_plugins, array_flip($active_plugins));
    if (!empty($inactive_plugins)) {
        $report['inactive_plugins'] = 'يوجد ' . count($inactive_plugins) . ' إضافات غير مفعلة';
    }
    return ['success' => true, 'message' => 'تم تحسين الموقع', 'report' => $report];
}

function wpai_create_menu($params) {
    if (empty($params['menu_name'])) {
        return new WP_Error('missing_params', 'اسم القائمة مطلوب', ['status' => 400]);
    }
    $menu_id = wp_create_nav_menu($params['menu_name']);
    if (is_wp_error($menu_id)) {
        return ['success' => false, 'message' => $menu_id->get_error_message()];
    }
    if (isset($params['menu_items']) && is_array($params['menu_items'])) {
        foreach ($params['menu_items'] as $item) {
            wp_update_nav_menu_item($menu_id, 0, [
                'menu-item-title' => $item['title'],
                'menu-item-url' => $item['url'],
                'menu-item-status' => 'publish'
            ]);
        }
    }
    if (isset($params['theme_location'])) {
        $locations = get_theme_mod('nav_menu_locations');
        $locations[$params['theme_location']] = $menu_id;
        set_theme_mod('nav_menu_locations', $locations);
    }
    return ['success' => true, 'menu_id' => $menu_id, 'message' => 'تم إنشاء القائمة بنجاح'];
}

function wpai_log_action($command, $params, $result) {
    $log_entry = [
        'timestamp' => current_time('mysql'),
        'command' => $command,
        'params' => $params,
        'result' => $result,
        'status' => isset($result['success']) ? ($result['success'] ? 'success' : 'error') : 'unknown'
    ];
    $logs = get_option('wpai_command_logs', []);
    $logs[] = $log_entry;
    if (count($logs) > 20) {
        $logs = array_slice($logs, -20);
    }
    update_option('wpai_command_logs', $logs);
}

function wpai_get_available_commands() {
    return [
        'commands' => [
            'create_post' => 'إنشاء منشور جديد',
            'create_page' => 'إنشاء صفحة جديدة',
            'inject_css' => 'حقن أكواد CSS',
            'update_option' => 'تحديث إعداد ووردبريس',
            'install_plugin' => 'تثبيت إضافة من ووردبريس',
            'optimize_site' => 'تحسين أداء وأمان الموقع',
            'create_menu' => 'إنشاء قائمة مخصصة'
        ],
        'version' => '1.0'
    ];
}

add_action('wp_enqueue_scripts', function () {
    foreach (['custom-ai-css'] as $handle) {
        $css = get_option('ai_custom_css_' . $handle);
        if ($css) {
            wp_register_style($handle, false);
            wp_enqueue_style($handle);
            wp_add_inline_style($handle, $css);
        }
    }
});

add_action('admin_enqueue_scripts', function () {
    wp_localize_script('wpai-1', 'dataIni', array_merge(
        [
            'siteName' => get_bloginfo('name'),
            'siteUrl' => home_url(),
        ],
        isset($GLOBALS['dataIni']) ? $GLOBALS['dataIni'] : []
    ));
});
