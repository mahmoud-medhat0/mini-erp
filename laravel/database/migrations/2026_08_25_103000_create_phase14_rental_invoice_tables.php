<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceMappingKeyConstraint([
            'ar_control',
            'ap_control',
            'opening_balance_offset',
            'cheques_under_collection',
            'cheques_payable',
            'sales_revenue',
            'purchase_expense',
            'inventory_asset',
            'grni_clearing',
            'cogs',
            'sales_returns',
            'inventory_return_variance',
            'inventory_scrap_loss',
            'purchase_returns_allowances',
            'output_tax_payable',
            'input_tax_receivable',
            'fixed_asset_cost',
            'accumulated_depreciation',
            'depreciation_expense',
            'fixed_asset_disposal_gain',
            'fixed_asset_disposal_loss',
            'fixed_asset_clearing',
            'inventory_adjustment_gain',
            'inventory_adjustment_loss',
            'prepaid_expense_asset',
            'accrued_expense_liability',
            'payroll_expense',
            'payroll_payable',
            'payroll_deductions_payable',
            'rental_revenue',
            'rental_damage_revenue',
            'rental_late_fee_revenue',
            'rental_other_revenue',
            'rental_deposit_liability',
        ]);

        Schema::create('rental_invoice', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('number')->unique()->nullable();
            $table->foreignUuid('rental_contract_id')->constrained('rental_contract')->restrictOnDelete();
            $table->foreignUuid('customer_id')->constrained('customer')->restrictOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branch')->nullOnDelete();
            $table->foreignUuid('fiscal_year_id')->constrained('fiscal_year')->restrictOnDelete();
            $table->foreignUuid('financial_period_id')->constrained('financial_period')->restrictOnDelete();
            $table->string('invoice_type')->default('periodic_rent');
            $table->string('status')->default('draft');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->date('billing_period_start')->nullable();
            $table->date('billing_period_end')->nullable();
            $table->string('currency', 3);
            $table->bigInteger('fx_rate_e6')->default(1000000);
            $table->bigInteger('subtotal_minor')->default(0);
            $table->bigInteger('tax_amount_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('journal_entry_id')->nullable()->constrained('journal_entry')->nullOnDelete();
            $table->foreignUuid('receivable_entry_id')->nullable()->constrained('receivable_entry')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->integer('lock_version')->default(1);
            $table->timestamps();

            $table->foreign('currency')->references('code')->on('currency')->cascadeOnUpdate()->restrictOnDelete();
            $table->index(['status', 'invoice_date']);
            $table->index(['rental_contract_id', 'status']);
            $table->index(['customer_id', 'invoice_date']);
            $table->index(['branch_id', 'invoice_date']);
        });

        Schema::create('rental_invoice_line', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('rental_invoice_id')->constrained('rental_invoice')->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->string('line_type');
            $table->foreignUuid('rental_contract_line_id')->nullable()->constrained('rental_contract_line')->restrictOnDelete();
            $table->foreignUuid('rental_return_id')->nullable()->constrained('rental_return')->restrictOnDelete();
            $table->foreignUuid('rental_return_line_id')->nullable()->constrained('rental_return_line')->restrictOnDelete();
            $table->text('description')->nullable();
            $table->bigInteger('quantity_e6')->default(1000000);
            $table->bigInteger('unit_amount_minor');
            $table->bigInteger('line_total_minor');
            $table->foreignUuid('tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();
            $table->integer('tax_rate_bps')->default(0);
            $table->bigInteger('tax_amount_minor')->default(0);
            $table->bigInteger('gross_amount_minor')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['rental_invoice_id', 'line_no']);
            $table->index(['rental_contract_line_id', 'line_type']);
            $table->index(['rental_return_line_id', 'line_type']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE rental_invoice ADD CONSTRAINT rental_invoice_status_check CHECK (status IN ('draft', 'submitted', 'approved', 'posted', 'cancelled'))");
            DB::statement("ALTER TABLE rental_invoice ADD CONSTRAINT rental_invoice_type_check CHECK (invoice_type IN ('periodic_rent', 'deposit', 'final_charges', 'mixed'))");
            DB::statement('ALTER TABLE rental_invoice ADD CONSTRAINT rental_invoice_amounts_check CHECK (fx_rate_e6 > 0 AND subtotal_minor >= 0 AND tax_amount_minor >= 0 AND total_minor >= 0)');
            DB::statement('ALTER TABLE rental_invoice ADD CONSTRAINT rental_invoice_billing_period_check CHECK (billing_period_start IS NULL OR billing_period_end IS NULL OR billing_period_end >= billing_period_start)');
            DB::statement("ALTER TABLE rental_invoice_line ADD CONSTRAINT rental_invoice_line_type_check CHECK (line_type IN ('rent', 'deposit', 'damage_charge', 'late_fee', 'other_charge'))");
            DB::statement('ALTER TABLE rental_invoice_line ADD CONSTRAINT rental_invoice_line_amounts_check CHECK (quantity_e6 > 0 AND unit_amount_minor >= 0 AND line_total_minor >= 0 AND tax_rate_bps >= 0 AND tax_amount_minor >= 0 AND gross_amount_minor >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_invoice_line');
        Schema::dropIfExists('rental_invoice');

        $this->replaceMappingKeyConstraint([
            'ar_control',
            'ap_control',
            'opening_balance_offset',
            'cheques_under_collection',
            'cheques_payable',
            'sales_revenue',
            'purchase_expense',
            'inventory_asset',
            'grni_clearing',
            'cogs',
            'sales_returns',
            'inventory_return_variance',
            'inventory_scrap_loss',
            'purchase_returns_allowances',
            'output_tax_payable',
            'input_tax_receivable',
            'fixed_asset_cost',
            'accumulated_depreciation',
            'depreciation_expense',
            'fixed_asset_disposal_gain',
            'fixed_asset_disposal_loss',
            'fixed_asset_clearing',
            'inventory_adjustment_gain',
            'inventory_adjustment_loss',
            'prepaid_expense_asset',
            'accrued_expense_liability',
            'payroll_expense',
            'payroll_payable',
            'payroll_deductions_payable',
        ]);
    }

    private function replaceMappingKeyConstraint(array $keys): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $quotedKeys = collect($keys)
            ->map(fn (string $key): string => "'{$key}'")
            ->implode(', ');

        DB::statement('ALTER TABLE accounting_account_mapping DROP CONSTRAINT IF EXISTS accounting_account_mapping_key_check');
        DB::statement("ALTER TABLE accounting_account_mapping ADD CONSTRAINT accounting_account_mapping_key_check CHECK (key IN ({$quotedKeys}))");
    }
};
