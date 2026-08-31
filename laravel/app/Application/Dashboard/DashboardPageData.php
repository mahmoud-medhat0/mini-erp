<?php

namespace App\Application\Dashboard;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\FinancialPeriod;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Models\Supplier;
use App\Models\User;

class DashboardPageData
{
    /**
     * @return array<string, mixed>
     */
    public function forUser(int|string $userId): array
    {
        $user = User::query()->findOrFail($userId);
        $counts = [];
        $health = [];

        if ($this->canAny($user, ['accounting.view', 'settings.configure'])) {
            $counts['accounts'] = Account::query()->count();
            $counts['postedJournals'] = JournalEntry::query()->where('status', 'posted')->count();
            $counts['ledgerEntries'] = LedgerEntry::query()->count();

            $latestPostingAt = JournalEntry::query()
                ->where('status', 'posted')
                ->whereNotNull('posted_at')
                ->latest('posted_at')
                ->value('posted_at');
            $ledgerTotals = LedgerEntry::query()
                ->selectRaw('COALESCE(SUM(debit_minor), 0) AS debit_total, COALESCE(SUM(credit_minor), 0) AS credit_total')
                ->first();

            $health = array_merge($health, [
                'ledgerBalanced' => (int) $ledgerTotals?->debit_total === (int) $ledgerTotals?->credit_total,
                'openPeriods' => FinancialPeriod::query()->openForPosting()->count(),
                'pendingJournals' => JournalEntry::query()->whereIn('status', ['draft', 'submitted', 'approved'])->count(),
                'latestPostingAt' => $latestPostingAt === null ? null : (string) $latestPostingAt,
            ]);
        }

        if ($this->canAny($user, ['accounting.view', 'accounting.currencies', 'manage_currencies', 'settings.configure'])) {
            $counts['currencies'] = Currency::query()->count();
        }

        if ($user->can('customers.view')) {
            $counts['customers'] = Customer::query()->count();
        }

        if ($user->can('suppliers.view')) {
            $counts['suppliers'] = Supplier::query()->count();
        }

        if ($this->canAny($user, ['settings.branches', 'settings.configure'])) {
            $health['activeBranches'] = Branch::query()->where('is_active', true)->count();
        }

        if ($this->canAny($user, ['settings.company', 'settings.configure'])) {
            $company = Company::query()->first();
            $health['companyName'] = $company === null ? null : (string) $company->name;
            $health['baseCurrency'] = $company?->base_currency;
        }

        return [
            'counts' => $counts,
            'health' => $health,
        ];
    }

    /**
     * @param  list<string>  $permissions
     */
    private function canAny(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
