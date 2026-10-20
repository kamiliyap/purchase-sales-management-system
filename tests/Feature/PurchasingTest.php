<?php

namespace Tests\Feature;

use App\Models\DetailPurchaseOrder;
use App\Models\InvoicePembelian;
use App\Models\MBarang;
use App\Models\MSupplier;
use App\Models\PermintaanPembelian;
use App\Models\PurchaseOrder;
use App\Models\ReturPembelian;
use App\Models\User;
use App\Services\PenerimaanBarangService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PurchasingTest extends TestCase
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

    public function test_request_crud_and_date_validation(): void
    {
        $this->get(route('permintaan-pembelian.index'))->assertOk()->assertSee('Belum ada Permintaan Pembelian');
        $this->get(route('permintaan-pembelian.create'))->assertOk()->assertSee('id="pemohon"', false);
        $data = $this->requestData();
        $this->post(route('permintaan-pembelian.store'), $data)->assertRedirect();
        $document = PermintaanPembelian::firstOrFail();
        $this->get(route('permintaan-pembelian.show', $document->getKey()))->assertOk()->assertSee($document->no_faktur);
        $this->get(route('permintaan-pembelian.edit', $document->getKey()))->assertOk()->assertSee($document->no_faktur);
        $data['status'] = 'Disetujui';
        $this->put(route('permintaan-pembelian.update', $document->getKey()), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('permintaan_pembelian', array_merge(collect($data)->except('items')->all(), ['no_faktur' => $document->no_faktur]));
        $this->post(route('permintaan-pembelian.store'), array_merge($data, ['tanggal_diperlukan' => '2026-10-01', 'id_pemohon' => 0]))
            ->assertSessionHasErrors(['tanggal_diperlukan', 'id_pemohon']);
        $this->delete(route('permintaan-pembelian.destroy', $document->getKey()))->assertRedirect(route('permintaan-pembelian.index'));
        $this->assertDatabaseCount('permintaan_pembelian', 0);
    }

    public function test_purchasing_flow_stores_real_item_snapshots_and_calculated_totals(): void
    {
        $data = $this->orderData();
        $data['total_harga'] = 1;
        $data['ppn'] = 0;
        $data['jumlah'] = 999;
        $data['items'][0]['sku'] = 'FORGED';
        $data['items'][0]['nama_barang'] = 'FORGED';
        $data['items'][0]['total_harga'] = 1;
        $this->get(route('purchase-order.create'))->assertOk()->assertSee('purchase-order.js');
        $this->post(route('purchase-order.store'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $order = PurchaseOrder::with('details')->firstOrFail();
        $this->assertDatabaseHas('purchase_order', ['id_purchase_order' => $order->getKey(), 'jumlah' => 5, 'ppn' => '37.95', 'total_harga' => '382.95']);
        $this->assertDatabaseHas('detail_purchase_order', ['id_purchase_order' => $order->getKey(), 'sku' => 'BRG-001', 'nama_barang' => 'Barang Pertama', 'total_harga' => '200.00']);
        $this->assertDatabaseHas('detail_purchase_order', ['id_purchase_order' => $order->getKey(), 'sku' => 'BRG-002', 'total_harga' => '145.00']);
        $this->assertDatabaseCount('detail_purchase_order', 2);
        $this->get(route('purchase-order.show', $order->getKey()))->assertOk()->assertSee('382,95')->assertSee('Barang Pertama');
        $this->delete(route('permintaan-pembelian.destroy', $order->id_permintaan))->assertSessionHasErrors('document');

        $this->receive($order);
        $this->get(route('invoice-pembelian.create'))->assertForbidden();
        $invoiceData = $this->invoiceData($order);
        $this->post(route('invoice-pembelian.store'), $invoiceData)->assertForbidden();
        $invoice = InvoicePembelian::firstOrFail();
        $this->get(route('invoice-pembelian.show', $invoice->getKey()))->assertOk()->assertSee($invoice->no_invoice)->assertSee($order->no_order_pembelian);
        $this->get(route('purchase-order.edit', $order->getKey()))->assertRedirect()->assertSessionHasErrors('document');
        $this->put(route('purchase-order.update', $order->getKey()), $data)->assertSessionHasErrors('document');
        $this->delete(route('purchase-order.destroy', $order->getKey()))->assertSessionHasErrors('document');
        $this->assertDatabaseCount('detail_purchase_order', 2);

        $this->get(route('retur-pembelian.create'))->assertOk()->assertSee('Penerimaan Barang');
        $returnData = ['id_invoice_pembelian' => $invoice->getKey(), 'no_retur' => 'RET-001', 'tanggal_retur' => '2026-10-15', 'alasan' => 'Barang rusak saat diterima', 'status' => 'Diajukan'];
        // Historical invoice-only records remain readable; new receipt-based flow is tested separately.
        ReturPembelian::create($returnData);
        $return = ReturPembelian::firstOrFail();
        $this->get(route('retur-pembelian.show', $return->getKey()))->assertOk()->assertSee($return->no_retur)->assertSee('Barang rusak saat diterima');
        $this->assertDatabaseHas('retur_pembelian', array_merge($returnData, ['no_retur' => $return->no_retur]));
        foreach (['permintaan-pembelian', 'purchase-order', 'invoice-pembelian', 'retur-pembelian'] as $module) {
            $html = $this->get(route($module.'.index'))->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/class="sidebar-link active"[^>]*href="'.preg_quote(route($module.'.index'), '/').'"[^>]*aria-current="page"/', $html);
        }
    }

    public function test_po_updates_replace_items_and_delete_cascades_items(): void
    {
        $data = $this->orderData();
        $this->post(route('purchase-order.store'), $data)->assertSessionHasNoErrors();
        $order = PurchaseOrder::firstOrFail();
        $this->get(route('purchase-order.edit', $order->getKey()))->assertOk()->assertSee('BRG-001')
            ->assertViewHas('document', fn ($document) => $document->details->count() === 2
                && (float) $document->details->first()->harga_unit === 100.10);
        $data['items'] = [['id_barang' => $data['items'][1]['id_barang'], 'kuantitas' => 1, 'harga_unit' => '99.99', 'diskon' => 0]];
        $data['ppn_rate'] = 10;
        $this->patch(route('purchase-order.update', $order->getKey()), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('detail_purchase_order', 1);
        $this->assertDatabaseHas('purchase_order', ['id_purchase_order' => $order->getKey(), 'jumlah' => 1, 'ppn' => '10.00', 'total_harga' => '109.99']);
        $this->delete(route('purchase-order.destroy', $order->getKey()))->assertRedirect(route('purchase-order.index'));
        $this->assertDatabaseCount('purchase_order', 0);
        $this->assertDatabaseCount('detail_purchase_order', 0);
    }

    public function test_invalid_order_items_do_not_create_headers_or_details(): void
    {
        $data = $this->orderData();
        foreach ([['items', []], ['id_supplier', 999], ['id_permintaan', 999], ['ppn_rate', 101], ['tanggal_dibutuhkan', '2026-10-01']] as [$field, $value]) {
            $this->post(route('purchase-order.store'), array_merge($data, [$field => $value]))->assertSessionHasErrors($field);
        }
        foreach ([['id_barang', 999], ['kuantitas', 0], ['kuantitas', 1.5], ['harga_unit', -1], ['harga_unit', '1.234'], ['diskon', 9999]] as [$field, $value]) {
            $bad = $data;
            $bad['items'][0][$field] = $value;
            $this->post(route('purchase-order.store'), $bad)->assertSessionHasErrors('items.0.'.$field);
        }
        $this->assertDatabaseCount('purchase_order', 0);
        $this->assertDatabaseCount('detail_purchase_order', 0);
    }

    public function test_totals_and_quantities_cannot_overflow_existing_columns(): void
    {
        $data = $this->orderData();
        $data['items'][0]['harga_unit'] = '9999999999999.99';
        $this->post(route('purchase-order.store'), $data)->assertSessionHasErrors('items.0.harga_unit');
        $data['items'][0]['kuantitas'] = 1;
        $data['items'] = [$data['items'][0]];
        $this->post(route('purchase-order.store'), $data)->assertSessionHasErrors('ppn_rate');
        $data['items'][0]['harga_unit'] = 0;
        $data['items'][0]['diskon'] = 0;
        $data['items'][0]['kuantitas'] = 2147483647;
        $data['items'][] = ['id_barang' => $data['items'][0]['id_barang'], 'kuantitas' => 1, 'harga_unit' => 0, 'diskon' => 0];
        $this->post(route('purchase-order.store'), $data)->assertSessionHasErrors('items');
        $this->assertDatabaseCount('purchase_order', 0);
    }

    public function test_po_transaction_rolls_back_header_and_items_on_detail_failure(): void
    {
        $data = $this->orderData();
        $this->post(route('purchase-order.store'), $data)->assertSessionHasNoErrors();
        $order = PurchaseOrder::firstOrFail();
        $beforeHeader = $order->toArray();
        $beforeDetails = $order->details()->get()->toArray();
        $event = 'eloquent.creating: '.DetailPurchaseOrder::class;
        Event::listen($event, fn () => throw new \RuntimeException('Simulated detail persistence failure'));
        $this->withoutExceptionHandling();
        try {
            foreach ([false, true] as $update) {
                try {
                    $update
                        ? $this->put(route('purchase-order.update', $order->getKey()), array_merge($data, ['no_order_pembelian' => 'PO-CHANGED']))
                        : $this->post(route('purchase-order.store'), $data);
                    $this->fail('Expected simulated persistence failure.');
                } catch (\RuntimeException $exception) {
                    $this->assertSame('Simulated detail persistence failure', $exception->getMessage());
                }
                $this->assertDatabaseCount('purchase_order', 1);
                $this->assertSame($beforeHeader, $order->fresh()->toArray());
                $this->assertSame($beforeDetails, $order->details()->get()->toArray());
            }
        } finally {
            Event::forget($event);
        }
    }

    public function test_invoice_and_return_validate_parents_dates_and_amounts(): void
    {
        $this->post(route('purchase-order.store'), $this->orderData())->assertSessionHasNoErrors();
        $order = PurchaseOrder::firstOrFail();
        $this->receive($order);
        $data = $this->invoiceData($order);
        $this->assertDatabaseCount('invoice_pembelian', 1);
        $invoice = InvoicePembelian::firstOrFail();
        $receipt = $order->penerimaanBarang()->firstOrFail();
        $return = ['id_penerimaan' => $receipt->getKey(), 'tanggal_retur' => '2026-10-15', 'alasan' => 'Rusak', 'status' => 'Diajukan',
            'items' => [['id_detail_penerimaan' => $receipt->details()->first()->getKey(), 'id_detail_purchase_order' => $order->details()->first()->getKey(), 'kuantitas' => 1, 'alasan' => 'Rusak']]];
        foreach ([['id_penerimaan', 999], ['tanggal_retur', '2026-10-01'], ['alasan', str_repeat('a', 256)]] as [$field, $value]) {
            $this->post(route('retur-pembelian.store'), array_merge($return, [$field => $value]))->assertSessionHasErrors($field);
        }
        $this->assertDatabaseCount('retur_pembelian', 0);
    }

    public function test_empty_prerequisites_and_missing_documents_render_safely(): void
    {
        foreach (['permintaan-pembelian', 'purchase-order', 'invoice-pembelian', 'retur-pembelian'] as $module) {
            $this->get(route($module.'.index'))->assertOk();
            $module === 'invoice-pembelian' ? $this->get(route($module.'.create'))->assertForbidden() : $this->get(route($module.'.create'))->assertOk();
            $this->get(route($module.'.show', 999))->assertNotFound();
        }
        $this->get(route('purchase-order.create'))->assertSee('Tambah Supplier');
        $this->get(route('invoice-pembelian.index'))->assertSee('Invoice dibuat otomatis');
        $this->get(route('retur-pembelian.create'))->assertSee('Belum ada penerimaan barang Rusak');
    }

    private function requestData(): array
    {
        $fixtureBarang = MBarang::firstOrCreate(['sku' => 'PR-FIXTURE'], ['nama_barang' => 'Barang Permintaan', 'satuan' => 'pcs', 'harga_beli' => 10, 'stok' => 0]);

        return ['items' => [['id_barang' => $fixtureBarang->getKey(), 'kuantitas' => 1, 'tanggal_dibutuhkan' => '2026-10-15']], 'no_faktur' => 'REQ-001', 'tanggal' => '2026-10-13', 'tanggal_diperlukan' => '2026-10-15', 'id_pemohon' => auth()->id(), 'id_penyetuju' => auth()->id(), 'status' => 'Draft'];
    }

    private function orderData(): array
    {
        $request = PermintaanPembelian::create(array_merge($this->requestData(), ['status' => 'Disetujui']));
        $supplier = MSupplier::create(['nama_supplier' => 'Supplier Purchasing', 'alamat' => 'Jakarta', 'telepon' => '081234567890', 'email' => 'supplier@example.com']);
        $first = MBarang::create(['sku' => 'BRG-001', 'nama_barang' => 'Barang Pertama', 'satuan' => 'pcs', 'harga_beli' => '100.10', 'stok' => 10]);
        $second = MBarang::create(['sku' => 'BRG-002', 'nama_barang' => 'Barang Kedua', 'satuan' => 'pcs', 'harga_beli' => 50, 'stok' => 10]);

        return ['id_permintaan' => $request->getKey(), 'id_supplier' => $supplier->getKey(), 'no_order_pembelian' => 'PO-001', 'tanggal' => '2026-10-13', 'tanggal_dibutuhkan' => '2026-10-15', 'ppn_rate' => 11, 'items' => [
            ['id_barang' => $first->getKey(), 'kuantitas' => 2, 'harga_unit' => '100.10', 'diskon' => '0.20'],
            ['id_barang' => $second->getKey(), 'kuantitas' => 3, 'harga_unit' => 50, 'diskon' => 5],
        ]];
    }

    private function receive(PurchaseOrder $order): void
    {
        app(PenerimaanBarangService::class)->save([
            'id_purchase_order' => $order->getKey(), 'tanggal_penerimaan' => $order->tanggal,
            'items' => $order->details->map(fn ($item) => ['id_barang' => $item->id_barang, 'kuantitas' => $item->kuantitas, 'kondisi' => 'Baik'])->all(),
        ], auth()->id());
    }

    private function invoiceData(PurchaseOrder $order): array
    {
        return ['id_purchase_order' => $order->getKey(), 'no_invoice' => 'INV-001', 'tanggal_invoice' => '2026-10-14', 'jatuh_tempo' => '2026-10-28', 'total_invoice' => '382.95', 'status' => 'Belum Lunas'];
    }
}
