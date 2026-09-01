<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_order', function (Blueprint $table): void {
            $table->index('customer_id', 'sales_order_customer_report_index');
            $table->index(['currency', 'order_date'], 'sales_order_currency_date_report_index');
        });
        Schema::table('sales_order_line', function (Blueprint $table): void {
            $table->index(['product_id', 'sales_order_id'], 'sales_order_line_product_report_index');
        });

        Schema::table('purchase_order', function (Blueprint $table): void {
            $table->index(['currency', 'order_date'], 'purchase_order_currency_date_report_index');
        });
        Schema::table('purchase_order_line', function (Blueprint $table): void {
            $table->index(['product_id', 'purchase_order_id'], 'purchase_order_line_product_report_index');
        });

        Schema::table('delivery_note', function (Blueprint $table): void {
            $table->index(['warehouse_id', 'delivery_date'], 'delivery_note_warehouse_date_report_index');
        });
        Schema::table('delivery_note_line', function (Blueprint $table): void {
            $table->index(['product_id', 'delivery_note_id'], 'delivery_note_line_product_report_index');
        });

        Schema::table('goods_receipt', function (Blueprint $table): void {
            $table->index(['warehouse_id', 'receipt_date'], 'goods_receipt_warehouse_date_report_index');
        });
        Schema::table('goods_receipt_line', function (Blueprint $table): void {
            $table->index(['product_id', 'goods_receipt_id'], 'goods_receipt_line_product_report_index');
        });

        Schema::table('customer_invoice', function (Blueprint $table): void {
            $table->index(['currency', 'invoice_date'], 'customer_invoice_currency_date_report_index');
        });
        Schema::table('customer_invoice_line', function (Blueprint $table): void {
            $table->index(['product_id', 'customer_invoice_id'], 'customer_invoice_line_product_report_index');
        });

        Schema::table('supplier_bill', function (Blueprint $table): void {
            $table->index(['currency', 'bill_date'], 'supplier_bill_currency_date_report_index');
        });
        Schema::table('supplier_bill_line', function (Blueprint $table): void {
            $table->index(['product_id', 'supplier_bill_id'], 'supplier_bill_line_product_report_index');
        });

        Schema::table('stock_movement_ledger', function (Blueprint $table): void {
            $table->index(['movement_type', 'movement_date'], 'stock_movement_type_date_report_index');
            $table->index(['currency', 'movement_date'], 'stock_movement_currency_date_report_index');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movement_ledger', function (Blueprint $table): void {
            $table->dropIndex('stock_movement_type_date_report_index');
            $table->dropIndex('stock_movement_currency_date_report_index');
        });

        Schema::table('supplier_bill_line', function (Blueprint $table): void {
            $table->dropIndex('supplier_bill_line_product_report_index');
        });
        Schema::table('supplier_bill', function (Blueprint $table): void {
            $table->dropIndex('supplier_bill_currency_date_report_index');
        });

        Schema::table('customer_invoice_line', function (Blueprint $table): void {
            $table->dropIndex('customer_invoice_line_product_report_index');
        });
        Schema::table('customer_invoice', function (Blueprint $table): void {
            $table->dropIndex('customer_invoice_currency_date_report_index');
        });

        Schema::table('goods_receipt_line', function (Blueprint $table): void {
            $table->dropIndex('goods_receipt_line_product_report_index');
        });
        Schema::table('goods_receipt', function (Blueprint $table): void {
            $table->dropIndex('goods_receipt_warehouse_date_report_index');
        });

        Schema::table('delivery_note_line', function (Blueprint $table): void {
            $table->dropIndex('delivery_note_line_product_report_index');
        });
        Schema::table('delivery_note', function (Blueprint $table): void {
            $table->dropIndex('delivery_note_warehouse_date_report_index');
        });

        Schema::table('purchase_order_line', function (Blueprint $table): void {
            $table->dropIndex('purchase_order_line_product_report_index');
        });
        Schema::table('purchase_order', function (Blueprint $table): void {
            $table->dropIndex('purchase_order_currency_date_report_index');
        });

        Schema::table('sales_order_line', function (Blueprint $table): void {
            $table->dropIndex('sales_order_line_product_report_index');
        });
        Schema::table('sales_order', function (Blueprint $table): void {
            $table->dropIndex('sales_order_customer_report_index');
            $table->dropIndex('sales_order_currency_date_report_index');
        });
    }
};
