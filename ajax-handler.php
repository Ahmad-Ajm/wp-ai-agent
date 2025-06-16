<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // لا وصول مباشر
}

/**
 * دالة مساعدة لتسجيل رسائل AJAX في debug.log.
 *
 * @param string $action اسم هوك AJAX الحالي مع الحالة (بدء/انتهاء).
 */
function wpai_debug_log_ajax( $action ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( "[ajax-handler.php::$action] استدعاء AJAX: $action" );
    }
}

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
add_action( 'wp_ajax_wpai_execute_code', 'wpai_execute_code_handler' );
function wpai_execute_code_handler() {
    // تحقق من صحة النونْس
    if ( empty( $_POST['security'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['security'] ), 'wpai_nonce' ) ) {
        wp_send_json_error( [ 'message' => 'فشل التحقق الأمني.' ] );
    }

    // الحصول على الحمولة (JSON) وفك تشفيرها
    if ( empty( $_POST['payload'] ) ) {
        wp_send_json_error( [ 'message' => 'لا توجد بيانات payload.' ] );
    }
    $payload_raw = wp_unslash( $_POST['payload'] );
    $data = json_decode( $payload_raw, true );
    if ( null === $data ) {
        wp_send_json_error( [ 'message' => 'تعذّر فك ترميز JSON.' ] );
    }

    // التوقعات: الهيكل يكون بهذا الشكل:
    // {
    //   "site": {
    //     "title": "عنوان الموقع",
    //     "pages": [
    //       { "name": "...", "content": "..." },
    //       { "name": "...", "content": "..." },
    //       ...
    //     ],
    //     "theme": {
    //       "colors": {
    //           "primary": "#ffcc00",
    //           "secondary": "#00ccff",
    //           "background": "#ffffff"
    //       }
    //     }
    //   }
    // }

    $result_messages = [];

    // 1. تغيير عنوان الموقع مباشرة (option 'blogname')
    if ( isset( $data['site']['title'] ) ) {
        update_option( 'blogname', sanitize_text_field( $data['site']['title'] ) );
        $result_messages[] = "تم ضبط عنوان الموقع: " . sanitize_text_field( $data['site']['title'] );
    }

    // 2. ضبط الألوان في خيارات السمة (Theme Mod) إذا وُجدت
    if ( isset( $data['site']['theme']['colors'] ) && is_array( $data['site']['theme']['colors'] ) ) {
        $colors = $data['site']['theme']['colors'];
        // على سبيل المثال، نفرض أننا نحفظها كـ option حتى يقرأها CSS لاحقًا
        update_option( 'wpai_theme_color_primary', sanitize_hex_color( $colors['primary'] ) );
        update_option( 'wpai_theme_color_secondary', sanitize_hex_color( $colors['secondary'] ) );
        update_option( 'wpai_theme_color_background', sanitize_hex_color( $colors['background'] ) );
        $result_messages[] = "تم ضبط ألوان السمة (primary, secondary, background).";
    }

    // 3. إنشاء الصفحات الواردة أو تحديثها
    if ( isset( $data['site']['pages'] ) && is_array( $data['site']['pages'] ) ) {
        foreach ( $data['site']['pages'] as $page_data ) {
            if ( ! isset( $page_data['name'] ) ) {
                continue;
            }
            $page_title = sanitize_text_field( $page_data['name'] );
            $page_content = '';
            if ( isset( $page_data['content'] ) ) {
                // إذا كان النص بسيطًا
                if ( is_string( $page_data['content'] ) ) {
                    $page_content = wp_kses_post( $page_data['content'] );
                }
                // إذا كان مصفوفة (مثل Products)
                elseif ( is_array( $page_data['content'] ) ) {
                    // نحمّل محتوى وهمي على شكل قائمة
                    $page_content .= "<ul>";
                    foreach ( $page_data['content'] as $item ) {
                        if ( is_array( $item ) && isset( $item['product_name'] ) ) {
                            $item_name = sanitize_text_field( $item['product_name'] );
                            $item_desc = isset( $item['description'] ) ? sanitize_text_field( $item['description'] ) : '';
                            $page_content .= "<li><strong>{$item_name}</strong>: {$item_desc}</li>";
                        }
                    }
                    $page_content .= "</ul>";
                }
            }

            // تحقق إذا كانت الصفحة موجودة بالفعل
            $existing = get_page_by_title( $page_title, OBJECT, 'page' );
            if ( $existing ) {
                // حدِّث المحتوى فقط
                wp_update_post( [
                    'ID'           => $existing->ID,
                    'post_content' => $page_content,
                ] );
                $result_messages[] = "تم تحديث صفحة «{$page_title}».";
            } else {
                // إنشاؤها
                $new_id = wp_insert_post( [
                    'post_title'   => $page_title,
                    'post_content' => $page_content,
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                ] );
                if ( is_wp_error( $new_id ) ) {
                    $result_messages[] = "❌ خطأ في إنشاء صفحة «{$page_title}»: " . $new_id->get_error_message();
                } else {
                    $result_messages[] = "✅ تم إنشاء صفحة «{$page_title}».";
                }
            }
        }
    }

    // 4. الرد بنجاح مع تفاصيل الإجراءات التي تمت
    wp_send_json_success( [ 'message' => implode("\n", $result_messages) ] );
}

