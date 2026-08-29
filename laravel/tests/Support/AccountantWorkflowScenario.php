<?php

namespace Tests\Support;

use App\Application\Accounting\CustomerReceiptService;
use App\Application\Accounting\GeneralLedgerService;
use App\Application\Accounting\PayableAllocationService;
use App\Application\Accounting\ReceivableAllocationService;
use App\Application\Accounting\ReceivableEntrySettlementService;
use App\Application\Accounting\SupplierPaymentService;
use App\Application\Purchasing\GoodsReceiptService;
use App\Application\Purchasing\PurchaseOrderService;
use App\Application\Purchasing\SupplierBillService;
use App\Application\Reports\ApToGlReconciliationReportService;
use App\Application\Reports\ArToGlReconciliationReportService;
use App\Application\Reports\BalanceSheetReportService;
use App\Application\Reports\IncomeStatementReportService;
use App\Application\Reports\VatRegisterReportService;
use App\Application\Reports\VatSummaryReportService;
use App\Application\Reports\VatToGlReconciliationService;
use App\Application\Sales\CustomerCreditNoteService;
use App\Application\Sales\CustomerInvoiceService;
use App\Application\Sales\DeliveryNoteService;
use App\Application\Sales\SalesOrderService;
use App\Application\Sales\SalesReturnService;
use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\FinancialPeriod;
use App\Models\Product;
use App\Models\StockLocation;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Database\Seeders\AccountantAcceptanceSeeder;
use Illuminate\Support\Facades\Artisan;

