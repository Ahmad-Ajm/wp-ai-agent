<?php
/*
Plugin Name: WP AI Agent
Description: إضافة لدمج واجهة دردشة مع أنظمة ذكاء اصطناعي في ووردبريس.
Version: 0.5.9
Author: Ahmad-Ajm
*/

if (!defined('ABSPATH')) {
    exit; // لا وصول مباشر
}

/**
 * دالة مساعدة لتسجيل رسائل التصحيح.
 * تسجّل الرسائل في error_log إذا كان WP_DEBUG مفعلًا.
 *
 * @param string $message رسالة التتبع أو الخطأ.
 * @param string $context (اختياري) معلومات إضافية.
 */
function wpai_debug_log($message, $context = '') {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        $file = isset($caller['file']) ? basename($caller['file']) : '';
        $func = isset($caller['function']) ? $caller['function'] : '';
        $entry = sprintf("[%s::%s] %s %s", $file, $func, $message, $context);
        error_log($entry);
    }
}

/**
 * عند تفعيل الإضافة: إنشاء جدول الجلسات في قاعدة البيانات إذا لم يكن موجودًا.
 */
function wpai_activate_plugin() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpai_sessions';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `title` varchar(255) NOT NULL,
        `summary` text NOT NULL,
        `messages` longtext NOT NULL,
        `created_at` datetime NOT NULL,
        `updated_at` datetime NOT NULL,
        `api_key_hash` varchar(255) NOT NULL,
        `user_id` bigint(20) unsigned NOT NULL,
        PRIMARY KEY (`id`)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';


    dbDelta($sql);
    wpai_debug_log("تم تشغيل wpai_activate_plugin: تم إنشاء جدول الجلسات إن لم يكن موجودًا");
}
register_activation_hook(__FILE__, 'wpai_activate_plugin');
require_once plugin_dir_path(__FILE__) . 'preview-page.php';
/**
 * تسجيل إضافة صفحة لوحة التحكم وصفحة إعدادات وصفحة إدارة الجلسات.
 */
add_action('admin_menu', 'wpai_register_admin_pages');
function wpai_register_admin_pages() {
    wpai_debug_log("بدء إضافة القوائم إلى لوحة التحكم");

    // الصفحة الرئيسية للـ AI Agent (الدردشة)
    add_menu_page(
        'WP AI Agent',
        'WP AI Agent',
        'manage_options',
        'wp-ai-agent',
        'wpai_render_agent_page',
        'dashicons-format-chat',
        3
    );

    // صفحة الإعدادات (Settings)
    add_submenu_page(
        'wp-ai-agent',
        'WP AI Agent Settings',
        'الإعدادات',
        'manage_options',
        'wp-ai-settings',
        'wpai_render_settings_page'
    );

add_submenu_page(
    'wp-ai-agent', // أو slug القائمة الرئيسية الخاصة بك
    'معاينة حية', // عنوان الصفحة
    'Live Preview', // اسم في القائمة
    'manage_options',
    'wpai-preview',
    'wpai_preview_page'
);


    // صفحة إدارة الجلسات
    add_submenu_page(
        'wp-ai-agent',
        'إدارة الجلسات',
        'إدارة الجلسات',
        'manage_options',
        'wp-ai-sessions',
        'wpai_render_sessions_page'
    );

    wpai_debug_log("انتهى إضافة القوائم إلى لوحة التحكم");
}

/**
 * تحميل ملفات الـ CSS و JS في صفحات الـ Admin الخاصة بالإضافة.
 */
add_action('admin_enqueue_scripts', 'wpai_enqueue_admin_assets');
function wpai_enqueue_admin_assets($hook) {
    $site_info = array(
        'name' => get_bloginfo('name'),
        'desc' => get_bloginfo('description'),
        'home' => home_url(),
        'admin' => admin_url(),
        'locale' => get_locale()
    );

    // نحرص على تحميل الأكواد فقط في صفحات هذه الإضافة
    $allowed_hooks = [
        'toplevel_page_wp-ai-agent',
        'wp-ai-agent_page_wp-ai-settings',
        'wp-ai-agent_page_wp-ai-sessions'
    ];
    if (!in_array($hook, $allowed_hooks, true)) {
        return;
    }

    wpai_debug_log("تحميل ملفات CSS/JS لصفحة: $hook");



    // تحميل CSS
    if (file_exists(plugin_dir_path(__FILE__) . 'design-panel.css')) {
        wp_enqueue_style('wpai-design-panel-css', plugin_dir_url(__FILE__) . 'design-panel.css', [], '1.0');
    }
    wp_enqueue_style(
        'wpai-admin-style',
        plugin_dir_url(__FILE__) . 'admin.css',
        [],
        '1.1' // إصدار محدث لضمان تحميل التغييرات
    );

    // تحميل jQuery أولًا
    wp_enqueue_script('jquery');

    // قائمة الاعتماديات والإصدار
    $js_deps = ['jquery'];
    $version_tag = '1.0';

    // التحميل المشترك لجميع الصفحات
    wp_enqueue_script(
        'wpai-1',
        plugin_dir_url(__FILE__) . '1.js',
        $js_deps,
        $version_tag,
        true
    );
    wp_enqueue_script(
        'wpai-2',
        plugin_dir_url(__FILE__) . '2.js',
        $js_deps,
        $version_tag,
        true
    );
    wp_enqueue_script(
        'wpai-3',
        plugin_dir_url(__FILE__) . '3.js',
        $js_deps,
        $version_tag,
        true
    );
    wp_enqueue_script(
        'wpai-sessions',
        plugin_dir_url(__FILE__) . 'sessions.js',
        $js_deps,
        $version_tag,
        true
    );
    wp_enqueue_script(
        'wpai-tabs',
        plugin_dir_url(__FILE__) . 'design-panel.js',
        $js_deps,
        $version_tag,
        true
    );

    // Provide basic site information to the front end.
    wp_localize_script('wpai-1', 'dataIni', $site_info);

    // تمرير متغيرات PHP إلى 1.js بما فيها البرومبت الرئيسي
    $prompt_file = plugin_dir_path(__FILE__) . 'prompt.txt';
    $base_prompt = '';
    if (file_exists($prompt_file)) {
        $base_prompt = trim(file_get_contents($prompt_file));
    }

    wp_localize_script(
        'wpai-1',
        'wpAiAgent',
        [
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('wp_ai_agent_nonce'),
            'sessionId'  => session_id() ?: uniqid(),
            'basePrompt' => $base_prompt,
            'userId'     => get_current_user_id(),
            'pluginUrl'  => plugin_dir_url(__FILE__),
        ]
    );
}

