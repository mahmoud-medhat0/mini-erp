<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('account_type', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->jsonb('name');
            $table->string('normal_balance'); // debit, credit
            $table->string('statement_type'); // balance_sheet, income_statement
            $table->string('category'); // asset, liability, equity, revenue, expense, contra_asset, contra_liability, contra_revenue
            $table->boolean('is_contra')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('account_group', function (Blueprint $table): void {
            $table->foreignUuid('account_type_id')
                ->nullable()
                ->constrained('account_type')
                ->restrictOnDelete();
        });

        Schema::table('account', function (Blueprint $table): void {
            $table->foreignUuid('account_type_id')
                ->nullable()
                ->constrained('account_type')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account', function (Blueprint $table): void {
            $table->dropForeign(['account_type_id']);
            $table->dropColumn('account_type_id');
        });

        Schema::table('account_group', function (Blueprint $table): void {
            $table->dropForeign(['account_type_id']);
            $table->dropColumn('account_type_id');
        });

        Schema::dropIfExists('account_type');
    }
};
