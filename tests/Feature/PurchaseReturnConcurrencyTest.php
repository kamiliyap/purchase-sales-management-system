<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class PurchaseReturnConcurrencyTest extends TestCase
{
    public function test_concurrent_returns_and_replacements_respect_quotas_and_create_one_invoice(): void
    {
        $database = 'purchase_sales_returns_test_'.bin2hex(random_bytes(8));
        $make = fn ($mode) => new Process([PHP_BINARY, base_path('tests/Support/purchase-return-concurrency.php'), $mode, $database], base_path(), null, null, 60);
        try {
            $prepare = $make('prepare');
            $prepare->mustRun();
            $this->assertSame('prepared', trim($prepare->getOutput()));
            foreach (['return-worker' => ['rejected', 'saved'], 'replacement-worker' => ['rejected', 'saved', 'saved']] as $mode => $expected) {
                if ($mode === 'replacement-worker') {
                    $ship = $make('ship');
                    $ship->mustRun();
                    $this->assertSame('shipped', trim($ship->getOutput()));
                }
                $workers = [];
                foreach ($expected as $_) {
                    $worker = $make($mode);
                    $worker->start();
                    $workers[] = $worker;
                }
                $outputs = [];
                foreach ($workers as $worker) {
                    $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
                    $outputs[] = trim($worker->getOutput());
                }
                sort($outputs);
                $this->assertSame($expected, $outputs);
            }
            $check = $make('check');
            $check->mustRun();
            $this->assertJson($check->getOutput());
            $result = json_decode($check->getOutput(), true);
            $this->assertSame(1, $result['returns']);
            $this->assertSame(2, $result['replacements']);
            $this->assertSame(1, $result['invoices']);
            $this->assertMatchesRegularExpression('/^INV-B\/[0-9]{4}\/0001$/', $result['number']);
        } finally {
            $make('cleanup')->mustRun();
        }
    }
}
