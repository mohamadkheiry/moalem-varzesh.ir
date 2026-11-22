(() => {
    for (const card of document.querySelectorAll('[data-support-image]')) {
        const input = card.querySelector('input[type="hidden"]');
        const preview = card.querySelector('img');
        let picker;
        card.querySelector('[data-pick-image]').addEventListener('click', () => {
            if (!picker) {
                picker = wp.media({ title: 'انتخاب تصویر صفحه', button: { text: 'استفاده از تصویر' }, library: { type: 'image' }, multiple: false });
                picker.on('open', () => picker.content.mode('browse'));
                picker.on('select', () => {
                    const photo = picker.state().get('selection').first().toJSON();
                    input.value = photo.id;
                    preview.src = photo.sizes?.medium?.url || photo.url;
                });
            }
            picker.open();
        });
        card.querySelector('[data-reset-image]').addEventListener('click', () => {
            input.value = '0';
            preview.src = card.dataset.defaultImage;
        });
    }
})();