/**
 * تضمين ملفات صفحات الإدارة ومعالج AJAX.
 */
require_once plugin_dir_path(__FILE__) . 'admin-page.php';
require_once plugin_dir_path(__FILE__) . 'ajax-handler.php';
require_once plugin_dir_path(__FILE__) . 'includes/rest-command-handler.php';


/**
 * عرض صفحة الإعدادات (Settings).
 */
function wpai_render_settings_page() {
    wpai_debug_log("بدء عرض صفحة الإعدادات");

    // جلب المفتاح الحالي (إذا موجود)
    $current_key = get_user_meta(get_current_user_id(), '_wpai_api_key_raw', true);
    ?>
    <div class="wrap wpai-container">
        <h1>إعدادات WP AI Agent</h1>

        <form method="post" action="">
            <?php wp_nonce_field('wp_ai_agent_nonce', 'security'); ?>

            <!-- اختيار المزود -->
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="ai-provider-select">إعدادات المزود:</label></th>
                    <td>
                        <select id="ai-provider-select" name="ai_provider">
                            <option value="gpt">OpenAI GPT-4</option>
                            <option value="deepseek">DeepSeekCoder</option>
                        </select>
                    </td>
                </tr>
            </table>

            <!-- حقل مفتاح الـ API -->
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="api-key-input">API مفتاح الـ:</label></th>
                    <td>
                        <input type="password" id="api-key-input" name="api_key" value="<?php echo esc_attr($current_key ? '********' : ''); ?>" placeholder="أدخل مفتاح API هنا" style="width:300px;">
                        <p class="description">إذا كنت تريد تغيير المفتاح، أدخله هنا ثم اضغط “حفظ المفتاح”.</p>
                        <p class="description">ترك هذا الحقل فارغًا يعطّل التحقق من الطلبات عبر REST.</p>
                    </td>
                </tr>
            </table>

            <!-- أزرار الحفظ والمسح -->
            <p class="submit">
                <button id="save-api-key" class="button button-primary">حفظ المفتاح</button>
                <button id="clear-api-key" class="button">مسح المفتاح</button>
            </p>
        </form>
    </div>
    <?php
    wpai_debug_log("انتهى عرض صفحة الإعدادات");
}

/**
 * عرض صفحة إدارة الجلسات.
 */
function wpai_render_sessions_page() {
    wpai_debug_log("بدء عرض صفحة إدارة الجلسات");
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpai_sessions';

    // نحضر الجلسات لتوليد الجزء الثابت في الـ HTML
    $sessions = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, created_at, updated_at, summary FROM {$table_name} WHERE user_id = %d ORDER BY created_at DESC",
            get_current_user_id()
        )
    );
    ?>
    <div class="wrap wpai-container">
        <h1>إدارة جلسات الذكاء الاصطناعي</h1>
        <div id="wpai-sessions-container">
            <?php if (!$sessions || empty($sessions)) : ?>
                <p>لا توجد جلسات محفوظة.</p>
            <?php else : ?>
                <?php foreach ($sessions as $session) : ?>
                    <div class="session-row" data-id="<?php echo esc_attr($session->id); ?>">
                        <span class="session-id"><?php echo esc_html($session->id); ?></span>
                        <span class="session-date"><?php echo esc_html(date('Y-m-d H:i', strtotime($session->created_at))); ?></span>
                        <button class="button view-session" data-id="<?php echo esc_attr($session->id); ?>">عرض</button>
                        <button class="button delete-session" data-id="<?php echo esc_attr($session->id); ?>">حذف</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
    wpai_debug_log("انتهى عرض صفحة إدارة الجلسات");
}
?>