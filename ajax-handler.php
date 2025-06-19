<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // لا وصول مباشر
}

// تضمين دوال التصحيح المشتركة
require_once plugin_dir_path( __FILE__ ) . 'includes/common-debug.php';


/**
 * حفظ جلسة جديدة في قاعدة البيانات.
 */
add_action( 'wp_ajax_wpai_save_session', function() {
    wpai_debug_log_ajax( 'wpai_save_session - بدء' );
    check_ajax_referer( 'wp_ai_agent_nonce', 'security' );

    $data = isset( $_POST['data'] ) ? $_POST['data'] : null;
    if ( ! $data ) {
        wp_send_json_error( [ 'message' => 'لا توجد بيانات للإرسال.' ] );
        wpai_debug_log_ajax( 'wpai_save_session - خطأ: لا توجد بيانات' );
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'wpai_sessions';
    $inserted = $wpdb->insert(
        $table_name,
        [
            'title'        => sanitize_text_field( 'جلسة ' . date( 'Y-m-d H:i' ) ),
            'summary'      => sanitize_text_field( $data['summary'] ?? '' ),
            'messages'     => wp_json_encode( $data['messages'] ?? [] ),
            'created_at'   => current_time( 'mysql' ),
            'updated_at'   => current_time( 'mysql' ),
            'api_key_hash' => wp_hash_password( $data['api_key'] ?? '' ),
            'user_id'      => get_current_user_id(),
        ],
        [
            '%s', // title
            '%s', // summary
            '%s', // messages
            '%s', // created_at
            '%s', // updated_at
            '%s', // api_key_hash
            '%d', // user_id
        ]
    );

    if ( false === $inserted ) {
        wp_send_json_error( [ 'message' => 'فشل إدخال الجلسة في قاعدة البيانات.' ] );
        wpai_debug_log_ajax( 'wpai_save_session - خطأ: فشل الإدخال' );
    } else {
        wp_send_json_success( [ 'session_id' => $wpdb->insert_id ] );
        wpai_debug_log_ajax( 'wpai_save_session - انتهى بنجاح (ID=' . $wpdb->insert_id . ')' );
    }
} );

/**
 * جلب جميع جلسات المستخدم الحالي.
 */
add_action( 'wp_ajax_wpai_get_sessions', function() {
    wpai_debug_log_ajax( 'wpai_get_sessions - بدء' );
    check_ajax_referer( 'wp_ai_agent_nonce', 'security' );

    global $wpdb;
    $table_name = $wpdb->prefix . 'wpai_sessions';
    $sessions = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, created_at, updated_at, summary FROM {$table_name} WHERE user_id = %d ORDER BY created_at DESC",
            get_current_user_id()
        )
    );

    if ( is_null( $sessions ) ) {
        wp_send_json_error( [ 'message' => 'فشل جلب الجلسات.' ] );
        wpai_debug_log_ajax( 'wpai_get_sessions - خطأ: نتيجة فارغة' );
    } else {
        wp_send_json_success( [ 'sessions' => $sessions ] );
        wpai_debug_log_ajax( 'wpai_get_sessions - انتهى بنجاح (عدد=' . count( $sessions ) . ')' );
    }
} );

/**
 * جلب جلسة معينة (عرضها).
 */
add_action( 'wp_ajax_wpai_get_session', function() {
    wpai_debug_log_ajax( 'wpai_get_session - بدء' );
    check_ajax_referer( 'wp_ai_agent_nonce', 'security' );

    $session_id = isset( $_POST['session_id'] ) ? intval( $_POST['session_id'] ) : 0;
    if ( $session_id <= 0 ) {
        wp_send_json_error( [ 'message' => 'رقم الجلسة غير صالح.' ] );
        wpai_debug_log_ajax( 'wpai_get_session - خطأ: session_id غير صالح' );
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'wpai_sessions';
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT messages, summary FROM {$table_name} WHERE id = %d AND user_id = %d LIMIT 1",
            $session_id,
            get_current_user_id()
        ),
        ARRAY_A
    );

    if ( null === $row ) {
        wp_send_json_error( [ 'message' => 'هذه الجلسة غير موجودة أو لا تملك صلاحيات العرض.' ] );
        wpai_debug_log_ajax( 'wpai_get_session - خطأ: لا توجد نتيجة أو صلاحية' );
    } else {
        wp_send_json_success( [
            'messages' => json_decode( $row['messages'], true ),
            'summary'  => $row['summary'],
        ] );
        wpai_debug_log_ajax( 'wpai_get_session - انتهى بنجاح (ID=' . $session_id . ')' );
    }
} );

/**
 * حذف جلسة معينة.
 */
