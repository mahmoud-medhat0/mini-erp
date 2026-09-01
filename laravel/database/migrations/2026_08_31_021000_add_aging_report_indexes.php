<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receivable_entry', function (Blueprint $table): void {
            $table->index(['currency', 'entry_date', 'customer_id'], 'receivable_aging_report_index');
        });
        Schema::table('payable_entry', function (Blueprint $table): void {
            $table->index(['currency', 'entry_date', 'supplier_id'], 'payable_aging_report_index');
        });
        Schema::table('receivable_allocation', function (Blueprint $table): void {
            $table->index(['receivable_entry_id', 'allocated_at', 'reversed_at'], 'receivable_allocation_aging_index');
        });
        Schema::table('payable_allocation', function (Blueprint $table): void {
            $table->index(['payable_entry_id', 'allocated_at', 'reversed_at'], 'payable_allocation_aging_index');
        });
        Schema::table('receivable_entry_settlement', function (Blueprint $table): void {
            $table->index(['target_receivable_entry_id', 'settled_at', 'reversed_at'], 'receivable_settlement_aging_index');
        });
        Schema::table('payable_entry_settlement', function (Blueprint $table): void {
            $table->index(['target_payable_entry_id', 'settled_at', 'reversed_at'], 'payable_settlement_aging_index');
        });
    }

    public function down(): void
    {
        Schema::table('payable_entry_settlement', function (Blueprint $table): void {
            $table->dropIndex('payable_settlement_aging_index');
        });
        Schema::table('receivable_entry_settlement', function (Blueprint $table): void {
            $table->dropIndex('receivable_settlement_aging_index');
        });
        Schema::table('payable_allocation', function (Blueprint $table): void {
            $table->dropIndex('payable_allocation_aging_index');
        });
        Schema::table('receivable_allocation', function (Blueprint $table): void {
            $table->dropIndex('receivable_allocation_aging_index');
        });
        Schema::table('payable_entry', function (Blueprint $table): void {
            $table->dropIndex('payable_aging_report_index');
        });
        Schema::table('receivable_entry', function (Blueprint $table): void {
            $table->dropIndex('receivable_aging_report_index');
        });
    }
};
