<div class="row g-4">
    @foreach ($fields as $name => $field)
        <div class="{{ $field['wide'] ?? false ? 'col-12' : 'col-md-6' }}">
            <label class="form-label" for="{{ $name }}">{{ $field['label'] }} @unless($field['readonly'] ?? false)<span class="text-danger">*</span>@endunless</label>
            @if (($field['type'] ?? '') === 'display')
                <input id="{{ $name }}" type="text" class="form-control bg-light" value="{{ $field['value'] }}" readonly>
                <div class="form-text">{{ $field['help'] ?? '' }}</div>
            @elseif ($field['readonly'] ?? false)
                <input id="{{ $name }}" name="{{ $name }}" type="text" class="form-control bg-light" value="{{ $document->$name ?? $field['default'] ?? '' }}" readonly aria-describedby="{{ $name }}-help">
                <div class="form-text" id="{{ $name }}-help">{{ $field['help'] ?? (isset($document) ? 'Nomor dokumen tetap dan tidak dapat diubah.' : 'Nomor otomatis. Nomor final ditetapkan saat dokumen disimpan.') }}</div>
            @elseif (($field['type'] ?? 'text') === 'select')
                <select id="{{ $name }}" name="{{ $name }}" class="form-select form-control @error($name) is-invalid @enderror" required @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror>
                    <option value="">Pilih {{ $field['label'] }}</option>
                    @foreach ($field['options'] as $value => $label)
                        <option value="{{ $value }}" @selected((string) old($name, $document->$name ?? $field['default'] ?? '') === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            @elseif (($field['type'] ?? 'text') === 'textarea')
                <textarea id="{{ $name }}" name="{{ $name }}" class="form-control @error($name) is-invalid @enderror" rows="3" maxlength="255" required @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror>{{ old($name, $document->$name ?? $field['default'] ?? '') }}</textarea>
            @else
                <input id="{{ $name }}" name="{{ $name }}" type="{{ $field['type'] ?? 'text' }}" class="form-control @error($name) is-invalid @enderror" value="{{ old($name, $document->$name ?? $field['default'] ?? '') }}" @if(($field['type'] ?? 'text') === 'number') min="{{ $field['min'] ?? 0 }}" max="{{ $field['max'] ?? '9999999999999.99' }}" step="{{ $field['step'] ?? '1' }}" @else maxlength="255" @endif required @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror>
            @endif
            @error($name)<div class="invalid-feedback" id="{{ $name }}-error">{{ $message }}</div>@enderror
        </div>
    @endforeach
</div>
