<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><title>{{ $title }} - {{ $number }}</title>
<style>
@page { size: A4 portrait; margin: 20mm 16mm 20mm; }
body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #111; background: #fff; line-height: 1.5; }
h1 { font-size: 18px; margin: 16px 0 10px; text-transform: uppercase; } h2 { font-size: 14px; margin: 0; }
.header { border-bottom: 2px solid #111; padding-bottom: 10px; } .logo { max-width: 65px; max-height: 55px; float: right; }
.muted { color: #444; font-size: 8px; } .meta { width: 100%; margin: 10px 0 18px; } .meta td { vertical-align: top; padding: 3px 6px 3px 0; }
table.items { width: 100%; border-collapse: collapse; table-layout: fixed; } .items th, .items td { border: 1px solid #444; padding: 6px 5px; vertical-align: top; overflow-wrap: break-word; word-wrap: break-word; }
.items th { background: #f3f3f3; font-weight: bold; text-align: left; } thead { display: table-header-group; } tr { page-break-inside: avoid; }
.right { text-align: right; } .summary { margin: 14px 0 16px auto; width: 55%; border-collapse: collapse; } .summary td { padding: 4px 6px; border-bottom: 1px solid #ccc; } .grand td { font-weight: bold; border-top: 2px solid #111; border-bottom: 2px solid #111; }
.signatures { width: 100%; margin-top: 28px; page-break-inside: avoid; border-collapse: collapse; } .signatures td { width: 50%; text-align: center; padding: 5px; vertical-align: top; } .sign-space { height: 65px; } .note { margin: 12px 0; } .footer { position: fixed; bottom: -12mm; left: 0; right: 0; border-top: 1px solid #aaa; padding-top: 4px; font-size: 7px; } .page:after { content: counter(page); }
@yield('pdf-styles')
</style></head><body>
<div class="header">@if($logo)<img class="logo" src="{{ $logo }}" alt="Logo">@endif<h2>{{ $company['name'] }}</h2><div>{{ $company['business'] }}</div>
@foreach(['address', 'email', 'phone'] as $field) @if($company[$field])<div>{{ $company[$field] }}</div>@endif @endforeach
@if($company['tax_number'])<div>NPWP: {{ $company['tax_number'] }}</div>@endif
@if($company['simulation'])<div class="muted">Identitas perusahaan simulasi untuk pembelajaran; bukan identitas legal perusahaan.</div>@endif</div>
@hasSection('document-heading')
@yield('document-heading')
@else
<h1>{{ $title }}</h1><table class="meta"><tr><td><strong>Nomor Dokumen</strong><br>{{ $number }}</td><td><strong>Tanggal Dokumen</strong><br>{{ $date }}</td><td><strong>Status</strong><br>{{ $document->status ?? 'Tercatat' }}</td></tr></table>
@endif
@yield('body')
<div class="footer">{{ $company['name'] }} | {{ $title }} | {{ $number }} <span style="float:right">Halaman <span class="page"></span></span></div>
</body></html>
