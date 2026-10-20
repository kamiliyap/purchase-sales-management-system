<?php

namespace Tests\Feature;

use App\Models\DetailPermintaanPembelian;
use App\Models\InvoicePembelian;
use App\Models\InvoicePenjualan;
use App\Models\MBarang;
use App\Models\MCustomer;
use App\Models\MSupplier;
use App\Models\PenerimaanBarang;
use App\Models\PermintaanPembelian;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\TransactionPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TransactionPdfTest extends TestCase
{
    use RefreshDatabase;

    private array $documents;

    private User $admin;

    private MBarang $barang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin']);
        $this->actingAs($this->admin);
        $this->barang = MBarang::create(['sku' => 'IT-01', 'nama_barang' => 'Monitor 24 inch', 'satuan' => 'pcs', 'harga_beli' => '100.10', 'stok' => 8]);
        $supplier = MSupplier::create(['nama_supplier' => 'Supplier Asli DB', 'alamat' => 'Alamat Supplier', 'telepon' => '123', 'email' => 'supplier@example.com']);
        $customer = MCustomer::create(['nama_customer' => 'Customer Asli DB', 'alamat' => 'Alamat Customer', 'telepon' => '456', 'email' => 'customer@example.com']);
        $pr = PermintaanPembelian::create(['tanggal' => '2026-10-13', 'tanggal_diperlukan' => '2026-10-15', 'id_pemohon' => $this->admin->id, 'id_penyetuju' => $this->admin->id, 'status' => 'Disetujui']);
        $pr->details()->create(['id_barang' => $this->barang->getKey(), 'sku' => 'IT-01', 'nama_barang' => 'Monitor 24 inch', 'kuantitas' => 2, 'tanggal_dibutuhkan' => '2026-10-15', 'keterangan' => 'Untuk operasional']);
        $po = PurchaseOrder::create(['id_permintaan' => $pr->getKey(), 'id_supplier' => $supplier->getKey(), 'tanggal' => '2026-10-14', 'tanggal_dibutuhkan' => '2026-10-15', 'jumlah' => 2, 'ppn' => '21.89', 'total_harga' => '220.89']);
        $po->details()->create(['id_barang' => $this->barang->getKey(), 'sku' => 'IT-01', 'nama_barang' => 'Monitor 24 inch', 'kuantitas' => 2, 'harga_unit' => '100.10', 'diskon' => '1.20', 'total_harga' => '199.00']);
        $receipt = PenerimaanBarang::create(['id_purchase_order' => $po->getKey(), 'tanggal_penerimaan' => '2026-10-15', 'id_penerima' => $this->admin->id]);
        foreach (['Baik', 'Rusak'] as $condition) {
            $receipt->details()->create(['id_barang' => $this->barang->getKey(), 'kuantitas' => 1, 'kondisi' => $condition, 'keterangan' => 'Pemeriksaan']);
        }
        $buy = InvoicePembelian::create(['id_purchase_order' => $po->getKey(), 'tanggal_invoice' => '2026-10-15', 'jatuh_tempo' => '2026-11-14', 'total_invoice' => '220.89', 'status' => 'Belum Lunas']);
        $so = SalesOrder::create(['id_customer' => $customer->getKey(), 'tanggal' => '2026-10-14', 'total_harga' => '299.25']);
        $so->details()->create(['id_barang' => $this->barang->getKey(), 'sku' => 'IT-01', 'nama_barang' => 'Monitor 24 inch', 'kuantitas' => 2, 'harga_jual' => '150.00', 'diskon' => '0.75', 'total_harga' => '299.25']);
        $sale = InvoicePenjualan::create(['id_sales_order' => $so->getKey(), 'tanggal_invoice' => '2026-10-15', 'jatuh_tempo' => '2026-11-14', 'total_invoice' => '299.25', 'status' => 'Belum Lunas']);
        $this->documents = ['permintaan-pembelian' => $pr, 'purchase-order' => $po, 'penerimaan-barang' => $receipt, 'invoice-pembelian' => $buy, 'invoice-penjualan' => $sale];
    }

    public function test_receipt_pdf_aggregates_good_and_damaged_without_changing_transactions(): void
    {
        $receipt = $this->documents['penerimaan-barang'];
        $before = $receipt->details()->get()->toArray();
        $service = app(TransactionPdfService::class);
        $data = $service->data('penerimaan-barang', $receipt->getKey());
        $this->assertCount(1, $data['receiptRows']);
        $row = $data['receiptRows']->first();
        $this->assertSame(2, $row['kuantitas']);
        $this->assertSame(1, $row['baik']);
        $this->assertSame(1, $row['rusak']);
        $this->assertSame($row['kuantitas'], $row['baik'] + $row['rusak']);
        $html = view('pdf.penerimaan-barang', $data)->render();
        foreach (['Nomor Penerimaan', $receipt->no_penerimaan, 'Tanggal Penerimaan', 'Nomor Order Pembelian (PO)', $receipt->purchaseOrder->no_order_pembelian, 'Kondisi Barang', 'Baik', 'Rusak', $this->admin->name] as $text) {
            $this->assertStringContainsString(e($text), $html);
        }
        $this->assertStringContainsString('colspan="2"', $html);
        $this->assertStringNotContainsString('Penyerah Barang', $html);
        $this->get($this->url('penerimaan-barang'))->assertOk();
        $this->get($this->url('penerimaan-barang', 'download-pdf'))->assertOk();
        $this->assertSame($before, $receipt->details()->get()->toArray());
        $receipt->details()->create(['id_barang' => $this->barang->getKey(), 'kuantitas' => 2, 'kondisi' => 'Baik', 'keterangan' => 'Catatan berbeda']);
        $rows = $service->data('penerimaan-barang', $receipt->getKey())['receiptRows'];
        $this->assertCount(2, $rows);
        $this->assertSame('Catatan berbeda', $rows->last()['keterangan']);
    }

    public function test_receipt_pdf_rejects_placeholder_numbers_without_replacing_them(): void
    {
        $receipt = $this->documents['penerimaan-barang'];
        foreach (['', '0-', '0', '---'] as $badNumber) {
            DB::table('penerimaan_barang')->where('id_penerimaan', $receipt->getKey())->update(['no_penerimaan' => $badNumber]);
            foreach (['preview-pdf', 'download-pdf'] as $mode) {
                $this->get($this->url('penerimaan-barang', $mode))->assertStatus(422);
            }
            $this->assertSame($badNumber, $receipt->fresh()->no_penerimaan);
        }
        DB::table('penerimaan_barang')->where('id_penerimaan', $receipt->getKey())->update(['no_penerimaan' => $receipt->no_penerimaan]);
        DB::table('purchase_order')->where('id_purchase_order', $receipt->id_purchase_order)->update(['no_order_pembelian' => '0-']);
        $this->get($this->url('penerimaan-barang'))->assertStatus(422);
        $this->assertSame('0-', $receipt->purchaseOrder->no_order_pembelian);
    }

    public function test_receipt_pdf_multiple_pages_preserve_all_condition_quantities(): void
    {
        $receipt = $this->documents['penerimaan-barang'];
        for ($i = 0; $i < 55; $i++) {
            $receipt->details()->create(['id_barang' => $this->barang->getKey(), 'kuantitas' => 1, 'kondisi' => $i % 2 ? 'Baik' : 'Rusak', 'keterangan' => 'Pemeriksaan baris '.$i.' '.str_repeat('Catatan ', 15)]);
        }
        $service = app(TransactionPdfService::class);
        $rows = $service->data('penerimaan-barang', $receipt->getKey())['receiptRows'];
        $this->assertSame(57, $rows->sum('kuantitas'));
        foreach ($rows as $row) {
            $this->assertSame($row['kuantitas'], $row['baik'] + $row['rusak']);
        }
        [$pdf] = $service->render('penerimaan-barang', $receipt->getKey());
        $this->assertStringStartsWith('%PDF-', $pdf->output());
        $canvas = $pdf->getDomPDF()->getCanvas();
        $this->assertGreaterThan(1, $canvas->get_page_count());
        $this->assertEqualsWithDelta(595.28, $canvas->get_width(), 0.1);
        $this->assertEqualsWithDelta(841.89, $canvas->get_height(), 0.1);
    }

    public function test_invalid_receipt_po_shows_business_message_even_with_debug_enabled(): void
    {
        config(['app.debug' => true]);
        $receipt = $this->documents['penerimaan-barang'];
        DB::table('purchase_order')->where('id_purchase_order', $receipt->id_purchase_order)->update(['no_order_pembelian' => '0-']);
        foreach (['preview-pdf', 'download-pdf'] as $mode) {
            $response = $this->get($this->url('penerimaan-barang', $mode));
            $response->assertStatus(422)->assertSee('PDF belum dapat dicetak')->assertSee('nilai tersimpan: 0-')->assertSee('Kembali ke Detail Dokumen')->assertDontSee('Exception trace')->assertDontSee('HttpException');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
        $this->assertSame('0-', $receipt->purchaseOrder->no_order_pembelian);
        $this->assertSame('PB/', substr($receipt->fresh()->no_penerimaan, 0, 3));
    }

    public function test_purchasing_pdf_fields_signatures_and_stored_payment_deadline(): void
    {
        $service = app(TransactionPdfService::class);
        $po = $this->documents['purchase-order'];
        $html = view('pdf.purchase-order', $service->data('purchase-order', $po->getKey()))->render();
        $this->assertStringContainsString('<strong>Penerima</strong>', $html);
        $this->assertStringContainsString('<strong>Perusahaan</strong>', $html);
        $this->assertStringNotContainsString('Supplier / Persetujuan', $html);
        $invoice = $this->documents['invoice-pembelian'];
        $invoice->update(['jatuh_tempo' => '2026-12-09']);
        $html = view('pdf.invoice-pembelian', $service->data('invoice-pembelian', $invoice->getKey()))->render();
        $this->assertStringContainsString('Telepon: 123', $html);
        $this->assertStringContainsString('Tanggal Jatuh Tempo', $html);
        $this->assertStringContainsString('Pembayaran paling lambat 2026-12-09', $html);
        $this->assertStringContainsString('Termin khusus tidak tercatat', $html);
        $this->assertStringNotContainsString('30 hari', $html);
        $this->assertStringContainsString('Rp 220,89', $html);
        $po->supplier->update(['telepon' => '']);
        $html = view('pdf.invoice-pembelian', $service->data('invoice-pembelian', $invoice->getKey()))->render();
        $this->assertStringNotContainsString('Telepon:', $html);
    }

    public function test_purchasing_pdf_explains_legacy_placeholder_numbers_without_changing_them(): void
    {
        $po = $this->documents['purchase-order'];
        DB::table('purchase_order')->where('id_purchase_order', $po->getKey())->update(['no_order_pembelian' => '0-']);
        $service = app(TransactionPdfService::class);
        foreach (['purchase-order', 'invoice-pembelian'] as $module) {
            $html = view('pdf.'.$module, $service->data($module, $this->documents[$module]->getKey()))->render();
            $this->assertStringContainsString('Nomor historis perlu verifikasi (nilai tersimpan: 0-)', $html);
            $this->get($this->url($module))->assertOk();
        }
        $this->assertSame('0-', $po->fresh()->no_order_pembelian);
        $this->get($this->url('penerimaan-barang'))->assertStatus(422);
    }

    private function url(string $module, string $mode = 'preview-pdf', mixed $id = null): string
    {
        return route($module.'.'.$mode, [str_replace('-', '_', $module) => $id ?? $this->documents[$module]->getKey()]);
    }

    private function requestData(): array
    {
        return ['tanggal' => '2026-10-14', 'tanggal_diperlukan' => '2026-10-16', 'id_penyetuju' => $this->admin->id, 'status' => 'Draft',
            'items' => [['id_barang' => $this->barang->getKey(), 'kuantitas' => 3, 'tanggal_dibutuhkan' => '2026-10-16', 'keterangan' => 'Kebutuhan IT', 'sku' => 'FORGED', 'nama_barang' => 'FORGED']]];
    }

    public function test_all_five_documents_render_actual_pdf_in_both_modes(): void
    {
        foreach ($this->documents as $module => $document) {
            foreach (['preview-pdf' => 'inline', 'download-pdf' => 'attachment'] as $mode => $disposition) {
                $response = $this->get($this->url($module, $mode).'?total_invoice=1&total_harga=1&type=kwitansi&download=false');
                $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
                $this->assertStringStartsWith('%PDF-', $response->getContent());
                $this->assertStringContainsString($disposition, $response->headers->get('Content-Disposition'));
                $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            }
        }
        $this->assertSame(8, $this->barang->fresh()->stok);
        $this->assertDatabaseCount('invoice_pembelian', 1);
    }

    public function test_pdf_permission_matches_show_for_every_role_and_mode(): void
    {
        $allowed = ['Admin' => array_keys($this->documents), 'Purchasing' => ['permintaan-pembelian', 'purchase-order', 'penerimaan-barang', 'invoice-pembelian'], 'Sales' => ['invoice-penjualan'], 'Finance' => ['purchase-order', 'penerimaan-barang', 'invoice-pembelian', 'invoice-penjualan']];
        foreach ($allowed as $role => $modules) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach ($this->documents as $module => $document) {
                foreach (['preview-pdf', 'download-pdf'] as $mode) {
                    $this->get($this->url($module, $mode))->assertStatus(in_array($module, $modules, true) ? 200 : 403);
                }
            }
        }
    }

    public function test_guest_missing_and_invalid_document_ids_are_protected(): void
    {
        foreach (array_keys($this->documents) as $module) {
            $this->get($this->url($module, id: 9999))->assertNotFound();
            $this->get($this->url($module, id: 'bad'))->assertNotFound();
        }
        auth()->logout();
        foreach (array_keys($this->documents) as $module) {
            foreach (['preview-pdf', 'download-pdf'] as $mode) {
                $this->get($this->url($module, $mode))->assertRedirect(route('login'));
            }
        }
    }

    public function test_pdf_content_uses_database_totals_snapshots_and_no_fictitious_tax(): void
    {
        $service = app(TransactionPdfService::class);
        $this->barang->update(['sku' => 'NEW-SKU', 'nama_barang' => 'New Name', 'harga_beli' => 999]);
        foreach ($this->documents as $module => $document) {
            $html = view('pdf.'.$module, $service->data($module, $document->getKey()))->render();
            $this->assertStringContainsString('PT Sinar Niaga Teknologi', $html);
            $this->assertStringContainsString('bukan identitas legal', $html);
            $this->assertStringContainsString('Monitor 24 inch', $html);
            $this->assertStringNotContainsString('NEW-SKU', $html);
            $this->assertStringNotContainsString('NPWP:', $html);
            if (in_array($module, ['purchase-order', 'invoice-pembelian'])) {
                foreach (['Rp 200,20', 'Rp 1,20', 'Rp 21,89', 'Rp 220,89'] as $amount) {
                    $this->assertStringContainsString($amount, $html);
                }
            }
            if ($module === 'invoice-penjualan') {
                $this->assertStringContainsString('Rp 299,25', $html);
                $this->assertStringNotContainsString('PPN (nominal', $html);
            }
        }
        $invoice = $this->documents['invoice-pembelian'];
        $invoice->update(['total_invoice' => '215.50', 'status' => 'Lunas']);
        $html = view('pdf.invoice-pembelian', $service->data('invoice-pembelian', $invoice->getKey()))->render();
        $this->assertStringContainsString('Rp 215,50', $html);
        $this->assertStringContainsString('bukan kwitansi', $html);
        $this->get('/kwitansi/1/pdf/preview')->assertNotFound();
    }

    public function test_a4_multiple_pages_and_html_escaping(): void
    {
        $request = $this->documents['permintaan-pembelian'];
        for ($i = 0; $i < 60; $i++) {
            $request->details()->create(['id_barang' => $this->barang->getKey(), 'sku' => 'IT-01', 'nama_barang' => 'Monitor 24 inch', 'kuantitas' => 1, 'tanggal_dibutuhkan' => '2026-10-15', 'keterangan' => '<script>alert(1)</script> '.$i]);
        }
        $service = app(TransactionPdfService::class);
        $html = view('pdf.permintaan-pembelian', $service->data('permintaan-pembelian', $request->getKey()))->render();
        $this->assertStringContainsString('&lt;script&gt;', $html);
        [$pdf] = $service->render('permintaan-pembelian', $request->getKey());
        $this->assertStringStartsWith('%PDF-', $pdf->output());
        $canvas = $pdf->getDomPDF()->getCanvas();
        $this->assertEqualsWithDelta(595.28, $canvas->get_width(), 0.1);
        $this->assertEqualsWithDelta(841.89, $canvas->get_height(), 0.1);
        $this->assertGreaterThan(1, $canvas->get_page_count());
        $this->assertFalse($pdf->getDomPDF()->getOptions()->getIsRemoteEnabled());
    }

    public function test_legacy_request_pdf_and_optional_company_fields(): void
    {
        $pr = $this->documents['permintaan-pembelian'];
        $pr->details()->delete();
        config(['company.email' => 'office@example.test', 'company.logo' => 'https://invalid.example/logo.png']);
        $data = app(TransactionPdfService::class)->data('permintaan-pembelian', $pr->getKey());
        $this->assertNull($data['logo']);
        $html = view('pdf.permintaan-pembelian', $data)->render();
        $this->assertStringContainsString('Detail barang belum tercatat', $html);
        $this->assertStringContainsString('office@example.test', $html);
        $this->get($this->url('permintaan-pembelian'))->assertOk();
    }

    public function test_request_detail_crud_snapshots_validation_and_po_prefill(): void
    {
        $data = $this->requestData();
        $data['items'][] = array_replace($data['items'][0], ['kuantitas' => 1]);
        $this->post(route('permintaan-pembelian.store'), $data)->assertSessionHasNoErrors();
        $pr = PermintaanPembelian::latest('id_permintaan')->firstOrFail();
        $this->assertCount(2, $pr->details);
        $this->assertSame('IT-01', $pr->details->first()->sku);
        $this->assertSame('Monitor 24 inch', $pr->details->first()->nama_barang);
        $data['status'] = 'Disetujui';
        $data['items'] = [$data['items'][0]];
        $data['items'][0]['kuantitas'] = 7;
        $this->put(route('permintaan-pembelian.update', $pr), $data)->assertSessionHasNoErrors();
        $this->assertSame(7, $pr->details()->first()->kuantitas);
        $this->assertSame(1, $pr->details()->count());
        $this->get(route('purchase-order.create', ['id_permintaan' => $pr->getKey()]))->assertOk()->assertViewHas('requestItems', fn ($items) => $items[0]['kuantitas'] === 7)->assertSee('IT-01');
        $this->delete(route('permintaan-pembelian.destroy', $pr))->assertRedirect();
        $this->assertDatabaseMissing('detail_permintaan_pembelian', ['id_permintaan' => $pr->getKey()]);
        foreach ([['id_barang', 999], ['kuantitas', 0], ['kuantitas', 1.5], ['tanggal_dibutuhkan', '2026-10-01']] as [$key, $value]) {
            $bad = $this->requestData();
            $bad['items'][0][$key] = $value;
            $this->post(route('permintaan-pembelian.store'), $bad)->assertSessionHasErrors('items.0.'.$key);
        }
        $this->post(route('permintaan-pembelian.store'), array_diff_key($data, ['items' => true]))->assertSessionHasErrors('items');
    }

    public function test_legacy_request_can_keep_header_without_inventing_details_then_add_real_details(): void
    {
        $pr = PermintaanPembelian::create(['tanggal' => '2026-10-14', 'tanggal_diperlukan' => '2026-10-16', 'id_pemohon' => $this->admin->id, 'id_penyetuju' => $this->admin->id, 'status' => 'Draft']);
        $data = $this->requestData();
        $header = $data;
        unset($header['items']);
        $this->put(route('permintaan-pembelian.update', $pr), $header)->assertSessionHasNoErrors();
        $this->assertSame(0, $pr->details()->count());
        $this->put(route('permintaan-pembelian.update', $pr), $data)->assertSessionHasNoErrors();
        $this->assertSame(1, $pr->details()->count());
        $this->barang->update(['sku' => 'CURRENT']);
        $this->assertSame('IT-01', $pr->details()->first()->sku);
    }

    public function test_request_persistence_failure_rolls_back_header_and_details(): void
    {
        $pr = PermintaanPembelian::create(['tanggal' => '2026-10-14', 'tanggal_diperlukan' => '2026-10-16', 'id_pemohon' => $this->admin->id, 'id_penyetuju' => $this->admin->id, 'status' => 'Draft']);
        $pr->details()->create(['id_barang' => $this->barang->getKey(), 'sku' => 'IT-01', 'nama_barang' => 'Original', 'kuantitas' => 1, 'tanggal_dibutuhkan' => '2026-10-16']);
        $header = $pr->fresh()->toArray();
        $details = $pr->details()->get()->toArray();
        $event = 'eloquent.creating: '.DetailPermintaanPembelian::class;
        Event::listen($event, fn () => throw new \RuntimeException('detail failure'));
        $this->withoutExceptionHandling();
        try {
            foreach ([false, true] as $update) {
                try {
                    $update ? $this->put(route('permintaan-pembelian.update', $pr), $this->requestData()) : $this->post(route('permintaan-pembelian.store'), $this->requestData());
                    $this->fail('Expected persistence failure');
                } catch (\RuntimeException $exception) {
                    $this->assertSame('detail failure', $exception->getMessage());
                }
                $this->assertDatabaseCount('permintaan_pembelian', 2);
                $this->assertSame($header, $pr->fresh()->toArray());
                $this->assertSame($details, $pr->details()->get()->toArray());
            }
        } finally {
            Event::forget($event);
        }
    }
}