class AccountantWorkflowScenario
{
    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public static function run(User $user, array $options = []): array
    {
        // 1. Ensure baseline seeds exist
        $supplier = Supplier::query()->where('code', 'ACC-SUPP-001')->first();
        if (! $supplier) {
            Artisan::call('db:seed', ['--class' => AccountantAcceptanceSeeder::class]);
            $supplier = Supplier::query()->where('code', 'ACC-SUPP-001')->firstOrFail();
        }

        $customer = Customer::query()->where('code', 'ACC-CUST-001')->firstOrFail();
        $stockProduct = Product::query()->where('code', 'ACC-PRD-STOCK-01')->firstOrFail();
        $uom = UnitOfMeasure::query()->where('id', $stockProduct->unit_of_measure_id)->firstOrFail();
        $taxCode = TaxCode::query()->where('code', 'VAT_STD_14')->firstOrFail();
        $warehouse = Warehouse::query()->where('code', 'ACC-WH-MAIN')->firstOrFail();
        $location = StockLocation::query()->where('code', 'ACC-LOC-MAIN-01')->firstOrFail();
        $bankAccount = BankAccount::query()->where('code', 'ACC-BANK-01')->firstOrFail();
        $cashAccount = CashAccount::query()->where('code', 'ACC-CASH-01')->firstOrFail();

        // Resolve active financial period and timeline dates
        $period = FinancialPeriod::query()
            ->whereIn('status', ['open', 'reopened'])
            ->orderBy('start_date')
            ->firstOrFail();

        $startCarbon = Carbon::parse($period->start_date);
        $poDate = $startCarbon->copy()->addDays(2)->format('Y-m-d');
        $grDate = $startCarbon->copy()->addDays(3)->format('Y-m-d');
        $billDate = $startCarbon->copy()->addDays(4)->format('Y-m-d');
        $paymentDate = $startCarbon->copy()->addDays(5)->format('Y-m-d');
        $soDate = $startCarbon->copy()->addDays(6)->format('Y-m-d');
        $dnDate = $startCarbon->copy()->addDays(7)->format('Y-m-d');
        $invDate = $startCarbon->copy()->addDays(8)->format('Y-m-d');
        $retDate = $startCarbon->copy()->addDays(9)->format('Y-m-d');
        $cnDate = $startCarbon->copy()->addDays(10)->format('Y-m-d');
        $receiptDate = $startCarbon->copy()->addDays(11)->format('Y-m-d');

        $currency = 'EGP';
        $referencePrefix = $options['reference_prefix'] ?? 'ACC-SCENARIO-';

        // -------------------------------------------------------------
        // Step 1: Procure-to-Pay (PO -> GR -> Bill -> Payment -> Settle)
        // -------------------------------------------------------------
        /** @var PurchaseOrderService $poService */
        $poService = app(PurchaseOrderService::class);
        $po = $poService->create([
            'supplier_id' => $supplier->id,
            'order_date' => $poDate,
            'expected_receipt_date' => $grDate,
            'currency' => $currency,
            'reference' => $referencePrefix.'PO',
            'lines' => [
                [
                    'product_id' => $stockProduct->id,
                    'unit_of_measure_id' => $uom->id,
                    'description' => 'Acceptance Physical Finished Good (Batch 100)',
                    'quantity_e6' => 100_000_000, // 100 units
                    'unit_price_minor' => 10_000, // 100.00 EGP
                ],
            ],
        ], $user->id);
        $poService->submit($po->id, $user->id);
        $confirmedPo = $poService->confirm($po->id, $user->id);

        /** @var GoodsReceiptService $grService */
        $grService = app(GoodsReceiptService::class);
        $poLine = $confirmedPo->lines->first();
        $gr = $grService->create([
            'purchase_order_id' => $confirmedPo->id,
            'warehouse_id' => $warehouse->id,
            'receipt_date' => $grDate,
            'reference' => $referencePrefix.'GR',
            'lines' => [
                [
                    'purchase_order_line_id' => $poLine->id,
                    'stock_location_id' => $location->id,
                    'quantity_e6' => 100_000_000,
                ],
            ],
        ], $user->id);
        $confirmedGr = $grService->confirm($gr->id, $user->id);

        /** @var SupplierBillService $billService */
        $billService = app(SupplierBillService::class);
        $grLine = $confirmedGr->lines->first();
        $bill = $billService->create([
            'supplier_id' => $supplier->id,
            'goods_receipt_id' => $confirmedGr->id,
            'bill_date' => $billDate,
            'due_date' => $paymentDate,
            'currency' => $currency,
            'supplier_reference' => 'INV-SUPP-ACCEPT-01',
            'reference' => $referencePrefix.'BILL',
            'lines' => [
                [
                    'goods_receipt_line_id' => $grLine->id,
                    'product_id' => $stockProduct->id,
                    'unit_of_measure_id' => $uom->id,
                    'description' => 'Stock product receipt billed',
                    'quantity_e6' => 100_000_000,
                    'unit_cost_minor' => 10_000,
                    'tax_code_id' => $taxCode->id,
                ],
            ],
        ], $user->id);
        $billService->submit($bill->id, $user->id);
        $billService->approve($bill->id, $user->id);
        $postedBill = $billService->post($bill->id, $user->id);

        /** @var SupplierPaymentService $paymentService */
        $paymentService = app(SupplierPaymentService::class);
        $payment = $paymentService->create([
            'supplier_id' => $supplier->id,
            'fiscal_year_id' => $period->fiscal_year_id,
            'financial_period_id' => $period->id,
            'payment_date' => $paymentDate,
            'currency' => $currency,
            'amount_minor' => (int) $postedBill->total_minor, // 11,400.00 EGP (1,140,000 minor)
            'bank_account_id' => $bankAccount->id,
            'reference' => $referencePrefix.'PMT',
            'description' => 'Settlement for Supplier Bill '.$postedBill->number,
        ], $user->id);
        $postedPayment = $paymentService->post($payment->id, $user->id);

        /** @var PayableAllocationService $payableAllocService */
        $payableAllocService = app(PayableAllocationService::class);
        $payableAllocations = $payableAllocService->allocatePayment(
            paymentId: $postedPayment->id,
            lines: [
                [
                    'payable_entry_id' => $postedBill->payable_entry_id,
                    'amount_minor' => (int) $postedBill->total_minor,
                ],
            ],
            actorId: $user->id,
        );

        // -------------------------------------------------------------
        // Step 2: Order-to-Cash (SO -> DN -> Invoice)
        // -------------------------------------------------------------
        /** @var SalesOrderService $soService */
        $soService = app(SalesOrderService::class);
        $so = $soService->create([
            'customer_id' => $customer->id,
            'order_date' => $soDate,
            'currency' => $currency,
            'reference' => $referencePrefix.'SO',
            'lines' => [
                [
                    'product_id' => $stockProduct->id,
                    'unit_of_measure_id' => $uom->id,
                    'description' => 'Acceptance Physical Finished Good (Batch 40 units)',
                    'quantity_e6' => 40_000_000, // 40 units
                    'unit_price_minor' => 15_000, // 150.00 EGP
                ],
            ],
        ], $user->id);
        $soService->submit($so->id, $user->id);
        $confirmedSo = $soService->confirm($so->id, $user->id);

        /** @var DeliveryNoteService $dnService */
        $dnService = app(DeliveryNoteService::class);
        $soLine = $confirmedSo->lines->first();
        $dn = $dnService->create([
            'sales_order_id' => $confirmedSo->id,
            'warehouse_id' => $warehouse->id,
            'delivery_date' => $dnDate,
            'reference' => $referencePrefix.'DN',
            'lines' => [
                [
                    'sales_order_line_id' => $soLine->id,
                    'stock_location_id' => $location->id,
                    'quantity_e6' => 40_000_000,
                ],
            ],
        ], $user->id);
        $confirmedDn = $dnService->confirm($dn->id, $user->id);

        /** @var CustomerInvoiceService $invoiceService */
        $invoiceService = app(CustomerInvoiceService::class);
        $dnLine = $confirmedDn->lines->first();
        $invoice = $invoiceService->create([
            'customer_id' => $customer->id,
            'delivery_note_id' => $confirmedDn->id,
            'invoice_date' => $invDate,
            'due_date' => $receiptDate,
            'currency' => $currency,
            'reference' => $referencePrefix.'INV',
            'lines' => [
                [
                    'delivery_note_line_id' => $dnLine->id,
                    'product_id' => $stockProduct->id,
                    'unit_of_measure_id' => $uom->id,
                    'description' => 'Delivered Stock Product Billed',
                    'quantity_e6' => 40_000_000,
                    'unit_price_minor' => 15_000,
                    'tax_code_id' => $taxCode->id,
                ],
            ],
        ], $user->id);
        $invoiceService->submit($invoice->id, $user->id);
        $invoiceService->approve($invoice->id, $user->id);
        $postedInvoice = $invoiceService->post($invoice->id, $user->id);

        // -------------------------------------------------------------
        // Step 3: Sales Return & Customer Credit Note (10 units return)
        // -------------------------------------------------------------
        /** @var SalesReturnService $returnService */
        $returnService = app(SalesReturnService::class);
        $salesReturn = $returnService->create([
            'customer_id' => $customer->id,
            'delivery_note_id' => $confirmedDn->id,
            'warehouse_id' => $warehouse->id,
            'return_date' => $retDate,
            'customer_invoice_id' => $postedInvoice->id,
            'reason' => 'Customer requested partial return of 10 units in good condition',
            'lines' => [
                [
                    'delivery_note_line_id' => $dnLine->id,
                    'product_id' => $stockProduct->id,
                    'quantity_e6' => 10_000_000, // 10 units returned
                    'disposition' => 'restock_original_cost',
                ],
            ],
        ], $user->id);
        $returnService->submit($salesReturn->id, $user->id);
        $returnService->approve($salesReturn->id, $user->id);
        $postedReturn = $returnService->post($salesReturn->id, $user->id);

        /** @var CustomerCreditNoteService $creditNoteService */
        $creditNoteService = app(CustomerCreditNoteService::class);
        $invoiceLine = $postedInvoice->lines->first();
        $creditNote = $creditNoteService->create([
            'customer_id' => $customer->id,
            'customer_invoice_id' => $postedInvoice->id,
            'sales_return_id' => $postedReturn->id,
            'credit_date' => $cnDate,
            'currency' => $currency,
            'reason' => 'Credit note corresponding to Sales Return '.$postedReturn->number,
            'lines' => [
                [
                    'customer_invoice_line_id' => $invoiceLine->id,
                    'description' => 'Credit for 10 returned units',
                    'quantity_e6' => 10_000_000,
                    'unit_price_minor' => 15_000,
                    'tax_code_id' => $taxCode->id,
                ],
            ],
        ], $user->id);
        $creditNoteService->submit($creditNote->id, $user->id);
        $creditNoteService->approve($creditNote->id, $user->id);
        $postedCreditNote = $creditNoteService->post($creditNote->id, $user->id);

        /** @var ReceivableEntrySettlementService $receivableSettlementService */
        $receivableSettlementService = app(ReceivableEntrySettlementService::class);
        $creditSettlements = $receivableSettlementService->settleCredit(
            sourceCreditEntryId: $postedCreditNote->receivable_entry_id,
            lines: [
                [
                    'target_receivable_entry_id' => $postedInvoice->receivable_entry_id,
                    'amount_minor' => (int) $postedCreditNote->total_minor, // 1,710.00 EGP (171,000 minor)
                ],
            ],
            actorId: $user->id,
        );

        // -------------------------------------------------------------
        // Step 4: Customer Receipt & Settlement (Remaining 5,130.00 EGP)
        // -------------------------------------------------------------
        $remainingInvoiceMinor = (int) $postedInvoice->total_minor - (int) $postedCreditNote->total_minor; // 684,000 - 171,000 = 513,000 minor (5,130.00 EGP)

        /** @var CustomerReceiptService $receiptService */
        $receiptService = app(CustomerReceiptService::class);
        $receipt = $receiptService->create([
            'customer_id' => $customer->id,
            'fiscal_year_id' => $period->fiscal_year_id,
            'financial_period_id' => $period->id,
            'receipt_date' => $receiptDate,
            'currency' => $currency,
            'amount_minor' => $remainingInvoiceMinor,
            'bank_account_id' => $bankAccount->id,
            'reference' => $referencePrefix.'REC',
            'description' => 'Final settlement for Invoice '.$postedInvoice->number.' net of Credit Note '.$postedCreditNote->number,
        ], $user->id);
        $postedReceipt = $receiptService->post($receipt->id, $user->id);

        /** @var ReceivableAllocationService $receivableAllocService */
        $receivableAllocService = app(ReceivableAllocationService::class);
        $receiptAllocations = $receivableAllocService->allocateReceipt(
            receiptId: $postedReceipt->id,
            lines: [
                [
                    'receivable_entry_id' => $postedInvoice->receivable_entry_id,
                    'amount_minor' => $remainingInvoiceMinor,
                ],
            ],
            actorId: $user->id,
        );

        // -------------------------------------------------------------
        // Step 5: Reports Compilation
        // -------------------------------------------------------------
        /** @var VatRegisterReportService $vatRegisterService */
        $vatRegisterService = app(VatRegisterReportService::class);
        $vatRegister = $vatRegisterService->generate([
            'from_date' => $period->start_date->format('Y-m-d'),
            'to_date' => $period->end_date->format('Y-m-d'),
            'type' => 'all',
        ]);

        /** @var VatSummaryReportService $vatSummaryService */
        $vatSummaryService = app(VatSummaryReportService::class);
        $vatSummary = $vatSummaryService->generate([
            'from_date' => $period->start_date->format('Y-m-d'),
            'to_date' => $period->end_date->format('Y-m-d'),
        ]);

        /** @var VatToGlReconciliationService $vatReconService */
        $vatReconService = app(VatToGlReconciliationService::class);
        $vatReconciliation = $vatReconService->generate([
            'from_date' => $period->start_date->format('Y-m-d'),
            'to_date' => $period->end_date->format('Y-m-d'),
            'currency' => $currency,
        ]);

        /** @var GeneralLedgerService $glService */
        $glService = app(GeneralLedgerService::class);
        $trialBalance = $glService->getTrialBalance([
            'period_id' => $period->id,
        ]);

        /** @var ArToGlReconciliationReportService $arReconService */
        $arReconService = app(ArToGlReconciliationReportService::class);
        $arReconciliation = $arReconService->generate($receiptDate, $currency);

        /** @var ApToGlReconciliationReportService $apReconService */
        $apReconService = app(ApToGlReconciliationReportService::class);
        $apReconciliation = $apReconService->generate($receiptDate, $currency);

        /** @var IncomeStatementReportService $incomeStatementService */
        $incomeStatementService = app(IncomeStatementReportService::class);
        $incomeStatement = $incomeStatementService->generate(
            $period->start_date->format('Y-m-d'),
            $period->end_date->format('Y-m-d'),
            $period->id
        );

        /** @var BalanceSheetReportService $balanceSheetService */
        $balanceSheetService = app(BalanceSheetReportService::class);
        $balanceSheet = $balanceSheetService->generate($receiptDate);

        return [
            'period' => $period,
            'supplier' => $supplier,
            'customer' => $customer,
            'product' => $stockProduct,
            'warehouse' => $warehouse,
            'bank_account' => $bankAccount,
            'cash_account' => $cashAccount,
            'purchase_order' => $confirmedPo,
            'goods_receipt' => $confirmedGr,
            'supplier_bill' => $postedBill,
            'supplier_payment' => $postedPayment,
            'payable_allocations' => $payableAllocations,
            'sales_order' => $confirmedSo,
            'delivery_note' => $confirmedDn,
            'customer_invoice' => $postedInvoice,
            'sales_return' => $postedReturn,
            'customer_credit_note' => $postedCreditNote,
            'credit_settlements' => $creditSettlements,
            'customer_receipt' => $postedReceipt,
            'receipt_allocations' => $receiptAllocations,
            'reports' => [
                'vat_register' => $vatRegister,
                'vat_summary' => $vatSummary,
                'vat_reconciliation' => $vatReconciliation,
                'trial_balance' => $trialBalance,
                'ar_reconciliation' => $arReconciliation,
                'ap_reconciliation' => $apReconciliation,
                'income_statement' => $incomeStatement,
                'balance_sheet' => $balanceSheet,
            ],
        ];
    }
}
