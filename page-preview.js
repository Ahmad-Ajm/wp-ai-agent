
// page-preview.js - معاينة الصفحات المختارة
jQuery(function($) {
    const pluginDirUrl = (window.wpAiAgent && wpAiAgent.pluginUrl) || '';
    window.wpAiPagePanel = {
        currentPage: null,
        templates: {},
        iframe: null,

        init: function() {
            this.loadTemplates();
            this.bindEvents();
            this.initLivePreview();
        },

        loadTemplates: function() {
            this.templates = {
                home: ['home-1.jpg', 'home-2.jpg', 'home-3.jpg'],
                blog: ['blog-1.jpg', 'blog-2.jpg'],
                about: ['about-1.jpg']
            };
        },

        bindEvents: function() {
            $('.page-column input[type="checkbox"]').on('change', function() {
                const page = $(this).val();
                if ($(this).is(':checked')) {
                    wpAiPagePanel.showPreview(page);
                }
            });
        },

        showPreview: function(page) {
            if (!this.templates[page] || this.templates[page].length === 0) {
                wpAiUI.appendLog(`⚠️ لا توجد قوالب متاحة لصفحة ${page}`);
                return;
            }
            const previewHtml = `
                <div class="page-preview" data-page="${page}">
                    <h3>معاينة صفحة ${page}</h3>
                    <div class="templates-container">
                        ${this.templates[page].map(t => `
                            <div class="template-item">
                                <img src="${pluginDirUrl}/templates/${t}" alt="${t}">
                                <button class="select-template" data-template="${t}">اختيار</button>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
            $('#page-previews-container').append(previewHtml);
        },

        updatePreview: function(data) {
            if (data.startsWith('http')) {
                $('#color-preview').css('background-image', `url(${data})`);
            }
        },

        applyDesign: function(designData) {
            console.log("تطبيق التصميم:", designData);
        },

        initLivePreview: function() {
            this.iframe = $('<iframe>', {
                id: 'live-preview-frame',
                src: window.location.origin,
                style: 'width:100%; height:500px; border:1px solid #ddd; border-radius:4px;'
            });
            $('#page-previews-container').prepend(`
                <div class="live-preview-section">
                    <h3>المعاينة الحية</h3>
                    <div class="preview-controls">
                        <button id="refresh-preview">🔄 تحديث</button>
                        <button id="fullscreen-preview">📺 ملء الشاشة</button>
                    </div>
                    <div class="preview-container"></div>
                </div>
            `);
            $('.preview-container').append(this.iframe);
            $('#refresh-preview').on('click', () => this.refreshPreview());
            $('#fullscreen-preview').on('click', () => this.toggleFullscreen());
        },

        refreshPreview: function() {
            this.iframe.attr('src', window.location.origin);
            wpAiUI.appendLog("🔄 تم تحديث المعاينة الحية");
        },

        toggleFullscreen: function() {
            const iframe = this.iframe[0];
            if (iframe.requestFullscreen) iframe.requestFullscreen();
            else if (iframe.webkitRequestFullscreen) iframe.webkitRequestFullscreen();
            else if (iframe.msRequestFullscreen) iframe.msRequestFullscreen();
        },

        applyDesignToPreview: function() {
            const message = { action: 'apply_design_preview', data: window.designData };
            this.iframe[0].contentWindow.postMessage(message, window.location.origin);
        },

        onDesignChange: function() {
            this.applyDesignToPreview();
        }
    };

    $(document).ready(() => {
        if (window.location.href.includes('wp-ai-agent')) {
            $('.wpai-content-area').append('<div id="page-previews-container"></div>');
            window.wpAiPagePanel.init();
        }
    });

    $(document).on('design_updated', () => {
        window.wpAiPagePanel.onDesignChange();
    });

    function fireDesignUpdated() {
        $(document).trigger('design_updated');
    }

    $('#site-name, #site-desc, #desc-input').on('input', fireDesignUpdated);
    $('.color-palette, input[name="color-tone"]').on('click', fireDesignUpdated);
    $('input[name="logo-style"]').on('change', fireDesignUpdated);
    $('input[name="layout"]').on('change', fireDesignUpdated);
    $('input[name="permalinks"]').on('change', fireDesignUpdated);
});
