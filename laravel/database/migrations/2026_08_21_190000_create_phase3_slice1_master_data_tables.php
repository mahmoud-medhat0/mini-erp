<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Customer table
        Schema::create('customer', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $this->jsonColumn($table, 'name');
            $table->string('status', 20)->default('active');
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('tax_number', 100)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->index('status');
        });

        // 2. Supplier table
        Schema::create('supplier', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $this->jsonColumn($table, 'name');
            $table->string('status', 20)->default('active');
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('tax_number', 100)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->index('status');
        });

        // 3. CashAccount table
        Schema::create('cash_account', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $this->jsonColumn($table, 'name');
            $table->uuid('gl_account_id');
            $table->char('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->foreign('gl_account_id')->references('id')->on('account')->onDelete('restrict');
            $table->foreign('currency')->references('code')->on('currency')->onDelete('restrict');
            $table->index('is_active');
        });

        // 4. BankAccount table
        Schema::create('bank_account', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $this->jsonColumn($table, 'name');
            $this->jsonColumn($table, 'bank_name')->nullable();
            $table->string('account_number', 100)->nullable();
            $table->string('iban', 100)->nullable();
            $table->string('swift', 50)->nullable();
            $table->uuid('gl_account_id');
            $table->char('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->foreign('gl_account_id')->references('id')->on('account')->onDelete('restrict');
            $table->foreign('currency')->references('code')->on('currency')->onDelete('restrict');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_account');
        Schema::dropIfExists('cash_account');
        Schema::dropIfExists('supplier');
        Schema::dropIfExists('customer');
    }

    private function jsonColumn(Blueprint $table, string $name): mixed
    {
        return DB::connection()->getDriverName() === 'pgsql'
            ? $table->jsonb($name)
            : $table->json($name);
    }
};
