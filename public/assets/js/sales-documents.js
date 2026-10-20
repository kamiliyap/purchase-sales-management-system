document.addEventListener('DOMContentLoaded', () => {
    const source = document.getElementById('sales-invoice-order-totals');
    const select = document.getElementById('id_sales_order');
    const amount = document.getElementById('total_invoice');
    if (!source || !select || !amount) return;
    const totals = JSON.parse(source.textContent);
    select.addEventListener('change', () => { amount.value = totals[select.value] ?? ''; });
});
