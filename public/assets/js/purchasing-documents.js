document.addEventListener('DOMContentLoaded', () => {
    const source = document.getElementById('invoice-order-totals');
    const select = document.getElementById('id_purchase_order');
    const amount = document.getElementById('total_invoice');
    if (!source || !select || !amount) return;
    const totals = JSON.parse(source.textContent);
    select.addEventListener('change', () => { amount.value = totals[select.value] ?? ''; });
});
