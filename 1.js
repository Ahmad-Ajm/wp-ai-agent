jQuery(function($) {
    // نظام إدارة الذاكرة
    const memoryManager = {
        history: [],
        promptCache: {},
        summary: '',
        dbStorage: {
            async save(data) {
                await $.post(wpAiAgent.ajaxUrl, {
                    action: 'wpai_save_memory',
                    data: data,
                    user_id: wpAiAgent.userId,
                    security: wpAiAgent.nonce
                });
            },
            async load(callback) {
                const response = await $.post(wpAiAgent.ajaxUrl, {
                    action: 'wpai_load_memory',
                    user_id: wpAiAgent.userId,
                    security: wpAiAgent.nonce
                });
                if (response.success) callback(response.data);
            }
        },
        add: async function(role, content) {
            this.history.push({ role, content });
            if (this.history.length > 10) this.history.shift();
            await this.dbStorage.save({ history: this.history, summary: this.summary });
        },
        getContext: function() {
            return [...this.history];
        },
        clear: async function() {
            this.history = [];
            await this.dbStorage.save({ history: this.history, summary: '' });
        }
    };

    // نظام السجل المحسن
    const logManager = {
        log: function(message, type = 'info') {
            const timestamp = new Date().toISOString();
            const logEntry = `[${timestamp}] [${type.toUpperCase()}] ${message}`;
            const startTime = performance.now();
            
            $.post(wpAiAgent.ajaxUrl, {
                action: 'wpai_log_event',
                message: message,
                type: type,
                security: wpAiAgent.nonce
            });
            
            const duration = performance.now() - startTime;
            wpAiUI.appendLog(`${logEntry} | الأداء: ${duration.toFixed(2)}ms`);
        },
        apiCall: function(endpoint, payload, response) {
            this.log(`API Call to ${endpoint}\nPayload: ${JSON.stringify(payload)}\nResponse: ${JSON.stringify(response)}`, 'api');
        },
        error: function(error, context = '') {
            this.log(`ERROR: ${error.message}\nStack: ${error.stack}\nContext: ${context}`, 'error');
        }
    };

    let basePromptSent = false;

    async function trySendBasePrompt() {
    if (!sessionStorage.getItem("basePromptSent") && wpAiAgent.basePrompt?.trim()) {
        // إنشاء محتوى البرومبت الكامل
        let fullPrompt = wpAiAgent.basePrompt;
        
        // إضافة dataIni إذا كان متاحاً
        if (typeof window.dataIni !== 'undefined') {
            const dataPayload = JSON.stringify(window.dataIni, null, 2);
            fullPrompt += "\n\n#dataini\n" + dataPayload;
        }
        
        memoryManager.add("system", fullPrompt);
        logManager.log("✅ تم إرسال البرومبت الأساسي مع بيانات الموقع");
        sessionStorage.setItem("basePromptSent", "true");
        basePromptSent = true;
    }
}

    let apiKey = '';
    let aiProvider = 'gpt';
    let instructionsSent = false;
    const PROMPT_SENT_KEY = 'wpai_prompt_sent';

    async function initializeAI() {
    if (instructionsSent) return;
    instructionsSent = true;

    wpAiUI.showLoading();
    try {
        trySendBasePrompt(); // تأكد من إرسال البرومبت عند كل بداية جلسة
    } catch (e) {
        logManager.error(e, "initializeAI - إضافة البرومبت الرئيسي");
    } finally {
        wpAiUI.hideLoading();
    }
}

    async function collectSiteInfo() {
        try {
            const response = await $.post(wpAiAgent.ajaxUrl, {
                action: 'wpai_get_site_info',
                security: wpAiAgent.nonce
            });
            return response.success ? JSON.stringify(response.data) : "تعذر جمع معلومات الموقع";
        } catch (e) {
            return "خطأ في جمع المعلومات: " + e.message;
        }
    }

    async function sendToAI(userMessage) {
    if (!wpAiAgent?.nonce) {
        logManager.log("⚠️ الـ nonce غير متوفر. تأكد من تحميل الصفحة من wp-admin.");
        return;
    }
    await memoryManager.add("user", userMessage);
    logManager.log("📤 تم إرسال إلى الذكاء:" + userMessage);

    let messagesToSend = memoryManager.getContext();

    // ✅ إرسال بيانات dataIni دائماً مع كل برومبت رئيسي، بشكل آمن ومحترف
    

    if (memoryManager.summary) {
        messagesToSend.push({ role: "system", content: "#smry2\n" + memoryManager.summary });
    }

    const payload = {
        model: aiProvider === 'gpt' ? "gpt-4o" : "deepseek-coder",
        messages: messagesToSend,
        max_tokens: 2000
    };

    if (JSON.stringify(payload).length > 1e6) {
        const compressed = await compressData(JSON.stringify(payload));
        logManager.log("إرسال بيانات مضغوطة:\n" + compressed);
    } else {
        logManager.log("إرسال:\n" + JSON.stringify(messagesToSend, null, 2));
    }

    const endpoint = aiProvider === 'gpt'
        ? "https://api.openai.com/v1/chat/completions"
        : "https://api.deepseek.com/v1/chat/completions";

    try {
        const res = await fetch(endpoint, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Authorization": "Bearer " + apiKey 
            },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error?.message || res.statusText);

        const reply = data.choices?.[0]?.message?.content || JSON.stringify(data);
        await memoryManager.add("assistant", reply);
        logManager.log("رد المزود:\n" + reply);
        handleAgentResponse(reply);
    } catch (e) {
        logManager.error(e, "sendToAI - إرسال إلى المزود");
        wpAiUI.addMessage('error', "خطأ: " + e.message);
    }
}


    async function sendToAIWithMessages(messagesToSend, hideFromChat = false) {
        const endpoint = aiProvider === 'gpt'
            ? "https://api.openai.com/v1/chat/completions"
            : "https://api.deepseek.com/v1/chat/completions";

        const payload = {
            model: aiProvider === 'gpt' ? "gpt-4o" : "deepseek-coder",
            messages: messagesToSend,
            max_tokens: 2000
        };

        try {
            const res = await fetch(endpoint, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Authorization": "Bearer " + apiKey
                },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error?.message || res.statusText);

            const reply = data.choices?.[0]?.message?.content || JSON.stringify(data);
            if (!hideFromChat) await memoryManager.add("assistant", reply);
            logManager.log("رد المزود:\n" + reply);
            handleAgentResponse(reply);
        } catch (e) {
            logManager.error(e, "sendToAIWithMessages - إرسال مخصص");
            if (!hideFromChat) wpAiUI.addMessage('error', "خطأ: " + e.message);
        }
    }

    function handleAgentResponse(text) {
      if (text.includes('#dsn')) {
            if (typeof loadDesignPanel === 'function') {
                $(document).trigger('wpai_assistant_response', [text]);
            }
        }

        if (text.includes('#smry')) {
            memoryManager.summary = text.split('#smry')[1]?.trim();
            memoryManager.dbStorage.save({ history: memoryManager.history, summary: memoryManager.summary });
        }

        if (text.includes('#preview')) {
            const previewData = text.split('#preview')[1]?.trim();
            if (window.wpAiPagePanel?.updatePreview) {
                window.wpAiPagePanel.updatePreview(previewData);
            }
        }

        if (text.includes('#apply')) {
            const designData = JSON.parse(text.split('#apply')[1]?.trim() || '{}');
            if (window.wpAiPagePanel?.applyDesign) {
                window.wpAiPagePanel.applyDesign(designData);
            }
            memoryManager.clear();
            memoryManager.add("system", wpAiAgent.basePrompt);
        }

        const plain = text.replace(/#smry[\s\S]*|```[\s\S]*?```|#code[\s\S]*/g, '').trim();
        if (plain) wpAiUI.addMessage('agent', plain, true);
    }

    async function compressData(data) {
        const stream = new Blob([data]).stream();
        const compressedStream = stream.pipeThrough(new CompressionStream('gzip'));
        const response = new Response(compressedStream);
        const blob = await response.blob();
        return await blob.text();
    }

    async function analyzePageSpeed(url) {
        const apiKeyPS = '';
        const endpoint = `https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=${encodeURIComponent(url)}&key=${apiKeyPS}`;
        try {
            const res = await fetch(endpoint);
            const data = await res.json();
            return data;
        } catch (e) {
            logManager.error(e, "analyzePageSpeed - خطأ في تحليل PageSpeed");
            return null;
        }
    }

    $('#ai-provider-select').on('change', function() {
        aiProvider = $(this).val();
        instructionsSent = false;
        logManager.log("تغيير المزود إلى: " + aiProvider);
    });

    $('#save-api-key').on('click', async function() {
        const keyVal = $('#api-key-input').val().trim();
        if (!keyVal) {
            return alert("أدخل مفتاح API");
        }

        await $.post(wpAiAgent.ajaxUrl, {
            action: 'wpai_save_api_key',
            key: keyVal,
            security: wpAiAgent.nonce
        }, function(response) {
            if (response.success) {
                apiKey = keyVal;
                logManager.log("تم حفظ المفتاح الأصلي بنجاح");
                const alreadySent = window.localStorage.getItem(PROMPT_SENT_KEY);
                if (!alreadySent) {
                    initializeAI().then(() => {
                        window.localStorage.setItem(PROMPT_SENT_KEY, '1');
                    });
                }
            } else {
                alert("فشل في حفظ المفتاح");
            }
        });
    });

    $('#clear-api-key').on('click', async function() {
        await $.post(wpAiAgent.ajaxUrl, {
            action: 'wpai_clear_api_key',
            security: wpAiAgent.nonce
        }, function(response) {
            apiKey = '';
            $('#api-key-input').val('');
            logManager.log("تم مسح المفتاح");
            window.localStorage.removeItem(PROMPT_SENT_KEY);
            instructionsSent = false;
            sessionStorage.removeItem("basePromptSent");
        });
    });

    $('#send-button').on('click', function() {
        const msg = $('#chat-input').val().trim();
        if (!msg || !apiKey) return;
        wpAiUI.addMessage('user', msg);
        $('#chat-input').val('');
        sendToAI(msg);
    });

    $('#reverse-send-button').on('click', function() {
        const msg = $('#chat-input').val().trim();
        if (!msg || memoryManager.history.some(m => m.content === msg)) return;
        wpAiUI.addMessage('agent', msg);
        memoryManager.add("assistant", msg);
        $('#chat-input').val('');
        handleAgentResponse(msg);
    });

    $('#save-chat').on('click', async function() {
        const messages = memoryManager.getContext();
        await $.post(wpAiAgent.ajaxUrl, {
            action: 'wpai_save_session',
            data: { messages, summary: memoryManager.summary, api_key: apiKey },
            security: wpAiAgent.nonce
        });
        logManager.log("تم حفظ المحادثة كجلسة جديدة");
    });

    $('#reload-session').on('click', function() {
        memoryManager.dbStorage.load(data => {
            memoryManager.history = data.history || [];
            memoryManager.summary = data.summary || '';
            wpAiUI.addMessage('system', "تم استعادة الجلسة من الذاكرة");
            sessionStorage.removeItem("basePromptSent");
        });
    });

    $(document).ready(async function() {
        const response = await $.post(wpAiAgent.ajaxUrl, {
            action: 'wpai_get_api_key',
            security: wpAiAgent.nonce
        });

        if (response.success && response.data.key) {
            apiKey = response.data.key;
            $('#api-key-input').val('********');
            logManager.log("تم تحميل المفتاح الأصلي");
        }

        trySendBasePrompt();
        try {
            const stored = memoryManager.getContext();
            if (!stored || stored.length === 0) {
                sessionStorage.removeItem("basePromptSent");
            }
        } catch (e) {
            console.warn("فشل في فحص الذاكرة");
        }
    });

    window.sendToAI = sendToAI;
});
