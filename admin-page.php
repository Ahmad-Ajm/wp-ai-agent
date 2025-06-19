<?php
// تضمين دوال التصحيح المشتركة
require_once plugin_dir_path(__FILE__) . 'includes/common-debug.php';
/**
 * عرض صفحة الـ AI Agent (الواجهة الأساسية للدردشة).
 */
function wpai_render_agent_page() {
    wpai_debug_log("بدء عرض صفحة WP AI Agent");
    ?>
    <div class="wrap wpai-container">
        <h1>WP AI Agent</h1>

        <div id="container1">
            <div id="container2">
                <!-- قسم الدردشة -->
                <div id="chat-section" class="wpai-chat-window">
                    <h2>الدردشة</h2>
                    <div id="chat-window" class="wpai-messages-container"></div>
                    <div class="wpai-input-area">
                        <textarea id="chat-input" rows="4" placeholder="اكتب رسالتك هنا..."></textarea>
                        <div class="wpai-input-actions">
                            <button id="send-button" class="button button-primary">إرسال</button>
                            <button id="reverse-send-button" class="button">إرسال عكسي</button>
                            <button id="design-site-button" class="button">🎨 تصميم الموقع</button>
                            <button id="upload-image-btn" class="button">📷 رفع صورة</button>
                        </div>
                    </div>
                    <div class="wpai-chat-controls" style="margin-top: 12px;">
                        <button id="save-chat" class="button">حفظ المحادثة</button>
                        <button id="reload-session" class="button">إعادة الجلسة</button>
                    </div>
                </div>

                <!-- سجل التصحيح -->
                <div id="debug-log" class="wpai-debug-log">
                    <h2>سجل التصحيح</h2>
                    <pre></pre>
                </div>

                <!-- سجلات النظام -->
                <div id="system-log-viewer" class="wpai-log-container">
                    <h2>سجلات النظام</h2>
                    <div class="wpai-log-controls" style="margin-top: 8px;">
                        <button id="refresh-logs" class="button">تحديث</button>
                        <button id="clear-logs" class="button">مسح السجلات</button>
                    </div>
                    <div class="wpai-system-logs"></div>
                </div>

                <div id="command-logs" class="wpai-log-container">
                    <h2>سجلات الأوامر</h2>
                    <div class="wpai-log-controls">
                        <button id="refresh-command-logs" class="button">تحديث السجلات</button>
                    </div>
                    <div class="wpai-command-logs"></div>
                </div>
        </div>
        <div id="container3" style="display: none;"></div>
    </div>
</div>
    <script>
    jQuery(function($) {
        function loadCommandLogs() {
            $.post(ajaxurl, {
                action: 'wpai_get_command_logs',
                security: wpAiAgent.nonce
            }, function(response) {
                if (response.success) {
                    $('.wpai-command-logs').html(
                        response.data.logs.map(log =>
                            `<div class="log-entry">
                                <strong>${log.timestamp}</strong> -
                                ${log.command}: ${log.status}
                                <button class="view-log-details button" data-log='${JSON.stringify(log)}'>
                                    التفاصيل
                                </button>
                            </div>`
                        ).join('')
                    );
                }
            });
        }

        $('#refresh-command-logs').click(loadCommandLogs);
        loadCommandLogs();
    });
    </script>
    <?php
    wpai_debug_log("انتهى عرض صفحة WP AI Agent");
}
?>