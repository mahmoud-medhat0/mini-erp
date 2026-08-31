<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PreProductionQaRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        $this->user = User::factory()->create(['locale' => 'en']);
        $this->user->givePermissionTo([
            'dashboard.view',
            'customers.view',
            'suppliers.view',
            'cash.view',
            'banks.view',
            'cheques.view',
        ]);
    }

    public function test_phase3_pages_that_failed_during_qa_render_cleanly(): void
    {
        $paths = [
            '/customer-opening-balances',
            '/supplier-opening-balances',
            '/customer-receipts',
            '/supplier-payments',
            '/receivable-allocations',
            '/payable-allocations',
            '/incoming-cheques',
            '/outgoing-cheques',
            '/bank-reconciliations',
        ];

        foreach ($paths as $path) {
            $this->actingAs($this->user)
                ->get($path)
                ->assertOk();
        }
    }

    public function test_legacy_locale_prefixed_urls_redirect_to_active_routes(): void
    {
        $this->actingAs($this->user)
            ->get('/en/dashboard')
            ->assertRedirect('/dashboard');

        $this->assertSame('en', session('locale'));

        $this->actingAs($this->user)
            ->get('/ar/dashboard')
            ->assertRedirect('/dashboard');

        $this->assertSame('ar', session('locale'));
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'locale' => 'ar',
        ]);
    }

    public function test_cheque_due_dates_are_explicit_schema_fields(): void
    {
        $this->assertTrue(Schema::hasColumn('incoming_cheque', 'due_date'));
        $this->assertTrue(Schema::hasColumn('outgoing_cheque', 'due_date'));
    }
}
