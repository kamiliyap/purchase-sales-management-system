<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>{{ $title }}</title><style>@include('pdf.purchasing.styles')</style></head><body>
<table class="kop"><tr><td class="company">
@if($logo)<img class="logo" src="{{ $logo }}" alt="Logo perusahaan">@endif
<div class="company-name">{{ $company['name'] }}</div>
<div class="business">{{ $company['business'] }}</div>
@if($company['simulation'])<div class="simulation">Identitas perusahaan simulasi untuk pembelajaran; bukan identitas legal perusahaan.</div>@endif
</td><td class="contacts">
@foreach(['address', 'phone', 'email'] as $field)@if($company[$field])<div>{{ $company[$field] }}</div>@endif @endforeach
@if($company['tax_number'])<div>NPWP: {{ $company['tax_number'] }}</div>@endif
</td></tr></table>

<table class="title-row"><tr><td><h1>{{ mb_strtoupper($title) }}</h1><div class="subtitle">PURCHASING DOCUMENT</div>@if($document->status)<div class="status">Status: {{ $document->status }}</div>@endif</td><td class="number-cell"><div class="number-box"><div class="label">{{ $numberLabel ?? 'Nomor Dokumen' }}</div><div class="document-number">@include('pdf.purchasing.number', ['value' => $number])</div></div></td></tr></table>
@yield('metadata')
@yield('body')
<div class="footer"><table><tr><td>{{ $company['name'] }} | {{ $title }} | @include('pdf.purchasing.number', ['value' => $number])</td><td class="page-cell">Halaman <span class="page"></span></td></tr></table></div>
</body></html>