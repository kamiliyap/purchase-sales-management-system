<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthenticationPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(?string $role): User
    {
        $user = User::factory()->create();
        $user->role = $role;
        $user->save();

        return $user;
    }

    public function test_password_command_updates_hash_without_changing_role(): void
    {
        $user = $this->userWithRole('Purchasing');
        $this->artisan('user:password', ['email' => $user->email])
            ->expectsQuestion('Password baru (minimal 12 karakter)', 'Test-only-new-password-123')
            ->expectsQuestion('Ulangi password', 'Test-only-new-password-123')
            ->assertSuccessful();
        $this->assertTrue(Hash::check('Test-only-new-password-123', $user->fresh()->password));
        $this->assertSame('Purchasing', $user->fresh()->role);
    }

    public function test_password_command_rejects_invalid_confirmation_or_short_password(): void
    {
        $user = $this->userWithRole('Purchasing');
        $original = $user->password;
        foreach ([['short', 'short'], ['Long-test-password-123', 'Different-password-123']] as [$password, $confirmation]) {
            $this->artisan('user:password', ['email' => $user->email])
                ->expectsQuestion('Password baru (minimal 12 karakter)', $password)
                ->expectsQuestion('Ulangi password', $confirmation)
                ->assertFailed();
            $this->assertSame($original, $user->fresh()->password);
        }
        $this->artisan('user:password', ['email' => 'missing@example.com'])->assertFailed();
    }

    public function test_guest_cannot_access_any_business_endpoint(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            if (! in_array('module.permission', $route->gatherMiddleware(), true)) {
                continue;
            }
            $uri = '/'.ltrim(preg_replace('/\{[^}]+\}/', '1', $route->uri()), '/');
            $this->call($route->methods()[0], $uri)->assertRedirect(route('login'));
        }
    }

    public function test_login_failure_success_and_logout(): void
    {
        $user = $this->userWithRole('Admin');
        $this->get('/login')->assertOk();
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->get('/')->assertOk();
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->get('/supplier')->assertRedirect('/login');
    }

    public function test_failed_login_is_rate_limited(): void
    {
        $user = $this->userWithRole('Admin');
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_role_matrix_is_enforced_on_real_endpoints(): void
    {
        $allowed = [
            'Admin' => ['supplier', 'customer', 'barang', 'permintaan-pembelian', 'purchase-order', 'invoice-pembelian', 'retur-pembelian'],
            'Purchasing' => ['supplier', 'barang', 'permintaan-pembelian', 'purchase-order', 'invoice-pembelian', 'retur-pembelian'],
            'Sales' => ['customer', 'barang'],
            'Finance' => ['purchase-order', 'invoice-pembelian', 'retur-pembelian'],
        ];
        foreach ($allowed as $role => $modules) {
            $this->actingAs($this->userWithRole($role));
            $this->get('/')->assertOk();
            foreach ($allowed['Admin'] as $module) {
                $this->get('/'.$module)->assertStatus(in_array($module, $modules, true) ? 200 : 403);
                if (! in_array($module, $modules, true)) {
                    $this->post('/'.$module, [])->assertForbidden();
                }
            }
        }
    }

    public function test_finance_is_read_only_outside_invoices(): void
    {
        $this->actingAs($this->userWithRole('Finance'));
        foreach (['purchase-order', 'retur-pembelian'] as $module) {
            $this->get('/'.$module.'/create')->assertForbidden();
            $this->post('/'.$module)->assertForbidden();
            $this->get('/'.$module)->assertOk()->assertDontSee('Tambah '.($module === 'purchase-order' ? 'Purchase Order' : 'Retur Pembelian'));
        }
        $this->get('/invoice-pembelian/create')->assertForbidden();
        $this->post('/invoice-pembelian')->assertForbidden();
    }

    public function test_unassigned_or_unknown_role_is_denied_and_form_cannot_assign_role(): void
    {
        foreach ([null, 'Other'] as $role) {
            $this->actingAs($this->userWithRole($role));
            $this->get('/')->assertForbidden();
            $this->get('/barang')->assertForbidden();
        }
        $user = new User(['name' => 'Test', 'role' => 'Admin']);
        $this->assertNull($user->role);
    }

    public function test_terminal_command_assigns_only_valid_roles_without_changing_password(): void
    {
        $user = $this->userWithRole(null);
        $password = $user->password;
        $this->artisan('user:access', ['email' => $user->email, 'role' => 'Finance'])->assertSuccessful();
        $this->assertSame('Finance', $user->fresh()->role);
        $this->assertSame($password, $user->fresh()->password);
        $this->artisan('user:access', ['email' => $user->email, 'role' => 'Other'])->assertFailed();
        $this->assertSame('Finance', $user->fresh()->role);
    }

    public function test_sales_permissions_match_registered_sales_routes(): void
    {
        foreach (['Admin', 'Purchasing', 'Sales', 'Finance'] as $role) {
            $user = $this->userWithRole($role);
            foreach (['sales-order', 'invoice-penjualan', 'retur-penjualan'] as $module) {
                foreach (['index', 'show', 'create', 'store', 'edit', 'update', 'destroy'] as $action) {
                    $expected = in_array($role, ['Admin', 'Sales'], true)
                        || ($role === 'Finance' && ($module === 'invoice-penjualan' || in_array($action, ['index', 'show'], true)));
                    $this->assertSame($expected, Gate::forUser($user)->allows('access-route', "$module.$action"));
                }
                $this->assertTrue(Route::has($module.'.index'));
            }
        }
    }
}
