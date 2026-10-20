<?php

namespace Tests\Feature;

use App\Models\DetailSalesOrder;
use App\Models\InvoicePenjualan;
use App\Models\MBarang;
use App\Models\MCustomer;
use App\Models\ReturPenjualan;
use App\Models\SalesOrder;
use App\Models\User;
use App\Support\SalesStatuses;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SalesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->user('Sales'));
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->role = $role;
        $user->save();

        return $user;
    }

    private function data(): array
    {
        $customer = MCustomer::create(['nama_customer' => 'Customer Sales', 'alamat' => 'Jakarta', 'telepon' => '081234567890', 'email' => 'customer@example.com']);
        $items = [];
        foreach ([[2, '100.10', '0.20'], [3, '50.00', '5.00']] as [$qty, $price, $discount]) {
            $barang = MBarang::create(['sku' => 'SKU-'.MBarang::count(), 'nama_barang' => 'Barang '.MBarang::count(), 'satuan' => 'pcs', 'harga_beli' => 10, 'stok' => 20]);
            $items[] = ['id_barang' => $barang->id_barang, 'kuantitas' => $qty, 'harga_jual' => $price, 'diskon' => $discount];
        }

        return ['id_customer' => $customer->id_customer, 'tanggal' => '2026-10-13', 'items' => $items];
    }

    private function order(): SalesOrder
    {
        $this->post(route('sales-order.store'), $this->data())->assertSessionHasNoErrors();

        return SalesOrder::latest('id_sales_order')->firstOrFail();
    }

    private function invoiceData(SalesOrder $order): array
    {
        return ['id_sales_order' => $order->id_sales_order, 'tanggal_invoice' => '2026-10-14', 'jatuh_tempo' => '2026-10-20', 'status' => 'Belum Lunas'];
    }

    private function invoice(SalesOrder $order): InvoicePenjualan
    {
        $this->post(route('invoice-penjualan.store'), $this->invoiceData($order))->assertSessionHasNoErrors();

        return $order->invoice()->firstOrFail();
    }

    private function returnData(InvoicePenjualan $invoice): array
    {
        return ['id_invoice_penjualan' => $invoice->id_invoice_penjualan, 'tanggal_retur' => '2026-10-15', 'alasan' => 'Barang rusak', 'status' => 'Diajukan'];
    }

    public function test_sales_order_crud_with_multiple_items_and_immutable_number(): void
    {
        $this->get(route('sales-order.index'))->assertOk()->assertSee('Belum ada Sales Order');
        $data = $this->data();
        $this->get(route('sales-order.create'))->assertOk()->assertSee('sales-order.js')->assertSee('Tambah Item');
        $this->post(route('sales-order.store'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();
        $number = $order->no_sales_order;
        $this->assertSame('345.00', $order->total_harga);
        $this->assertSame('Terbuka', $order->status);
        $this->assertCount(2, $order->details);
        $this->get(route('sales-order.show', $order))->assertOk()->assertSee($number)->assertSee('345,00');
        $this->get(route('sales-order.edit', $order))->assertOk()->assertSee('100.10');
        $data['items'] = [array_replace($data['items'][1], ['kuantitas' => 1, 'harga_jual' => '99.99', 'diskon' => 0])];
        $data['no_sales_order'] = 'FORGED';
        $this->put(route('sales-order.update', $order), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('detail_sales_order', 1);
        $this->assertSame('99.99', $order->fresh()->total_harga);
        $this->assertSame($number, $order->fresh()->no_sales_order);
        $this->delete(route('sales-order.destroy', $order))->assertRedirect(route('sales-order.index'));
        $this->assertDatabaseCount('sales_order', 0);
        $this->assertDatabaseCount('detail_sales_order', 0);
    }

    public function test_server_calculates_totals_and_snapshots_instead_of_trusting_frontend(): void
    {
        $data = $this->data();
        $data['total_harga'] = 1;
        $data['no_sales_order'] = 'FORGED';
        foreach ($data['items'] as &$item) {
            $item['sku'] = 'FORGED';
            $item['nama_barang'] = 'FORGED';
            $item['total_harga'] = 1;
        }
        unset($item);
        $this->post(route('sales-order.store'), $data)->assertSessionHasNoErrors();
        $order = SalesOrder::with('details')->firstOrFail();
        $this->assertSame('345.00', $order->total_harga);
        $this->assertSame(['200.00', '145.00'], $order->details->pluck('total_harga')->all());
        $this->assertSame(['SKU-0', 'SKU-1'], $order->details->pluck('sku')->all());
        $this->assertNotSame('FORGED', $order->no_sales_order);
        MBarang::firstOrFail()->update(['nama_barang' => 'Nama master baru', 'sku' => 'NEW-SKU']);
        $this->assertSame('SKU-0', $order->details()->firstOrFail()->sku);
        $this->assertSame([20, 20], MBarang::pluck('stok')->all());
    }

    public function test_header_and_item_validation_apply_to_create_and_update(): void
    {
        $data = $this->data();
        $this->post(route('sales-order.store'), $data)->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();
        foreach ([['id_customer', 99999], ['tanggal', '2026-02-30'], ['tanggal', 'invalid'], ['items', []], ['items', 'invalid'], ['status', 'Diinvoice']] as [$field, $value]) {
            $invalid = array_replace($data, [$field => $value]);
            $this->post(route('sales-order.store'), $invalid)->assertSessionHasErrors($field);
            $this->put(route('sales-order.update', $order), $invalid)->assertSessionHasErrors($field);
        }
        foreach ([['id_barang', 99999], ['kuantitas', 0], ['kuantitas', -1], ['kuantitas', 1.5], ['kuantitas', 2147483648],
            ['harga_jual', -1], ['harga_jual', '1.123'], ['harga_jual', '10000000000000.00'], ['diskon', -1], ['diskon', '0.001'], ['diskon', 9999]] as [$field, $value]) {
            $invalid = $data;
            $invalid['items'][0][$field] = $value;
            $this->post(route('sales-order.store'), $invalid)->assertSessionHasErrors('items.0.'.$field);
            $this->put(route('sales-order.update', $order), $invalid)->assertSessionHasErrors('items.0.'.$field);
        }
        $this->assertDatabaseCount('sales_order', 1);
        $this->assertDatabaseCount('detail_sales_order', 2);
        $this->assertSame('345.00', $order->fresh()->total_harga);
    }

    public function test_duplicate_barang_and_more_than_one_hundred_items_are_rejected(): void
    {
        $data = $this->data();
        $data['items'][] = $data['items'][0];
        $this->post(route('sales-order.store'), $data)->assertSessionHasErrors('items.0.id_barang');
        $data['items'] = array_fill(0, 101, $data['items'][0]);
        $this->post(route('sales-order.store'), $data)->assertSessionHasErrors('items');
        $this->assertDatabaseCount('sales_order', 0);
    }

    public function test_prices_and_totals_cannot_overflow_decimal_columns(): void
    {
        $data = $this->data();
        $data['items'][0]['harga_jual'] = '9999999999999.99';
        $this->post(route('sales-order.store'), $data)->assertSessionHasErrors('items.0.harga_jual');
        $data['items'][0]['kuantitas'] = 1;
        $data['items'][0]['diskon'] = 0;
        $this->post(route('sales-order.store'), $data)->assertSessionHasErrors('items');
        $data['items'] = [$data['items'][0]];
        $this->post(route('sales-order.store'), $data)->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();
        $this->assertSame('9999999999999.99', $order->total_harga);
        $this->assertSame('9999999999999.99', $this->invoice($order)->total_invoice);
    }

    public function test_zero_price_is_allowed_and_omitted_discount_defaults_to_zero(): void
    {
        $data = $this->data();
        foreach ($data['items'] as &$item) {
            $item['harga_jual'] = 0;
            unset($item['diskon']);
        }
        unset($item);
        $this->post(route('sales-order.store'), $data)->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();
        $this->assertSame('0.00', $order->total_harga);
        $this->assertSame(['0.00', '0.00'], $order->details->pluck('diskon')->all());
        $this->invoice($order);
    }

    public function test_detail_failure_rolls_back_create_and_update(): void
    {
        $data = $this->data();
        $this->post(route('sales-order.store'), $data)->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();
        $before = $order->toArray();
        $details = $order->details()->get()->toArray();
        $event = 'eloquent.creating: '.DetailSalesOrder::class;
        Event::listen($event, fn () => throw new \RuntimeException('Simulated Sales detail failure'));
        $this->withoutExceptionHandling();
        try {
            foreach ([false, true] as $update) {
                try {
                    $update ? $this->put(route('sales-order.update', $order), $data) : $this->post(route('sales-order.store'), $data);
                    $this->fail('Expected detail failure.');
                } catch (\RuntimeException $exception) {
                    $this->assertSame('Simulated Sales detail failure', $exception->getMessage());
                }
                $this->assertDatabaseCount('sales_order', 1);
                $this->assertSame($before, $order->fresh()->toArray());
                $this->assertSame($details, $order->details()->get()->toArray());
            }
        } finally {
            Event::forget($event);
        }
    }

    public function test_full_invoice_ignores_forged_amount_and_updates_derived_so_status(): void
    {
        foreach ([[], ['total_invoice' => 1], ['total_invoice' => -1], ['total_invoice' => ['invalid']]] as $override) {
            $order = $this->order();
            $this->get(route('invoice-penjualan.create', ['sales_order' => $order->id_sales_order]))->assertOk()->assertSee('345.00');
            $this->post(route('invoice-penjualan.store'), array_replace($this->invoiceData($order), $override))->assertSessionHasNoErrors();
            $this->assertSame('345.00', number_format((float) $order->invoice->total_invoice, 2, '.', ''));
            $this->assertSame('Diinvoice', $order->fresh()->status);
            $this->get(route('sales-order.show', $order))->assertOk()->assertSee('Diinvoice')->assertDontSee('Buat Invoice');
            $this->get(route('invoice-penjualan.show', $order->invoice))->assertOk()->assertSee($order->no_sales_order);
        }
    }

    public function test_invoice_duplicate_is_business_error_and_excluded_from_form(): void
    {
        $order = $this->order();
        $this->invoice($order);
        $this->post(route('invoice-penjualan.store'), $this->invoiceData($order))->assertSessionHasErrors('id_sales_order');
        $this->assertDatabaseCount('invoice_penjualan', 1);
        $this->get(route('invoice-penjualan.create'))->assertOk()->assertViewHas('orders', fn ($orders) => $orders->isEmpty());
    }

    public function test_database_constraint_rejects_duplicate_invoice_outside_controller(): void
    {
        $order = $this->order();
        $this->invoice($order);
        $this->expectException(QueryException::class);
        DB::table('invoice_penjualan')->insert(array_merge($this->invoiceData($order), ['no_invoice' => 'DUPLICATE', 'total_invoice' => 1]));
    }

    public function test_sales_migration_preserves_legacy_duplicates_and_refuses_to_run(): void
    {
        $order = $this->order();
        $this->invoice($order);
        $migration = require database_path('migrations/2026_10_16_000003_enforce_one_sales_invoice_per_order.php');
        $migration->down();
        DB::table('invoice_penjualan')->insert(array_merge($this->invoiceData($order), ['no_invoice' => 'LEGACY-DUPLICATE', 'total_invoice' => 1]));
        try {
            $migration->up();
            $this->fail('Expected duplicate preflight rejection.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('beberapa invoice penjualan', $exception->getMessage());
        }
        $this->assertDatabaseCount('invoice_penjualan', 2);
    }

    public function test_invoiced_so_cannot_be_edited_updated_or_deleted(): void
    {
        $data = $this->data();
        $this->post(route('sales-order.store'), $data)->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();
        $this->invoice($order);
        $this->get(route('sales-order.edit', $order))->assertRedirect()->assertSessionHasErrors('document');
        $this->put(route('sales-order.update', $order), $data)->assertSessionHasErrors('document');
        $this->delete(route('sales-order.destroy', $order))->assertSessionHasErrors('document');
        $this->assertDatabaseCount('detail_sales_order', 2);
        $this->assertSame('345.00', $order->fresh()->total_harga);
    }

    public function test_invoice_parent_dates_and_status_are_validated(): void
    {
        $order = $this->order();
        foreach ([['id_sales_order', 99999], ['tanggal_invoice', '2026-10-12'], ['tanggal_invoice', '2026-02-30'],
            ['jatuh_tempo', '2026-10-13'], ['status', 'Disetujui'], ['status', 'INVALID']] as [$field, $value]) {
            $this->post(route('invoice-penjualan.store'), array_replace($this->invoiceData($order), [$field => $value]))->assertSessionHasErrors($field);
        }
        $this->assertDatabaseCount('invoice_penjualan', 0);
        foreach (SalesStatuses::INVOICE as $status) {
            $newOrder = $this->order();
            $this->post(route('invoice-penjualan.store'), array_replace($this->invoiceData($newOrder), ['status' => $status]))->assertSessionHasNoErrors();
        }
    }

    public function test_invalid_or_empty_legacy_so_cannot_be_invoiced(): void
    {
        $order = $this->order();
        $order->details()->delete();
        $this->post(route('invoice-penjualan.store'), $this->invoiceData($order))->assertSessionHasErrors('id_sales_order');
        $this->get(route('invoice-penjualan.create'))->assertViewHas('orders', fn ($orders) => $orders->isEmpty());
        $order = $this->order();
        $order->update(['total_harga' => 1]);
        $this->post(route('invoice-penjualan.store'), $this->invoiceData($order))->assertSessionHasErrors('id_sales_order');
        $order->update(['total_harga' => 345]);
        $order->details()->firstOrFail()->update(['total_harga' => 1]);
        $this->post(route('invoice-penjualan.store'), $this->invoiceData($order))->assertSessionHasErrors('id_sales_order');
        $this->assertDatabaseCount('invoice_penjualan', 0);
    }

    public function test_failed_invoice_does_not_mark_order_as_invoiced(): void
    {
        $order = $this->order();
        $event = 'eloquent.creating: '.InvoicePenjualan::class;
        Event::listen($event, fn () => throw new \RuntimeException('Simulated invoice failure'));
        $this->withoutExceptionHandling();
        try {
            $this->post(route('invoice-penjualan.store'), $this->invoiceData($order));
            $this->fail('Expected invoice failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated invoice failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertSame('Terbuka', $order->fresh()->status);
        $this->assertDatabaseCount('invoice_penjualan', 0);
    }

    public function test_returns_validate_parent_date_reason_and_status_and_render_details(): void
    {
        $order = $this->order();
        $invoice = $this->invoice($order);
        $data = $this->returnData($invoice);
        $this->get(route('retur-penjualan.create', ['invoice' => $invoice->id_invoice_penjualan]))->assertOk()->assertSee($invoice->no_invoice);
        foreach ([['id_invoice_penjualan', 99999], ['tanggal_retur', '2026-10-13'], ['tanggal_retur', 'bad-date'],
            ['alasan', ''], ['alasan', str_repeat('a', 256)], ['status', 'Lunas'], ['status', 'INVALID']] as [$field, $value]) {
            $this->post(route('retur-penjualan.store'), array_replace($data, [$field => $value]))->assertSessionHasErrors($field);
        }
        $this->assertDatabaseCount('retur_penjualan', 0);
        foreach (SalesStatuses::RETURN as $status) {
            $this->post(route('retur-penjualan.store'), array_replace($data, ['status' => $status]))->assertSessionHasNoErrors();
        }
        $return = ReturPenjualan::firstOrFail();
        $this->get(route('retur-penjualan.show', $return))->assertOk()->assertSee('Barang rusak')->assertSee($order->no_sales_order);
        $this->get(route('retur-penjualan.index'))->assertOk()->assertSee($return->no_retur);
        $this->assertSame(20, MBarang::firstOrFail()->stok);
    }

    public function test_purchasing_gets_403_on_every_sales_endpoint_including_mutations(): void
    {
        $order = $this->order();
        $invoice = $this->invoice($order);
        $this->post(route('retur-penjualan.store'), $this->returnData($invoice))->assertSessionHasNoErrors();
        $return = ReturPenjualan::firstOrFail();
        $this->actingAs($this->user('Purchasing'));
        foreach (app('router')->getRoutes() as $route) {
            if (! preg_match('/^(sales-order|invoice-penjualan|retur-penjualan)\./', $route->getName() ?? '')) {
                continue;
            }
            $uri = str_replace(['{sales_order}', '{invoice_penjualan}', '{retur_penjualan}'], [$order->id_sales_order, $invoice->id_invoice_penjualan, $return->id_retur_penjualan], $route->uri());
            $this->call($route->methods()[0], '/'.$uri)->assertForbidden();
        }
    }

    public function test_finance_reads_so_and_returns_but_only_writes_invoices(): void
    {
        $data = $this->data();
        $this->post(route('sales-order.store'), $data)->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();
        $this->actingAs($this->user('Finance'));
        $this->get(route('sales-order.index'))->assertOk()->assertDontSee('Tambah Sales Order');
        $this->get(route('sales-order.show', $order))->assertOk()->assertDontSee('Edit SO');
        $this->get(route('sales-order.create'))->assertForbidden();
        $this->get(route('sales-order.edit', $order))->assertForbidden();
        $this->post(route('sales-order.store'), $data)->assertForbidden();
        $this->put(route('sales-order.update', $order), $data)->assertForbidden();
        $this->delete(route('sales-order.destroy', $order))->assertForbidden();
        $this->get(route('invoice-penjualan.create', ['sales_order' => $order->id_sales_order]))->assertOk();
        $invoice = $this->invoice($order);
        $this->get(route('invoice-penjualan.show', $invoice))->assertOk()->assertDontSee('Buat Retur');
        $this->get(route('retur-penjualan.index'))->assertOk();
        $this->get(route('retur-penjualan.create'))->assertForbidden();
        $this->post(route('retur-penjualan.store'), $this->returnData($invoice))->assertForbidden();
        $this->actingAs($this->user('Admin'));
        $this->post(route('retur-penjualan.store'), $this->returnData($invoice))->assertSessionHasNoErrors();
        $return = ReturPenjualan::firstOrFail();
        $this->actingAs($this->user('Finance'));
        $this->get(route('retur-penjualan.show', $return))->assertOk();
    }

    public function test_admin_and_sales_can_access_sales_pages_and_sidebar_without_detail_menu(): void
    {
        $order = $this->order();
        $invoice = $this->invoice($order);
        foreach (['Admin', 'Sales'] as $role) {
            $this->actingAs($this->user($role));
            foreach (['sales-order', 'invoice-penjualan', 'retur-penjualan'] as $module) {
                $this->get(route($module.'.index'))->assertOk()->assertSee(route($module.'.index'))->assertDontSee('detail-sales-order');
                $this->get(route($module.'.create'))->assertOk();
            }
            $this->get(route('customer.index'))->assertOk();
            $this->get(route('barang.index'))->assertOk();
        }
        $this->assertFalse(Route::has('detail-sales-order.index'));
    }

    public function test_missing_documents_and_empty_prerequisites_render_safely(): void
    {
        foreach (['sales-order', 'invoice-penjualan', 'retur-penjualan'] as $module) {
            $this->get(route($module.'.index'))->assertOk();
            $this->get(route($module.'.create'))->assertOk();
            $this->get(route($module.'.show', 99999))->assertNotFound();
        }
        $this->get(route('sales-order.edit', 99999))->assertNotFound();
        $this->put(route('sales-order.update', 99999), [])->assertNotFound();
        $this->delete(route('sales-order.destroy', 99999))->assertNotFound();
        $this->get(route('sales-order.create'))->assertSee('Tambah Customer')->assertSee('Tambah Barang');
    }
}
