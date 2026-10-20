<?php

namespace App\Models\Concerns;

use App\Services\DocumentNumberService;

trait HasDocumentNumber
{
    public function save(array $options = [])
    {
        $numbers = app(DocumentNumberService::class);
        if ($this->exists) {
            // A saved document keeps its number, even if a request submits another one.
            $column = $numbers->column($this);
            $this->setAttribute($column, $this->getRawOriginal($column));

            return parent::save($options);
        }

        return $numbers->save($this, fn () => parent::save($options));
    }
}
