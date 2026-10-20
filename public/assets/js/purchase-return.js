document.addEventListener('click', event => { if (event.target.matches('[data-remove-return]')) event.target.closest('tr').remove(); });
