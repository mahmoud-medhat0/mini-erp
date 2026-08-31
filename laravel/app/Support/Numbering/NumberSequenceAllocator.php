<?php

namespace App\Support\Numbering;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NumberSequenceAllocator
{
    public function nextValue(string $key): int
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => $this->nextValuePostgres($key),
            default => $this->nextValuePortable($key),
        };
    }

    private function nextValuePostgres(string $key): int
    {
        $row = DB::selectOne(
            <<<'SQL'
            INSERT INTO number_sequence (id, key, doc_type, prefix, include_year, padding, reset_policy, next_value)
            VALUES (?, ?, ?, '', true, 5, 'yearly', 1)
            ON CONFLICT (key)
            DO UPDATE SET next_value = number_sequence.next_value + 1
            RETURNING next_value
            SQL,
            [(string) Str::uuid(), $key, $key],
        );

        return (int) $row->next_value;
    }

    private function nextValuePortable(string $key): int
    {
        return DB::transaction(function () use ($key): int {
            try {
                DB::table('number_sequence')->insert([
                    'id' => (string) Str::uuid(),
                    'key' => $key,
                    'doc_type' => $key,
                    'prefix' => '',
                    'include_year' => true,
                    'padding' => 5,
                    'reset_policy' => 'yearly',
                    'next_value' => 1,
                ]);

                return 1;
            } catch (QueryException) {
                DB::table('number_sequence')
                    ->where('key', $key)
                    ->increment('next_value');

                return (int) DB::table('number_sequence')
                    ->where('key', $key)
                    ->value('next_value');
            }
        });
    }
}
