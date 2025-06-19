<?php
// common-debug.php - دوال تصحيح موحدة للإضافة

if (!function_exists('wpai_debug_log')) {
    function wpai_debug_log($message, $context = '') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $prefix = $context ? "[$context] " : '';
            error_log($prefix . $message);
        }
    }
}

if (!function_exists('wpai_debug_log_ajax')) {
    function wpai_debug_log_ajax($action) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[AJAX::$action] تنفيذ إجراء AJAX: $action");
        }
    }
}
