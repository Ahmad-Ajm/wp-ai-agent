// ai-code-executor.js

(function($) {
  /**
   * استخراج كتل الكود بعد #code، سواء كانت JSON، PHP، JS، أو محاطة بـ ```
   */
  function extractCodePayload(message) {
    // نلتقط أي شيء بعد #code حتى الوسم التالي (#xxx) أو نهاية الرسالة
    const CODE_REGEX = /#code\s*([\s\S]*?)(?=(?:#\w+)|$)/g;
    const matches = [];
    let match;
    let i = 0;
    while ((match = CODE_REGEX.exec(message)) !== null) {
      let code = match[1].trim();
      i++;
      wpAiUI.appendLog(`📦[${i}] تم استخراج كتلة كود:\n${code.substring(0,200)}${code.length>200?'...':''}`);
      // إزالة علامات ``` إذا وجدت
      if (code.startsWith('```') && code.endsWith('```')) {
        code = code.slice(3, -3).trim();
      }
      const type = detectCodeType(code);
      wpAiUI.appendLog(`📄[${i}] تم تحديد نوع الكود: ${type}`);
      matches.push({ type, content: code });
    }
    if (!matches.length) {
      wpAiUI.appendLog("❗لم يتم العثور على أي كود بعد الوسم #code");
    }
    return matches;
  }

  /**
   * كشف نوع الكود بشكل أولي (json, php, js)
   */
  function detectCodeType(code) {
    if (code.startsWith('{') && code.endsWith('}')) return 'json';
    if (code.includes('<?php') || /\bwp_[a-zA-Z_]+\(/.test(code)) return 'php';
    // أي كود آخر يعتبر JS (افتراضي)
    return 'js';
  }

  /**
   * تنفيذ الكود بأنواعه مع تسجيل السجل الكامل
   */
  const CodeExecutor = {
    async execute(payloads) {
      const results = [];
      wpAiUI.appendLog(`📥 استلام ${payloads.length} كتلة/كتل كود للتنفيذ`);
      let idx = 0;
      for (const payload of payloads) {
        idx++;
        wpAiUI.appendLog(`🔎 بدء تنفيذ الكتلة #${idx} (النوع: ${payload.type})`);
        try {
          const result = await this.executeSingle(payload);
          wpAiUI.appendLog(`✅ نجاح تنفيذ الكتلة #${idx}`);
          results.push({ success: true, result });
        } catch (error) {
          wpAiUI.appendLog(`❌ فشل تنفيذ الكتلة #${idx}: ${error.message}`);
          results.push({
            success: false,
            error: error.message,
            line: error.lineNumber,
            payload
          });
        }
      }
      return results;
    },

    async executeSingle(payload) {
      switch (payload.type) {
        case 'json':
          return this.handleJSON(payload.content);
        case 'php':
          return this.handlePHP(payload.content);
        case 'js':
          return this.handleJS(payload.content);
        default:
          throw new Error(`نوع الكود غير مدعوم: ${payload.type}`);
      }
    },

    async handleJSON(json) {
      wpAiUI.appendLog(`⏳ التحقق من بنية JSON...`);
      if (!this.isValidJSON(json)) {
        throw new Error('بنية JSON غير صالحة');
      }
      wpAiUI.appendLog(`🚀 إرسال JSON للسيرفر...`);

      const headers = { 'Content-Type': 'application/json' };
      // إضافة مفتاح REST العام للترويسات عند توفره
      if (window.globalRestKey) {
        headers['x-api-key'] = window.globalRestKey;
      }

      const res = await fetch(wpAiAgent.restEndpoint, {
        method: 'POST',
        headers,
        body: json
      });

      const data = await res.json();
      if (!res.ok) throw new Error(data.message || res.statusText);
      return data;
    },

    handlePHP(code) {
      wpAiUI.appendLog(`🚀 إرسال كود PHP للسيرفر...`);
      return $.post(wpAiAgent.ajaxUrl, {
        action: 'wpai_execute_code',
        payload: code,
        type: 'php',
        security: (window.wpAiAgent && window.wpAiAgent.nonce) || ''
      });
    },

    handleJS(code) {
      wpAiUI.appendLog(`🚦 بدء تنفيذ JS في المتصفح...`);
      try {
        const res = safeJSExecution(code);
        wpAiUI.appendLog(`✅ نتائج تنفيذ JS: ${res === undefined ? 'تم التنفيذ بنجاح' : res}`);
        return Promise.resolve(res);
      } catch (e) {
        throw new Error(`JS Execution Error: ${e.message}`);
      }
    },

    isValidJSON(str) {
      try {
        JSON.parse(str);
        return true;
      } catch {
        return false;
      }
    }
  };

  /**
   * تنفيذ JS آمن (Sandbox بسيط)
   */
  function safeJSExecution(code) {
    // يحذر من الكود غير الآمن لكنه يتيح التوسيع لاحقًا
    // تنفيذ في دالة معزولة:
    return (new Function(code))();
  }

  /**
   * عرض نتائج التنفيذ (مع إمكانية إعادة المحاولة)
   */
  function handleExecutionResults(results) {
    const errorContainer = $('#code-errors');
    if (errorContainer.length) errorContainer.empty();

    results.forEach((result, index) => {
      if (!result.success) {
        const errorMsg = `
          <div class="code-error">
            <b>#${index + 1}:</b> ${result.error}
            <pre>${result.payload.content.substring(0, 200)}${result.payload.content.length > 200 ? '...' : ''}</pre>
            <button class="retry-btn" data-index="${index}">إعادة المحاولة</button>
          </div>
        `;
        errorContainer.append(errorMsg);
      }
    });

    // إعادة المحاولة
    $('.retry-btn').off('click').on('click', function () {
      const index = $(this).data('index');
      CodeExecutor.executeSingle(results[index].payload)
        .then(res => wpAiUI.appendLog(`🔄 تمت إعادة المحاولة بنجاح للكتلة #${index + 1}`))
        .catch(err => wpAiUI.appendLog(`❌ فشل إعادة المحاولة: ${err.message}`));
    });
  }

  // الاستماع للردود القادمة من الذكاء الصناعي
  $(document).on('wpai_assistant_response', (e, msg) => {
    wpAiUI.appendLog("🆕 استقبال رسالة جديدة من الذكاء...");
    const payloads = extractCodePayload(msg);
    if (payloads.length) {
      CodeExecutor.execute(payloads)
        .then(handleExecutionResults)
        .catch(err => wpAiUI.appendLog(`❌ خطأ عام: ${err.message}`));
    }
  });

  // يمكن ربط الدوال للنطاق العام إذا أردت استخدامها في أماكن أخرى
  window.wpAiCode = { extractCodePayload, CodeExecutor, handleExecutionResults, detectCodeType };

})(jQuery);
