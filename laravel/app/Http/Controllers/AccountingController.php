<?php

namespace App\Http\Controllers;

use App\Application\Accounting\ExchangeRateService;
use App\Application\Accounting\GeneralLedgerService;
use App\Application\Accounting\JournalDraftService;
use App\Application\Accounting\OpeningBalanceService;
use App\Application\Accounting\PeriodService;
use App\Application\Accounting\PostingEngine;
use App\Application\Accounting\ReversalService;
use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\AccountGroup;
use App\Models\AccountType;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\OpeningBalance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AccountingController extends Controller
{
    public function __construct(
        private readonly JournalDraftService $draftService,
        private readonly PostingEngine $postingEngine,
        private readonly ReversalService $reversalService,
        private readonly PeriodService $periodService,
        private readonly GeneralLedgerService $glService,
        private readonly OpeningBalanceService $openingBalanceService,
        private readonly ExchangeRateService $exchangeRateService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'accounting.view');

        $activeFiscalYear = FiscalYear::query()->where('status', 'open')->orderBy('year', 'desc')->first();
        $recentJournals = JournalEntry::query()
            ->with(['period', 'createdBy'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        $accountsCount = Account::query()->count();
        $postedJournalsCount = JournalEntry::query()->where('status', 'posted')->count();
        $draftJournalsCount = JournalEntry::query()->where('status', 'draft')->count();

        return Inertia::render('Accounting/Index', [
            'activeFiscalYear' => $activeFiscalYear,
            'recentJournals' => $recentJournals,
            'counts' => [
                'accounts' => $accountsCount,
                'postedJournals' => $postedJournalsCount,
                'draftJournals' => $draftJournalsCount,
            ],
        ]);
    }

    public function coa(Request $request): Response
    {
        $this->authorizePermission($request, 'accounting.view');

        $groups = AccountGroup::query()
            ->with(['accountType', 'children', 'accounts'])
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        $allAccounts = Account::query()
            ->with(['accountType', 'group', 'currencyRef'])
            ->orderBy('code')
            ->get();

        $accountTypes = AccountType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        $currencies = Currency::query()->orderBy('code')->get();

        return Inertia::render('Accounting/ChartOfAccounts', [
            'groups' => $groups,
            'accounts' => $allAccounts,
            'accountTypes' => $accountTypes,
            'currencies' => $currencies,
        ]);
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.create');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:account_group,code'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'account_type_id' => ['required', 'uuid', 'exists:account_type,id'],
            'statement_section' => ['nullable', 'string', 'max:50'],
            'parent_id' => ['nullable', 'uuid', 'exists:account_group,id'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $accountType = AccountType::findOrFail($validated['account_type_id']);

        if (! empty($validated['parent_id'])) {
            $parentGroup = AccountGroup::findOrFail($validated['parent_id']);
            if ($parentGroup->account_type_id && $parentGroup->account_type_id !== $accountType->id) {
                return redirect()->back()->withErrors(['account_type_id' => __('Parent group must share the same account type.')]);
            }
        }

        AccountGroup::create([
            'id' => (string) Str::uuid(),
            'code' => $validated['code'],
            'name' => [
                'en' => $validated['name_en'],
                'ar' => $validated['name_ar'],
            ],
            'account_type_id' => $accountType->id,
            'type' => $accountType->category,
            'statement_section' => $validated['statement_section'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return redirect()->back()->with('success', __('Account Group created successfully.'));
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.create');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:account,code'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'account_type_id' => ['required', 'uuid', 'exists:account_type,id'],
            'nature' => ['nullable', 'string', 'in:debit,credit'],
            'account_group_id' => ['nullable', 'uuid', 'exists:account_group,id'],
            'parent_id' => ['nullable', 'uuid', 'exists:account,id'],
            'currency' => ['nullable', 'string', 'size:3', 'exists:currency,code'],
            'is_control' => ['nullable', 'boolean'],
            'allow_manual_posting' => ['nullable', 'boolean'],
        ]);

        $accountType = AccountType::findOrFail($validated['account_type_id']);

        if (! empty($validated['account_group_id'])) {
            $group = AccountGroup::findOrFail($validated['account_group_id']);
            if ($group->account_type_id && $group->account_type_id !== $accountType->id) {
                return redirect()->back()->withErrors(['account_group_id' => __('Selected account group does not match the account type.')]);
            }
        }

        Account::create([
            'id' => (string) Str::uuid(),
            'code' => $validated['code'],
            'name' => [
                'en' => $validated['name_en'],
                'ar' => $validated['name_ar'],
            ],
            'account_type_id' => $accountType->id,
            'type' => $accountType->category,
            'nature' => $validated['nature'] ?? $accountType->normal_balance,
            'account_group_id' => $validated['account_group_id'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
            'currency' => $validated['currency'] ?? 'EGP',
            'is_control' => $validated['is_control'] ?? false,
            'allow_manual_posting' => $validated['allow_manual_posting'] ?? true,
        ]);

        return redirect()->back()->with('success', __('Account created successfully.'));
    }

    public function journal(Request $request): Response
    {
        $this->authorizePermission($request, 'accounting.view');

        $journals = $this->glService->getGeneralJournal($request->all());
        $periods = FinancialPeriod::query()->with('fiscalYear')->orderBy('start_date', 'desc')->get();

        return Inertia::render('Accounting/GeneralJournal', [
            'journals' => $journals,
            'periods' => $periods,
            'filters' => $request->only(['status', 'period_id', 'start_date', 'end_date']),
        ]);
    }

    public function createJournal(Request $request): Response
    {
        $this->authorizePermission($request, 'accounting.create');

        $periods = FinancialPeriod::query()->with('fiscalYear')->whereIn('status', ['open', 'reopened'])->orderBy('start_date', 'desc')->get();
        $accounts = Account::query()->where('is_active', true)->orderBy('code')->get();
        $currencies = Currency::query()->orderBy('code')->get();

        return Inertia::render('Accounting/JournalForm', [
            'periods' => $periods,
            'accounts' => $accounts,
            'currencies' => $currencies,
        ]);
    }

    public function storeJournal(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.create');

        $validated = $request->validate([
            'entry_date' => ['required', 'date'],
            'financial_period_id' => ['required', 'uuid', 'exists:financial_period,id'],
            'description' => ['nullable', 'string'],
            'reference' => ['nullable', 'string', 'max:100'],
            'currency' => ['nullable', 'string', 'size:3', 'exists:currency,code'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'uuid', 'exists:account,id'],
            'lines.*.debit_minor' => ['required', 'integer', 'min:0'],
            'lines.*.credit_minor' => ['required', 'integer', 'min:0'],
            'lines.*.memo' => ['nullable', 'string', 'max:255'],
        ]);

        $entry = $this->draftService->createDraft($validated, $validated['lines'], $request->user()->id);

        return redirect()->route('accounting.journal.show', $entry->id)->with('success', __('Journal draft created successfully.'));
    }

    public function showJournal(Request $request, JournalEntry $journalEntry): Response
    {
        $this->authorizePermission($request, 'accounting.view');

        $journalEntry->load(['lines.account', 'period.fiscalYear', 'currencyRef', 'createdBy', 'postedBy', 'reversesEntry', 'reversalEntry']);
        $openPeriods = FinancialPeriod::query()->with('fiscalYear')->whereIn('status', ['open', 'reopened'])->orderBy('start_date', 'desc')->get();

        return Inertia::render('Accounting/JournalDetail', [
            'journal' => $journalEntry,
            'openPeriods' => $openPeriods,
        ]);
    }

    public function submitJournal(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.submit');

        $this->draftService->submit($journalEntry, $request->user()->id);

        return redirect()->back()->with('success', __('Journal submitted successfully.'));
    }

    public function approveJournal(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.approve');

        $this->draftService->approve($journalEntry, $request->user()->id);

        return redirect()->back()->with('success', __('Journal approved successfully.'));
    }

    public function postJournal(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.post');

        $allowControl = $request->user()->can('accounting.override_control');
        $this->postingEngine->post($journalEntry, $request->user()->id, allowControlAccounts: $allowControl);

        return redirect()->back()->with('success', __('Journal posted to ledger successfully.'));
    }

    public function reverseJournal(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.reverse');

        $validated = $request->validate([
            'reversal_period_id' => ['required', 'uuid', 'exists:financial_period,id'],
        ]);

        $reversal = $this->reversalService->reverse($journalEntry, $validated['reversal_period_id'], $request->user()->id);

        return redirect()->route('accounting.journal.show', $reversal->id)->with('success', __('Journal reversed successfully.'));
    }

    public function ledger(Request $request): Response
    {
        $this->authorizePermission($request, 'accounting.view');

        $ledgerData = $this->glService->getGeneralLedger($request->all());
        $accounts = Account::query()->orderBy('code')->get();
        $periods = FinancialPeriod::query()->with('fiscalYear')->orderBy('start_date', 'desc')->get();

        return Inertia::render('Accounting/GeneralLedger', [
            'ledger' => $ledgerData['entries'],
            'totals' => [
                'debit' => $ledgerData['total_debit'],
                'credit' => $ledgerData['total_credit'],
                'net' => $ledgerData['net_movement'],
            ],
            'accounts' => $accounts,
            'periods' => $periods,
            'filters' => $request->only(['account_id', 'period_id', 'start_date', 'end_date']),
        ]);
    }

    public function trialBalance(Request $request): Response
    {
        $this->authorizePermission($request, 'accounting.view');

        $tbData = $this->glService->getTrialBalance($request->all());
        $periods = FinancialPeriod::query()->with('fiscalYear')->orderBy('start_date', 'desc')->get();

        return Inertia::render('Accounting/TrialBalance', [
            'rows' => $tbData['rows'],
            'totals' => [
                'debit' => $tbData['total_debit'],
                'credit' => $tbData['total_credit'],
                'is_balanced' => $tbData['is_balanced'],
            ],
            'periods' => $periods,
            'filters' => $request->only(['period_id', 'start_date', 'end_date', 'include_zero']),
        ]);
    }

    public function periods(Request $request): Response
    {
        $this->authorizePermission($request, 'settings.configure');

        $fiscalYears = FiscalYear::query()->with('periods')->orderBy('year', 'desc')->get();

        return Inertia::render('Accounting/Periods', [
            'fiscalYears' => $fiscalYears,
        ]);
    }

    public function storeFiscalYear(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'settings.configure');

        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100', 'unique:fiscal_year,year'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ], [
            'year.unique' => __('Fiscal year :year already exists.', ['year' => $request->input('year')]),
            'year.required' => __('Fiscal year is required.'),
            'start_date.required' => __('Start date is required.'),
            'end_date.required' => __('End date is required.'),
            'end_date.after' => __('End date must be after start date.'),
        ]);

        try {
            $this->periodService->createFiscalYear($validated['year'], $validated['start_date'], $validated['end_date']);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['year' => __('Fiscal year :year already exists.', ['year' => $validated['year']])]);
        }

        return redirect()->back()->with('success', __('Fiscal Year created with 12 monthly periods.'));
    }

    public function closePeriod(Request $request, FinancialPeriod $period): RedirectResponse
    {
        $this->authorizePermission($request, 'close_period');

        $this->periodService->closePeriod($period, $request->user()->id);

        return redirect()->back()->with('success', __('Financial period closed successfully.'));
    }

    public function reopenPeriod(Request $request, FinancialPeriod $period): RedirectResponse
    {
        $this->authorizePermission($request, 'reopen_period');

        $this->periodService->reopenPeriod($period, $request->user()->id);

        return redirect()->back()->with('success', __('Financial period reopened successfully.'));
    }

    public function openingBalances(Request $request): Response
    {
        $this->authorizePermission($request, 'accounting.view');

        $fiscalYears = FiscalYear::query()->orderBy('year', 'desc')->get();
        $selectedYearId = $request->query('fiscal_year_id') ?? $fiscalYears->first()?->id;

        $accounts = Account::query()->with('group')->orderBy('code')->get();
        $existingBalances = [];

        if ($selectedYearId) {
            $existingBalances = OpeningBalance::query()
                ->where('fiscal_year_id', $selectedYearId)
                ->get()
                ->keyBy('account_id')
                ->toArray();
        }

        return Inertia::render('Accounting/OpeningBalances', [
            'fiscalYears' => $fiscalYears,
            'selectedYearId' => $selectedYearId,
            'accounts' => $accounts,
            'existingBalances' => $existingBalances,
        ]);
    }

    public function saveOpeningBalances(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.create');

        $validated = $request->validate([
            'fiscal_year_id' => ['required', 'uuid', 'exists:fiscal_year,id'],
            'balances' => ['required', 'array'],
            'balances.*.account_id' => ['required', 'uuid', 'exists:account,id'],
            'balances.*.debit_minor' => ['required', 'integer', 'min:0'],
            'balances.*.credit_minor' => ['required', 'integer', 'min:0'],
        ]);

        $this->openingBalanceService->saveDraft($validated['fiscal_year_id'], $validated['balances'], $request->user()->id);

        return redirect()->back()->with('success', __('Opening balance drafts saved successfully.'));
    }

    public function postOpeningBalances(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.post');

        $validated = $request->validate([
            'fiscal_year_id' => ['required', 'uuid', 'exists:fiscal_year,id'],
        ]);

        $journal = $this->openingBalanceService->postOpeningBalances($validated['fiscal_year_id'], $request->user()->id);

        return redirect()->route('accounting.journal.show', $journal->id)->with('success', __('Opening balances posted to ledger successfully.'));
    }

    public function fxRates(Request $request): Response
    {
        $this->authorizePermission($request, 'accounting.view');

        $rates = ExchangeRate::query()->with('currencyRef')->orderBy('date', 'desc')->paginate(30);
        $currencies = Currency::query()->orderBy('code')->get();

        return Inertia::render('Accounting/ExchangeRates', [
            'rates' => $rates,
            'currencies' => $currencies,
        ]);
    }

    public function storeFxRate(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.create');

        $validated = $request->validate([
            'currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'date' => ['required', 'date'],
            'rate' => ['required', 'numeric', 'gt:0'],
        ]);

        $this->exchangeRateService->setRate($validated['currency'], $validated['date'], $validated['rate'], $request->user()->id);

        return redirect()->back()->with('success', __('Exchange rate saved successfully.'));
    }

    public function currencies(Request $request): Response
    {
        $this->authorizePermission($request, 'accounting.view');

        $currencies = Currency::query()
            ->with([
                'accounts' => fn ($q) => $q->select('id', 'code', 'name', 'type', 'nature', 'currency')->orderBy('code'),
                'exchangeRates' => fn ($q) => $q->select('id', 'currency', 'date', 'rate_e6')->orderBy('date', 'desc'),
            ])
            ->withCount(['accounts', 'journalEntries', 'exchangeRates'])
            ->orderBy('code')
            ->get();

        return Inertia::render('Accounting/Currencies', [
            'currencies' => $currencies,
        ]);
    }

    public function storeCurrency(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.create');

        $validated = $request->validate([
            'code' => ['required', 'string', 'size:3', 'unique:currency,code'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'symbol' => ['required', 'string', 'max:10'],
            'exponent' => ['required', 'integer', 'min:0', 'max:4'],
        ], [
            'code.unique' => __('Currency code :code already exists.', ['code' => strtoupper((string) $request->input('code'))]),
        ]);

        $code = strtoupper($validated['code']);

        Currency::create([
            'code' => $code,
            'name' => [
                'en' => $validated['name_en'],
                'ar' => $validated['name_ar'],
            ],
            'symbol' => $validated['symbol'],
            'exponent' => (int) $validated['exponent'],
        ]);

        return redirect()->back()->with('success', __('Currency created successfully.'));
    }

    public function updateCurrency(Request $request, Currency $currency): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.create');

        $validated = $request->validate([
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'symbol' => ['required', 'string', 'max:10'],
            'exponent' => ['required', 'integer', 'min:0', 'max:4'],
        ]);

        $currency->setTranslation('name', 'en', $validated['name_en']);
        $currency->setTranslation('name', 'ar', $validated['name_ar']);
        $currency->symbol = $validated['symbol'];
        $currency->exponent = (int) $validated['exponent'];
        $currency->save();

        return redirect()->back()->with('success', __('Currency updated successfully.'));
    }

    public function destroyCurrency(Request $request, Currency $currency): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.create');

        $linkedAccountsCount = $currency->accounts()->count();
        $linkedJournalLinesCount = $currency->journalLines()->count();
        $linkedFxRatesCount = $currency->exchangeRates()->count();

        if ($linkedAccountsCount > 0 || $linkedJournalLinesCount > 0 || $linkedFxRatesCount > 0) {
            return redirect()->back()->with('error', __('Cannot delete currency because it has linked accounts, journals, or exchange rates.'));
        }

        $currency->delete();

        return redirect()->back()->with('success', __('Currency deleted successfully.'));
    }

    public function accountTypes(Request $request): Response
    {
        $this->authorizePermission($request, 'accounting.account_types');

        $accountTypes = AccountType::query()
            ->with(['accountCategory', 'groups', 'accounts'])
            ->withCount(['groups', 'accounts'])
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        $accountCategories = AccountCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        return Inertia::render('Accounting/AccountTypes', [
            'accountTypes' => $accountTypes,
            'accountCategories' => $accountCategories,
        ]);
    }

    public function storeAccountType(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.account_types');

        $validated = $request->validate([
            'account_category_id' => ['required', 'uuid', 'exists:account_category,id'],
            'code' => ['required', 'string', 'max:50', 'unique:account_type,code'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'normal_balance' => ['nullable', 'string', Rule::in(['debit', 'credit'])],
            'statement_type' => ['nullable', 'string', Rule::in(['balance_sheet', 'income_statement'])],
            'is_contra' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $accountCategory = AccountCategory::findOrFail($validated['account_category_id']);

        AccountType::create([
            'id' => (string) Str::uuid(),
            'account_category_id' => $accountCategory->id,
            'code' => strtoupper($validated['code']),
            'name' => [
                'en' => $validated['name_en'],
                'ar' => $validated['name_ar'],
            ],
            'normal_balance' => $validated['normal_balance'] ?? $accountCategory->normal_balance,
            'statement_type' => $validated['statement_type'] ?? $accountCategory->statement_type,
            'category' => strtolower($accountCategory->code),
            'is_contra' => $validated['is_contra'] ?? $accountCategory->is_contra,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_system' => false,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->back()->with('success', __('Account Type created successfully.'));
    }

    public function updateAccountType(Request $request, AccountType $accountType): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.account_types');

        $validated = $request->validate([
            'account_category_id' => ['required', 'uuid', 'exists:account_category,id'],
            'code' => ['required', 'string', 'max:50', Rule::unique('account_type', 'code')->ignore($accountType->id)],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'normal_balance' => ['nullable', 'string', Rule::in(['debit', 'credit'])],
            'statement_type' => ['nullable', 'string', Rule::in(['balance_sheet', 'income_statement'])],
            'is_contra' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $accountCategory = AccountCategory::findOrFail($validated['account_category_id']);

        $accountType->update([
            'account_category_id' => $accountCategory->id,
            'code' => strtoupper($validated['code']),
            'name' => [
                'en' => $validated['name_en'],
                'ar' => $validated['name_ar'],
            ],
            'normal_balance' => $validated['normal_balance'] ?? $accountCategory->normal_balance,
            'statement_type' => $validated['statement_type'] ?? $accountCategory->statement_type,
            'category' => strtolower($accountCategory->code),
            'is_contra' => $validated['is_contra'] ?? $accountCategory->is_contra,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->back()->with('success', __('Account Type updated successfully.'));
    }

    public function destroyAccountType(Request $request, AccountType $accountType): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.account_types');

        if ($accountType->is_system) {
            return redirect()->back()->withErrors(['account_type' => __('System account types cannot be deleted.')]);
        }

        if ($accountType->groups()->exists() || $accountType->accounts()->exists()) {
            return redirect()->back()->withErrors(['account_type' => __('Cannot delete account type in use by account groups or accounts.')]);
        }

        $accountType->delete();

        return redirect()->back()->with('success', __('Account Type deleted successfully.'));
    }

    public function accountCategories(Request $request): Response
    {
        $this->authorizePermission($request, 'accounting.account_categories');

        $accountCategories = AccountCategory::query()
            ->with(['accountTypes'])
            ->withCount('accountTypes')
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        return Inertia::render('Accounting/AccountCategories', [
            'accountCategories' => $accountCategories,
        ]);
    }

    public function storeAccountCategory(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.account_categories');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:account_category,code'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'normal_balance' => ['required', 'string', Rule::in(['debit', 'credit'])],
            'statement_type' => ['required', 'string', Rule::in(['balance_sheet', 'income_statement'])],
            'is_contra' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        AccountCategory::create([
            'id' => (string) Str::uuid(),
            'code' => strtoupper($validated['code']),
            'name' => [
                'en' => $validated['name_en'],
                'ar' => $validated['name_ar'],
            ],
            'normal_balance' => $validated['normal_balance'],
            'statement_type' => $validated['statement_type'],
            'is_contra' => $validated['is_contra'] ?? false,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_system' => false,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->back()->with('success', __('Account Category created successfully.'));
    }

    public function updateAccountCategory(Request $request, AccountCategory $accountCategory): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.account_categories');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('account_category', 'code')->ignore($accountCategory->id)],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'normal_balance' => ['required', 'string', Rule::in(['debit', 'credit'])],
            'statement_type' => ['required', 'string', Rule::in(['balance_sheet', 'income_statement'])],
            'is_contra' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $accountCategory->update([
            'code' => strtoupper($validated['code']),
            'name' => [
                'en' => $validated['name_en'],
                'ar' => $validated['name_ar'],
            ],
            'normal_balance' => $validated['normal_balance'],
            'statement_type' => $validated['statement_type'],
            'is_contra' => $validated['is_contra'] ?? false,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->back()->with('success', __('Account Category updated successfully.'));
    }

    public function destroyAccountCategory(Request $request, AccountCategory $accountCategory): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.account_categories');

        if ($accountCategory->is_system) {
            return redirect()->back()->withErrors(['account_category' => __('System account categories cannot be deleted.')]);
        }

        if ($accountCategory->accountTypes()->exists()) {
            return redirect()->back()->withErrors(['account_category' => __('Cannot delete account category in use by account types.')]);
        }

        $accountCategory->delete();

        return redirect()->back()->with('success', __('Account Category deleted successfully.'));
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        if ($user->can('settings.configure') || $user->can($permission)) {
            return;
        }

        abort(403, __('You do not have permission to perform this accounting action.'));
    }
}
