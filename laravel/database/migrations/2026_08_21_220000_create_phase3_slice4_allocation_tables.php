<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receivable_allocation', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('customer')->restrictOnDelete();
            $table->foreignUuid('customer_receipt_id')->constrained('customer_receipt')->restrictOnDelete();
            $table->foreignUuid('receivable_entry_id')->constrained('receivable_entry')->restrictOnDelete();

            $table->string('currency', 3);
            $table->foreign('currency')->references('code')->on('currency')->restrictOnDelete();

            $table->bigInteger('amount_minor');
            $table->string('status')->default('active');
            $table->timestamp('allocated_at');
            $table->timestamp('reversed_at')->nullable();

            $table->text('reason')->nullable();
            $table->text('reversed_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['customer_receipt_id', 'status']);
            $table->index(['receivable_entry_id', 'status']);
            $table->index(['customer_id', 'allocated_at']);
            $table->index('currency');
        });

        Schema::create('payable_allocation', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_id')->constrained('supplier')->restrictOnDelete();
            $table->foreignUuid('supplier_payment_id')->constrained('supplier_payment')->restrictOnDelete();
            $table->foreignUuid('payable_entry_id')->constrained('payable_entry')->restrictOnDelete();

            $table->string('currency', 3);
            $table->foreign('currency')->references('code')->on('currency')->restrictOnDelete();

            $table->bigInteger('amount_minor');
            $table->string('status')->default('active');
            $table->timestamp('allocated_at');
            $table->timestamp('reversed_at')->nullable();

            $table->text('reason')->nullable();
            $table->text('reversed_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['supplier_payment_id', 'status']);
            $table->index(['payable_entry_id', 'status']);
            $table->index(['supplier_id', 'allocated_at']);
            $table->index('currency');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE receivable_allocation ADD CONSTRAINT check_receivable_allocation_amount CHECK (amount_minor > 0)');
            DB::statement("ALTER TABLE receivable_allocation ADD CONSTRAINT check_receivable_allocation_status CHECK (status IN ('active', 'reversed'))");

            DB::statement('ALTER TABLE payable_allocation ADD CONSTRAINT check_payable_allocation_amount CHECK (amount_minor > 0)');
            DB::statement("ALTER TABLE payable_allocation ADD CONSTRAINT check_payable_allocation_status CHECK (status IN ('active', 'reversed'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payable_allocation');
        Schema::dropIfExists('receivable_allocation');
    }
};
