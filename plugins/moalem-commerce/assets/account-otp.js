(() => {
    const form = document.querySelector('[data-mv-otp-timing]');
    if (!form) return;

    const active = form.querySelector('[data-mv-otp-active]');
    const expired = form.querySelector('[data-mv-otp-expired]');
    const count = form.querySelector('[data-mv-otp-count]');
    const confirm = form.querySelector('.mv-account-submit');
    const resend = form.querySelector('[name="mv_resend"]');
    const resendCount = form.querySelector('[data-mv-otp-resend-count]');
    const started = Date.now();
    const number = new Intl.NumberFormat('fa-IR', { minimumIntegerDigits: 2, useGrouping: false });
    const format = seconds => number.format(Math.floor(seconds / 60)) + ':' + number.format(seconds % 60);

    const update = () => {
        const elapsed = Math.max(0, Math.floor((Date.now() - started) / 1000));
        const validFor = Math.max(0, Number(form.dataset.validFor) - elapsed);
        const resendIn = Math.max(0, Number(form.dataset.resendIn) - elapsed);
        active.hidden = validFor === 0;
        expired.hidden = validFor > 0;
        confirm.disabled = validFor === 0;
        resend.disabled = resendIn > 0;
        count.textContent = format(validFor);
        if (resendCount) {
            resendCount.hidden = resendIn === 0;
            resendCount.textContent = format(resendIn);
        }
        if (validFor === 0 && resendIn === 0) clearInterval(interval);
    };
    const interval = setInterval(update, 1000);
    update();
    window.addEventListener('pageshow', update);
})();
