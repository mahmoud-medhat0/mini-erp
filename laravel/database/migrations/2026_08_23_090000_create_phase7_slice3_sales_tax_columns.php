<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Customer Invoice & Lines
        if (! Schema::hasColumn('customer_invoice', 'tax_amount_minor')) {
            Schema::table('customer_invoice', function (Blueprint $table) {
                $table->bigInteger('tax_amount_minor')->default(0)->after('subtotal_minor');
            });
        }

        Schema::table('customer_invoice_line', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_invoice_line', 'tax_code_id')) {
                $table->foreignUuid('tax_code_id')->nullable()->after('line_total_minor')->constrained('tax_codes')->nullOnDelete();
            }
            if (! Schema::hasColumn('customer_invoice_line', 'tax_rate_bps')) {
                $table->integer('tax_rate_bps')->default(0)->after('tax_code_id');
            }
            if (! Schema::hasColumn('customer_invoice_line', 'tax_amount_minor')) {
                $table->bigInteger('tax_amount_minor')->default(0)->after('tax_rate_bps');
            }
            if (! Schema::hasColumn('customer_invoice_line', 'gross_amount_minor')) {
                $table->bigInteger('gross_amount_minor')->default(0)->after('tax_amount_minor');
            }
        });

        // 2. Customer Credit Note & Lines
        if (! Schema::hasColumn('customer_credit_note', 'tax_amount_minor')) {
            Schema::table('customer_credit_note', function (Blueprint $table) {
                $table->bigInteger('tax_amount_minor')->default(0)->after('subtotal_minor');
            });
        }

        Schema::table('customer_credit_note_line', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_credit_note_line', 'tax_code_id')) {
                $table->foreignUuid('tax_code_id')->nullable()->after('line_total_minor')->constrained('tax_codes')->nullOnDelete();
            }
            if (! Schema::hasColumn('customer_credit_note_line', 'tax_amount_minor')) {
                $table->bigInteger('tax_amount_minor')->default(0)->after('tax_rate_bps');
            }
            if (! Schema::hasColumn('customer_credit_note_line', 'gross_amount_minor')) {
                $table->bigInteger('gross_amount_minor')->default(0)->after('tax_amount_minor');
            }
        });

        // 3. Sales Return & Lines
        if (! Schema::hasColumn('sales_return', 'tax_amount_minor')) {
            Schema::table('sales_return', function (Blueprint $table) {
                $table->bigInteger('tax_amount_minor')->default(0)->after('subtotal_minor');
            });
        }

        Schema::table('sales_return_line', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_return_line', 'tax_code_id')) {
                $table->foreignUuid('tax_code_id')->nullable()->after('line_total_minor')->constrained('tax_codes')->nullOnDelete();
            }
            if (! Schema::hasColumn('sales_return_line', 'tax_rate_bps')) {
                $table->integer('tax_rate_bps')->default(0)->after('tax_code_id');
            }
            if (! Schema::hasColumn('sales_return_line', 'tax_amount_minor')) {
                $table->bigInteger('tax_amount_minor')->default(0)->after('tax_rate_bps');
            }
            if (! Schema::hasColumn('sales_return_line', 'gross_amount_minor')) {
                $table->bigInteger('gross_amount_minor')->default(0)->after('tax_amount_minor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_return_line', function (Blueprint $table) {
            if (Schema::hasColumn('sales_return_line', 'tax_code_id')) {
                $table->dropForeign(['tax_code_id']);
                $table->dropColumn(['tax_code_id']);
            }
            $cols = array_filter(['tax_rate_bps', 'tax_amount_minor', 'gross_amount_minor'], fn ($c) => Schema::hasColumn('sales_return_line', $c));
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });

        if (Schema::hasColumn('sales_return', 'tax_amount_minor')) {
            Schema::table('sales_return', function (Blueprint $table) {
                $table->dropColumn(['tax_amount_minor']);
            });
        }

        Schema::table('customer_credit_note_line', function (Blueprint $table) {
            if (Schema::hasColumn('customer_credit_note_line', 'tax_code_id')) {
                $table->dropForeign(['tax_code_id']);
                $table->dropColumn(['tax_code_id']);
            }
            $cols = array_filter(['tax_amount_minor', 'gross_amount_minor'], fn ($c) => Schema::hasColumn('customer_credit_note_line', $c));
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });

        if (Schema::hasColumn('customer_credit_note', 'tax_amount_minor')) {
            Schema::table('customer_credit_note', function (Blueprint $table) {
                $table->dropColumn(['tax_amount_minor']);
            });
        }

        Schema::table('customer_invoice_line', function (Blueprint $table) {
            if (Schema::hasColumn('customer_invoice_line', 'tax_code_id')) {
                $table->dropForeign(['tax_code_id']);
                $table->dropColumn(['tax_code_id']);
            }
            $cols = array_filter(['tax_rate_bps', 'tax_amount_minor', 'gross_amount_minor'], fn ($c) => Schema::hasColumn('customer_invoice_line', $c));
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });

        if (Schema::hasColumn('customer_invoice', 'tax_amount_minor')) {
            Schema::table('customer_invoice', function (Blueprint $table) {
                $table->dropColumn(['tax_amount_minor']);
            });
        }
    }
};
