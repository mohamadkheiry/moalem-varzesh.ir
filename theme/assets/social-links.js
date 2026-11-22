(() => {
    const social = document.querySelector('.mv-social');
    if (!social) return;

    const toggle = social.querySelector('summary');
    document.addEventListener('click', event => {
        if (social.open && !social.contains(event.target)) social.open = false;
    });
    document.addEventListener('keydown', event => {
        if (!social.open || event.defaultPrevented || event.key !== 'Escape') return;
        social.open = false;
        toggle.focus();
    });
})();
