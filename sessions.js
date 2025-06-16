jQuery(function($){
    const sessionManager = {
        init: function() {
            this.bindEvents();
            this.loadSessions();
            this.injectImageUploadButton(); // إضافة زر رفع الصورة
        },

        bindEvents: function() {
            $(document)
                .on('click', '.view-session', this.viewSession.bind(this))
                .on('click', '.delete-session', this.deleteSession.bind(this))
                .on('click', '#refresh-sessions', this.loadSessions.bind(this))
                .on('click', '#upload-image-btn', function() {
                    $('#hidden-image-input').click();
                });
        },

        loadSessions: function() {
            wpAiUI.appendLog("⬇️ طلب جلب الجلسات");
            $.post(wpAiAgent.ajaxUrl, { action: 'wpai_get_sessions', security: wpAiAgent.nonce }, function(response) {
                if (response.success) {
                    this.renderSessions(response.data.sessions);
                    wpAiUI.appendLog("✅ تم جلب الجلسات بنجاح");
                } else {
                    wpAiUI.appendLog("❌ فشل جلب الجلسات");
                }
            }.bind(this)).fail(function() {
                wpAiUI.appendLog("❌ خطأ في الاتصال عند جلب الجلسات");
            });
        },

        renderSessions: function(sessions) {
            const container = $('#wpai-sessions-container').empty();
            if (!sessions || sessions.length === 0) {
                container.append("<p>لا توجد جلسات محفوظة.</p>");
                return;
            }
            sessions.forEach(session => {
                const row = $(`
                    <div class="session-row" data-id="${session.id}">
                        <span class="session-id">${session.id}</span>
                        <span class="session-date">${session.created_at}</span>
                        <button class="button view-session" data-id="${session.id}">عرض</button>
                        <button class="button delete-session" data-id="${session.id}">حذف</button>
                    </div>
                `);
                container.append(row);
            });
        },

        viewSession: function(e) {
            e.preventDefault();
            const sessionId = $(e.currentTarget).data('id');
            wpAiUI.appendLog(`⬇️ طلب عرض الجلسة #${sessionId}`);
            $.post(wpAiAgent.ajaxUrl, { action: 'wpai_get_session', session_id: sessionId, security: wpAiAgent.nonce }, function(response) {
                if (response.success) {
                    window.memoryManager.history = response.data.messages;
                    window.memoryManager.summary = response.data.summary;
                    wpAiUI.addMessage('system', "✅ تم تحميل الجلسة " + sessionId);
                    wpAiUI.appendLog(`✅ تمت إضافة بيانات الجلسة إلى الذاكرة (#${sessionId})`);
                } else {
                    wpAiUI.appendLog(`❌ فشل تحميل الجلسة #${sessionId}`);
                }
            }).fail(function() {
                wpAiUI.appendLog(`❌ خطأ في الاتصال عند عرض الجلسة #${sessionId}`);
            });
        },

        deleteSession: function(e) {
            e.preventDefault();
            const sessionId = $(e.currentTarget).data('id');
            if (!confirm("هل تريد حذف هذه الجلسة؟")) return;
            wpAiUI.appendLog(`⬇️ طلب حذف الجلسة #${sessionId}`);
            $.post(wpAiAgent.ajaxUrl, { action: 'wpai_delete_session', session_id: sessionId, security: wpAiAgent.nonce }, function(response) {
                if (response.success) {
                    wpAiUI.appendLog(`✅ تم حذف الجلسة #${sessionId}`);
                    this.loadSessions();
                } else {
                    wpAiUI.appendLog(`❌ فشل حذف الجلسة #${sessionId}`);
                }
            }.bind(this)).fail(function() {
                wpAiUI.appendLog(`❌ خطأ في الاتصال عند حذف الجلسة #${sessionId}`);
            });
        },

        /**
         * إضافة زر رفع الصورة وعنصر مُدخل ملفات مخفي،
         * وربط حدث onchange لقراءة الملف وتحويله إلى Base64 ثم إرساله للذكاء.
         */
        injectImageUploadButton: function() {
            // 1. أنشئ عنصر input مخفي من نوع file
            const hiddenInput = $('<input>')
                .attr('type', 'file')
                .attr('id', 'hidden-image-input')
                .attr('accept', 'image/*')
                .css('display', 'none')
                .on('change', function(event) {
                    const file = event.target.files[0];
                    if (!file) return;

                    // تحقق من أن الملف صورة
                    if (!file.type.startsWith('image/')) {
                        alert('الرجاء اختيار ملف صورة فقط');
                        return;
                    }

                    // قراءتها وتحويلها إلى Base64
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const base64Data = e.target.result.split(',')[1]; // نحذف الجزء الأول
                        const promptText = `#img ${base64Data}`;
                        // إضافة رسالة للمستخدم في المحادثة
                        wpAiUI.addMessage('user', '📤 تم إرسال صورة إلى الذكاء');
                        // أرسل للذكاء
                        if (typeof window.sendToAI === 'function') {
                            window.sendToAI(promptText, true);
                        } else {
                            wpAiUI.appendLog("❌ دالة sendToAI غير متوفرة");
                        }
                    };
                    reader.readAsDataURL(file);

                    // إعادة تهيئة قيمة الحقل
                    $(this).val('');
                });

            // 2. أضف العنصر المخفي إلى جسم الصفحة
            $('body').append(hiddenInput);
        }
    };

    sessionManager.init();

    /**
     * التحقق بعد وصول رسالة من الذكاء تحتوي #code
     * إذا كانت الرسالة تحتوي JSON بعد #code، نفككه ثم نرسله إلى السيرفر لتنفيذ الكود
     */
    $(document).on('wpai_assistant_response', function(e, aiMessage) {
        // aiMessage: النص الكامل الذي ظهر في الدردشة من قبل assistant
        if (!aiMessage.includes('#code')) return;

        // استخراج الجزء بعد "#code"
        const parts = aiMessage.split('#code');
        if (parts.length < 2) return;
        let codeBlock = parts[1].trim();
        wpAiUI.appendLog( codeBlock);

        // نتوقع أن يكون JSON صالح (يبدأ بـ { وينتهي بـ })
        if (codeBlock.startsWith('{') && codeBlock.endsWith('}')) {
            let jsonData = null;
            try {
                jsonData = JSON.parse(codeBlock);
            } catch (err) {
                wpAiUI.appendLog("❌ خطأ في فك JSON من الرسالة: " + err.message);
                return;
            }

            // أرسل JSON إلى السيرفر عبر AJAX لإنشاء الصفحات وضبط الخيارات
            wpAiUI.appendLog("⬇️ إرسال JSON للتنفيذ: " + JSON.stringify(jsonData));
            $.post(wpAiAgent.ajaxUrl, {
                action: 'wpai_execute_code',
                payload: JSON.stringify(jsonData),
                security: wpAiAgent.nonce
            }, function(response) {
                if (response.success) {
                    wpAiUI.appendLog("✅ تم تنفيذ الكود بنجاح");
                    wpAiUI.addMessage('system', "✅ تم تنفيذ التعديلات البرمجية بنجاح");
                } else {
                    wpAiUI.appendLog("❌ فشل تنفيذ الكود: " + response.data.message);
                    wpAiUI.addMessage('system', "❌ فشل تنفيذ الكود: " + response.data.message);
                }
            }).fail(function() {
                wpAiUI.appendLog("❌ خطأ في الاتصال عند إرسال JSON للتنفيذ");
            });
        }
    });

});
