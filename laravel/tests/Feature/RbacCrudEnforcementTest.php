<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RbacSeeder;
use Database\Seeders\UnitOfMeasureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RbacCrudEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(CurrencySeeder::class);

        $this->user = User::factory()->create();
    }

    public function test_create_is_denied_without_module_create_permission(): void
    {
        $this->user->givePermissionTo('customers.view');

        $this->actingAs($this->user)
            ->post('/customers', ['code' => 'CUST-001', 'name' => 'Acme'])
            ->assertForbidden();
    }

    public function test_edit_is_denied_without_module_edit_permission(): void
    {
        $customer = Customer::query()->create([
            'code' => 'CUST-001',
            'name' => 'Acme',
            'status' => 'active',
            'lock_version' => 0,
        ]);

        $this->user->givePermissionTo(['customers.view', 'customers.create']);

        $this->actingAs($this->user)
            ->patch("/customers/{$customer->id}", ['name' => 'Renamed'])
            ->assertForbidden();
    }

    public function test_view_is_denied_without_module_view_permission(): void
    {
        $this->actingAs($this->user)
            ->get('/customers')
            ->assertForbidden();
    }

    public function test_delete_is_separated_from_edit_on_catalog_resources(): void
    {
        $this->seed(UnitOfMeasureSeeder::class);

        $this->user->givePermissionTo(['uom.view', 'uom.edit']);

        $uomId = (string) DB::table('unit_of_measure')->where('code', 'PCS')->value('id');

        $this->actingAs($this->user)
            ->put("/catalog/uoms/{$uomId}", ['code' => 'PCS', 'name' => ['en' => 'Piece'], 'is_active' => true])
            ->assertRedirect();

        $this->actingAs($this->user)
            ->delete("/catalog/uoms/{$uomId}")
            ->assertForbidden();

        $this->user->givePermissionTo('uom.delete');

        $this->actingAs($this->user)
            ->delete("/catalog/uoms/{$uomId}")
            ->assertRedirect();
    }

    public function test_currency_destroy_requires_accounting_delete(): void
    {
        $this->user->givePermissionTo(['accounting.view', 'accounting.create']);

        $this->actingAs($this->user)
            ->post('/accounting/currencies', [
                'code' => 'XPT',
                'name_en' => 'Platinum',
                'name_ar' => 'بلاتين',
                'symbol' => 'XPT',
                'exponent' => 2,
            ])
            ->assertRedirect();

        $this->actingAs($this->user)
            ->delete('/accounting/currencies/XPT')
            ->assertForbidden();

        $this->user->givePermissionTo('accounting.delete');

        $this->actingAs($this->user)
            ->delete('/accounting/currencies/XPT')
            ->assertRedirect();

        $this->assertDatabaseMissing('currency', ['code' => 'XPT']);
    }

    public function test_role_templates_do_not_receive_new_delete_grants(): void
    {
        $this->seed(PermissionSeeder::class);

        foreach (['ACCOUNTANT', 'HR'] as $roleName) {
            $role = Role::query()->where('name', $roleName)->firstOrFail();

            $deletePermissions = $role->permissions
                ->pluck('name')
                ->filter(fn (string $name): bool => str_ends_with($name, '.delete'));

            $this->assertSame([], $deletePermissions->values()->all(), "[{$roleName}] must not hold delete permissions.");
        }
    }
}
