/* global jQuery */
for (const fields of document.querySelectorAll('.mv-print-fields')) {
    const form = fields.closest('form.variations_form');
    if (!form) continue;

    const selections = Array.from(form.querySelectorAll('.variations select'));
    const inputs = fields.querySelectorAll('input');
    const customOptions = new Set(JSON.parse(fields.dataset.mvPrintOptions || '[]'));
    const update = () => {
        const custom = selections.some(select => customOptions.has(select.value));
        fields.hidden = !custom;
        for (const input of inputs) {
            input.disabled = !custom;
            input.required = custom;
        }
    };
    jQuery(form).on('woocommerce_variation_has_changed wc_variation_form', update);
    update();
}
