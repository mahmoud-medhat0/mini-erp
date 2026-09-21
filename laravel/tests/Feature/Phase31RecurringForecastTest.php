<?php

namespace Tests\Feature;

use App\Application\Recurring\RecurringTemplateService;
use App\Application\Reports\ForecastService;
use App\Models\Account;
use App\Models\CashAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 31 - Recurring transactions engine + simple forecasting
 * (PHASE_25_GAP_CLOSURE_DECISION_PACK.md §10). The recurring engine always
 * generates a DRAFT expense for review (never auto-posts), reusing
 * ExpenseService end to end so every existing validation rule applies
 * identically to generated drafts.
 */
class Phase31RecurringForecastTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ExpenseCategory $category;

    private CashAccount $cashAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'DatabaseSeeder']);
        $this->withoutVite();

        $this->user = User::factory()->create(['locale' => 'en']);
        $this->user->givePermissionTo([
            'recurring.view', 'recurring.create', 'recurring.edit', 'recurring.delete',
            'expenses.view', 'expenses.create', 'reports.view', 'view_financials',
        ]);
        $this->actingAs($this->user);

        $this->category = ExpenseCategory::query()->where('code', 'GENERAL_ADMIN')->firstOrFail();

        $cashGlAccount = Account::query()->where('code', '1100')->firstOrFail();
        $this->cashAccount = CashAccount::query()->create([
            'code' => 'RECUR-CASH-01',
            'name' => ['en' => 'Recurring Test Cash', 'ar' => 'خزينة اختبار التكرار'],
            'gl_account_id' => $cashGlAccount->id,
            'currency' => 'EGP',
            'is_active' => true,
            'lock_version' => 1,
        ]);
    }

    private function templatePayload(): array
    {
        return [
            'settlement_method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
            'currency' => 'EGP',
            'description' => 'Monthly office rent',
            'expense_category_id' => $this->category->id,
            'unit_amount_minor' => 500_000,
        ];
    }

    public function test_creating_a_template_seeds_next_run_date_from_start_date(): void
    {
        $service = app(RecurringTemplateService::class);

        $template = $service->create([
            'code' => 'RENT-001',
            'name' => ['en' => 'Office Rent', 'ar' => 'إيجار المكتب'],
            'frequency' => 'monthly',
            'interval_count' => 1,
            'start_date' => '2026-01-05',
            'template_payload' => $this->templatePayload(),
        ], $this->user->id);

        $this->assertSame('active', $template->status);
        $this->assertSame('2026-01-05', $template->next_run_date->format('Y-m-d'));
        $this->assertSame(0, $template->generated_count);
    }

    public function test_generate_due_creates_a_draft_expense_and_advances_next_run_date_monthly(): void
    {
        $service = app(RecurringTemplateService::class);
        $template = $service->create([
            'code' => 'RENT-002',
            'name' => ['en' => 'Office Rent', 'ar' => 'إيجار المكتب'],
            'frequency' => 'monthly',
            'interval_count' => 1,
            'start_date' => '2026-01-05',
            'template_payload' => $this->templatePayload(),
        ], $this->user->id);

        $result = $service->generateDue(Carbon::parse('2026-01-05'));

        $this->assertSame(1, $result['generated']);
        $this->assertSame(0, $result['paused']);

        $template = $template->fresh();
        $this->assertSame(1, $template->generated_count);
        $this->assertSame('2026-01-05', $template->last_generated_date->format('Y-m-d'));
        $this->assertSame('2026-02-05', $template->next_run_date->format('Y-m-d'));

        $expense = Expense::query()->where('recurring_template_id', $template->id)->sole();
        $this->assertSame('draft', $expense->status);
        $this->assertSame('2026-01-05', $expense->expense_date->format('Y-m-d'));
        $this->assertSame(500_000, (int) $expense->total_minor);
        $this->assertNull($expense->journal_entry_id, 'Recurring generation must never auto-post.');
    }

    public function test_generate_due_is_idempotent_within_the_same_day(): void
    {
        $service = app(RecurringTemplateService::class);
        $service->create([
            'code' => 'RENT-003',
            'name' => ['en' => 'Office Rent', 'ar' => 'إيجار المكتب'],
            'frequency' => 'monthly',
            'interval_count' => 1,
            'start_date' => '2026-01-05',
            'template_payload' => $this->templatePayload(),
        ], $this->user->id);

        $service->generateDue(Carbon::parse('2026-01-05'));
        $result = $service->generateDue(Carbon::parse('2026-01-05'));

        $this->assertSame(0, $result['generated'], 'A template must not generate twice for the same due date in one run.');
        $this->assertSame(1, Expense::query()->count());
    }

    public function test_quarterly_and_yearly_frequencies_advance_correctly(): void
    {
        $service = app(RecurringTemplateService::class);

        $quarterly = $service->create([
            'code' => 'INSURANCE-Q',
            'name' => ['en' => 'Insurance', 'ar' => 'تأمين'],
            'frequency' => 'quarterly',
            'interval_count' => 1,
            'start_date' => '2026-01-10',
            'template_payload' => $this->templatePayload(),
        ], $this->user->id);

        $yearly = $service->create([
            'code' => 'LICENSE-Y',
            'name' => ['en' => 'Software License', 'ar' => 'ترخيص برمجي'],
            'frequency' => 'yearly',
            'interval_count' => 1,
            'start_date' => '2026-01-10',
            'template_payload' => $this->templatePayload(),
        ], $this->user->id);

        $service->generateDue(Carbon::parse('2026-01-10'));

        $this->assertSame('2026-04-10', $quarterly->fresh()->next_run_date->format('Y-m-d'));
        $this->assertSame('2027-01-10', $yearly->fresh()->next_run_date->format('Y-m-d'));
    }

    public function test_template_completes_automatically_once_next_occurrence_passes_end_date(): void
    {
        $service = app(RecurringTemplateService::class);
        $template = $service->create([
            'code' => 'SHORT-001',
            'name' => ['en' => 'Short Subscription', 'ar' => 'اشتراك قصير'],
            'frequency' => 'monthly',
            'interval_count' => 1,
            'start_date' => '2026-01-05',
            'end_date' => '2026-01-31',
            'template_payload' => $this->templatePayload(),
        ], $this->user->id);

        $service->generateDue(Carbon::parse('2026-01-05'));

        $template = $template->fresh();
        $this->assertSame('completed', $template->status);
        $this->assertSame(1, $template->generated_count);
    }

    public function test_a_failing_occurrence_pauses_the_template_with_the_error_recorded(): void
    {
        $service = app(RecurringTemplateService::class);
        $payload = $this->templatePayload();
        $template = $service->create([
            'code' => 'BROKEN-001',
            'name' => ['en' => 'Broken Template', 'ar' => 'قالب معطل'],
            'frequency' => 'monthly',
            'interval_count' => 1,
            'start_date' => '2026-01-05',
            'template_payload' => $payload,
        ], $this->user->id);

        // Deactivate the category after the template was created, so
        // generation fails deep inside ExpenseService validation.
        $this->category->update(['is_active' => false]);

        $result = $service->generateDue(Carbon::parse('2026-01-05'));

        $this->assertSame(0, $result['generated']);
        $this->assertSame(1, $result['paused']);

        $template = $template->fresh();
        $this->assertSame('paused', $template->status);
        $this->assertNotNull($template->last_error);
        $this->assertSame(0, $template->generated_count);
        $this->assertSame('2026-01-05', $template->next_run_date->format('Y-m-d'), 'A failed occurrence must not be skipped.');
    }

    public function test_resume_reactivates_a_paused_template(): void
    {
        $service = app(RecurringTemplateService::class);
        $template = $service->create([
            'code' => 'PAUSE-001',
            'name' => ['en' => 'Pausable', 'ar' => 'قابل للإيقاف'],
            'frequency' => 'monthly',
            'interval_count' => 1,
            'start_date' => '2026-01-05',
            'template_payload' => $this->templatePayload(),
        ], $this->user->id);

        $service->pause($template->id, $this->user->id);
        $this->assertSame('paused', $template->fresh()->status);

        $resumed = $service->resume($template->id, $this->user->id);
        $this->assertSame('active', $resumed->status);
        $this->assertNull($resumed->last_error);
    }

    public function test_template_with_generated_documents_cannot_be_deleted(): void
    {
        $service = app(RecurringTemplateService::class);
        $template = $service->create([
            'code' => 'NODELETE-001',
            'name' => ['en' => 'No Delete', 'ar' => 'غير قابل للحذف'],
            'frequency' => 'monthly',
            'interval_count' => 1,
            'start_date' => '2026-01-05',
            'template_payload' => $this->templatePayload(),
        ], $this->user->id);

        $service->generateDue(Carbon::parse('2026-01-05'));

        $this->expectException(ValidationException::class);
        $service->delete($template->id, $this->user->id);
    }

    public function test_recurring_generate_console_command_runs_successfully(): void
    {
        app(RecurringTemplateService::class)->create([
            'code' => 'CMD-001',
            'name' => ['en' => 'Command Test', 'ar' => 'اختبار الأمر'],
            'frequency' => 'monthly',
            'interval_count' => 1,
            'start_date' => '2026-01-05',
            'template_payload' => $this->templatePayload(),
        ], $this->user->id);

        $this->artisan('recurring:generate', ['--date' => '2026-01-05'])->assertSuccessful();

        $this->assertSame(1, Expense::query()->count());
    }

    public function test_recurring_template_routes_require_recurring_permissions(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('dashboard.view');
        $this->actingAs($viewer)->get('/recurring/templates')->assertForbidden();

        $this->actingAs($this->user)->get('/recurring/templates')->assertOk();

        $this->actingAs($this->user)->post('/recurring/templates', [
            'code' => 'ROUTE-001',
            'name' => ['en' => 'Route Template', 'ar' => 'قالب المسار'],
            'frequency' => 'monthly',
            'interval_count' => 1,
            'start_date' => '2026-01-05',
            'template_payload' => $this->templatePayload(),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('recurring_template', ['code' => 'ROUTE-001']);
    }

    public function test_forecast_service_returns_history_and_projection(): void
    {
        $forecast = app(ForecastService::class)->forecast(lookbackMonths: 3, monthsAhead: 2, salesGrowthPct: 10.0, expenseGrowthPct: 5.0);

        $this->assertCount(3, $forecast['history']);
        $this->assertCount(2, $forecast['projection']);
        $this->assertSame(10.0, $forecast['assumptions']['sales_growth_pct']);

        foreach ($forecast['projection'] as $month) {
            $this->assertSame($month['sales_minor'] - $month['expense_minor'], $month['profit_minor']);
            $this->assertSame($month['profit_minor'], $month['cash_flow_minor']);
        }
    }

    public function test_forecast_report_route_requires_view_financials_permission(): void
    {
        $limited = User::factory()->create();
        $limited->givePermissionTo('reports.view');
        $this->actingAs($limited)->get('/reports/forecast')->assertForbidden();

        $this->actingAs($this->user)->get('/reports/forecast')->assertOk();
    }
}
