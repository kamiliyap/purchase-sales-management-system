<?php

namespace Tests\Feature;

use App\Models\MSupplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardUiTest extends TestCase
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


    public function test_dashboard_shows_database_totals_and_monthly_order_values(): void
    {
        $this->travelTo(Carbon::parse('2026-10-13 10:00:00', 'Asia/Jakarta'));
        $supplier = MSupplier::create($this->supplierData());
        $customerId = DB::table('m_customer')->insertGetId([
            'nama_customer' => 'Customer Test', 'alamat' => 'Jakarta',
            'telepon' => '081234567890', 'email' => 'customer@example.com',
        ]);
        $requestId = DB::table('permintaan_pembelian')->insertGetId([
            'no_faktur' => 'REQ-001', 'tanggal' => '2026-10-01',
            'tanggal_diperlukan' => '2026-10-20', 'id_pemohon' => 1,
            'id_penyetuju' => 2, 'status' => 'Disetujui',
        ]);
        foreach ([['2026-09-01', 150000], ['2026-10-05', 250000], ['2026-04-01', 990000]] as [$date, $amount]) {
            DB::table('purchase_order')->insert([
                'no_order_pembelian' => 'PO-'.$date, 'id_permintaan' => $requestId,
                'id_supplier' => $supplier->id_supplier, 'tanggal' => $date,
                'tanggal_dibutuhkan' => $date, 'jumlah' => 1, 'ppn' => 0,
                'total_harga' => $amount,
            ]);
        }
        DB::table('sales_order')->insert([
            'no_sales_order' => 'SO-001', 'id_customer' => $customerId,
            'tanggal' => '2026-10-06', 'total_harga' => 450000,
        ]);

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Selamat pagi')
            ->assertSee('assets/css/dashboard.css')
            ->assertViewHas('stats', ['purchase_orders' => 3, 'sales_orders' => 1, 'suppliers' => 1, 'customers' => 1])
            ->assertViewHas('chart', fn ($chart) => count($chart['labels']) === 6
                && $chart['purchase'] === [0.0, 0.0, 0.0, 0.0, 150000.0, 250000.0]
                && $chart['sales'] === [0.0, 0.0, 0.0, 0.0, 0.0, 450000.0]);
    }

    public function test_supplier_pages_use_the_theme_and_crud_still_works(): void
    {
        $this->get(route('supplier.index'))->assertOk()->assertSee('Mulai dengan supplier pertama');
        $this->get(route('supplier.create'))->assertOk()
            ->assertSee('assets/css/dashboard.css')->assertSee('name="nama_supplier"', false)
            ->assertSee('name="_token"', false)->assertSee('Simpan Supplier');

        $data = $this->supplierData();
        $this->post(route('supplier.store'), $data)->assertRedirect(route('supplier.index'));
        $supplier = MSupplier::firstOrFail();
        $this->assertDatabaseHas('m_supplier', $data);
        $this->get(route('supplier.index'))->assertOk()->assertSee($data['nama_supplier'])
            ->assertSee('name="_method" value="DELETE"', false);
        $this->get(route('supplier.edit', $supplier->id_supplier))->assertOk()
            ->assertSee('assets/css/dashboard.css')->assertSee($data['nama_supplier'])
            ->assertSee('name="_method" value="PUT"', false);

        $data['nama_supplier'] = 'Supplier Diperbarui';
        $this->put(route('supplier.update', $supplier->id_supplier), $data)
            ->assertRedirect(route('supplier.index'));
        $this->assertDatabaseHas('m_supplier', $data);
        $this->delete(route('supplier.destroy', $supplier->id_supplier))
            ->assertRedirect(route('supplier.index'));
        $this->assertDatabaseMissing('m_supplier', ['id_supplier' => $supplier->id_supplier]);
    }

    public function test_invalid_supplier_input_preserves_values_and_shows_errors(): void
    {
        $this->from(route('supplier.create'))->post(route('supplier.store'), [
            'nama_supplier' => 'Supplier Belum Lengkap',
            'email' => 'bukan-email',
        ])->assertRedirect(route('supplier.create'))->assertSessionHasErrors(['alamat', 'telepon', 'email']);
        $this->get(route('supplier.create'))->assertOk()->assertSee('Supplier Belum Lengkap')
            ->assertSee('Periksa kembali data Anda.')->assertSee('email-error');
        $this->assertDatabaseCount('m_supplier', 0);
    }

    private function supplierData(): array
    {
        return [
            'nama_supplier' => 'PT Supplier Test', 'alamat' => 'Jl. Merdeka, Jakarta',
            'telepon' => '081234567890', 'email' => 'supplier@example.com',
        ];
    }
}
