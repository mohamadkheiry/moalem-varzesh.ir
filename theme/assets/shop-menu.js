(() => {
    const menu = document.querySelector('[data-shop-disclosure]');
    if (!menu) return;
    const trigger = menu.querySelector('summary');

    document.addEventListener('pointerdown', event => {
        if (menu.open && !menu.contains(event.target)) menu.open = false;
    });
    menu.addEventListener('keydown', event => {
        if (menu.open && event.key === 'Escape') {
            event.preventDefault();
            menu.open = false;
            trigger.focus();
        }
    });
})();
