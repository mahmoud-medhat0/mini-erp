<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_statement_line', function (Blueprint $table) {
            $table->string('cash_flow_activity')->nullable()->after('statement_type');
        });

        Schema::table('account', function (Blueprint $table) {
            $table->string('cash_flow_activity')->nullable()->after('financial_statement_line_id');
        });
    }

    public function down(): void
    {
        Schema::table('account', function (Blueprint $table) {
            $table->dropColumn('cash_flow_activity');
        });

        Schema::table('financial_statement_line', function (Blueprint $table) {
            $table->dropColumn('cash_flow_activity');
        });
    }
};
