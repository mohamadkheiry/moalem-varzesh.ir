const cart = document.querySelector('.mv-purchase--cart .mv-purchase-content');
if (cart) {
    const enhanceQuantities = () => {
        for (const input of cart.querySelectorAll('.cart_item input.qty[type="number"]:not([readonly]):not([disabled])')) {
            const quantity = input.closest('.quantity');
            if (!quantity || quantity.classList.contains('mv-quantity') || input.step === 'any') continue;
            quantity.classList.add('mv-quantity');
            const buttons = [-1, 1].map(direction => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'mv-quantity-button';
                button.textContent = direction === 1 ? '+' : '−';
                const label = direction === 1 ? 'افزایش تعداد' : 'کاهش تعداد';
                const item = input.closest('.cart_item').querySelector('.product-name a')?.textContent.trim();
                button.setAttribute('aria-label', item ? label + ' ' + item : label);
                button.addEventListener('click', () => {
                    const previous = input.value;
                    if (input.value === '') input.value = input.min || '1';
                    else if (direction === 1) input.stepUp();
                    else input.stepDown();
                    if (input.value === previous) return;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                });
                return button;
            });
            input.before(buttons[0]);
            input.after(buttons[1]);
        }
    };
    enhanceQuantities();
    new MutationObserver(enhanceQuantities).observe(cart, { childList: true, subtree: true });
}
