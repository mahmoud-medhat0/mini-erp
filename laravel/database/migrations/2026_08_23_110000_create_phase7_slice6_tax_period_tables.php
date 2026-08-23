<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('period_label')->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 32)->default('open'); // open, draft_return, filed
            $table->timestamp('filed_at')->nullable();
            $table->uuid('filed_by')->nullable();
            $table->string('file_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['start_date', 'end_date']);
        });

        Schema::create('tax_returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tax_period_id');
            $table->string('number')->unique();
            $table->string('status', 32)->default('draft'); // draft, filed
            $table->bigInteger('output_tax_minor')->default(0);
            $table->bigInteger('input_tax_minor')->default(0);
            $table->bigInteger('net_payable_minor')->default(0);
            $table->jsonb('snapshot')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->uuid('generated_by')->nullable();
            $table->timestamp('filed_at')->nullable();
            $table->uuid('filed_by')->nullable();
            $table->timestamps();

            $table->foreign('tax_period_id')->references('id')->on('tax_periods')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_returns');
        Schema::dropIfExists('tax_periods');
    }
};
