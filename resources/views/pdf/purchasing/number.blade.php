@if(! is_string($value) || trim($value) === '' || preg_match('/^[\s0\/._-]+$/D', $value))
<span class="number-warning">Nomor historis perlu verifikasi (nilai tersimpan: {{ is_string($value) && trim($value) !== '' ? $value : 'kosong' }})</span>
@else{{ $value }}@endif