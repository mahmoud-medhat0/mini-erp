<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_entry', function (Blueprint $table): void {
            $table->index(
                ['account_id', 'currency', 'entry_date', 'created_at'],
                'ledger_book_scope_order_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('ledger_entry', function (Blueprint $table): void {
            $table->dropIndex('ledger_book_scope_order_index');
        });
    }
};
