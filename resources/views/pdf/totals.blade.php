@php($summary = \App\Support\PdfAmounts::summary($details))
<table class="summary"><tr><td>Subtotal sebelum diskon</td><td class="right">{{ \App\Support\PdfAmounts::money($summary['subtotal']) }}</td></tr><tr><td>Diskon</td><td class="right">{{ \App\Support\PdfAmounts::money($summary['diskon']) }}</td></tr><tr><td>Subtotal setelah diskon</td><td class="right">{{ \App\Support\PdfAmounts::money($summary['net']) }}</td></tr>
@if(isset($tax))<tr><td>PPN (nominal tersimpan)</td><td class="right">{{ \App\Support\PdfAmounts::money($tax) }}</td></tr>@endif
<tr class="grand"><td>{{ $totalLabel ?? 'Total' }}</td><td class="right">{{ \App\Support\PdfAmounts::money($total) }}</td></tr></table>
