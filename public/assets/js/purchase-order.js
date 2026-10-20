document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('purchase-order-form');
    if (!form) return;
    const body = document.getElementById('po-items');
    const add = document.getElementById('add-po-item');
    const rate = document.getElementById('ppn_rate');
    const numberFormat = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const money = cents => `Rp ${numberFormat.format(Number(cents) / 100)}`;
    const max = 999999999999999n;
    let nextIndex = Math.max(-1, ...[...body.querySelectorAll('[data-item-field="id_barang"]')].map(input => Number(input.name.match(/items\[(\d+)\]/)?.[1] ?? 0))) + 1;

    function cents(value) {
        if (!/^(\d+|\d*\.\d{1,2})$/.test(value)) return null;
        const [whole, fraction = ''] = value.split('.');
        return BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0'));
    }

    function recalculate() {
        let subtotal = 0n, quantity = 0n, invalid = false;
        const rows = [...body.querySelectorAll('[data-po-item]')];
        rows.forEach(row => {
            const qtyInput = row.querySelector('[data-item-field="kuantitas"]');
            const priceInput = row.querySelector('[data-item-field="harga_unit"]');
            const discountInput = row.querySelector('[data-item-field="diskon"]');
            const qty = /^\d+$/.test(qtyInput.value) ? BigInt(qtyInput.value) : 0n;
            const price = cents(priceInput.value), discount = cents(discountInput.value);
            discountInput.setCustomValidity('');
            const gross = qty * (price ?? 0n);
            if (discount !== null && discount > gross) {
                discountInput.setCustomValidity('Diskon tidak boleh melebihi kuantitas × harga unit.');
                invalid = true;
            }
            if (price === null || discount === null || qty < 1n || gross > max) invalid = true;
            const total = gross >= (discount ?? 0n) ? gross - (discount ?? 0n) : 0n;
            subtotal += total;
            quantity += qty;
            row.querySelector('[data-item-total]').textContent = numberFormat.format(Number(total) / 100);
            row.querySelector('[data-remove-item]').disabled = rows.length === 1;
        });
        const basisPoints = cents(rate.value) ?? 0n;
        const tax = (subtotal * basisPoints + 5000n) / 10000n;
        document.getElementById('po-subtotal').textContent = money(subtotal);
        document.getElementById('po-tax').textContent = money(tax);
        document.getElementById('po-grand-total').textContent = money(subtotal + tax);
        const outOfRange = subtotal + tax > max || quantity > 2147483647n;
        const error = document.getElementById('po-calculation-error');
        error.hidden = !outOfRange;
        error.textContent = outOfRange ? 'Total nominal atau jumlah barang melebihi batas yang dapat disimpan.' : '';
        add.disabled = rows.length >= 100;
        return !invalid && !outOfRange && rows.length > 0;
    }

    function populate(row) {
        const select = row.querySelector('[data-item-field="id_barang"]');
        const option = select.selectedOptions[0];
        row.querySelector('[data-item-sku]').textContent = option?.dataset.sku ?? '—';
        row.querySelector('[data-item-name]').textContent = option?.dataset.name ?? 'Pilih barang terlebih dahulu';
        row.querySelector('[data-item-field="harga_unit"]').value = option?.dataset.price ?? '';
    }

    add.addEventListener('click', () => {
        const template = document.getElementById('po-item-template');
        body.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(nextIndex++)));
        body.lastElementChild.querySelector('select').focus();
        recalculate();
    });
    body.addEventListener('change', event => {
        if (event.target.matches('[data-item-field="id_barang"]')) populate(event.target.closest('[data-po-item]'));
        recalculate();
    });
    body.addEventListener('input', recalculate);
    body.addEventListener('click', event => {
        const button = event.target.closest('[data-remove-item]');
        if (!button || body.children.length <= 1) return;
        button.closest('[data-po-item]').remove();
        recalculate();
    });
    rate.addEventListener('input', recalculate);
    form.addEventListener('submit', event => {
        if (!recalculate() || !form.checkValidity()) {
            event.preventDefault();
            form.reportValidity();
        }
    });
    recalculate();
});
