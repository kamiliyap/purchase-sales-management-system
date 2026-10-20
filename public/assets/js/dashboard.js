document.addEventListener('DOMContentLoaded', () => {
    ['supplier', 'customer', 'barang'].forEach(resource => {
        const search = document.getElementById(`${resource}-search`);
        const rows = [...document.querySelectorAll(`[data-${resource}-row]`)];
        if (!search) return;
        search.addEventListener('input', () => {
            const query = search.value.trim().toLocaleLowerCase('id');
            let visible = 0;
            rows.forEach(row => {
                const content = [...row.cells].slice(1, -1).map(cell => cell.textContent).join(' ');
                row.hidden = !content.toLocaleLowerCase('id').includes(query);
                if (!row.hidden) visible++;
            });
            document.getElementById(`${resource}-no-results`).hidden = visible > 0 || rows.length === 0;
            document.getElementById(`${resource}-result-count`).textContent = query
                ? `${visible} dari ${rows.length} ${resource} ditemukan`
                : `${rows.length} ${resource} tercatat`;
        });
    });
    document.querySelectorAll('[data-confirm-delete]').forEach(form => {
        form.addEventListener('submit', event => {
            const resource = form.dataset.confirmDelete || 'supplier';
            if (!window.confirm(`Yakin ingin menghapus ${resource} ini?`)) event.preventDefault();
        });
    });
});
