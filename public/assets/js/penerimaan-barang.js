document.getElementById('receipt-items')?.addEventListener('click', (event) => {
    const row = event.target.closest('tr');
    if (event.target.matches('[data-remove]')) row.remove();
    if (event.target.matches('[data-split]')) {
        const clone = row.cloneNode(true);
        const indexes = [...document.querySelectorAll('#receipt-items input[name]')].map(input => Number(input.name.match(/items\[(\d+)\]/)?.[1] ?? 0));
        const next = Math.max(-1, ...indexes) + 1;
        clone.querySelectorAll('[name]').forEach(input => { input.name = input.name.replace(/items\[\d+\]/, `items[${next}]`); });
        row.after(clone);
    }
});
