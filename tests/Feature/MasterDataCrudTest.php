<?php

namespace Tests\Feature;

use App\Models\MBarang;
use App\Models\MCustomer;
use App\Models\MSupplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MasterDataCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = \App\Models\User::factory()->create();
        $user->role = 'Admin';
        $user->save();
        $this->actingAs($user);
    }


    public static function resources(): array
    {
        return [['customer', MCustomer::class, 'nama_customer'], ['barang', MBarang::class, 'nama_barang']];
    }

    #[DataProvider('resources')]
    public function test_all_resource_routes_and_crud_work(string $resource, string $model, string $nameField): void
    {
        $label = ucfirst($resource);
        $this->get(route($resource.'.index'))->assertOk()->assertSee('Belum ada data '.$resource);
        $this->get(route($resource.'.create'))->assertOk()
            ->assertSee('assets/css/dashboard.css')->assertSee('name="_token"', false);

        $data = $this->dataFor($resource);
        $this->post(route($resource.'.store'), $data)->assertRedirect(route($resource.'.index'))
            ->assertSessionHas('success', 'Data '.$resource.' berhasil ditambahkan.');
        $record = $model::firstOrFail();
        $this->assertDatabaseHas($record->getTable(), $data);
        $this->get(route($resource.'.index'))->assertOk()->assertSee($data[$nameField])
            ->assertSee('name="_method" value="DELETE"', false);
        $this->get(route($resource.'.show', $record->getKey()))->assertOk()
            ->assertSee('Detail '.$label)->assertSee($data[$nameField]);
        $this->get(route($resource.'.edit', $record->getKey()))->assertOk()
            ->assertSee('name="_method" value="PUT"', false)->assertSee($data[$nameField]);

        $data[$nameField] = $label.' Diperbarui';
        $this->put(route($resource.'.update', $record->getKey()), $data)
            ->assertRedirect(route($resource.'.index'))->assertSessionHas('success');
        $this->assertDatabaseHas($record->getTable(), $data);
        $data[$nameField] = $label.' Patch';
        $this->patch(route($resource.'.update', $record->getKey()), $data)
            ->assertRedirect(route($resource.'.index'));
        $this->assertDatabaseHas($record->getTable(), $data);

        $this->delete(route($resource.'.destroy', $record->getKey()))
            ->assertRedirect(route($resource.'.index'))->assertSessionHas('success');
        $this->assertDatabaseMissing($record->getTable(), [$record->getKeyName() => $record->getKey()]);
    }

    #[DataProvider('resources')]
    public function test_validation_preserves_input_and_rejects_missing_fields(string $resource, string $model, string $nameField): void
    {
        $this->from(route($resource.'.create'))->post(route($resource.'.store'), [$nameField => 'Data Belum Lengkap'])
            ->assertRedirect(route($resource.'.create'))->assertSessionHasErrors();
        $this->get(route($resource.'.create'))->assertOk()->assertSee('Data Belum Lengkap')
            ->assertSee('Periksa kembali data Anda.')->assertSee('is-invalid');
        $this->assertDatabaseCount((new $model)->getTable(), 0);
    }

    #[DataProvider('resources')]
    public function test_unknown_record_returns_404(string $resource, string $model, string $nameField): void
    {
        foreach (['show', 'edit'] as $action) {
            $this->get(route($resource.'.'.$action, 999))->assertNotFound();
        }
        $this->put(route($resource.'.update', 999), $this->dataFor($resource))->assertNotFound();
        $this->delete(route($resource.'.destroy', 999))->assertNotFound();
    }

    public function test_customer_validation_matches_existing_column_limits(): void
    {
        $invalid = [
            'nama_customer' => str_repeat('n', 101), 'alamat' => str_repeat('a', 201),
            'telepon' => str_repeat('1', 21), 'email' => 'bukan-email',
        ];
        $this->post(route('customer.store'), $invalid)
            ->assertSessionHasErrors(array_keys($invalid));
        $customer = MCustomer::create($this->dataFor('customer'));
        $this->put(route('customer.update', $customer->id_customer), $invalid)
            ->assertSessionHasErrors(array_keys($invalid));
        $this->assertDatabaseHas('m_customer', $this->dataFor('customer'));
    }

    public function test_barang_sku_is_unique_and_unchanged_sku_can_be_updated(): void
    {
        $data = $this->dataFor('barang');
        $barang = MBarang::create($data);
        $this->post(route('barang.store'), $data)->assertSessionHasErrors('sku');
        $other = MBarang::create(array_merge($data, ['sku' => 'BRG-002']));
        $this->put(route('barang.update', $other->id_barang), $data)->assertSessionHasErrors('sku');
        $this->put(route('barang.update', $barang->id_barang), array_merge($data, ['stok' => 0, 'harga_beli' => 0]))
            ->assertRedirect(route('barang.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('m_barang', ['id_barang' => $barang->id_barang, 'stok' => 0, 'harga_beli' => 0]);
    }

    public function test_barang_rejects_invalid_prices_and_stock_on_create_and_update(): void
    {
        $barang = MBarang::create($this->dataFor('barang'));
        foreach ([['harga_beli', -1], ['harga_beli', 100000000], ['harga_beli', '10.123'], ['stok', -1], ['stok', 1.5], ['stok', 2147483648]] as [$field, $value]) {
            $invalid = array_merge($this->dataFor('barang'), ['sku' => 'BRG-INVALID', $field => $value]);
            $this->post(route('barang.store'), $invalid)->assertSessionHasErrors($field);
            $this->put(route('barang.update', $barang->id_barang), $invalid)->assertSessionHasErrors($field);
        }
        $this->assertDatabaseCount('m_barang', 1);
        $this->assertDatabaseHas('m_barang', $this->dataFor('barang'));
    }

    public function test_customer_and_barang_in_sales_orders_cannot_be_deleted(): void
    {
        $customer = MCustomer::create($this->dataFor('customer'));
        $barang = MBarang::create($this->dataFor('barang'));
        $orderId = DB::table('sales_order')->insertGetId([
            'no_sales_order' => 'SO-TEST', 'id_customer' => $customer->id_customer,
            'tanggal' => '2026-10-13', 'total_harga' => 15000,
        ]);
        DB::table('detail_sales_order')->insert([
            'id_sales_order' => $orderId, 'id_barang' => $barang->id_barang,
            'sku' => $barang->sku, 'nama_barang' => $barang->nama_barang,
            'kuantitas' => 1, 'harga_jual' => 15000, 'diskon' => 0, 'total_harga' => 15000,
        ]);
        $this->delete(route('customer.destroy', $customer->id_customer))
            ->assertRedirect(route('customer.index'))->assertSessionHasErrors('customer');
        $this->delete(route('barang.destroy', $barang->id_barang))
            ->assertRedirect(route('barang.index'))->assertSessionHasErrors('barang');
        $this->assertDatabaseCount('m_customer', 1);
        $this->assertDatabaseCount('m_barang', 1);
    }

    public function test_barang_in_purchase_orders_cannot_be_deleted(): void
    {
        $barang = MBarang::create($this->dataFor('barang'));
        $supplier = MSupplier::create([
            'nama_supplier' => 'Supplier Test', 'alamat' => 'Jakarta',
            'telepon' => '081234567890', 'email' => 'supplier@example.com',
        ]);
        $requestId = DB::table('permintaan_pembelian')->insertGetId([
            'no_faktur' => 'REQ-TEST', 'tanggal' => '2026-10-13',
            'tanggal_diperlukan' => '2026-10-15', 'id_pemohon' => 1,
            'id_penyetuju' => 2, 'status' => 'Disetujui',
        ]);
        $orderId = DB::table('purchase_order')->insertGetId([
            'no_order_pembelian' => 'PO-TEST', 'id_permintaan' => $requestId,
            'id_supplier' => $supplier->id_supplier, 'tanggal' => '2026-10-13',
            'tanggal_dibutuhkan' => '2026-10-15', 'jumlah' => 1, 'ppn' => 0, 'total_harga' => 12500,
        ]);
        DB::table('detail_purchase_order')->insert([
            'id_purchase_order' => $orderId, 'id_barang' => $barang->id_barang,
            'sku' => $barang->sku, 'nama_barang' => $barang->nama_barang,
            'kuantitas' => 1, 'harga_unit' => 12500, 'diskon' => 0, 'total_harga' => 12500,
        ]);
        $this->delete(route('barang.destroy', $barang->id_barang))
            ->assertRedirect(route('barang.index'))->assertSessionHasErrors('barang');
        $this->assertDatabaseCount('m_barang', 1);
    }

    public function test_sidebar_customer_and_barang_links_are_active_without_coming_soon_labels(): void
    {
        foreach (['customer', 'barang'] as $resource) {
            $response = $this->get(route($resource.'.index'))->assertOk();
            $html = $response->getContent();
            $this->assertMatchesRegularExpression('/class="sidebar-link active"[^>]*href="'.preg_quote(route($resource.'.index'), '/').'"[^>]*aria-current="page"/', $html);
            $this->assertStringNotContainsString('aria-label="'.ucfirst($resource).' — segera tersedia"', $html);
        }
    }

    private function dataFor(string $resource): array
    {
        return $resource === 'customer'
            ? ['nama_customer' => 'PT Customer Test', 'alamat' => 'Jl. Merdeka, Jakarta', 'telepon' => '081234567890', 'email' => 'customer@example.com']
            : ['sku' => 'BRG-001', 'nama_barang' => 'Barang Test', 'satuan' => 'pcs', 'harga_beli' => '12500.50', 'stok' => 10];
    }
}
