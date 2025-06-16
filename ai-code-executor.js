// ملف ai-code-executor.js – وحدة تنفيذ أكواد الذكاء الصناعي بأمان ومرونة

(function($){

    /** استخراج الكتل البرمجية من رسالة الذكاء */
    function extractCodeBlocks(text) {
        const blocks = [];
        const regex = /```(\w+)?\s*([\s\S]+?)```/g;
        let match;
        while ((match = regex.exec(text)) !== null) {
            blocks.push({
                lang: match[1] ? match[1].toLowerCase() : 'unknown',
                code: match[2].trim()
            });
        }
        return blocks;
    }

    /** التحقق من صحة JSON */
    function isValidJSON(code) {
        try {
            JSON.parse(code);
            return true;
        } catch (e) {
            return false;
        }
    }

    /** تنفيذ آمن لـ JavaScript */
    function safeJSExecution(code) {
        const allowedFunctions = ['jQuery', '$', 'document', 'console.log', 'wpAiUI', 'wpAiAgent', 'memoryManager'];
        const safeEnv = {
            console: {
                log: (...args) => wpAiUI.appendLog('[JS]: ' + args.join(' '))
            }
        };

        const func = new Function(...allowedFunctions, `
            "use strict";
            try {
                return (function() {
                    ${code}
                })();
            } catch (e) {
                return 'JS Error: ' + e.message;
            }
        `);

        return func(...allowedFunctions.map(f => {
            if (f === 'jQuery' || f === '$') return jQuery;
            if (f === 'wpAiUI') return wpAiUI;
            if (f === 'wpAiAgent') return wpAiAgent;
            if (f === 'memoryManager') return window.memoryManager;
            return window[f];
        }));
    }

    /** إرسال كود PHP للسيرفر */
    function executePHP(code) {
        $.post(wpAiAgent.ajaxUrl, {
            action: 'wpai_execute_php',
            code: code,
            security: wpAiAgent.nonce
        }, function(response){
            if (response.success) {
                wpAiUI.addMessage('agent', response.data.output);
            } else {
                wpAiUI.addMessage('error', 'PHP Error: ' + response.data);
            }
        }).fail(function(){
            wpAiUI.addMessage('error', 'فشل في إرسال كود PHP للسيرفر');
        });
    }

    /** تنفيذ كتل الكود المستخرجة */
    function handleCodeBlocks(aiMessage) {
        const blocks = extractCodeBlocks(aiMessage);
        if (blocks.length === 0) return;

        blocks.forEach(block => {
            wpAiUI.appendLog(`🔍 وجدت كود ${block.lang}: ${block.code.substring(0, 50)}...`);

            switch (block.lang) {
                case 'json':
                    if (!isValidJSON(block.code)) {
                        wpAiUI.appendLog("❌ JSON غير صالح");
                        return;
                    }
                    const jsonData = JSON.parse(block.code);
                    $.post(wpAiAgent.ajaxUrl, {
                        action: 'wpai_execute_code',
                        payload: JSON.stringify(jsonData),
                        security: wpAiAgent.nonce
                    });
                    break;

                case 'php':
                    executePHP(block.code);
                    break;

                case 'js':
                case 'javascript':
                    try {
                        const result = safeJSExecution(block.code);
                        wpAiUI.appendLog("✅ تم تنفيذ JS: " + result);
                    } catch (e) {
                        wpAiUI.appendLog("❌ خطأ في JS: " + e.message);
                    }
                    break;

                case 'html':
                case 'css':
                    if (window.wpAiPagePanel?.updatePreview) {
                        window.wpAiPagePanel.updatePreview(block.code);
                    }
                    break;

                default:
                    wpAiUI.appendLog(`⚠️ نوع الكود غير معروف: ${block.lang}`);
            }
        });
    }

    // تصدير الدالة للاستخدام في sessions.js و 1.js
    window.handleCodeBlocks = handleCodeBlocks;

    // تشغيل تلقائي عند استقبال رد من الذكاء
    $(document).on('wpai_assistant_response', function(e, msg) {
        if (msg.includes('```')) {
            handleCodeBlocks(msg);
        }
    });

})(jQuery);
