<?php

namespace App\Application\Reports;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\CostCenter;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class ReportPageOptions
{
    /**
     * @param  array<int, string>  $columns
     * @return EloquentCollection<int, Project>
     */
    public function projects(array $columns = ['*']): EloquentCollection
    {
        return Project::query()
            ->orderBy('code')
            ->get($columns);
    }

    /**
     * @param  array<int, string>  $columns
     * @return EloquentCollection<int, CostCenter>
     */
    public function costCenters(array $columns = ['*']): EloquentCollection
    {
        return CostCenter::query()
            ->orderBy('code')
            ->get($columns);
    }

    /**
     * @param  array<int, string>  $columns
     * @return EloquentCollection<int, Account>
     */
    public function accounts(array $columns = ['*']): EloquentCollection
    {
        return Account::query()
            ->orderBy('code')
            ->get($columns);
    }

    /**
     * @param  array<int, string>  $columns
     * @return EloquentCollection<int, Customer>
     */
    public function activeCustomers(string $sortBy = 'code', array $columns = ['*']): EloquentCollection
    {
        return Customer::query()
            ->where('status', 'active')
            ->orderBy($sortBy)
            ->get($columns);
    }

    /**
     * @param  array<int, string>  $columns
     * @return EloquentCollection<int, Supplier>
     */
    public function activeSuppliers(string $sortBy = 'code', array $columns = ['*']): EloquentCollection
    {
        return Supplier::query()
            ->where('status', 'active')
            ->orderBy($sortBy)
            ->get($columns);
    }

    /**
     * @param  array<int, string>  $columns
     * @return EloquentCollection<int, Product>
     */
    public function activeProducts(string $sortBy = 'code', array $columns = ['*']): EloquentCollection
    {
        return Product::query()
            ->where('status', 'active')
            ->orderBy($sortBy)
            ->get($columns);
    }

    /**
     * @param  array<int, string>  $columns
     * @return EloquentCollection<int, Currency>
     */
    public function currencies(array $columns = ['*']): EloquentCollection
    {
        return Currency::query()
            ->orderBy('code')
            ->get($columns);
    }

    /**
     * @param  array<int, string>  $columns
     * @return EloquentCollection<int, BankAccount>
     */
    public function activeBankAccounts(array $columns = ['*']): EloquentCollection
    {
        return BankAccount::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get($columns);
    }

    /**
     * @param  array<int, string>  $columns
     * @return EloquentCollection<int, CashAccount>
     */
    public function activeCashAccounts(array $columns = ['*']): EloquentCollection
    {
        return CashAccount::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get($columns);
    }

    /**
     * @param  array<int, string>  $columns
     * @return EloquentCollection<int, Warehouse>
     */
    public function activeWarehouses(array $columns = ['*'], bool $defaultFirst = false, bool $withBranch = false): EloquentCollection
    {
        $query = Warehouse::query()
            ->where('is_active', true)
            ->when($withBranch, fn ($query) => $query->with('branch'))
            ->when($defaultFirst, fn ($query) => $query->orderByDesc('is_default'))
            ->orderBy('code');

        return $query->get($columns);
    }

    /**
     * Branch is an operational/reporting dimension here, not a tenant or security scope.
     *
     * @param  array<int, string>  $columns
     * @return EloquentCollection<int, Branch>
     */
    public function branches(array $columns = ['*']): EloquentCollection
    {
        return Branch::query()
            ->orderBy('code')
            ->get($columns);
    }
}
