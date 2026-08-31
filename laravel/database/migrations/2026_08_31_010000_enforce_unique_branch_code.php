<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch', function (Blueprint $table): void {
            $table->unique('code', 'branch_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('branch', function (Blueprint $table): void {
            $table->dropUnique('branch_code_unique');
        });
    }
};
