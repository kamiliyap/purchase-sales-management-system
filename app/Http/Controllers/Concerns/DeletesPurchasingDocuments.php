<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

trait DeletesPurchasingDocuments
{
    private function deleteDocument(Model $document, string $route, string $label)
    {
        try {
            DB::transaction(function () use ($document) {
                $locked = $document->newQuery()->lockForUpdate()->findOrFail($document->getKey());
                $locked->delete();
            });
        } catch (QueryException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) !== 1451
                && !str_contains((string) ($exception->errorInfo[2] ?? ''), 'FOREIGN KEY constraint failed')) {
                throw $exception;
            }

            return redirect()->route($route)->withErrors(['document' => "$label tidak dapat dihapus karena sudah digunakan pada dokumen berikutnya."]);
        }

        return redirect()->route($route)->with('success', "$label berhasil dihapus.");
    }
}
