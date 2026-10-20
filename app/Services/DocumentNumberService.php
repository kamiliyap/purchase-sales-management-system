<?php

namespace App\Services;

use App\Models\InvoicePembelian;
use App\Models\InvoicePenjualan;
use App\Models\PenerimaanBarang;
use App\Models\PermintaanPembelian;
use App\Models\PurchaseOrder;
use App\Models\ReturPembelian;
use App\Models\ReturPenjualan;
use App\Models\SalesOrder;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class DocumentNumberService
{
    private const DOCUMENTS = [
        PermintaanPembelian::class => ['no_faktur', 'PR'],
        PenerimaanBarang::class => ['no_penerimaan', 'PB'],
        PurchaseOrder::class => ['no_order_pembelian', 'PO'],
        SalesOrder::class => ['no_sales_order', 'SO'],
        InvoicePembelian::class => ['no_invoice', 'INV-B'],
        InvoicePenjualan::class => ['no_invoice', 'INV-J'],
        ReturPembelian::class => ['no_retur', 'RET-B'],
        ReturPenjualan::class => ['no_retur', 'RET-J'],
    ];

    private array $locks = [];

    public function column(Model $model): string
    {
        return self::DOCUMENTS[$model::class][0];
    }

    public function preview(string $modelClass): string
    {
        return $this->next(new $modelClass, now('Asia/Jakarta')->year, false);
    }

    /** Generate and insert under a lock held until the enclosing transaction ends. */
    public function save(Model $model, callable $save): bool
    {
        $connection = $model->getConnection();
        $year = now('Asia/Jakarta')->year;

        $persist = function () use ($connection, $model, $year, $save) {
            $this->acquire($connection, self::DOCUMENTS[$model::class][1], $year);
            $model->setAttribute($this->column($model), $this->next($model, $year, true));

            return $save();
        };

        return $connection->transactionLevel() > 0 ? $persist() : $connection->transaction($persist, 5);
    }

    public function releaseConnection(Connection $connection): void
    {
        $prefix = spl_object_id($connection).':';
        foreach ($this->locks as $key => $token) {
            if (str_starts_with($key, $prefix)) {
                unset($this->locks[$key]);
                ($token->release)();
            }
        }
    }

    private function next(Model $model, int $year, bool $lock): string
    {
        [$column, $prefix] = self::DOCUMENTS[$model::class];
        $stem = "$prefix/$year/";
        $query = $model->newQueryWithoutScopes()->useWritePdo()->where($column, 'like', $stem.'%');
        if ($lock) {
            $query->lockForUpdate();
        }
        $maximum = 0;
        foreach ($query->pluck($column) as $number) {
            if (! preg_match('/^'.preg_quote($stem, '/').'([0-9]+)$/D', $number, $matches)) {
                continue;
            }
            $digits = ltrim($matches[1], '0');
            if (strlen($digits) > 18) {
                throw ValidationException::withMessages(['document' => 'Nomor urut dokumen melebihi batas yang dapat diproses.']);
            }
            $maximum = max($maximum, (int) $digits);
        }

        return $stem.str_pad((string) ($maximum + 1), 4, '0', STR_PAD_LEFT);
    }

    private function acquire(Connection $connection, string $prefix, int $year): void
    {
        $driver = $connection->getDriverName();
        $parts = [$driver, $connection->getConfig('host'), $connection->getConfig('port'), $connection->getDatabaseName()];
        // SQLite has one writer per database, so all document types share its lock.
        if ($driver !== 'sqlite') {
            array_push($parts, $prefix, $year);
        }
        $identity = implode('|', $parts);
        $name = 'document:'.substr(hash('sha256', $identity), 0, 48);
        $key = spl_object_id($connection).':'.$name;
        if (isset($this->locks[$key])) {
            return;
        }

        if ($driver === 'mysql') {
            $result = $connection->selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$name], false);
            if ((int) $result->acquired !== 1) {
                throw ValidationException::withMessages(['document' => 'Penomoran dokumen sedang sibuk. Silakan simpan kembali.']);
            }
            $release = fn () => $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$name], false);
        } elseif ($driver === 'sqlite') {
            $directory = storage_path('app/document-number-locks');
            if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                throw new \RuntimeException('Tidak dapat membuat direktori penguncian nomor dokumen.');
            }
            $file = fopen($directory.'/'.hash('sha256', $identity).'.lock', 'c');
            if ($file === false) {
                throw new \RuntimeException('Tidak dapat membuka penguncian nomor dokumen.');
            }
            $deadline = microtime(true) + 10;
            while (! flock($file, LOCK_EX | LOCK_NB)) {
                if (microtime(true) >= $deadline) {
                    fclose($file);
                    throw ValidationException::withMessages(['document' => 'Penomoran dokumen sedang sibuk. Silakan simpan kembali.']);
                }
                usleep(20000);
            }
            $release = function () use ($file) {
                flock($file, LOCK_UN);
                fclose($file);
            };
        } else {
            throw new \RuntimeException('Penomoran dokumen mendukung koneksi MySQL dan SQLite.');
        }

        $token = (object) ['release' => $release];
        $this->locks[$key] = $token;
        $finish = function () use ($key, $token) {
            if (($this->locks[$key] ?? null) === $token) {
                unset($this->locks[$key]);
                ($token->release)();
            }
        };
        $connection->afterRollBack($finish);
        $connection->afterCommit($finish);
    }
}