add_action( 'wp_ajax_wpai_delete_session', function() {
    wpai_debug_log_ajax( 'wpai_delete_session - بدء' );
    check_ajax_referer( 'wp_ai_agent_nonce', 'security' );

    $session_id = isset( $_POST['session_id'] ) ? intval( $_POST['session_id'] ) : 0;
    if ( $session_id <= 0 ) {
        wp_send_json_error( [ 'message' => 'رقم الجلسة غير صالح.' ] );
        wpai_debug_log_ajax( 'wpai_delete_session - خطأ: session_id غير صالح' );
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'wpai_sessions';
    $deleted = $wpdb->delete(
        $table_name,
        [
            'id'      => $session_id,
            'user_id' => get_current_user_id()
        ],
        [
            '%d',
            '%d'
        ]
    );

    if ( false === $deleted ) {
        wp_send_json_error( [ 'message' => 'فشل حذف الجلسة.' ] );
        wpai_debug_log_ajax( 'wpai_delete_session - خطأ: فشل الحذف (ID=' . $session_id . ')' );
    } else {
        wp_send_json_success( [ 'deleted_id' => $session_id ] );
        wpai_debug_log_ajax( 'wpai_delete_session - انتهى بنجاح (ID=' . $session_id . ')' );
    }
} );

/**
 * حفظ مفتاح API (plaintext) في user_meta.
 */
add_action( 'wp_ajax_wpai_save_api_key', function() {
    wpai_debug_log_ajax( 'wpai_save_api_key - بدء' );
    check_ajax_referer( 'wp_ai_agent_nonce', 'security' );

    $key = isset( $_POST['key'] ) ? sanitize_text_field( $_POST['key'] ) : '';
    if ( empty( $key ) ) {
        wp_send_json_error( [ 'message' => 'المفتاح فارغ.' ] );
        wpai_debug_log_ajax( 'wpai_save_api_key - خطأ: المفتاح فارغ' );
        return;
    }

    // نخزن المفتاح الأصلي في user_meta
    update_user_meta( get_current_user_id(), '_wpai_api_key_raw', $key );
    wp_send_json_success();
    wpai_debug_log_ajax( 'wpai_save_api_key - انتهى بنجاح' );
} );

/**
 * جلب مفتاح API الأصلي للمستخدم الحالي.
 */
add_action( 'wp_ajax_wpai_get_api_key', function() {
    wpai_debug_log_ajax( 'wpai_get_api_key - بدء' );
    check_ajax_referer( 'wp_ai_agent_nonce', 'security' );

    $raw = get_user_meta( get_current_user_id(), '_wpai_api_key_raw', true );
    if ( empty( $raw ) ) {
        wp_send_json_error( [ 'message' => 'لا يوجد مفتاح محفوظ.' ] );
        wpai_debug_log_ajax( 'wpai_get_api_key - خطأ: لا يوجد مفتاح' );
    } else {
        wp_send_json_success( [ 'key' => $raw ] );
        wpai_debug_log_ajax( 'wpai_get_api_key - انتهى بنجاح' );
    }
} );

/**
 * مسح مفتاح API (حذف من user_meta).
 */
add_action( 'wp_ajax_wpai_clear_api_key', function() {
    wpai_debug_log_ajax( 'wpai_clear_api_key - بدء' );
    check_ajax_referer( 'wp_ai_agent_nonce', 'security' );

    delete_user_meta( get_current_user_id(), '_wpai_api_key_raw' );
    wp_send_json_success();
    wpai_debug_log_ajax( 'wpai_clear_api_key - انتهى بنجاح' );
} );

/**
 * تسجيل الأحداث (logging) القادمة من الواجهة (logManager).
 */
add_action( 'wp_ajax_wpai_log_event', function() {
    wpai_debug_log_ajax( 'wpai_log_event - بدء' );
    check_ajax_referer( 'wp_ai_agent_nonce', 'security' );

    $message = isset( $_POST['message'] ) ? sanitize_text_field( $_POST['message'] ) : '';
    $type    = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : 'info';

    // يمكن تخزينها في جدول أو ملف حسب الحاجة، هنا نكتفي بتسجيلها في debug.log
    wpai_debug_log( $message, "[type=$type]" );

    wp_send_json_success();
    wpai_debug_log_ajax( 'wpai_log_event - انتهى بنجاح' );
} );

/**
 * جلب سجل الأحداث (logs).
 */
add_action( 'wp_ajax_wpai_get_logs', function() {
    wpai_debug_log_ajax( 'wpai_get_logs - بدء' );
    check_ajax_referer( 'wp_ai_agent_nonce', 'security' );

    // لنفترض أننا نخزن السجل في جدول wpai_events أو نقرأه من ملف debug.log
    // هنا مثال بسيط: نعيد مصفوفة فارغة أو يمكنك تنفيذ جلب القراءات
    $logs = []; // يمكنك تعديلها حسب طريقة التخزين الفعلية

    wp_send_json_success( [ 'logs' => $logs ] );
    wpai_debug_log_ajax( 'wpai_get_logs - انتهى بنجاح' );
} );

/**
 * مسح سجل الأحداث (logs).
 */
add_action( 'wp_ajax_wpai_clear_logs', function() {
    wpai_debug_log_ajax( 'wpai_clear_logs - بدء' );
    check_ajax_referer( 'wp_ai_agent_nonce', 'security' );

    // إذا استخدمت جدولًا أو ملفًا، قم بمسحه هنا
    wp_send_json_success();
    wpai_debug_log_ajax( 'wpai_clear_logs - انتهى بنجاح' );
} );

/**
 * حفظ الذاكرة (memory) في user_meta.
 */
add_action( 'wp_ajax_wpai_save_memory', function() {
    wpai_debug_log_ajax( 'wpai_save_memory - بدء' );
    check_ajax_referer( 'wp_ai_agent_nonce', 'security' );

    $data = isset( $_POST['data'] ) ? $_POST['data'] : null;
    if ( ! is_array( $data ) ) {
        wp_send_json_error( [ 'message' => 'تنسيق بيانات خاطئ.' ] );
        wpai_debug_log_ajax( 'wpai_save_memory - خطأ: تنسيق بيانات خاطئ' );
        return;
    }

    // نخزن الذاكرة في user_meta
    update_user_meta( get_current_user_id(), '_wpai_memory', wp_json_encode( $data ) );
    wp_send_json_success();
    wpai_debug_log_ajax( 'wpai_save_memory - انتهى بنجاح' );
} );

/**
 * جلب الذاكرة (memory) للمستخدم الحالي.
 */
add_action( 'wp_ajax_wpai_load_memory', function() {
    wpai_debug_log_ajax( 'wpai_load_memory - بدء' );
    check_ajax_referer( 'wp_ai_agent_nonce', 'security' );

    $json = get_user_meta( get_current_user_id(), '_wpai_memory', true );
    if ( ! $json ) {
        wp_send_json_success( [ 'data' => [ 'history' => [], 'summary' => '' ] ] );
        wpai_debug_log_ajax( 'wpai_load_memory - انتهى بنجاح: لا توجد بيانات' );
        return;
    }

    $data = json_decode( $json, true );
    wp_send_json_success( [ 'data' => $data ] );
    wpai_debug_log_ajax( 'wpai_load_memory - انتهى بنجاح: تم جلب الذاكرة' );
} );

/**
 * جلب معلومات الموقع (site info) مثل اسم الموقع، الوصف، الإعدادات الأساسية، إلخ.
 */
add_action( 'wp_ajax_wpai_get_site_info', function() {
    wpai_debug_log_ajax( 'wpai_get_site_info - بدء' );
    check_ajax_referer( 'wp_ai_agent_nonce', 'security' );

    $site_info = array(
        'name'        => get_bloginfo( 'name' ),
        'description' => get_bloginfo( 'description' ),
        'url'         => get_site_url(),
        'admin_email' => get_bloginfo( 'admin_email' ),
        // أضف المزيد من المعلومات إذا لزم الأمر
    );

    wp_send_json_success( [ 'data' => $site_info ] );
    wpai_debug_log_ajax( 'wpai_get_site_info - انتهى بنجاح' );
} );

/**
 * إجراء جديد للتعامل مع JSON الذي يرسله الذكاء بعد #code
 * يقوم بإنشاء الصفحات وتعيين الألوان والإعدادات بناءً على الهيكل المرسل.
 */


add_action( 'wp_ajax_wpai_execute_code', 'wpai_execute_code_callback' );
function wpai_execute_code_callback() {
    // Use the same nonce used when localising the scripts.
    check_ajax_referer( 'wp_ai_agent_nonce', 'security' );

    $type    = isset( $_POST['type'] )    ? sanitize_text_field( $_POST['type'] )    : '';
    $payload = isset( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : '';

    try {
        // 1. فك JSON والتحقق من صحته
        $data = json_decode( $payload, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            throw new ValidationException(
                'بنية JSON غير صالحة',
                [ 'json_error' => json_last_error_msg() ]
            );
        }

        // 2. التحقق من سلامة البيانات حسب النوع
        // Classes reside under the plugin namespace.
        $validator = new \WP_AI_Agent\Includes\CodeValidator();
        $validator->validate( $type, $data );

        // 3. إنشاء المنفذ المناسب وتنفيذ الكود
        $executor = \WP_AI_Agent\Includes\CodeExecutorFactory::create( $type );
        $result   = $executor->execute( $data );

        // 4. إعادة النتيجة بنجاح
        wp_send_json_success( $result );

    } catch ( \WP_AI_Agent\Includes\ValidationException $e ) {
        // أخطاء التحقق
        wp_send_json_error( [
            'type'    => 'validation',
            'message' => $e->getMessage(),
            'errors'  => $e->getDetails(),
        ] );

    } catch ( \WP_AI_Agent\Includes\ExecutionException $e ) {
        // أخطاء أثناء التنفيذ
        wp_send_json_error( [
            'type'    => 'execution',
            'message' => $e->getMessage(),
            'trace'   => $e->getTrace(),
        ] );

    } catch ( Exception $e ) {
        // أي خطأ عام غير متوقع
        wp_send_json_error( [
            'type'    => 'general',
            'message' => $e->getMessage(),
        ] );
    }
}
