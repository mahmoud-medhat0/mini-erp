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
        Schema::create('account_category', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->jsonb('name');
            $table->string('normal_balance'); // debit, credit
            $table->string('statement_type'); // balance_sheet, income_statement
            $table->boolean('is_contra')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('account_type', function (Blueprint $table) {
            $table->foreignUuid('account_category_id')
                ->nullable()
                ->after('id')
                ->constrained('account_category')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_type', function (Blueprint $table) {
            $table->dropForeign(['account_category_id']);
            $table->dropColumn('account_category_id');
        });

        Schema::dropIfExists('account_category');
    }
};
