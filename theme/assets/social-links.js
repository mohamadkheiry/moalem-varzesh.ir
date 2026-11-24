(() => {
    const social = document.querySelector('.mv-social');
    if (!social) return;

    const toggle = social.querySelector('summary');
    const footer = document.querySelector('#site-footer');
    if (footer?.querySelector('.mv-footer-social') && 'IntersectionObserver' in window) {
        let footerInView = false;
        const update = () => {
            social.hidden = footerInView && !social.open && !social.contains(document.activeElement);
        };
        const observer = new IntersectionObserver(([entry]) => {
            footerInView = entry.isIntersecting;
            update();
        }, { rootMargin: '0px 0px -68px 0px' });
        observer.observe(footer);
        social.addEventListener('toggle', update);
        social.addEventListener('focusout', () => requestAnimationFrame(update));
    }

    document.addEventListener('click', event => {
        if (social.open && !social.contains(event.target)) social.open = false;
    });
    document.addEventListener('keydown', event => {
        if (!social.open || event.defaultPrevented || event.key !== 'Escape') return;
        social.open = false;
        toggle.focus();
    });
})();
