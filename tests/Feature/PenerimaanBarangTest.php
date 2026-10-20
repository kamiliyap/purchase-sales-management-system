<?php

namespace Tests\Feature;

use App\Models\DetailPenerimaanBarang;
use App\Models\InvoicePembelian;
use App\Models\MBarang;
use App\Models\MSupplier;
use App\Models\PenerimaanBarang;
use App\Models\PermintaanPembelian;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\AutomaticPurchaseInvoiceService;
use App\Services\PenerimaanBarangService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PenerimaanBarangTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseOrder $order;

    private array $data;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create(['role' => 'Purchasing']);
        $this->actingAs($user);
        $pr = PermintaanPembelian::create(['tanggal' => '2026-10-13', 'tanggal_diperlukan' => '2026-10-15', 'id_pemohon' => $user->id, 'id_penyetuju' => $user->id, 'status' => 'Disetujui']);
        $supplier = MSupplier::create(['nama_supplier' => 'Supplier', 'alamat' => 'Jakarta', 'telepon' => '123', 'email' => 's@example.com']);
        $this->order = PurchaseOrder::create(['id_permintaan' => $pr->getKey(), 'id_supplier' => $supplier->getKey(), 'tanggal' => '2026-10-13', 'tanggal_dibutuhkan' => '2026-10-15', 'jumlah' => 10, 'ppn' => 0, 'total_harga' => 100]);
        $items = [];
        foreach (['A', 'B'] as $sku) {
            $barang = MBarang::create(['sku' => $sku, 'nama_barang' => $sku, 'satuan' => 'pcs', 'harga_beli' => 10, 'stok' => 7]);
            $this->order->details()->create(['id_barang' => $barang->getKey(), 'sku' => $sku, 'nama_barang' => $sku, 'kuantitas' => 5, 'harga_unit' => 10, 'diskon' => 0, 'total_harga' => 50]);
            $items[] = ['id_barang' => $barang->getKey(), 'kuantitas' => 2, 'kondisi' => 'Baik', 'keterangan' => 'Diperiksa'];
        }
        $this->data = ['id_purchase_order' => $this->order->getKey(), 'tanggal_penerimaan' => '2026-10-14', 'items' => $items];
    }

    private function receive(): PenerimaanBarang
    {
        $this->post(route('penerimaan-barang.store'), $this->data)->assertRedirect()->assertSessionHasNoErrors();

        return PenerimaanBarang::latest('id_penerimaan')->firstOrFail();
    }

    public function test_create_multiple_items_and_views_without_stock_changes(): void
    {
        $this->get(route('penerimaan-barang.create', ['purchase_order' => $this->order->getKey()]))->assertOk()->assertSee('A');
        $receipt = $this->receive();
        $this->assertDatabaseCount('detail_penerimaan_barang', 2);
        $this->assertSame(auth()->id(), $receipt->id_penerima);
        $this->assertStringStartsWith('PB/', $receipt->no_penerimaan);
        $this->get(route('penerimaan-barang.index'))->assertOk()->assertSee($receipt->no_penerimaan);
        $this->get(route('penerimaan-barang.show', $receipt))->assertOk()->assertSee('Diperiksa');
        $this->get(route('penerimaan-barang.edit', $receipt))->assertOk();
        $this->assertSame([7, 7], MBarang::pluck('stok')->all());
    }

    public function test_invalid_fields_and_outside_po_are_rejected_atomically(): void
    {
        $outside = MBarang::create(['sku' => 'X', 'nama_barang' => 'X', 'satuan' => 'pcs', 'harga_beli' => 1, 'stok' => 0]);
        foreach ([['id_barang', $outside->getKey()], ['kuantitas', 6], ['kuantitas', 0], ['kuantitas', 1.5], ['kondisi', 'Hilang']] as [$field, $value]) {
            $data = $this->data;
            $data['items'][1][$field] = $value;
            $this->post(route('penerimaan-barang.store'), $data)->assertSessionHasErrors('items.1.'.$field);
        }
        foreach ([['tanggal_penerimaan', '2026-10-12'], ['id_purchase_order', 999], ['items', []], ['id_penerima', 999]] as [$field, $value]) {
            $this->post(route('penerimaan-barang.store'), array_replace($this->data, [$field => $value]))->assertSessionHasErrors($field);
        }
        $this->assertDatabaseCount('penerimaan_barang', 0);
        $this->assertDatabaseCount('detail_penerimaan_barang', 0);
    }

    public function test_partial_receipts_and_duplicate_rows_share_po_limit(): void
    {
        $this->receive();
        $this->receive();
        $this->post(route('penerimaan-barang.store'), $this->data)->assertSessionHasErrors('items.0.kuantitas');
        $data = $this->data;
        $data['items'] = [$data['items'][0], $data['items'][0]];
        $this->post(route('penerimaan-barang.store'), $data)->assertSessionHasErrors('items.0.kuantitas');
        $data['items'] = [array_replace($data['items'][0], ['kuantitas' => 1, 'kondisi' => 'Rusak'])];
        $this->post(route('penerimaan-barang.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('penerimaan_barang', 3);
    }

    public function test_update_delete_and_po_protection(): void
    {
        $receipt = $this->receive();
        $data = $this->data;
        $data['items'][0]['kuantitas'] = 4;
        $this->put(route('penerimaan-barang.update', $receipt), $data)->assertSessionHasNoErrors();
        $this->assertSame(4, $receipt->details()->first()->kuantitas);
        $this->delete(route('purchase-order.destroy', $this->order))->assertSessionHasErrors('document');
        $this->get(route('purchase-order.edit', $this->order))->assertSessionHasErrors('document');
        $this->put(route('purchase-order.update', $this->order), [
            'id_permintaan' => $this->order->id_permintaan, 'id_supplier' => $this->order->id_supplier,
            'tanggal' => '2026-10-13', 'tanggal_dibutuhkan' => '2026-10-15', 'ppn_rate' => 0,
            'items' => [['id_barang' => $data['items'][0]['id_barang'], 'kuantitas' => 1, 'harga_unit' => 10, 'diskon' => 0]],
        ])->assertSessionHasErrors('document');
        $this->delete(route('penerimaan-barang.destroy', $receipt))->assertRedirect();
        $this->assertDatabaseCount('detail_penerimaan_barang', 0);
    }

    public function test_invoice_requires_receipt_and_then_uses_server_total_and_locks_receipt(): void
    {
        $this->assertDatabaseCount('invoice_pembelian', 0);
        $this->data['items'] = array_map(fn ($item) => array_replace($item, ['kuantitas' => 5]), $this->data['items']);
        $receipt = $this->receive();
        $this->assertEquals(100, InvoicePembelian::firstOrFail()->total_invoice);
        $this->post(route('invoice-pembelian.store'), [])->assertForbidden();
        $this->put(route('penerimaan-barang.update', $receipt), $this->data)->assertSessionHasErrors('document');
        $this->delete(route('penerimaan-barang.destroy', $receipt))->assertSessionHasErrors('document');
        $this->assertDatabaseCount('penerimaan_barang', 1);
    }

    public function test_role_permissions_cover_every_endpoint(): void
    {
        $receipt = $this->receive();
        foreach (['Sales', 'Finance', 'Admin', 'Purchasing'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['index', 'show', 'create', 'edit'] as $action) {
                $response = $this->get(route('penerimaan-barang.'.$action, in_array($action, ['show', 'edit']) ? $receipt : []));
                ($role === 'Sales' || ($role === 'Finance' && in_array($action, ['create', 'edit']))) ? $response->assertForbidden() : $response->assertOk();
            }
            if (in_array($role, ['Sales', 'Finance'])) {
                $this->post(route('penerimaan-barang.store'), $this->data)->assertForbidden();
                $this->put(route('penerimaan-barang.update', $receipt), $this->data)->assertForbidden();
                $this->delete(route('penerimaan-barang.destroy', $receipt))->assertForbidden();
            } else {
                $this->put(route('penerimaan-barang.update', $receipt), $this->data)->assertSessionHasNoErrors();
            }
        }
    }

    public function test_transaction_rolls_back_header_and_details_on_persistence_failure(): void
    {
        $receipt = $this->receive();
        $before = $receipt->details()->get()->toArray();
        $event = 'eloquent.creating: '.DetailPenerimaanBarang::class;
        Event::listen($event, fn () => throw new \RuntimeException('detail failure'));
        try {
            foreach ([null, $receipt] as $existing) {
                try {
                    app(PenerimaanBarangService::class)->save($this->data, auth()->id(), $existing);
                    $this->fail('Expected failure');
                } catch (\RuntimeException $exception) {
                    $this->assertSame('detail failure', $exception->getMessage());
                }
                $this->assertDatabaseCount('penerimaan_barang', 1);
                $this->assertSame($before, $receipt->details()->get()->toArray());
            }
        } finally {
            Event::forget($event);
        }
    }

    public function test_partial_good_receipts_create_invoice_only_when_every_item_is_complete(): void
    {
        $this->assertDatabaseCount('invoice_pembelian', 0);
        $this->receive();
        $this->assertSame('Terbuka', $this->order->fresh()->status);
        $this->assertDatabaseCount('invoice_pembelian', 0);
        $this->data['tanggal_penerimaan'] = '2026-10-17';
        $this->data['items'] = array_map(fn ($item) => array_replace($item, ['kuantitas' => 3]), $this->data['items']);
        $this->data['total_invoice'] = 1;
        $this->data['no_invoice'] = 'FORGED';
        $this->receive();
        $invoice = InvoicePembelian::firstOrFail();
        $this->assertEquals(100, $invoice->total_invoice);
        $this->assertTrue($invoice->dibuat_otomatis);
        $this->assertSame('2026-10-17', $invoice->tanggal_invoice);
        $this->assertSame('2026-11-16', $invoice->jatuh_tempo);
        $this->assertSame('Belum Lunas', $invoice->status);
        $this->assertSame('Diinvoice', $this->order->fresh()->status);
        $this->assertMatchesRegularExpression('/^INV-B\/[0-9]{4}\/[0-9]{4}$/', $invoice->no_invoice);
        $again = app(AutomaticPurchaseInvoiceService::class)->createIfComplete($this->order->getKey());
        $this->assertSame($invoice->getKey(), $again->getKey());
        $this->assertDatabaseCount('invoice_pembelian', 1);
        $this->get(route('invoice-pembelian.index'))->assertOk()->assertSee($invoice->no_invoice)->assertDontSee('Tambah Invoice Pembelian');
        $this->get(route('invoice-pembelian.show', $invoice))->assertOk()->assertSee('Invoice dibuat otomatis oleh sistem');
    }

    public function test_damaged_items_prevent_invoice_until_corrected_to_good(): void
    {
        $this->data['items'] = array_map(fn ($item) => array_replace($item, ['kuantitas' => 5]), $this->data['items']);
        $this->data['items'][1]['kondisi'] = 'Rusak';
        $receipt = $this->receive();
        $this->assertDatabaseCount('invoice_pembelian', 0);
        $this->assertSame('Terbuka', $this->order->fresh()->status);
        $this->data['items'][1]['kondisi'] = 'Baik';
        $this->put(route('penerimaan-barang.update', $receipt), $this->data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('invoice_pembelian', 1);
    }

    public function test_repeated_po_barang_rows_cannot_reuse_same_received_quantity(): void
    {
        $line = $this->order->details()->first();
        $this->order->details()->create(array_replace($line->only(['id_barang', 'sku', 'nama_barang', 'kuantitas', 'harga_unit', 'diskon', 'total_harga']), ['kuantitas' => 2]));
        $this->data['items'] = array_map(fn ($item) => array_replace($item, ['kuantitas' => 5]), $this->data['items']);
        $this->receive();
        $this->assertDatabaseCount('invoice_pembelian', 0);
        $this->data['items'] = [array_replace($this->data['items'][0], ['kuantitas' => 2])];
        $this->receive();
        $this->assertDatabaseCount('invoice_pembelian', 1);
    }

    public function test_invoice_insert_failure_rolls_back_completing_receipt_and_update(): void
    {
        $receipt = $this->receive();
        $before = $receipt->details()->get()->toArray();
        $event = 'eloquent.created: '.InvoicePembelian::class;
        Event::listen($event, fn () => throw new \RuntimeException('invoice failure'));
        try {
            foreach ([null, $receipt] as $existing) {
                $data = $this->data;
                $data['items'] = array_map(fn ($item) => array_replace($item, ['kuantitas' => $existing ? 5 : 3]), $data['items']);
                try {
                    app(PenerimaanBarangService::class)->save($data, auth()->id(), $existing);
                    $this->fail('Expected invoice failure');
                } catch (\RuntimeException $exception) {
                    $this->assertSame('invoice failure', $exception->getMessage());
                }
                $this->assertDatabaseCount('invoice_pembelian', 0);
                $this->assertDatabaseCount('penerimaan_barang', 1);
                $this->assertSame($before, $receipt->details()->get()->toArray());
                $this->assertSame('Terbuka', $this->order->fresh()->status);
            }
        } finally {
            Event::forget($event);
        }
        $data['items'] = array_map(fn ($item) => array_replace($item, ['kuantitas' => 3]), $this->data['items']);
        app(PenerimaanBarangService::class)->save($data, auth()->id());
        $this->assertMatchesRegularExpression('/^INV-B\/[0-9]{4}\/0001$/', InvoicePembelian::firstOrFail()->no_invoice);
    }

    public function test_finance_can_only_change_invoice_status_and_sales_is_denied(): void
    {
        $this->data['items'] = array_map(fn ($item) => array_replace($item, ['kuantitas' => 5]), $this->data['items']);
        $this->receive();
        $invoice = InvoicePembelian::firstOrFail();
        $this->actingAs(User::factory()->create(['role' => 'Finance']));
        $this->patch(route('invoice-pembelian.update', $invoice), ['status' => 'Invalid'])->assertSessionHasErrors('status');
        $this->patch(route('invoice-pembelian.update', $invoice), ['status' => 'Lunas', 'total_invoice' => 1, 'jatuh_tempo' => '2000-01-01'])->assertSessionHasNoErrors();
        $this->assertSame('Lunas', $invoice->fresh()->status);
        $this->assertEquals(100, $invoice->fresh()->total_invoice);
        $this->assertNotSame('2000-01-01', $invoice->fresh()->jatuh_tempo);
        $this->actingAs(User::factory()->create(['role' => 'Sales']));
        $this->patch(route('invoice-pembelian.update', $invoice), ['status' => 'Lunas'])->assertForbidden();
        $this->get(route('invoice-pembelian.index'))->assertForbidden();
    }

    public function test_guest_is_redirected_and_valid_receiver_cannot_be_spoofed(): void
    {
        $other = User::factory()->create(['role' => 'Admin']);
        $this->post(route('penerimaan-barang.store'), array_replace($this->data, ['id_penerima' => $other->id]))->assertSessionHasErrors('id_penerima');
        auth()->logout();
        $this->get(route('penerimaan-barang.index'))->assertRedirect(route('login'));
        $this->post(route('penerimaan-barang.store'), $this->data)->assertRedirect(route('login'));
    }

    public function test_historical_invoice_without_receipt_remains_readable(): void
    {
        $invoice = InvoicePembelian::create(['id_purchase_order' => $this->order->getKey(), 'tanggal_invoice' => '2026-10-14', 'jatuh_tempo' => '2026-10-20', 'status' => 'Belum Lunas', 'total_invoice' => 100]);
        $this->get(route('invoice-pembelian.show', $invoice))->assertOk();
        $this->assertDatabaseCount('penerimaan_barang', 0);
        $before = $invoice->fresh()->toArray();
        $this->data['items'] = array_map(fn ($item) => array_replace($item, ['kuantitas' => 5]), $this->data['items']);
        $this->receive();
        $this->assertDatabaseCount('invoice_pembelian', 1);
        $this->assertSame($before, $invoice->fresh()->toArray());
        $this->assertFalse($invoice->fresh()->dibuat_otomatis);
    }
}
