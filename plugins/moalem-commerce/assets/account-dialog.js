(() => {
    const dialog = document.querySelector('.mv-account-dialog');
    if (!dialog || typeof dialog.showModal !== 'function') return;

    const content = dialog.querySelector('.mv-account-dialog-content');
    const status = dialog.querySelector('.mv-account-dialog-status');
    const account = new URL(dialog.dataset.account);
    const path = url => url.pathname.replace(/\/+$/, '');
    const destinations = [dialog.dataset.cart, dialog.dataset.checkout].map(url => path(new URL(url)));
    let authorized = false;
    let intent = null;
    let trigger = null;
    let request = null;

    async function load(url, options = {}) {
        if (request) return;
        const controller = new AbortController();
        request = controller;
        status.textContent = 'در حال بارگذاری…';
        status.hidden = false;
        content.inert = true;
        try {
            const response = await fetch(url, {
                ...options, credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
            });
            const returned = new URL(response.url);
            if (!response.ok || returned.origin !== account.origin || path(returned) !== path(account)
                || returned.searchParams.get('mv_modal') !== '1') throw new Error('Account fragment unavailable');
            const fragment = new DOMParser().parseFromString(await response.text(), 'text/html');
            if (controller.signal.aborted || !dialog.open) return;
            if (fragment.querySelector('[data-mv-authenticated]')) {
                authorized = true;
                const resume = intent;
                dialog.close();
                resume?.();
                return;
            }
            const panel = fragment.querySelector('.mv-account');
            if (!panel) throw new Error('Account fragment missing');
            content.replaceChildren(panel);
            content.inert = false;
            status.hidden = true;
            document.dispatchEvent(new CustomEvent('mv-account-fragment', { detail: content }));
            const heading = panel.querySelector('h2');
            if (heading) {
                heading.tabIndex = -1;
                heading.focus();
            }
        } catch {
            if (controller.signal.aborted) return;
            status.textContent = 'بارگذاری پنجرهٔ ورود ممکن نشد. ';
            const fallback = document.createElement('a');
            fallback.href = dialog.dataset.accountPage;
            fallback.textContent = 'رفتن به صفحهٔ ورود';
            fallback.addEventListener('click', event => {
                if (!intent) return;
                event.preventDefault();
                authorized = true;
                const resume = intent;
                dialog.close();
                resume();
            });
            status.append(fallback);
            fallback.focus();
        } finally {
            if (request === controller) {
                content.inert = false;
                request = null;
            }
        }
    }

    function open(action, control) {
        if (dialog.open) return;
        intent = action;
        trigger = control;
        dialog.showModal();
        load(account);
    }

    document.addEventListener('submit', event => {
        const form = event.target;
        if (authorized || !form.matches('form.cart') || new URL(form.action).origin !== location.origin) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        const button = event.submitter;
        open(() => form.requestSubmit(button || undefined), button || form);
    }, true);

    document.addEventListener('click', event => {
        if (authorized || event.defaultPrevented || event.button || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const link = event.target.closest('a[href]');
        if (!link || (link.target && link.target !== '_self') || link.hasAttribute('download')) return;
        const url = new URL(link.href);
        if (url.origin !== location.origin || (!destinations.includes(path(url)) && !url.searchParams.has('add-to-cart'))) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        open(() => location.assign(url), link);
    }, true);

    dialog.addEventListener('click', event => {
        if (event.target.closest('.mv-account-dialog-close')) dialog.close();
        const link = event.target.closest('.mv-account a[href]');
        if (!link || event.button || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const url = new URL(link.href);
        if (url.origin !== account.origin || path(url) !== path(account) || url.searchParams.get('mv_modal') !== '1') return;
        event.preventDefault();
        load(url);
    });

    dialog.addEventListener('submit', event => {
        event.preventDefault();
        load(event.target.action, { method: 'POST', body: new FormData(event.target, event.submitter) });
    });

    dialog.addEventListener('close', () => {
        request?.abort();
        request = null;
        content.replaceChildren();
        status.hidden = true;
        intent = null;
        trigger?.focus({ preventScroll: true });
        trigger = null;
    });
})();
