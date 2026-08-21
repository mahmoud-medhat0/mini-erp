<?php

namespace App\Support\Numbering;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NumberSequenceAllocator
{
    public function nextValue(string $companyId, string $key): int
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => $this->nextValuePostgres($companyId, $key),
            default => $this->nextValuePortable($companyId, $key),
        };
    }

    private function nextValuePostgres(string $companyId, string $key): int
    {
        $row = DB::selectOne(
            <<<'SQL'
            INSERT INTO number_sequence (id, company_id, key, doc_type, prefix, include_year, include_branch, padding, reset_policy, next_value)
            VALUES (?, ?, ?, ?, '', true, false, 5, 'yearly', 1)
            ON CONFLICT (company_id, key)
            DO UPDATE SET next_value = number_sequence.next_value + 1
            RETURNING next_value
            SQL,
            [(string) Str::uuid(), $companyId, $key, $key],
        );

        return (int) $row->next_value;
    }

    private function nextValuePortable(string $companyId, string $key): int
    {
        return DB::transaction(function () use ($companyId, $key): int {
            try {
                DB::table('number_sequence')->insert([
                    'id' => (string) Str::uuid(),
                    'company_id' => $companyId,
                    'key' => $key,
                    'doc_type' => $key,
                    'prefix' => '',
                    'include_year' => true,
                    'include_branch' => false,
                    'padding' => 5,
                    'reset_policy' => 'yearly',
                    'next_value' => 1,
                ]);

                return 1;
            } catch (QueryException) {
                DB::table('number_sequence')
                    ->where('company_id', $companyId)
                    ->where('key', $key)
                    ->increment('next_value');

                return (int) DB::table('number_sequence')
                    ->where('company_id', $companyId)
                    ->where('key', $key)
                    ->value('next_value');
            }
        });
    }
}
