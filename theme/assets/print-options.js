for (const fields of document.querySelectorAll('.mv-print-fields')) {
    const form = fields.closest('form.variations_form');
    if (!form) continue;

    const selections = Array.from(form.querySelectorAll('.variations select'));
    const inputs = fields.querySelectorAll('input');
    const update = () => {
        const custom = selections.some(select => select.value.replace('شمارهٔ', 'شماره').includes(fields.dataset.needle));
        fields.hidden = !custom;
        for (const input of inputs) {
            input.disabled = !custom;
            input.required = custom;
        }
    };
    for (const select of selections) select.addEventListener('change', update);
    form.querySelector('.reset_variations')?.addEventListener('click', () => setTimeout(update, 0));
    update();
}
