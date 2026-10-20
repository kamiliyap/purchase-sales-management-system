document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById('purchaseSalesChart');
    const source = document.getElementById('purchase-sales-data');
    if (!canvas || !source) return;
    if (typeof Chart === 'undefined') {
        canvas.closest('.chart-container').hidden = true;
        document.querySelector('.chart-details').open = true;
        return;
    }
    const data = JSON.parse(source.textContent);
    const rupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
    const compact = new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 });
    new Chart(canvas, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [
                { label: 'Purchase', data: data.purchase, borderColor: '#3a57e8', backgroundColor: 'rgba(58,87,232,.08)', fill: true, tension: .35, borderWidth: 3, pointRadius: 3, pointHoverRadius: 6 },
                { label: 'Sales', data: data.sales, borderColor: '#17a398', backgroundColor: 'rgba(23,163,152,.04)', fill: true, tension: .35, borderWidth: 3, pointRadius: 3, pointHoverRadius: 6 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 650 },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: { backgroundColor: '#192444', padding: 12, callbacks: { label: context => `${context.dataset.label}: ${rupiah.format(context.parsed.y)}` } }
            },
            scales: {
                x: { grid: { display: false }, border: { display: false }, ticks: { color: '#7b849a', font: { size: 11 }, maxRotation: 0 } },
                y: { beginAtZero: true, suggestedMax: data.purchase.every(value => value === 0) && data.sales.every(value => value === 0) ? 1000000 : undefined, border: { display: false }, grid: { color: '#f0f2f7' }, ticks: { color: '#7b849a', maxTicksLimit: 5, callback: value => `Rp ${compact.format(value)}` } }
            }
        }
    });
});
