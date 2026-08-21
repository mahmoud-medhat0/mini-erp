<?php

namespace App\Support\Concurrency;

use Illuminate\Support\Facades\DB;

class OptimisticLock
{
    /**
     * @param  array<string, mixed>  $key
     * @param  array<string, mixed>  $values
     */
    public function update(string $table, array $key, int $expectedVersion, array $values): int
    {
        if (! in_array($table, ['company', 'branch', 'customer', 'supplier', 'cash_account', 'bank_account', 'customer_opening_balance', 'supplier_opening_balance', 'customer_receipt', 'supplier_payment', 'incoming_cheque', 'outgoing_cheque', 'bank_reconciliation', 'bank_reconciliation_line'], true)) {
            throw new \InvalidArgumentException("Optimistic locking is not enabled for [{$table}].");
        }

        $query = DB::table($table);

        foreach ($key as $column => $value) {
            $query->where($column, $value);
        }

        $affected = $query
            ->where('lock_version', $expectedVersion)
            ->update([
                ...$values,
                'lock_version' => DB::raw('lock_version + 1'),
            ]);

        if ($affected !== 1) {
            throw new ConcurrencyConflictException;
        }

        return $expectedVersion + 1;
    }
}
