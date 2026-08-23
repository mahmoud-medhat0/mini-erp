<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_bill', function (Blueprint $table): void {
            if (! Schema::hasColumn('supplier_bill', 'tax_amount_minor')) {
                $table->bigInteger('tax_amount_minor')->default(0)->after('subtotal_minor');
            }
        });

        Schema::table('supplier_bill_line', function (Blueprint $table): void {
            if (! Schema::hasColumn('supplier_bill_line', 'tax_code_id')) {
                $table->foreignUuid('tax_code_id')->nullable()->after('line_total_minor')->constrained('tax_codes')->nullOnDelete();
            }
            if (! Schema::hasColumn('supplier_bill_line', 'tax_rate_bps')) {
                $table->integer('tax_rate_bps')->default(0)->after('tax_code_id');
            }
            if (! Schema::hasColumn('supplier_bill_line', 'tax_amount_minor')) {
                $table->bigInteger('tax_amount_minor')->default(0)->after('tax_rate_bps');
            }
            if (! Schema::hasColumn('supplier_bill_line', 'gross_amount_minor')) {
                $table->bigInteger('gross_amount_minor')->default(0)->after('tax_amount_minor');
            }
        });

        Schema::table('supplier_adjustment_note', function (Blueprint $table): void {
            if (! Schema::hasColumn('supplier_adjustment_note', 'tax_amount_minor')) {
                $table->bigInteger('tax_amount_minor')->default(0)->after('subtotal_minor');
            }
        });

        Schema::table('supplier_adjustment_note_line', function (Blueprint $table): void {
            if (! Schema::hasColumn('supplier_adjustment_note_line', 'tax_code_id')) {
                $table->foreignUuid('tax_code_id')->nullable()->after('line_total_minor')->constrained('tax_codes')->nullOnDelete();
            }
            if (! Schema::hasColumn('supplier_adjustment_note_line', 'tax_rate_bps')) {
                $table->integer('tax_rate_bps')->default(0)->after('tax_code_id');
            }
            if (! Schema::hasColumn('supplier_adjustment_note_line', 'tax_amount_minor')) {
                $table->bigInteger('tax_amount_minor')->default(0)->after('tax_rate_bps');
            }
            if (! Schema::hasColumn('supplier_adjustment_note_line', 'gross_amount_minor')) {
                $table->bigInteger('gross_amount_minor')->default(0)->after('tax_amount_minor');
            }
        });

        Schema::table('purchase_return', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchase_return', 'tax_amount_minor')) {
                $table->bigInteger('tax_amount_minor')->default(0)->after('subtotal_minor');
            }
        });

        Schema::table('purchase_return_line', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchase_return_line', 'tax_code_id')) {
                $table->foreignUuid('tax_code_id')->nullable()->after('line_total_minor')->constrained('tax_codes')->nullOnDelete();
            }
            if (! Schema::hasColumn('purchase_return_line', 'tax_rate_bps')) {
                $table->integer('tax_rate_bps')->default(0)->after('tax_code_id');
            }
            if (! Schema::hasColumn('purchase_return_line', 'tax_amount_minor')) {
                $table->bigInteger('tax_amount_minor')->default(0)->after('tax_rate_bps');
            }
            if (! Schema::hasColumn('purchase_return_line', 'gross_amount_minor')) {
                $table->bigInteger('gross_amount_minor')->default(0)->after('tax_amount_minor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_return_line', function (Blueprint $table): void {
            $table->dropForeign(['tax_code_id']);
            $table->dropColumn(['tax_code_id', 'tax_rate_bps', 'tax_amount_minor', 'gross_amount_minor']);
        });

        Schema::table('purchase_return', function (Blueprint $table): void {
            $table->dropColumn(['tax_amount_minor']);
        });

        Schema::table('supplier_adjustment_note_line', function (Blueprint $table): void {
            $table->dropForeign(['tax_code_id']);
            $table->dropColumn(['tax_code_id', 'tax_rate_bps', 'tax_amount_minor', 'gross_amount_minor']);
        });

        Schema::table('supplier_adjustment_note', function (Blueprint $table): void {
            $table->dropColumn(['tax_amount_minor']);
        });

        Schema::table('supplier_bill_line', function (Blueprint $table): void {
            $table->dropForeign(['tax_code_id']);
            $table->dropColumn(['tax_code_id', 'tax_rate_bps', 'tax_amount_minor', 'gross_amount_minor']);
        });

        Schema::table('supplier_bill', function (Blueprint $table): void {
            $table->dropColumn(['tax_amount_minor']);
        });
    }
};
