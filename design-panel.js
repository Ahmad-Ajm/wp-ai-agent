// design-panel.js - بناء واجهة بانيل تصميم الموقع بشكل كامل

jQuery(function($) {
    window.loadDesignPanel = window.showDesignPanel = showDesignPanel;

    let brmtList = []; // جميع الأوصاف
    let brmtIndex = 0;

    function updateBrmtDisplay() {
        const current = brmtList[brmtIndex] || '';
        $('#desc-input').val(current);
        window.designData.brmt = current;
    }

    function showDesignPanel() {
        const container = $('.wpai-container');
        if ($('#design-panel').length > 0 || container.length === 0) return;

        const data = window.dataIni || {};
        window.designData = {
            name: data.name || '',
            desc: data.desc || '',
            type: '',
            colors: {},
            layout: '',
            theme: '',
            plugins: [],
            permalinks: '',
            fonts: {},
            pageBuilder: '',
            pages: [],
            brmt: ''
        };

        // إخفاء شريط ووردبريس الجانبي
        $('#adminmenumain').hide();
        $('#wpcontent').css('margin-left', '0');

        // ضبط أحجام الحاويات
        $('#container2').css({
            'width': '30%',
            'margin': '0',
            'margin-left': 'auto'
        });
        $('#container3').css({
            'width': '70%',
            'display': 'block'
        });

        // إضافة لوحة التصميم إلى container3
        $('#container3').append(`
            <div id="design-panel">
                <h2>🛠️ لوحة تصميم الموقع</h2>

                <label>🧾 اسم الموقع</label>
                <input type="text" id="site-name" value="${data.name || ''}" />

                <label>📘 وصف عام (من إعدادات الموقع)</label>
                <textarea id="site-desc">${data.desc || ''}</textarea>

                <label>📝 وصف مخصص لطريقة عرض الموقع</label>
                <textarea id="desc-input" placeholder="اكتب وصفاً..."></textarea>
                <div style="display:flex;gap:10px;margin:5px 0;">
                    <button id="desc-generate">🔁 توليد وصف</button>
                    <button id="desc-prev">⬅️</button>
                    <button id="desc-next">➡️</button>
                </div>

                <label>🔽 نوع الموقع</label>
                <select id="site-type">
                    <option value="">-- اختر نوعاً --</option>
                    <option value="store">متجر</option>
                    <option value="blog">مدونة</option>
                    <option value="services">خدمات</option>
                    <option value="personal">شخصي</option>
                </select>

                <label>📄 الصفحات المطلوبة</label>
                <div id="page-list" style="display:flex;flex-wrap:wrap;gap:10px;"></div>

                <label>🎨 باندل الألوان</label>
                <div id="color-bundles" style="display:flex;flex-wrap:wrap;gap:10px;"></div>

                <div>
                    <button id="preview-colors">👁️ معاينة الألوان</button>
                </div>

                <div style="margin-top:20px;">
                    <button id="apply-design">✅ تطبيق التصميم</button>
                </div>
            </div>
        `);

        $('#design-panel').css('width', '100%');

        $('#desc-generate').on('click', function() {
            const text = $('#desc-input').val().trim();
            if (!text) return alert("يرجى كتابة وصف");
            if (typeof sendToAI === 'function') sendToAI(`#brmt\n${text}`);
        });

        $('#desc-prev').on('click', function() {
            if (brmtIndex > 0) {
                brmtIndex--;
                updateBrmtDisplay();
            }
        });

        $('#desc-next').on('click', function() {
            if (brmtIndex < brmtList.length - 1) {
                brmtIndex++;
                updateBrmtDisplay();
            }
        });

        $('#site-type').on('change', function() {
            const val = $(this).val();
            const name = $('#site-name').val();
            const desc = $('#desc-input').val();
            if (typeof sendToAI === 'function') sendToAI(`#type\n${name}\n${desc}\n${val}`);
            window.designData.type = val;
        });

        $('#site-name').on('input', function() {
            window.designData.name = $(this).val();
        });
        $('#site-desc').on('input', function() {
            window.designData.desc = $(this).val();
        });
        $('#desc-input').on('input', function() {
            window.designData.brmt = $(this).val();
        });

        $(document).on('wpai_assistant_response', function(e, msg) {
            if (msg.includes('#brmt2')) {
                const newBrmt = msg.split('#brmt2')[1]?.trim();
                if (newBrmt && !brmtList.includes(newBrmt)) {
                    brmtList.push(newBrmt);
                    brmtIndex = brmtList.length - 1;
                    updateBrmtDisplay();
                }
            }
        });

        $('#preview-colors').on('click', function() {
            const desc = $('#desc-input').val();
            const bundle = 'default';
            const tone = 'pastel';
            const name = $('#site-name').val();
            if (typeof sendToAI === 'function') {
                sendToAI(`#preview\nsite: ${name}\ndesc: ${desc}\nbundle: ${bundle}\ntone: ${tone}`);
            }
        });

        $('#apply-design').on('click', function() {
            const confirmApply = confirm("⚠️ قد يؤدي التخصيص إلى تغيير تصميم الموقع. هل ترغب بالمتابعة؟");
            if (!confirmApply) return;

            // إرسال البيانات الحالية فقط بدون بقية الأوصاف
            delete window.designData.brmtList;
            if (typeof sendToAI === 'function') {
                sendToAI(`#apply\n${JSON.stringify(window.designData)}`);
            }
        });
    }

    $('#design-site-button').on('click', function(e) {
        e.preventDefault();
        showDesignPanel();
        if (typeof sendToAI === 'function') sendToAI('#dsn');
    });
});