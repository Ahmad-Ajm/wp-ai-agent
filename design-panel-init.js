
// design-panel-init.js - تهيئة البيانات الأولية للوحة التصميم
jQuery(function($) {
    window.dataIni = {
        name: "اسم موقعي",
        desc: "وصف موقعي الرائع",
        pages: ["home", "blog", "about"],
        logo: "https://via.placeholder.com/150?text=شعار+حالي"
    };

    const initTaxonomies = () => {
        const categories = ["أخبار", "مقالات", "مراجعات"];
        const tags = ["تكنولوجيا", "تصميم", "تطوير"];
        categories.forEach(cat => {
            $('#categories-list').append(`<div class="taxonomy-item">${cat} <span class="remove-tax">✕</span></div>`);
        });
        tags.forEach(tag => {
            $('#tags-list').append(`<div class="taxonomy-item">${tag} <span class="remove-tax">✕</span></div>`);
        });
    };

    $(document).ready(() => {
        if (window.location.href.includes('wp-ai-agent')) {
            setTimeout(() => {
                if (typeof showDesignPanel === 'function') {
                    showDesignPanel();
                    setTimeout(() => {
                        window.dataIni.pages.forEach(page => {
                            $(`.page-column input[value="${page}"]`).prop('checked', true).trigger('change');
                        });
                        if (window.dataIni.logo) {
                            $('#logo-preview').attr('src', window.dataIni.logo);
                        }
                        initTaxonomies();
                    }, 500);
                }
            }, 1000);
        }
    });
});
