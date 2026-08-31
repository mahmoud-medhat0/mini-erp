<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Models\User;
use Database\Seeders\AccountingDemoSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounting_demo_seeder_creates_posted_journal_and_ledger_entries(): void
    {
        $this->seed(AccountingDemoSeeder::class);

        $demoJournal = JournalEntry::query()
            ->where('reference', 'DEMO-POSTED-SALE-001')
            ->first();

        $this->assertNotNull($demoJournal);
        $this->assertEquals('posted', $demoJournal->status);
        $this->assertEquals('manual_journal', $demoJournal->source_type);
        $this->assertEquals('demo-sale-receipt-001', $demoJournal->source_id);

        $ledgerCount = LedgerEntry::query()
            ->where('journal_entry_id', $demoJournal->id)
            ->count();

        $this->assertEquals(2, $ledgerCount);
    }

    public function test_accounting_demo_seeder_is_idempotent_on_multiple_runs(): void
    {
        $this->seed(AccountingDemoSeeder::class);

        $initialJournalCount = JournalEntry::query()->where('reference', 'DEMO-POSTED-SALE-001')->count();
        $initialLedgerCount = LedgerEntry::query()->count();

        $this->assertEquals(1, $initialJournalCount);
        $this->assertEquals(2, $initialLedgerCount);

        // Run seeder second time
        $this->seed(AccountingDemoSeeder::class);

        $secondJournalCount = JournalEntry::query()->where('reference', 'DEMO-POSTED-SALE-001')->count();
        $secondLedgerCount = LedgerEntry::query()->count();

        $this->assertEquals(1, $secondJournalCount);
        $this->assertEquals(2, $secondLedgerCount);
    }

    public function test_ledger_page_returns_posted_data_after_demo_seeding(): void
    {
        $this->seed(AccountingDemoSeeder::class);

        $user = User::query()->first();

        $response = $this->actingAs($user)->get(route('accounting.ledger'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/GeneralLedger')
            ->has('ledger.data', 2)
            ->where('totals.debit', 100000)
            ->where('totals.credit', 100000)
        );
    }

    public function test_empty_state_props_rendered_when_no_accounting_data_exists(): void
    {
        $this->seed([RbacSeeder::class, PermissionSeeder::class]);
        $user = User::factory()->create();
        $user->givePermissionTo('accounting.view');

        // 1. General Ledger
        $responseLedger = $this->actingAs($user)->get(route('accounting.ledger'));
        $responseLedger->assertOk();
        $responseLedger->assertInertia(fn ($page) => $page
            ->component('Accounting/GeneralLedger')
            ->has('ledger.data', 0)
        );

        // 2. General Journal
        $responseJournal = $this->actingAs($user)->get(route('accounting.journal'));
        $responseJournal->assertOk();
        $responseJournal->assertInertia(fn ($page) => $page
            ->component('Accounting/GeneralJournal')
            ->where('journals.data', [])
        );

        // 3. Trial Balance
        $responseTb = $this->actingAs($user)->get(route('accounting.trial_balance'));
        $responseTb->assertOk();
        $responseTb->assertInertia(fn ($page) => $page
            ->component('Accounting/TrialBalance')
            ->has('rows', 0)
        );
    }
}
