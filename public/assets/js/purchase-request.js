const requestItems = document.getElementById('request-items');
document.getElementById('add-request-item')?.addEventListener('click', () => {
    const indexes = [...requestItems.querySelectorAll('[name]')].map(el => Number(el.name.match(/items\[(\d+)\]/)?.[1] ?? 0));
    const index = Math.max(-1, ...indexes) + 1;
    requestItems.insertAdjacentHTML('beforeend', document.getElementById('request-item-template').innerHTML.replaceAll('__INDEX__', String(index)));
});
requestItems?.addEventListener('click', event => {
    if (event.target.matches('[data-remove-request-item]')) event.target.closest('tr').remove();
});
