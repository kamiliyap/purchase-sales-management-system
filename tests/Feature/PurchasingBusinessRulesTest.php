<?php

namespace Tests\Feature;

use App\Models\InvoicePembelian;
use App\Models\MBarang;
use App\Models\MSupplier;
use App\Models\PermintaanPembelian;
use App\Models\PurchaseOrder;
use App\Models\ReturPembelian;
use App\Models\User;
use App\Services\PenerimaanBarangService;
use App\Support\PurchasingStatuses;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PurchasingBusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->user('Admin');
        $this->actingAs($this->admin);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->role = $role;
        $user->save();

        return $user;
    }

    private function supplierData(): array
    {
        return ['nama_supplier' => 'Supplier', 'alamat' => 'Jakarta', 'telepon' => '081234567890', 'email' => 'supplier@example.com'];
    }

    private function requestData(array $overrides = []): array
    {
        $fixtureBarang = MBarang::firstOrCreate(['sku' => 'PR-FIXTURE'], ['nama_barang' => 'Barang Permintaan', 'satuan' => 'pcs', 'harga_beli' => 10, 'stok' => 0]);

        return array_replace(['items' => [['id_barang' => $fixtureBarang->getKey(), 'kuantitas' => 1, 'tanggal_dibutuhkan' => '2026-10-15']], 'tanggal' => '2026-10-13', 'tanggal_diperlukan' => '2026-10-15',
            'id_penyetuju' => $this->admin->id, 'status' => 'Draft'], $overrides);
    }

    private function orderData(string $status = 'Disetujui'): array
    {
        $request = PermintaanPembelian::create($this->requestData(['id_pemohon' => $this->admin->id, 'status' => $status]));
        $supplier = MSupplier::create($this->supplierData());
        $barang = MBarang::create(['sku' => 'SKU-'.MBarang::count(), 'nama_barang' => 'Barang', 'satuan' => 'pcs', 'harga_beli' => 100, 'stok' => 0]);

        return ['id_permintaan' => $request->id_permintaan, 'id_supplier' => $supplier->id_supplier,
            'tanggal' => '2026-10-13', 'tanggal_dibutuhkan' => '2026-10-15', 'ppn_rate' => 11,
            'items' => [['id_barang' => $barang->id_barang, 'kuantitas' => 2, 'harga_unit' => '100.10', 'diskon' => '0.10']]];
    }

    private function order(): PurchaseOrder
    {
        $data = $this->orderData();
        $this->post(route('purchase-order.store'), $data)->assertSessionHasNoErrors();
        $order = PurchaseOrder::latest('id_purchase_order')->firstOrFail();
        app(PenerimaanBarangService::class)->save(['id_purchase_order' => $order->getKey(), 'tanggal_penerimaan' => $order->tanggal,
            'items' => $order->details->map(fn ($item) => ['id_barang' => $item->id_barang, 'kuantitas' => $item->kuantitas, 'kondisi' => 'Baik'])->all()], auth()->id());

        return $order;
    }

    private function invoiceData(PurchaseOrder $order, array $overrides = []): array
    {
        return array_replace(['id_purchase_order' => $order->id_purchase_order,
            'tanggal_invoice' => '2026-10-14', 'jatuh_tempo' => '2026-10-20', 'status' => 'Belum Lunas'], $overrides);
    }

    public function test_supplier_limits_are_enforced_on_create_and_update_and_boundaries_are_accepted(): void
    {
        $supplier = MSupplier::create($this->supplierData());
        foreach (['nama_supplier' => str_repeat('n', 101), 'alamat' => str_repeat('a', 201),
            'email' => str_repeat('a', 60).'@'.str_repeat('b', 36).'.com'] as $field => $value) {
            $data = array_replace($this->supplierData(), [$field => $value]);
            $this->post(route('supplier.store'), $data)->assertSessionHasErrors($field);
            $this->put(route('supplier.update', $supplier), $data)->assertSessionHasErrors($field);
        }
        $boundary = array_replace($this->supplierData(), ['nama_supplier' => str_repeat('n', 100),
            'alamat' => str_repeat('a', 200), 'email' => str_repeat('a', 60).'@'.str_repeat('b', 35).'.com']);
        $this->post(route('supplier.store'), $boundary)->assertSessionHasNoErrors();
        $this->put(route('supplier.update', $supplier), $boundary)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('m_supplier', array_merge($boundary, ['id_supplier' => $supplier->id_supplier]));
        $this->assertFalse(Route::has('supplier.show'));
        $this->get('/supplier/'.$supplier->id_supplier)->assertStatus(405);
    }

    public function test_used_supplier_cannot_be_deleted_and_unused_supplier_can_be_deleted(): void
    {
        $order = $this->order();
        $this->delete(route('supplier.destroy', $order->id_supplier))->assertRedirect(route('supplier.index'))
            ->assertSessionHasErrors(['supplier' => 'Supplier tidak dapat dihapus karena sudah digunakan pada Purchase Order.']);
        $this->assertDatabaseHas('m_supplier', ['id_supplier' => $order->id_supplier]);
        $unused = MSupplier::create($this->supplierData());
        $this->delete(route('supplier.destroy', $unused))->assertRedirect(route('supplier.index'))->assertSessionHas('success');
        $this->assertModelMissing($unused);
    }

    public function test_requester_is_logged_in_user_and_cannot_be_spoofed_or_changed_on_edit(): void
    {
        $requester = $this->user('Purchasing');
        $other = $this->user('Purchasing');
        $this->actingAs($requester);
        $this->post(route('permintaan-pembelian.store'), $this->requestData(['id_pemohon' => $other->id]))->assertSessionHasErrors('id_pemohon');
        $this->post(route('permintaan-pembelian.store'), $this->requestData())->assertSessionHasNoErrors();
        $document = PermintaanPembelian::firstOrFail();
        $this->assertSame($requester->id, $document->pemohon->id);
        $this->assertSame($this->admin->id, $document->penyetuju->id);
        $this->actingAs($this->admin);
        $this->put(route('permintaan-pembelian.update', $document), $this->requestData(['id_pemohon' => $this->admin->id]))->assertSessionHasErrors('id_pemohon');
        $this->put(route('permintaan-pembelian.update', $document), $this->requestData())->assertSessionHasNoErrors();
        $this->assertSame($requester->id, $document->fresh()->pemohon->id);
        $this->get(route('permintaan-pembelian.edit', $document))->assertOk()->assertDontSee('name="id_pemohon"', false)->assertSee($requester->name);
    }

    public function test_approver_must_exist_and_have_purchasing_approval_permission(): void
    {
        $valid = PermintaanPembelian::create($this->requestData(['id_pemohon' => $this->admin->id]));
        foreach ([99999, $this->user('Sales')->id, $this->user('Finance')->id] as $id) {
            $data = $this->requestData(['id_penyetuju' => $id]);
            $this->post(route('permintaan-pembelian.store'), $data)->assertSessionHasErrors('id_penyetuju');
            $this->put(route('permintaan-pembelian.update', $valid), $data)->assertSessionHasErrors('id_penyetuju');
        }
        $buyer = $this->user('Purchasing');
        $this->post(route('permintaan-pembelian.store'), $this->requestData(['id_penyetuju' => $buyer->id]))->assertSessionHasNoErrors();
        $this->get(route('permintaan-pembelian.create'))->assertOk()->assertSee('name="id_penyetuju"', false)->assertSee($buyer->name);
    }

    public function test_only_assigned_approver_or_admin_can_approve_or_reject(): void
    {
        $buyer = $this->user('Purchasing');
        $approver = $this->user('Purchasing');
        $data = $this->requestData(['id_penyetuju' => $approver->id]);
        $this->actingAs($buyer);
        foreach (['Disetujui', 'Ditolak'] as $status) {
            $this->post(route('permintaan-pembelian.store'), array_replace($data, ['status' => $status]))->assertForbidden();
        }
        $this->post(route('permintaan-pembelian.store'), $data)->assertSessionHasNoErrors();
        $document = PermintaanPembelian::firstOrFail();
        $data['status'] = 'Disetujui';
        $this->put(route('permintaan-pembelian.update', $document), $data)->assertForbidden();
        $this->actingAs($approver);
        $this->put(route('permintaan-pembelian.update', $document), $data)->assertSessionHasNoErrors();
        $this->actingAs($buyer);
        $this->put(route('permintaan-pembelian.update', $document), array_replace($data, ['status' => 'Draft', 'id_penyetuju' => $buyer->id]))->assertForbidden();
        $this->actingAs($this->admin);
        $this->put(route('permintaan-pembelian.update', $document), array_replace($data, ['status' => 'Ditolak']))->assertSessionHasNoErrors();
    }

    public function test_request_status_values_are_validated_on_create_and_update(): void
    {
        foreach (PurchasingStatuses::REQUEST as $status) {
            $this->post(route('permintaan-pembelian.store'), $this->requestData(['status' => $status]))->assertSessionHasNoErrors();
        }
        $document = PermintaanPembelian::firstOrFail();
        foreach (['Lunas', 'INVALID', ['Draft']] as $status) {
            $this->post(route('permintaan-pembelian.store'), $this->requestData(['status' => $status]))->assertSessionHasErrors('status');
            $this->put(route('permintaan-pembelian.update', $document), $this->requestData(['status' => $status]))->assertSessionHasErrors('status');
        }
    }

    public function test_po_requires_approved_request_and_valid_chronology_on_create_and_update(): void
    {
        $data = $this->orderData('Draft');
        $request = PermintaanPembelian::findOrFail($data['id_permintaan']);
        foreach (['Draft', 'Diajukan', 'Ditolak'] as $status) {
            $request->update(['status' => $status]);
            $this->post(route('purchase-order.store'), $data)->assertSessionHasErrors('id_permintaan');
        }
        $this->assertDatabaseCount('purchase_order', 0);
        $request->update(['status' => 'Disetujui']);
        $this->post(route('purchase-order.store'), array_replace($data, ['tanggal' => '2026-10-12']))->assertSessionHasErrors('tanggal');
        $this->post(route('purchase-order.store'), $data)->assertSessionHasNoErrors();
        $order = PurchaseOrder::firstOrFail();
        $this->assertSame('Terbuka', $order->status);
        $this->put(route('purchase-order.update', $order), array_replace($data, ['tanggal' => '2026-10-12']))->assertSessionHasErrors('tanggal');
        $request->update(['status' => 'Draft']);
        $this->put(route('purchase-order.update', $order), $data)->assertSessionHasErrors('id_permintaan');
        $this->assertSame('2026-10-13', $order->fresh()->tanggal);
    }

    public function test_po_choices_exclude_unapproved_requests_and_posted_status_is_rejected(): void
    {
        $draft = $this->orderData('Draft');
        $approved = $this->orderData();
        $this->get(route('purchase-order.create'))->assertOk()->assertViewHas('requests', fn ($requests) => $requests->pluck('id_permintaan')->all() === [$approved['id_permintaan']]);
        foreach (['INVALID', 'Diinvoice', 'Terbuka'] as $status) {
            $this->post(route('purchase-order.store'), array_replace($approved, ['status' => $status]))->assertSessionHasErrors('status');
        }
        $this->post(route('purchase-order.store'), array_replace($approved, ['id_permintaan' => 99999]))->assertSessionHasErrors('id_permintaan');
    }

    public function test_request_cannot_be_changed_after_it_is_used_by_po(): void
    {
        $order = $this->order();
        $document = $order->permintaanPembelian;
        $this->get(route('permintaan-pembelian.edit', $document))->assertRedirect()->assertSessionHasErrors('document');
        $this->put(route('permintaan-pembelian.update', $document), $this->requestData(['status' => 'Draft']))->assertSessionHasErrors('document');
        $this->assertSame('Disetujui', $document->fresh()->status);
    }

    public function test_legacy_request_with_missing_requester_is_not_silently_reassigned(): void
    {
        $document = PermintaanPembelian::create($this->requestData(['id_pemohon' => 99999]));
        $this->put(route('permintaan-pembelian.update', $document), $this->requestData())->assertSessionHasErrors('id_pemohon');
        $this->assertSame(99999, (int) $document->fresh()->id_pemohon);
    }

    public function test_invoice_always_uses_server_po_total_even_if_client_forges_or_omits_it(): void
    {
        foreach ([[], ['total_invoice' => 1], ['total_invoice' => -1], ['total_invoice' => ['malformed']], ['total_invoice' => '999999999999999999999']] as $override) {
            $order = $this->order();
            $this->post(route('invoice-pembelian.store'), $this->invoiceData($order, $override))->assertForbidden();
            $invoice = $order->invoice()->firstOrFail();
            $this->assertSame('222.11', number_format((float) $invoice->total_invoice, 2, '.', ''));
            $this->assertSame('Diinvoice', $order->fresh()->status);
        }
    }

    public function test_duplicate_invoice_is_business_error_and_po_is_removed_from_choices(): void
    {
        $order = $this->order();
        $data = $this->invoiceData($order);
        $this->post(route('invoice-pembelian.store'), $data)->assertForbidden();
        $this->post(route('invoice-pembelian.store'), $data)->assertForbidden();
        $this->assertDatabaseCount('invoice_pembelian', 1);
        $this->get(route('invoice-pembelian.create'))->assertForbidden();
        $this->get(route('purchase-order.show', $order))->assertOk()->assertDontSee('Buat Invoice');
    }

    public function test_database_unique_constraint_blocks_duplicate_invoices_outside_controller(): void
    {
        $order = $this->order();
        $this->expectException(QueryException::class);
        DB::table('invoice_pembelian')->insert(array_merge($this->invoiceData($order), ['no_invoice' => 'DUPLICATE', 'total_invoice' => 1]));
    }

    public function test_invoice_and_return_only_accept_their_own_status_values(): void
    {
        $order = $this->order();
        foreach (['Disetujui', 'INVALID', ['Lunas']] as $status) {
            $this->patch(route('invoice-pembelian.update', $order->invoice), ['status' => $status])->assertSessionHasErrors('status');
        }
        foreach (PurchasingStatuses::INVOICE as $status) {
            $newOrder = $this->order();
            $this->patch(route('invoice-pembelian.update', $newOrder->invoice), ['status' => $status])->assertSessionHasNoErrors();
        }
        $invoice = InvoicePembelian::firstOrFail();
        $return = ['id_invoice_pembelian' => $invoice->id_invoice_pembelian, 'tanggal_retur' => '2026-10-15', 'alasan' => 'Rusak'];
        foreach (['Lunas', 'INVALID', ['Diajukan']] as $status) {
            $this->post(route('retur-pembelian.store'), array_merge($return, ['status' => $status]))->assertSessionHasErrors('status');
        }
        foreach (PurchasingStatuses::RETURN as $status) {
            // Historical status values are preserved; new transitions have dedicated tests.
            ReturPembelian::create(array_merge($return, ['status' => $status]));
        }
    }

    public function test_full_invoice_migration_refuses_legacy_duplicates_without_deleting_them(): void
    {
        $order = $this->order();
        $migration = require database_path('migrations/2026_10_16_000002_enforce_one_purchase_invoice_per_order.php');
        $migration->down();
        DB::table('invoice_pembelian')->insert(array_merge($this->invoiceData($order), ['no_invoice' => 'LEGACY-DUPLICATE', 'total_invoice' => 1]));
        try {
            $migration->up();
            $this->fail('Duplicate legacy invoices must prevent migration.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Terdapat PO dengan beberapa invoice', $exception->getMessage());
        }
        $this->assertDatabaseCount('invoice_pembelian', 2);
    }

    public function test_invalid_legacy_request_identities_cannot_be_used_for_po(): void
    {
        $data = $this->orderData();
        $request = PermintaanPembelian::findOrFail($data['id_permintaan']);
        $request->update(['id_pemohon' => 99999]);
        $this->post(route('purchase-order.store'), $data)->assertSessionHasErrors('id_permintaan');
        $request->update(['id_pemohon' => $this->admin->id, 'id_penyetuju' => $this->user('Finance')->id]);
        $this->post(route('purchase-order.store'), $data)->assertSessionHasErrors('id_permintaan');
        $this->assertDatabaseCount('purchase_order', 0);
    }

    public function test_invoice_amount_is_readonly_and_finance_cannot_create_purchase_orders(): void
    {
        $order = $this->order();
        $this->actingAs($this->user('Finance'));
        $this->get(route('invoice-pembelian.create'))->assertForbidden();
        $this->get(route('invoice-pembelian.show', $order->invoice))->assertOk()->assertSee('Invoice dibuat otomatis oleh sistem');
        $this->post(route('purchase-order.store'), [])->assertForbidden();
        $this->patch(route('invoice-pembelian.update', $order->invoice), ['status' => 'Lunas', 'total_invoice' => 1])->assertSessionHasNoErrors();
        $this->assertSame('Lunas', $order->invoice->fresh()->status);
        $this->assertEquals($order->total_harga, $order->invoice->fresh()->total_invoice);
    }
}
