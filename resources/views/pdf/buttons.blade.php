<div class="d-flex gap-2 flex-wrap mb-3">
@can('access-route', $pdfModule.'.preview-pdf')<a class="btn btn-outline-primary" href="{{ route($pdfModule.'.preview-pdf', [str_replace('-', '_', $pdfModule) => $document->getKey()]) }}" target="_blank" rel="noopener">Preview PDF</a>@endcan
@can('access-route', $pdfModule.'.download-pdf')<a class="btn btn-outline-secondary" href="{{ route($pdfModule.'.download-pdf', [str_replace('-', '_', $pdfModule) => $document->getKey()]) }}">Download PDF</a>@endcan
</div>
