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
        Schema::create('financial_statement_line', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('statement_type')->index(); // balance_sheet or income_statement
            $table->string('section_code');
            $table->json('name');
            $table->string('normal_balance'); // debit or credit
            $table->integer('sort_order')->default(0);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('account', function (Blueprint $table) {
            $table->foreignUuid('financial_statement_line_id')
                ->nullable()
                ->after('account_group_id')
                ->constrained('financial_statement_line')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account', function (Blueprint $table) {
            $table->dropForeign(['financial_statement_line_id']);
            $table->dropColumn('financial_statement_line_id');
        });

        Schema::dropIfExists('financial_statement_line');
    }
};
