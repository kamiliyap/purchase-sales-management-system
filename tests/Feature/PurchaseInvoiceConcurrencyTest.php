<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class PurchaseInvoiceConcurrencyTest extends TestCase
{
    public function test_two_concurrent_receipt_writers_produce_exactly_one_invoice(): void
    {
        $database = 'purchase_sales_invoice_test_'.bin2hex(random_bytes(8));
        $make = fn (string $mode) => new Process([PHP_BINARY, base_path('tests/Support/purchase-invoice-concurrency.php'), $mode, $database], base_path(), null, null, 60);
        $prepare = $make('prepare');
        try {
            $prepare->mustRun();
            $this->assertSame('prepared', trim($prepare->getOutput()));
            $first = $make('worker');
            $second = $make('worker');
            $first->start();
            $second->start();
            $this->assertSame(0, $first->wait(), $first->getErrorOutput());
            $this->assertSame(0, $second->wait(), $second->getErrorOutput());
            $this->assertSame('saved', trim($first->getOutput()));
            $this->assertSame('saved', trim($second->getOutput()));
            $check = $make('check');
            $check->mustRun();
            $output = $check->getOutput();
            $this->assertJson($output, $output);
            $result = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame(2, $result['receipts']);
            $this->assertSame(1, $result['invoices']);
            $this->assertSame('Diinvoice', $result['status']);
            $this->assertMatchesRegularExpression('/^INV-B\/[0-9]{4}\/0001$/', $result['number']);
        } finally {
            $make('cleanup')->mustRun();
        }
    }
}
