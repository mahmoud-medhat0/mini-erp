<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incoming_cheque', function (Blueprint $table): void {
            if (! Schema::hasColumn('incoming_cheque', 'due_date')) {
                $table->date('due_date')->nullable()->after('drawer_bank_name');
                $table->index('due_date');
            }
        });

        Schema::table('outgoing_cheque', function (Blueprint $table): void {
            if (! Schema::hasColumn('outgoing_cheque', 'due_date')) {
                $table->date('due_date')->nullable()->after('payee_name');
                $table->index('due_date');
            }
        });

        $this->backfillIncomingDueDates();
        $this->backfillOutgoingDueDates();
    }

    public function down(): void
    {
        Schema::table('outgoing_cheque', function (Blueprint $table): void {
            if (Schema::hasColumn('outgoing_cheque', 'due_date')) {
                $table->dropIndex(['due_date']);
                $table->dropColumn('due_date');
            }
        });

        Schema::table('incoming_cheque', function (Blueprint $table): void {
            if (Schema::hasColumn('incoming_cheque', 'due_date')) {
                $table->dropIndex(['due_date']);
                $table->dropColumn('due_date');
            }
        });
    }

    private function backfillIncomingDueDates(): void
    {
        DB::table('incoming_cheque')
            ->select(['id', 'received_date', 'created_at'])
            ->whereNull('due_date')
            ->orderBy('id')
            ->get()
            ->each(function ($row): void {
                DB::table('incoming_cheque')
                    ->where('id', $row->id)
                    ->update([
                        'due_date' => $this->dateValue($row->received_date ?? null, $row->created_at ?? null),
                    ]);
            });
    }

    private function backfillOutgoingDueDates(): void
    {
        DB::table('outgoing_cheque')
            ->select(['id', 'issued_date', 'created_at'])
            ->whereNull('due_date')
            ->orderBy('id')
            ->get()
            ->each(function ($row): void {
                DB::table('outgoing_cheque')
                    ->where('id', $row->id)
                    ->update([
                        'due_date' => $this->dateValue($row->issued_date ?? null, $row->created_at ?? null),
                    ]);
            });
    }

    private function dateValue(mixed $primaryDate, mixed $fallbackDate): string
    {
        $date = $primaryDate ?: $fallbackDate ?: now()->toDateString();

        return substr((string) $date, 0, 10);
    }
};
