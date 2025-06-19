<?php
// preview-page.php
if (!defined('ABSPATH')) exit;

function wpai_preview_page() {
    ?>
    <div class="wrap">
        <h1>🖥️ معاينة الموقع الحية</h1>
        <p>تعرض هذه الصفحة نسخة WordPress المثبتة على <code>http://localhost</code>.</p>
        <iframe src="http://localhost" style="width: 100%; height: 700px; border: 1px solid #ccc; border-radius: 6px;"></iframe>
    </div>
    <?php
}
