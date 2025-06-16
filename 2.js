jQuery(function($){
    // تحميل مكتبة marked.js إذا لم تكن محملة
    if (typeof window.marked === 'undefined') {
        $.getScript('https://cdn.jsdelivr.net/npm/marked/marked.min.js');
    }

    function showLoading() { $('#loading').show(); }
    function hideLoading() { $('#loading').hide(); }

    function addMessage(sender, text, isHtml = false) {
        const cls = sender === 'user' ? 'user-msg' : sender === 'agent' ? 'agent-msg' : 'error-msg';
        let content;
        if (isHtml && typeof window.marked !== "undefined") {
            content = window.marked.parse(text);
        } else {
            content = $('<div>').text(text).html();
        }
        $('#chat-window')
            .append(`<div class="${cls}" style="color: ${sender === 'user' ? '#0073aa' : sender === 'agent' ? '#666' : '#d63638'}">${content}</div>`)
            .scrollTop($('#chat-window')[0].scrollHeight);
    }

    function appendLog(line) {
        $('#debug-log pre').append(`<div>${$('<div>').text(line).html()}</div>`).scrollTop($('#debug-log pre')[0].scrollHeight);
    }

    function renderGutenbergPreview(blocks) {
        if (!blocks || !blocks.length) return $('#gutenberg-preview').hide();
        let html = '';
        blocks.forEach(block => {
            html += block.name === 'core/paragraph' ? `<div class="gb-paragraph">${block.attributes.content}</div>` : 
                    block.name === 'core/image' ? `<img src="${block.attributes.url}" class="gb-image">` : 
                    `<div class="gb-block">${JSON.stringify(block)}</div>`;
        });
        $('#blocks-container').html(html);
        $('#gutenberg-preview').show();
    }

    window.wpAiUI = { showLoading, hideLoading, addMessage, appendLog, renderGutenbergPreview };
});
