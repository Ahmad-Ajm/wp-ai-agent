
// live-preview.js - معالجة المعاينة الحية في iframe
jQuery(function($) {
    window.addEventListener('message', function(event) {
        if (event.origin !== window.location.origin) return;

        const message = event.data;
        if (message.action === 'apply_design_preview') {
            applyDesignPreview(message.data);
        }
    });

    function applyDesignPreview(designData) {
        $('body').css({
            '--color-primary': designData.colors.primary,
            '--color-secondary': designData.colors.secondary,
            '--color-background': designData.colors.background
        });

        if (designData.logo.url) {
            $('.site-logo img').attr('src', designData.logo.url);
        }

        const fontLink = `https://fonts.googleapis.com/css2?family=${designData.fonts.primary}&family=${designData.fonts.secondary}&display=swap`;
        $('head').append(`<link href="${fontLink}" rel="stylesheet">`);

        $('body').css({
            'font-family': `"${designData.fonts.primary}", sans-serif`,
            '--font-heading': `"${designData.fonts.secondary}", sans-serif`
        });

        applyLayout(designData.layout);
    }

    function applyLayout(layout) {
        $('body').removeClass('layout-one-column layout-two-columns layout-grid layout-sidebar');
        switch (layout) {
            case 'one-column':
                $('body').addClass('layout-one-column');
                break;
            case 'two-columns':
                $('body').addClass('layout-two-columns');
                break;
            case 'grid':
                $('body').addClass('layout-grid');
                break;
            case 'sidebar':
                $('body').addClass('layout-sidebar');
                break;
        }
    }
});
