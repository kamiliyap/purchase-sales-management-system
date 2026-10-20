<?php

namespace Tests\Feature;

use App\Models\InvoicePembelian;
use App\Models\InvoicePenjualan;
use App\Models\MBarang;
use App\Models\MCustomer;
use App\Models\MSupplier;
use App\Models\PenerimaanBarang;
use App\Models\PermintaanPembelian;
use App\Models\PurchaseOrder;
use App\Models\ReturPembelian;
use App\Models\ReturPenjualan;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\DocumentNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DocumentNumberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->role = 'Admin';
        $user->save();
        $this->actingAs($user);
    }

    public function test_all_seven_document_types_increment_and_reset_at_jakarta_new_year(): void
    {
        $this->travelTo(Carbon::parse('2026-12-31 23:59:59', 'Asia/Jakarta'));
        $first = $this->bundle();
        $second = $this->bundle();
        foreach ($first as $prefix => [$document, $column]) {
            $this->assertSame("$prefix/2026/0001", $document->$column);
            $this->assertSame("$prefix/2026/0002", $second[$prefix][0]->$column);
        }
        $this->travelTo(Carbon::parse('2027-01-01 00:00:00', 'Asia/Jakarta'));
        foreach ($this->bundle() as $prefix => [$document, $column]) {
            $this->assertSame("$prefix/2027/0001", $document->$column);
        }
    }

    public function test_existing_numbers_cannot_be_changed_and_old_documents_keep_their_year(): void
    {
        $this->travelTo(Carbon::parse('2026-12-31 23:59:59', 'Asia/Jakarta'));
        $documents = $this->bundle();
        $this->travelTo(Carbon::parse('2027-01-01 00:00:00', 'Asia/Jakarta'));
        foreach ($documents as $prefix => [$document, $column]) {
            $document->update([$column => 'MANUAL/9999/0001']);
            $this->assertSame("$prefix/2026/0001", $document->fresh()->$column);
        }
    }

    public function test_preview_does_not_reserve_a_number_and_existing_sequences_are_read_numerically(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 08:00:00', 'Asia/Jakarta'));
        $numbers = app(DocumentNumberService::class);
        $this->assertSame('PR/2026/0001', $numbers->preview(PermintaanPembelian::class));
        $this->assertSame('PR/2026/0001', $numbers->preview(PermintaanPembelian::class));
        $this->assertDatabaseCount('permintaan_pembelian', 0);
        foreach (['PR/2025/9000', 'PR/2026/0009', 'PR/2026/10000', 'PR/2026/OTHER'] as $number) {
            DB::table('permintaan_pembelian')->insert(array_merge(collect($this->requestData())->except('items')->all(), ['no_faktur' => $number]));
        }
        $this->assertSame('PR/2026/10001', $numbers->preview(PermintaanPembelian::class));
        $document = PermintaanPembelian::create(array_merge($this->requestData(), ['no_faktur' => 'FORGED']));
        $this->assertSame('PR/2026/10001', $document->no_faktur);
    }

    public function test_numbering_is_rolled_back_and_lock_released_on_failure(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 08:00:00', 'Asia/Jakarta'));
        try {
            DB::transaction(function () {
                PermintaanPembelian::create($this->requestData());
                throw new \RuntimeException('Rollback numbering');
            });
            $this->fail('Expected rollback.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Rollback numbering', $exception->getMessage());
        }
        $this->assertDatabaseCount('permintaan_pembelian', 0);
        $document = PermintaanPembelian::create($this->requestData());
        $this->assertSame('PR/2026/0001', $document->no_faktur);
    }

    public function test_controller_accepts_no_document_number_and_ignores_forged_number_on_store_and_update(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 08:00:00', 'Asia/Jakarta'));
        $response = $this->get(route('permintaan-pembelian.create'))->assertOk();
        $this->assertMatchesRegularExpression('/name="no_faktur"[^>]*value="PR\/2026\/0001"[^>]*readonly/', $response->getContent());
        $this->post(route('permintaan-pembelian.store'), $this->requestData())->assertSessionHasNoErrors();
        $document = PermintaanPembelian::firstOrFail();
        $this->assertSame('PR/2026/0001', $document->no_faktur);
        $forged = array_merge($this->requestData(), ['no_faktur' => 'HACKED', 'status' => 'Disetujui']);
        $this->put(route('permintaan-pembelian.update', $document->getKey()), $forged)->assertSessionHasNoErrors();
        $this->assertSame('PR/2026/0001', $document->fresh()->no_faktur);
        $this->post(route('permintaan-pembelian.store'), $forged)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('permintaan_pembelian', ['no_faktur' => 'PR/2026/0002']);
        $this->assertDatabaseMissing('permintaan_pembelian', ['no_faktur' => 'HACKED']);
    }

    public function test_purchase_order_invoice_and_return_forms_are_readonly(): void
    {
        $documents = $this->bundle();
        $purchaseRequest = PermintaanPembelian::create(array_merge($this->requestData(), ['status' => 'Disetujui']));
        PurchaseOrder::create(['id_permintaan' => $purchaseRequest->getKey(), 'id_supplier' => MSupplier::firstOrFail()->getKey(), 'tanggal' => '2026-10-14', 'tanggal_dibutuhkan' => '2026-10-15', 'jumlah' => 1, 'ppn' => 0, 'total_harga' => 100]);
        MBarang::create(['sku' => 'TEST', 'nama_barang' => 'Test Barang', 'satuan' => 'pcs', 'harga_beli' => 100, 'stok' => 0]);
        PenerimaanBarang::create(['id_purchase_order' => PurchaseOrder::latest('id_purchase_order')->firstOrFail()->getKey(), 'tanggal_penerimaan' => '2026-10-14', 'id_penerima' => auth()->id()]);
        foreach (['purchase-order' => 'no_order_pembelian', 'retur-pembelian' => 'no_retur'] as $route => $column) {
            $html = $this->get(route($route.'.create'))->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/name="'.$column.'"[^>]*readonly/', $html);
        }
    }

    private function requestData(): array
    {
        $fixtureBarang = MBarang::firstOrCreate(['sku' => 'PR-FIXTURE'], ['nama_barang' => 'Barang Permintaan', 'satuan' => 'pcs', 'harga_beli' => 10, 'stok' => 0]);

        return ['items' => [['id_barang' => $fixtureBarang->getKey(), 'kuantitas' => 1, 'tanggal_dibutuhkan' => '2026-10-15']], 'tanggal' => '2026-10-14', 'tanggal_diperlukan' => '2026-10-15', 'id_pemohon' => auth()->id(), 'id_penyetuju' => auth()->id(), 'status' => 'Draft'];
    }

    private function bundle(): array
    {
        $request = PermintaanPembelian::create($this->requestData());
        $supplier = MSupplier::firstOrCreate(['email' => 'supplier@example.com'], ['nama_supplier' => 'Supplier Test', 'alamat' => 'Jakarta', 'telepon' => '081234567890']);
        $customer = MCustomer::firstOrCreate(['email' => 'customer@example.com'], ['nama_customer' => 'Customer Test', 'alamat' => 'Jakarta', 'telepon' => '081234567890']);
        $order = PurchaseOrder::create(['id_permintaan' => $request->getKey(), 'id_supplier' => $supplier->getKey(), 'tanggal' => '2026-10-14', 'tanggal_dibutuhkan' => '2026-10-15', 'jumlah' => 1, 'ppn' => 0, 'total_harga' => 100]);
        $sales = SalesOrder::create(['id_customer' => $customer->getKey(), 'tanggal' => '2026-10-14', 'total_harga' => 100]);
        $buyInvoice = InvoicePembelian::create(['id_purchase_order' => $order->getKey(), 'tanggal_invoice' => '2026-10-14', 'jatuh_tempo' => '2026-10-15', 'total_invoice' => 100, 'status' => 'Belum Lunas']);
        $saleInvoice = InvoicePenjualan::create(['id_sales_order' => $sales->getKey(), 'tanggal_invoice' => '2026-10-14', 'jatuh_tempo' => '2026-10-15', 'total_invoice' => 100, 'status' => 'Belum Lunas']);
        $buyReturn = ReturPembelian::create(['id_invoice_pembelian' => $buyInvoice->getKey(), 'tanggal_retur' => '2026-10-15', 'alasan' => 'Rusak', 'status' => 'Diajukan']);
        $saleReturn = ReturPenjualan::create(['id_invoice_penjualan' => $saleInvoice->getKey(), 'tanggal_retur' => '2026-10-15', 'alasan' => 'Rusak', 'status' => 'Diajukan']);

        return ['PR' => [$request, 'no_faktur'], 'PO' => [$order, 'no_order_pembelian'], 'SO' => [$sales, 'no_sales_order'], 'INV-B' => [$buyInvoice, 'no_invoice'], 'INV-J' => [$saleInvoice, 'no_invoice'], 'RET-B' => [$buyReturn, 'no_retur'], 'RET-J' => [$saleReturn, 'no_retur']];
    }
}
