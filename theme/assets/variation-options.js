/* global jQuery */
jQuery(function ($) {
    for (const form of document.querySelectorAll('.mv-product-buy form.variations_form')) {
        const groups = [];

        for (const select of form.querySelectorAll('.variations select')) {
            const label = select.labels[0];
            const originalOptions = $(select).data('attribute_options') || Array.from(select.options);
            const options = originalOptions.filter(option => option.value !== '');
            if (!label || !options.length) continue;

            label.id ||= select.id + '-choices-label';
            const group = document.createElement('div');
            group.className = 'mv-variation-choices';
            group.setAttribute('role', 'radiogroup');
            group.setAttribute('aria-labelledby', label.id);

            const choices = new Map();
            for (const option of options) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'mv-variation-choice';
                button.setAttribute('role', 'radio');
                const status = document.createElement('span');
                status.className = 'mv-variation-choice-status';
                status.setAttribute('aria-hidden', 'true');
                status.textContent = 'ناموجود';
                button.append(option.textContent, status);
                button.addEventListener('click', () => {
                    if (select.value === option.value) return;
                    select.value = option.value;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                });
                choices.set(option.value, { button, status });
                group.append(button);
            }

            function refresh() {
                const available = new Set(Array.from(select.options)
                    .filter(option => !option.disabled)
                    .map(option => option.value));
                let firstAvailable;
                let selected;
                for (const [value, { button, status }] of choices) {
                    button.disabled = !available.has(value) || select.disabled;
                    status.hidden = !button.disabled;
                    button.setAttribute('aria-checked', String(select.value === value));
                    button.tabIndex = -1;
                    if (button.disabled) continue;
                    firstAvailable ||= button;
                    if (select.value === value) selected = button;
                }
                const tabbable = selected || firstAvailable;
                if (tabbable) tabbable.tabIndex = 0;
            }

            group.addEventListener('keydown', event => {
                const buttons = Array.from(choices.values(), ({ button }) => button).filter(button => !button.disabled);
                const index = buttons.indexOf(event.target);
                if (index === -1) return;

                const rtl = getComputedStyle(group).direction === 'rtl';
                let next;
                switch (event.key) {
                    case 'ArrowLeft': next = index + (rtl ? 1 : -1); break;
                    case 'ArrowRight': next = index + (rtl ? -1 : 1); break;
                    case 'ArrowDown': next = index + 1; break;
                    case 'ArrowUp': next = index - 1; break;
                    case 'Home': next = 0; break;
                    case 'End': next = buttons.length - 1; break;
                    default: return;
                }
                event.preventDefault();
                const button = buttons[(next + buttons.length) % buttons.length];
                button.focus();
                button.click();
            });

            refresh();
            select.after(group);
            select.classList.add('mv-variation-native');
            select.hidden = true;
            groups.push({ group, refresh });
        }

        $(form).on('woocommerce_update_variation_values woocommerce_variation_has_changed wc_variation_form', () => {
            for (const { refresh } of groups) refresh();
        });
        $(form).on('reset_focus', () => {
            groups[0]?.group.querySelector('[tabindex="0"]')?.focus();
        });
    }
});
