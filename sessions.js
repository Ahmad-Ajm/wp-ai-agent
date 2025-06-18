jQuery(function($){
  /**
   * Session Manager: يحتفظ بإدارة الجلسات كما كان
   */
  const sessionManager = {
    init: function() {
      this.bindEvents();
      this.loadSessions();
      this.injectImageUploadButton();
    },

    bindEvents: function() {
      $(document)
        .on('click', '.view-session', this.viewSession.bind(this))
        .on('click', '.delete-session', this.deleteSession.bind(this))
        .on('click', '#refresh-sessions', this.loadSessions.bind(this))
        .on('click', '#upload-image-btn', () => $('#hidden-image-input').click());
    },

    loadSessions: function() {
      wpAiUI.appendLog("⬇️ طلب جلب الجلسات");
      $.post(wpAiAgent.ajaxUrl, { action: 'wpai_get_sessions', security: wpAiAgent.nonce }, response => {
        if (response.success) {
          this.renderSessions(response.data.sessions);
          wpAiUI.appendLog("✅ تم جلب الجلسات بنجاح");
        } else {
          wpAiUI.appendLog("❌ فشل جلب الجلسات");
        }
      }).fail(() => {
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
        const row = $(
          `<div class="session-row" data-id="${session.id}">` +
            `<span class="session-id">${session.id}</span>` +
            `<span class="session-date">${session.created_at}</span>` +
            `<button class="button view-session" data-id="${session.id}">عرض</button>` +
            `<button class="button delete-session" data-id="${session.id}">حذف</button>` +
          `</div>`
        );
        container.append(row);
      });
    },

    viewSession: function(e) {
      e.preventDefault();
      const sessionId = $(e.currentTarget).data('id');
      wpAiUI.appendLog(`⬇️ طلب عرض الجلسة #${sessionId}`);
      $.post(wpAiAgent.ajaxUrl, { action: 'wpai_get_session', session_id: sessionId, security: wpAiAgent.nonce }, response => {
        if (response.success) {
          window.memoryManager.history = response.data.messages;
          window.memoryManager.summary = response.data.summary;
          wpAiUI.addMessage('system', `✅ تم تحميل الجلسة ${sessionId}`);
          wpAiUI.appendLog(`✅ تمت إضافة بيانات الجلسة إلى الذاكرة (#${sessionId})`);
        } else {
          wpAiUI.appendLog(`❌ فشل تحميل الجلسة #${sessionId}`);
        }
      }).fail(() => {
        wpAiUI.appendLog(`❌ خطأ في الاتصال عند عرض الجلسة #${sessionId}`);
      });
    },

    deleteSession: function(e) {
      e.preventDefault();
      const sessionId = $(e.currentTarget).data('id');
      if (!confirm("هل تريد حذف هذه الجلسة؟")) return;
      wpAiUI.appendLog(`⬇️ طلب حذف الجلسة #${sessionId}`);
      $.post(wpAiAgent.ajaxUrl, { action: 'wpai_delete_session', session_id: sessionId, security: wpAiAgent.nonce }, response => {
        if (response.success) {
          wpAiUI.appendLog(`✅ تم حذف الجلسة #${sessionId}`);
          this.loadSessions();
        } else {
          wpAiUI.appendLog(`❌ فشل حذف الجلسة #${sessionId}`);
        }
      }).fail(() => {
        wpAiUI.appendLog(`❌ خطأ في الاتصال عند حذف الجلسة #${sessionId}`);
      });
    },

    injectImageUploadButton: function() {
      const hiddenInput = $('<input>')
        .attr('type', 'file')
        .attr('id', 'hidden-image-input')
        .attr('accept', 'image/*')
        .css('display', 'none')
        .on('change', function(event) {
          const file = event.target.files[0];
          if (!file) return;
          if (!file.type.startsWith('image/')) return alert('الرجاء اختيار ملف صورة فقط');
          const reader = new FileReader();
          reader.onload = function(e) {
            const base64Data = e.target.result.split(',')[1];
            const promptText = `#img ${base64Data}`;
            wpAiUI.addMessage('user', '📤 تم إرسال صورة إلى الذكاء');
            if (typeof window.sendToAI === 'function') {
              window.sendToAI(promptText, true);
            } else {
              wpAiUI.appendLog("❌ دالة sendToAI غير متوفرة");
            }
          };
          reader.readAsDataURL(file);
          $(this).val('');
        });
      $('body').append(hiddenInput);
    }
  };
  sessionManager.init();

  /**
   * استخراج الأكواد من رسالة الذكاء
   */
  function extractCodePayload(message) {
    const CODE_REGEX = /#code\s+(\{[\s\S]*?\}|```[\s\S]*?```)/g;
    const matches = [];
    let match;
    while ((match = CODE_REGEX.exec(message)) !== null) {
      let code = match[1].trim();
      if (code.startsWith('```') && code.endsWith('```')) {
        code = code.slice(3, -3).trim();
      }
      matches.push({ type: detectCodeType(code), content: code });
    }
    return matches;
  }

  /**
   * تحديد نوع الكود
   */
  function detectCodeType(code) {
    if (/^\s*\{[\s\S]*\}\s*$/.test(code)) return 'json';
    if (/\bphp_open_tag\b|<\?php|function\s+|wp_/.test(code)) return 'php';
    if (/function\s*\(|=>|console\.log|document\.|window\.|\$\(/.test(code)) return 'js';
    return 'unknown';
  }

  /**
   * تنفيذ الأكواد المستخرجة
   */
  const CodeExecutor = {
    async execute(payloads) {
      const results = [];
      for (const payload of payloads) {
        wpAiUI.appendLog(`📤 بدء تنفيذ كود من النوع ${payload.type}`);
        try {
          const result = await this.executeSingle(payload);
          results.push({ success: true, result, payload });
          wpAiUI.appendLog(`✅ تنفيذ ${payload.type} ناجح`);
        } catch (error) {
          results.push({ success: false, error: error.message, line: error.lineNumber, payload });
          wpAiUI.appendLog(`❌ خطأ في تنفيذ ${payload.type}: ${error.message}`);
        }
      }
      return results;
    },

    async executeSingle({ type, content }) {
      switch (type) {
        case 'json':
          return this.handleJSON(content);
        case 'php':
          return this.handlePHP(content);
        case 'js':
          return this.handleJS(content);
        default:
          throw new Error(`نوع الكود غير مدعوم: ${type}`);
      }
    },

    handleJSON(json) {
      // تحقق من صلاحية JSON
      try { JSON.parse(json); } catch (e) {
        throw new Error('بنية JSON غير صالحة');
      }
      return $.post(wpAiAgent.ajaxUrl, {
        action: 'wpai_execute_code',
        payload: json,
        type: 'json',
        security: wpAiAgent.nonce
      });
    },

    handlePHP(code) {
      return $.post(wpAiAgent.ajaxUrl, {
        action: 'wpai_execute_code',
        payload: code,
        type: 'php',
        security: wpAiAgent.nonce
      });
    },

    handleJS(code) {
      try {
        const result = safeJSExecution(code);
        return Promise.resolve(result);
      } catch (e) {
        return Promise.reject(new Error(e.message));
      }
    }
  };

  /**
   * تنفيذ آمن لجافاسكربت
   */
  function safeJSExecution(code) {
    const func = new Function('jQuery', 'wpAiUI', 'wpAiAgent', 'memoryManager', `"use strict"; ${code}`);
    return func(jQuery, wpAiUI, wpAiAgent, window.memoryManager);
  }

  /**
   * عرض نتائج التنفيذ والأخطاء مع زر إعادة المحاولة
   */
  function handleExecutionResults(results) {
    const errorContainer = $('#code-errors').empty();
    results.forEach((res, idx) => {
      if (!res.success) {
        const html = `
          <div class="code-error">
            <b>#${idx+1}:</b> ${res.error}
            <pre>${res.payload.content.substring(0, 200)}...</pre>
            <button class="retry-btn" data-index="${idx}">إعادة المحاولة</button>
          </div>`;
        errorContainer.append(html);
      }
    });

    // إعادة المحاولة
    errorContainer.find('.retry-btn').click(function() {
      const i = $(this).data('index');
      const payload = results[i].payload;
      wpAiUI.appendLog(`🔄 إعادة تنفيذ الكود #${i+1}`);
      CodeExecutor.executeSingle(payload)
        .then(() => wpAiUI.appendLog(`✅ إعادة تنفيذ ناجح #${i+1}`))
        .catch(err => wpAiUI.appendLog(`❌ إعادة تنفيذ فشل #${i+1}: ${err.message}`));
    });
  }

  /**
   * استماع لردود الذكاء وتحديد الأكواد للتنفيذ
   */
  $(document).on('wpai_assistant_response', function(e, msg) {
    const payloads = extractCodePayload(msg);
    if (payloads.length > 0) {
      wpAiUI.appendLog(`🔍 اكتشاف ${payloads.length} كتلة/كتل كود`);
      CodeExecutor.execute(payloads)
        .then(handleExecutionResults)
        .catch(err => wpAiUI.appendLog(`❌ خطأ عام في التنفيذ: ${err.message}`));
    }
  });
});
