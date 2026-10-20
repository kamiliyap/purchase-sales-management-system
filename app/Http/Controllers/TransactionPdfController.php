<?php

namespace App\Http\Controllers;

use App\Services\TransactionPdfService;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class TransactionPdfController extends Controller
{
    public function __invoke(Request $request, TransactionPdfService $service)
    {
        $module = $request->route('type');
        $id = (int) $request->route(str_replace('-', '_', $module));
        try {
            [$pdf, $filename] = $service->render($module, $id);
        } catch (HttpExceptionInterface $exception) {
            if ($exception->getStatusCode() !== 422) {
                throw $exception;
            }

            return response()->view('pdf.export-unavailable', [
                'message' => $exception->getMessage(),
                'documentUrl' => route($module.'.show', $id),
            ], 422)->header('Cache-Control', 'private, no-store, max-age=0');
        }
        $response = $request->route('download') ? $pdf->download($filename) : $pdf->stream($filename);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
