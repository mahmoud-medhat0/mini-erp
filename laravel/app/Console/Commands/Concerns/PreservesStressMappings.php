<?php

namespace App\Console\Commands\Concerns;

use App\Models\AccountingAccountMapping;

trait PreservesStressMappings
{
    /**
     * @param  list<string>  $keys
     * @return array<string, array<string, mixed>>
     */
    protected function captureGlobalStressMappings(array $keys): array
    {
        return AccountingAccountMapping::query()
            ->whereNull('branch_id')
            ->whereIn('key', $keys)
            ->get()
            ->mapWithKeys(fn (AccountingAccountMapping $mapping): array => [
                $mapping->key => [
                    'account_id' => $mapping->account_id,
                    'description' => $mapping->description,
                    'is_system' => $mapping->is_system,
                    'created_by' => $mapping->created_by,
                    'updated_by' => $mapping->updated_by,
                ],
            ])
            ->all();
    }

    /**
     * @param  list<string>  $keys
     * @param  array<string, array<string, mixed>>  $previousMappings
     */
    protected function restoreGlobalStressMappings(array $keys, array $previousMappings): void
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $previousMappings)) {
                AccountingAccountMapping::query()
                    ->where('key', $key)
                    ->whereNull('branch_id')
                    ->delete();

                continue;
            }

            AccountingAccountMapping::query()->updateOrCreate(
                ['key' => $key, 'branch_id' => null],
                $previousMappings[$key],
            );
        }
    }
}
