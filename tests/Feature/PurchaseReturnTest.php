<?php

namespace Tests\Feature;

use App\Models\DetailPurchaseOrder;
use App\Models\DetailReturPembelian;
use App\Models\InvoicePembelian;
use App\Models\MBarang;
use App\Models\MSupplier;
use App\Models\PenerimaanBarang;
use App\Models\PermintaanPembelian;
use App\Models\PurchaseOrder;
use App\Models\ReturPembelian;
use App\Models\User;
use App\Services\PenerimaanBarangService;
use App\Services\ReturPembelianService;
use App\Services\TransactionPdfService;
use App\Support\PurchaseReturnAmounts;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PurchaseReturnTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private PurchaseOrder $order;

    private PenerimaanBarang $receipt;

    private $poLine;

    private $damaged;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buyer = User::factory()->create(['role' => 'Purchasing', 'name' => 'Penerima Uji']);
        $this->actingAs($this->buyer);
        $pr = PermintaanPembelian::create(['tanggal' => '2026-10-01', 'tanggal_diperlukan' => '2026-10-02', 'id_pemohon' => $this->buyer->id, 'id_penyetuju' => $this->buyer->id, 'status' => 'Disetujui']);
        $supplier = MSupplier::create(['nama_supplier' => 'Supplier Uji', 'alamat' => 'Alamat Uji', 'telepon' => '123', 'email' => 'return@example.test']);
        $barang = MBarang::create(['sku' => 'RET-ITEM', 'nama_barang' => 'Perangkat Uji', 'satuan' => 'unit', 'harga_beli' => 100.10, 'stok' => 42]);
        $this->order = PurchaseOrder::create(['id_permintaan' => $pr->getKey(), 'id_supplier' => $supplier->getKey(), 'tanggal' => '2026-10-02', 'tanggal_dibutuhkan' => '2026-10-03', 'jumlah' => 10, 'ppn' => '110.00', 'total_harga' => '1110.00']);
        $this->poLine = $this->order->details()->create(['id_barang' => $barang->getKey(), 'sku' => $barang->sku, 'nama_barang' => $barang->nama_barang, 'kuantitas' => 10, 'harga_unit' => '100.10', 'diskon' => '1.00', 'total_harga' => '1000.00']);
        $this->receipt = app(PenerimaanBarangService::class)->save(['id_purchase_order' => $this->order->getKey(), 'tanggal_penerimaan' => '2026-10-03', 'items' => [
            ['id_barang' => $barang->getKey(), 'kuantitas' => 7, 'kondisi' => 'Baik'], ['id_barang' => $barang->getKey(), 'kuantitas' => 3, 'kondisi' => 'Rusak']]], $this->buyer->id);
        $this->damaged = $this->receipt->details()->where('kondisi', 'Rusak')->firstOrFail();
    }

    private function data(int $qty = 3): array
    {
        return ['id_penerimaan' => $this->receipt->getKey(), 'tanggal_retur' => '2026-10-04', 'alasan' => 'Kerusakan fisik', 'items' => [
            ['id_detail_penerimaan' => $this->damaged->getKey(), 'id_detail_purchase_order' => $this->poLine->getKey(), 'kuantitas' => $qty, 'alasan' => 'Casing rusak']]];
    }

    private function submit(int $qty = 3): ReturPembelian
    {
        $this->post(route('retur-pembelian.store'), $this->data($qty))->assertRedirect()->assertSessionHasNoErrors();

        return ReturPembelian::latest('id_retur_pembelian')->firstOrFail();
    }

    private function ship(ReturPembelian $return): void
    {
        foreach (['Disetujui', 'Selesai'] as $status) {
            $this->patch(route('retur-pembelian.update', $return), ['status' => $status, 'tanggal_dikirim' => '2026-10-05'])->assertSessionHasNoErrors();
        }
    }

    private function replacementData($line, int $qty, string $condition = 'Baik'): array
    {
        return ['id_purchase_order' => $this->order->getKey(), 'tanggal_penerimaan' => CarbonImmutable::parse($line->returPembelian->tanggal_dikirim ?? '2026-10-05')->addDay()->toDateString(), 'items' => [
            ['id_barang' => $this->poLine->id_barang, 'kuantitas' => $qty, 'kondisi' => $condition, 'id_detail_retur_asal' => $line->getKey()]]];
    }

    private function replace($line, int $qty, string $condition = 'Baik'): PenerimaanBarang
    {
        $this->post(route('penerimaan-barang.store'), $this->replacementData($line, $qty, $condition))->assertSessionHasNoErrors();

        return PenerimaanBarang::latest('id_penerimaan')->firstOrFail();
    }

    public function test_return_before_invoice_uses_server_po_amounts_and_reserves_damaged_units(): void
    {
        $data = $this->data();
        $data += ['total_retur' => 1, 'ppn' => 0, 'id_invoice_pembelian' => 999, 'no_retur' => 'FORGED'];
        $data['items'][0] += ['harga_unit' => 1, 'diskon' => 999, 'total_harga' => 1];
        $this->post(route('retur-pembelian.store'), $data)->assertSessionHasNoErrors();
        $return = ReturPembelian::firstOrFail();
        $this->assertNull($return->id_invoice_pembelian);
        $this->assertSame('Diajukan', $return->status);
        $this->assertSame('300.00', $return->subtotal);
        $this->assertSame('0.30', $return->diskon);
        $this->assertSame('33.00', $return->ppn);
        $this->assertSame('333.00', $return->total_retur);
        $this->assertSame('100.10', $return->details->first()->harga_unit);
        $this->assertStringStartsWith('RET-B/', $return->no_retur);
        $this->assertDatabaseCount('invoice_pembelian', 0);
        $this->post(route('retur-pembelian.store'), $this->data(1))->assertSessionHasErrors('items.0.kuantitas');
        $this->assertSame(42, MBarang::firstOrFail()->stok);
    }

    public function test_invalid_sources_quantities_dates_and_duplicate_pairs_are_rejected(): void
    {
        foreach ([['kuantitas', 4], ['kuantitas', 0], ['kuantitas', 1.5], ['id_detail_penerimaan', 999], ['id_detail_purchase_order', 999], ['id_detail_penerimaan', $this->receipt->details()->where('kondisi', 'Baik')->first()->getKey()]] as [$key,$value]) {
            $data = $this->data();
            $data['items'][0][$key] = $value;
            $this->post(route('retur-pembelian.store'), $data)->assertSessionHasErrors('items.0.'.$key);
        }
        $data = $this->data(2);
        $data['items'][] = $data['items'][0];
        $this->post(route('retur-pembelian.store'), $data)->assertSessionHasErrors('items.1.kuantitas');
        $this->post(route('retur-pembelian.store'), array_replace($this->data(), ['tanggal_retur' => '2026-10-02']))->assertSessionHasErrors('tanggal_retur');
        $this->post(route('retur-pembelian.store'), array_replace($this->data(), ['status' => 'Selesai']))->assertSessionHasErrors('status');
        $this->assertDatabaseCount('retur_pembelian', 0);
    }

    public function test_all_status_transitions_are_checked_and_shipping_date_is_required(): void
    {
        $return = $this->submit(1);
        $valid = ['Diajukan' => ['Disetujui', 'Ditolak'], 'Disetujui' => ['Selesai']];
        foreach (['Diajukan', 'Disetujui', 'Ditolak', 'Selesai'] as $from) {
            foreach (['Diajukan', 'Disetujui', 'Ditolak', 'Selesai'] as $to) {
                DB::table('retur_pembelian')->where('id_retur_pembelian', $return->getKey())->update(['status' => $from]);
                $response = $this->patch(route('retur-pembelian.update', $return), ['status' => $to, 'tanggal_dikirim' => '2026-10-05']);
                if (in_array($to, $valid[$from] ?? [], true)) {
                    $response->assertSessionHasNoErrors();
                    $this->assertSame($to, $return->fresh()->status);
                } else {
                    $response->assertSessionHasErrors('status');
                    $this->assertSame($from, $return->fresh()->status);
                }
            }
        }
        DB::table('retur_pembelian')->where('id_retur_pembelian', $return->getKey())->update(['status' => 'Disetujui']);
        $this->patch(route('retur-pembelian.update', $return), ['status' => 'Selesai'])->assertSessionHasErrors('tanggal_dikirim');
        $this->patch(route('retur-pembelian.update', $return), ['status' => 'Selesai', 'tanggal_dikirim' => '2026-10-01'])->assertSessionHasErrors('tanggal_dikirim');
    }

    public function test_rejection_releases_reservation_but_does_not_open_replacement_quota(): void
    {
        $return = $this->submit();
        $this->patch(route('retur-pembelian.update', $return), ['status' => 'Ditolak'])->assertSessionHasNoErrors();
        $this->submit();
        $this->post(route('penerimaan-barang.store'), $this->replacementData($return->details->first(), 1))->assertSessionHasErrors('items.0.id_detail_retur_asal');
        $this->assertDatabaseCount('invoice_pembelian', 0);
    }

    public function test_partial_replacements_complete_good_quantity_and_create_one_full_invoice(): void
    {
        $return = $this->submit();
        $line = $return->details->first();
        $this->post(route('penerimaan-barang.store'), $this->replacementData($line, 1))->assertSessionHasErrors('items.0.id_detail_retur_asal');
        $this->ship($return);
        $this->assertDatabaseCount('invoice_pembelian', 0);
        $this->replace($line, 1);
        $this->assertDatabaseCount('invoice_pembelian', 0);
        $this->replace($line, 2);
        $this->assertDatabaseCount('invoice_pembelian', 1);
        $this->assertSame('Diinvoice', $this->order->fresh()->status);
        $this->assertEquals('1110.00', InvoicePembelian::firstOrFail()->total_invoice);
        $this->assertSame('Belum Lunas', InvoicePembelian::first()->status);
        $this->post(route('penerimaan-barang.store'), $this->replacementData($line, 1))->assertSessionHasErrors('items.0.kuantitas');
        $this->assertDatabaseCount('invoice_pembelian', 1);
    }

    public function test_repeated_damaged_replacements_have_separate_quotas_without_double_counting(): void
    {
        $return = $this->submit();
        $this->ship($return);
        for ($generation = 0; $generation < 3; $generation++) {
            $line = $return->details->first();
            $replacement = $this->replace($line, 3, 'Rusak');
            $this->assertDatabaseCount('invoice_pembelian', 0);
            $this->post(route('penerimaan-barang.store'), $this->replacementData($line, 1))->assertSessionHasErrors('items.0.kuantitas');
            $data = $this->data();
            $data['id_penerimaan'] = $replacement->getKey();
            $data['tanggal_retur'] = CarbonImmutable::parse($replacement->tanggal_penerimaan)->addDay()->toDateString();
            $data['items'][0]['id_detail_penerimaan'] = $replacement->details()->first()->getKey();
            $this->post(route('retur-pembelian.store'), $data)->assertSessionHasNoErrors();
            $return = ReturPembelian::latest('id_retur_pembelian')->firstOrFail();
            foreach (['Disetujui', 'Selesai'] as $status) {
                $this->patch(route('retur-pembelian.update', $return), ['status' => $status, 'tanggal_dikirim' => CarbonImmutable::parse($data['tanggal_retur'])->addDay()->toDateString()])->assertSessionHasNoErrors();
            }
            // Subsequent physical events must follow this shipment.
            $this->travelTo(now()->addDay());
        }
        $data = $this->replacementData($return->details->first(), 3);
        $this->post(route('penerimaan-barang.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('invoice_pembelian', 1);
        $this->assertSame(42, MBarang::first()->stok);
    }

    public function test_receipts_with_lineage_cannot_be_edited_deleted_or_reassigned(): void
    {
        $return = $this->submit();
        $this->ship($return);
        $replacement = $this->replace($return->details->first(), 1);
        foreach ([$this->receipt, $replacement] as $receipt) {
            $this->delete(route('penerimaan-barang.destroy', $receipt))->assertSessionHasErrors('document');
            $this->get(route('penerimaan-barang.edit', $receipt))->assertSessionHasErrors('document');
            $this->put(route('penerimaan-barang.update', $receipt), ['id_purchase_order' => $this->order->getKey(), 'tanggal_penerimaan' => '2026-10-06', 'items' => [['id_barang' => $this->poLine->id_barang, 'kuantitas' => 1, 'kondisi' => 'Baik']]])->assertSessionHasErrors('document');
        }
        $this->assertDatabaseCount('penerimaan_barang', 2);
    }

    public function test_permissions_views_and_pdf_for_each_role(): void
    {
        $return = $this->submit(1);
        foreach (['Admin', 'Purchasing', 'Finance', 'Sales'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['index', 'show', 'preview-pdf', 'download-pdf'] as $action) {
                $response = $this->get(route('retur-pembelian.'.$action, $action === 'index' ? [] : $return->getKey()));
                if ($role === 'Sales') {
                    $response->assertForbidden();
                } else {
                    $response->assertOk();
                }
            }
            $response = $this->get(route('retur-pembelian.create', ['penerimaan' => $this->receipt->getKey()]));
            in_array($role, ['Admin', 'Purchasing']) ? $response->assertOk() : $response->assertForbidden();
            if (in_array($role, ['Finance', 'Sales'])) {
                $this->post(route('retur-pembelian.store'), $this->data(1))->assertForbidden();
                $this->patch(route('retur-pembelian.update', $return), ['status' => 'Ditolak'])->assertForbidden();
            }
        }
    }

    public function test_legacy_invoice_only_return_and_invoice_remain_unchanged(): void
    {
        $invoice = InvoicePembelian::create(['id_purchase_order' => $this->order->getKey(), 'tanggal_invoice' => '2026-10-03', 'jatuh_tempo' => '2026-10-20', 'total_invoice' => 1110, 'status' => 'Lunas']);
        $legacy = ReturPembelian::create(['id_invoice_pembelian' => $invoice->getKey(), 'tanggal_retur' => '2026-10-04', 'alasan' => 'Legacy', 'status' => 'Selesai']);
        $before = $legacy->fresh()->toArray();
        $invoiceBefore = $invoice->fresh()->toArray();
        $this->get(route('retur-pembelian.show', $legacy))->assertOk()->assertSee('Retur historis');
        $this->get(route('retur-pembelian.preview-pdf', $legacy))->assertOk();
        $this->patch(route('retur-pembelian.update', $legacy), ['status' => 'Disetujui'])->assertSessionHasErrors('document');
        $return = $this->submit();
        $this->assertSame($invoice->getKey(), $return->id_invoice_pembelian);
        $this->ship($return);
        $this->replace($return->details->first(), 3);
        $this->assertSame($before, $legacy->fresh()->toArray());
        $this->assertSame($invoiceBefore, $invoice->fresh()->toArray());
        $this->assertDatabaseCount('invoice_pembelian', 1);
    }

    public function test_multiple_po_price_lines_require_explicit_valid_allocation_and_replacement_keeps_it(): void
    {
        $other = $this->order->details()->create(['id_barang' => $this->poLine->id_barang, 'sku' => 'RET-ITEM', 'nama_barang' => 'Perangkat Uji', 'kuantitas' => 1, 'harga_unit' => '200.00', 'diskon' => 0, 'total_harga' => '200.00']);
        $data = $this->data(2);
        $data['items'][0]['id_detail_purchase_order'] = $other->getKey();
        $this->post(route('retur-pembelian.store'), $data)->assertSessionHasErrors('items.0.kuantitas');
        $data['items'][0]['kuantitas'] = 1;
        $this->post(route('retur-pembelian.store'), $data)->assertSessionHasNoErrors();
        $return = ReturPembelian::firstOrFail();
        $this->assertSame('200.00', $return->details->first()->harga_unit);
        $this->ship($return);
        $replacement = $this->replace($return->details->first(), 1, 'Rusak');
        $data = $this->data(1);
        $data['id_penerimaan'] = $replacement->getKey();
        $data['tanggal_retur'] = '2026-10-07';
        $data['items'][0]['id_detail_penerimaan'] = $replacement->details()->first()->getKey();
        $this->post(route('retur-pembelian.store'), $data)->assertSessionHasErrors('items.0.id_detail_purchase_order');
        $data['items'][0]['id_detail_purchase_order'] = $other->getKey();
        $this->post(route('retur-pembelian.store'), $data)->assertSessionHasNoErrors();
    }

    public function test_normal_receipt_balance_remains_separate_from_replacement_allowance(): void
    {
        $this->receipt = app(PenerimaanBarangService::class)->save(['id_purchase_order' => $this->order->getKey(), 'tanggal_penerimaan' => '2026-10-03', 'items' => [
            ['id_barang' => $this->poLine->id_barang, 'kuantitas' => 4, 'kondisi' => 'Baik'], ['id_barang' => $this->poLine->id_barang, 'kuantitas' => 2, 'kondisi' => 'Rusak']]], $this->buyer->id, $this->receipt);
        $this->damaged = $this->receipt->details()->where('kondisi', 'Rusak')->first();
        $return = $this->submit(2);
        $this->ship($return);
        $this->replace($return->details->first(), 2);
        $this->assertDatabaseCount('invoice_pembelian', 0);
        $data = ['id_purchase_order' => $this->order->getKey(), 'tanggal_penerimaan' => '2026-10-07', 'items' => [['id_barang' => $this->poLine->id_barang, 'kuantitas' => 4, 'kondisi' => 'Baik']]];
        $this->post(route('penerimaan-barang.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('invoice_pembelian', 1);
        $data['items'][0]['kuantitas'] = 1;
        $this->post(route('penerimaan-barang.store'), $data)->assertSessionHasErrors('items.0.kuantitas');
    }

    public function test_replacement_wrong_barang_po_and_date_are_rejected_and_quota_is_unchanged(): void
    {
        $return = $this->submit();
        $this->ship($return);
        $line = $return->details->first();
        $data = $this->replacementData($line, 1);
        $data['tanggal_penerimaan'] = '2026-10-04';
        $this->post(route('penerimaan-barang.store'), $data)->assertSessionHasErrors('tanggal_penerimaan');
        $other = MBarang::create(['sku' => 'OTHER', 'nama_barang' => 'Other', 'satuan' => 'unit', 'harga_beli' => 1, 'stok' => 0]);
        $this->order->details()->create(['id_barang' => $other->getKey(), 'sku' => 'OTHER', 'nama_barang' => 'Other', 'kuantitas' => 1, 'harga_unit' => 1, 'diskon' => 0, 'total_harga' => 1]);
        $data = $this->replacementData($line, 1);
        $data['items'][0]['id_barang'] = $other->getKey();
        $this->post(route('penerimaan-barang.store'), $data)->assertSessionHasErrors('items.0.id_detail_retur_asal');
        $this->assertSame(0, $line->pengganti()->count());
        $this->get(route('penerimaan-barang.create', ['retur' => $return->getKey()]))->assertOk()->assertSee('Penerimaan pengganti');
    }

    public function test_proportional_rounding_and_large_amounts_use_integer_decimal_arithmetic(): void
    {
        $line = new DetailPurchaseOrder(['kuantitas' => 3, 'harga_unit' => '0.05', 'diskon' => '0.01', 'total_harga' => '0.14']);
        $amounts = PurchaseReturnAmounts::line($line, 1, 14, 1);
        $this->assertSame('0.00', $amounts['diskon']);
        $this->assertSame('0.05', $amounts['subtotal']);
        $this->assertSame('0.00', $amounts['ppn']);
        $full = PurchaseReturnAmounts::line($line, 3, 14, 1);
        $this->assertSame('0.01', $full['diskon']);
        $this->assertSame('0.01', $full['ppn']);
        $this->assertSame('0.15', $full['total_harga']);
        $line->harga_unit = '1000000000.00';
        $line->kuantitas = 1000;
        $line->diskon = '1.00';
        $value = PurchaseReturnAmounts::line($line, 1000, 99999999999900, 0);
        $this->assertSame('999999999999.00', $value['total_harga']);
    }

    public function test_migration_preserves_legacy_headers_and_foreign_keys_and_rejects_unsafe_rollback(): void
    {
        $invoice = InvoicePembelian::create(['id_purchase_order' => $this->order->getKey(), 'tanggal_invoice' => '2026-10-03', 'jatuh_tempo' => '2026-10-20', 'total_invoice' => 1110, 'status' => 'Lunas']);
        $legacy = ReturPembelian::create(['id_invoice_pembelian' => $invoice->getKey(), 'tanggal_retur' => '2026-10-04', 'alasan' => 'Legacy', 'status' => 'Selesai']);
        $columns = ['id_retur_pembelian', 'id_invoice_pembelian', 'no_retur', 'tanggal_retur', 'alasan', 'status', 'created_at', 'updated_at'];
        $before = (array) DB::table('retur_pembelian')->select($columns)->first();
        $migration = require database_path('migrations/2026_10_19_000004_add_receipt_based_purchase_returns.php');
        $migration->down();
        $migration->up();
        $this->assertSame($before, (array) DB::table('retur_pembelian')->select($columns)->first());
        $this->assertNull($legacy->fresh()->id_penerimaan);
        $this->assertNull($legacy->fresh()->total_retur);
        $this->assertNotEmpty(Schema::getForeignKeys('retur_pembelian'));
        $new = $this->submit(1);
        // Explicitly create a receipt-based pre-invoice record for rollback guard.
        DB::table('retur_pembelian')->where('id_retur_pembelian', $new->getKey())->update(['id_invoice_pembelian' => null]);
        try {
            $migration->down();
            $this->fail('Expected rollback guard');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Rollback ditolak', $e->getMessage());
        }
        $this->assertDatabaseCount('retur_pembelian', 2);
        $this->assertTrue(Schema::hasTable('detail_retur_pembelian'));
    }

    public function test_status_write_failure_rolls_back_transition_and_preserves_reservation(): void
    {
        $return = $this->submit();
        $before = $return->fresh()->toArray();
        $event = 'eloquent.updated: '.ReturPembelian::class;
        Event::listen($event, fn () => throw new \RuntimeException('status failure'));
        try {
            try {
                app(ReturPembelianService::class)->transition($return, ['status' => 'Ditolak'], $this->buyer->id);
                $this->fail('Expected failure');
            } catch (\RuntimeException $e) {
                $this->assertSame('status failure', $e->getMessage());
            }
        } finally {
            Event::forget($event);
        }
        $this->assertSame($before, $return->fresh()->toArray());
        $this->post(route('retur-pembelian.store'), $this->data(1))->assertSessionHasErrors('items.0.kuantitas');
    }

    public function test_return_pdf_uses_stored_values_and_legacy_missing_details_are_explicit(): void
    {
        $return = $this->submit();
        $data = app(TransactionPdfService::class)->data('retur-pembelian', $return->getKey());
        $html = view('pdf.retur-pembelian', $data)->render();
        foreach (['Atas Penerimaan Nomor', $this->receipt->no_penerimaan, $this->order->no_order_pembelian, 'Supplier Uji', 'RET-ITEM', 'Casing rusak', 'Rp 333,00', 'Rp 33,00', 'Diajukan', 'Penerima Uji'] as $text) {
            $this->assertStringContainsString(e($text), $html);
        }
        foreach (['preview-pdf' => 'inline', 'download-pdf' => 'attachment'] as $action => $disposition) {
            $response = $this->get(route('retur-pembelian.'.$action, $return).'?total_retur=1&ppn=0');
            $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-', $response->getContent());
            $this->assertStringContainsString($disposition, $response->headers->get('Content-Disposition'));
        }
        $this->assertSame('333.00', $return->fresh()->total_retur);
        $legacy = ReturPembelian::create(['id_invoice_pembelian' => null, 'tanggal_retur' => '2026-10-04', 'alasan' => 'Histori tidak lengkap', 'status' => 'Diajukan']);
        $html = view('pdf.retur-pembelian', app(TransactionPdfService::class)->data('retur-pembelian', $legacy->getKey()))->render();
        $this->assertStringContainsString('Detail retur historis belum tercatat', $html);
        $this->assertStringContainsString('Nominal retur historis tidak tercatat', $html);
        $this->assertStringNotContainsString('Rp 0,00', $html);
    }

    public function test_multiple_return_items_have_correct_totals_and_render_multiple_a4_pages(): void
    {
        $this->order->update(['jumlah' => 34, 'ppn' => '242.00', 'total_harga' => '2442.00']);
        $receiving = [];
        $poIds = [];
        for ($i = 1; $i <= 24; $i++) {
            $barang = MBarang::create(['sku' => 'MULTI-'.$i, 'nama_barang' => 'Barang Multi '.$i, 'satuan' => 'unit', 'harga_beli' => 50, 'stok' => 0]);
            $line = $this->order->details()->create(['id_barang' => $barang->getKey(), 'sku' => $barang->sku, 'nama_barang' => $barang->nama_barang, 'kuantitas' => 1, 'harga_unit' => 50, 'diskon' => 0, 'total_harga' => 50]);
            $poIds[$barang->getKey()] = $line->getKey();
            $receiving[] = ['id_barang' => $barang->getKey(), 'kuantitas' => 1, 'kondisi' => 'Rusak'];
        }
        $receipt = app(PenerimaanBarangService::class)->save(['id_purchase_order' => $this->order->getKey(), 'tanggal_penerimaan' => '2026-10-03', 'items' => $receiving], $this->buyer->id);
        $items = $receipt->details->map(fn ($line) => ['id_detail_penerimaan' => $line->getKey(), 'id_detail_purchase_order' => $poIds[$line->id_barang], 'kuantitas' => 1, 'alasan' => str_repeat('Catatan kerusakan fisik perangkat. ', 5)])->all();
        $return = app(ReturPembelianService::class)->create(['id_penerimaan' => $receipt->getKey(), 'tanggal_retur' => '2026-10-04', 'alasan' => 'Multi item', 'items' => $items], $this->buyer->id);
        $this->assertCount(24, $return->details);
        $this->assertSame('1200.00', $return->subtotal);
        $this->assertSame('132.00', $return->ppn);
        $this->assertSame('1332.00', $return->total_retur);
        [$pdf] = app(TransactionPdfService::class)->render('retur-pembelian', $return->getKey());
        $this->assertStringStartsWith('%PDF-', $pdf->output());
        $canvas = $pdf->getDomPDF()->getCanvas();
        $this->assertGreaterThan(1, $canvas->get_page_count());
        $this->assertEqualsWithDelta(595.28, $canvas->get_width(), 0.1);
        $this->assertEqualsWithDelta(841.89, $canvas->get_height(), 0.1);
    }

    public function test_return_creation_and_replacement_invoice_failure_roll_back_atomically(): void
    {
        $event = 'eloquent.created: '.DetailReturPembelian::class;
        Event::listen($event, fn () => throw new \RuntimeException('return failure'));
        try {
            try {
                app(ReturPembelianService::class)->create($this->data(), $this->buyer->id);
                $this->fail('Expected failure');
            } catch (\RuntimeException $e) {
                $this->assertSame('return failure', $e->getMessage());
            }
        } finally {
            Event::forget($event);
        }
        $this->assertDatabaseCount('retur_pembelian', 0);
        $this->assertDatabaseCount('detail_retur_pembelian', 0);
        $return = $this->submit();
        $this->ship($return);
        $line = $return->details->first();
        $event = 'eloquent.created: '.InvoicePembelian::class;
        Event::listen($event, fn () => throw new \RuntimeException('invoice failure'));
        try {
            try {
                app(PenerimaanBarangService::class)->save($this->replacementData($line, 3), $this->buyer->id);
                $this->fail('Expected failure');
            } catch (\RuntimeException $e) {
                $this->assertSame('invoice failure', $e->getMessage());
            }
        } finally {
            Event::forget($event);
        }
        $this->assertDatabaseCount('penerimaan_barang', 1);
        $this->assertSame(0, $line->pengganti()->count());
        $this->assertDatabaseCount('invoice_pembelian', 0);
        $this->replace($line,3);
        $this->assertDatabaseCount('invoice_pembelian',1);
    }
}
