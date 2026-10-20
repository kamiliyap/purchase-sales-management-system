<dl class="row mb-0">
    @foreach($fields as $field => $label)
        <dt class="col-sm-4 mb-2">{{ $label }}</dt><dd class="col-sm-8 mb-4 text-break">@if(in_array($field, $moneyFields ?? []))Rp {{ number_format(data_get($document, $field), 2, ',', '.') }}@else{{ data_get($document, $field) }}@endif</dd>
    @endforeach
</dl>
