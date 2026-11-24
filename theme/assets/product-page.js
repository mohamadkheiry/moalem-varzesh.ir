/* global jQuery */
for (const summary of document.querySelectorAll('.mv-product-page .summary.entry-summary')) {
    const form = summary.querySelector('form.variations_form');
    const stock = summary.querySelector('.mv-product-stock');
    if (!form || !stock) continue;

    const originalStock = stock.textContent;
    jQuery(form).on('show_variation', (event, variation, purchasable) => {
        const availability = form.querySelector('.woocommerce-variation-availability .stock');
        stock.textContent = availability?.textContent.trim()
            || (purchasable ? 'آمادهٔ سفارش' : 'این گزینه در دسترس نیست');
        stock.dataset.stock = !variation.is_in_stock ? 'out'
            : availability?.classList.contains('available-on-backorder') ? 'backorder' : '';
    });
    jQuery(form).on('hide_variation', () => {
        stock.textContent = originalStock;
        delete stock.dataset.stock;
    });
}

for (const quantity of document.querySelectorAll('.mv-product-buy .quantity')) {
    const input = quantity.querySelector('input.qty[type="number"]');
    if (!input || input.readOnly || input.step === 'any') continue;

    const decrease = document.createElement('button');
    decrease.type = 'button';
    decrease.className = 'mv-quantity-step';
    decrease.textContent = '−';
    decrease.setAttribute('aria-label', 'کاهش تعداد');
    const increase = document.createElement('button');
    increase.type = 'button';
    increase.className = 'mv-quantity-step';
    increase.textContent = '+';
    increase.setAttribute('aria-label', 'افزایش تعداد');

    function update() {
        decrease.disabled = input.disabled || (input.min !== '' && input.valueAsNumber <= Number(input.min));
        increase.disabled = input.disabled || (input.max !== '' && input.valueAsNumber >= Number(input.max));
    }

    for (const [button, direction] of [[decrease, -1], [increase, 1]]) {
        button.addEventListener('click', () => {
            if (direction < 0) input.stepDown();
            else input.stepUp();
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    input.before(decrease);
    input.after(increase);
    input.addEventListener('input', update);
    input.addEventListener('change', update);
    new MutationObserver(update).observe(input, {
        attributes: true,
        attributeFilter: ['min', 'max', 'disabled', 'value'],
    });
    quantity.classList.add('mv-quantity-stepper');
    update();
}

for (const gallery of document.querySelectorAll('.mv-product-page .woocommerce-product-gallery')) {
    function updateThumbnails() {
        const thumbnails = gallery.querySelectorAll('.flex-control-thumbs img');
        thumbnails.forEach((thumbnail, index) => {
            const active = thumbnail.classList.contains('flex-active');
            if (!thumbnail.hasAttribute('role')) {
                thumbnail.setAttribute('role', 'button');
                thumbnail.tabIndex = 0;
                const number = new Intl.NumberFormat('fa-IR').format(index + 1);
                thumbnail.setAttribute('aria-label', 'نمایش تصویر ' + number);
            }
            const pressed = String(active);
            if (thumbnail.getAttribute('aria-pressed') !== pressed) {
                thumbnail.setAttribute('aria-pressed', pressed);
            }
        });
    }

    gallery.addEventListener('keydown', event => {
        if (!event.target.matches('.flex-control-thumbs img') || !['Enter', ' '].includes(event.key)) return;
        event.preventDefault();
        event.target.click();
    });
    new MutationObserver(updateThumbnails).observe(gallery, {
        subtree: true,
        childList: true,
        attributes: true,
        attributeFilter: ['class'],
    });
    updateThumbnails();
}
