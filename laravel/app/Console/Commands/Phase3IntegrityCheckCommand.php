<?php

namespace App\Console\Commands;

use App\Application\Reports\ApAgingReportService;
use App\Application\Reports\ApToGlReconciliationReportService;
use App\Application\Reports\ArAgingReportService;
use App\Application\Reports\ArToGlReconciliationReportService;
use App\Application\Reports\BankReconciliationReportService;
use App\Application\Reports\ChequeRegisterReportService;
use App\Application\Reports\CustomerStatementReportService;
use App\Application\Reports\SupplierStatementReportService;
use App\Models\AccountingAccountMapping;
use App\Models\BankReconciliationLine;
use App\Models\CustomerReceipt;
use App\Models\IncomingCheque;
use App\Models\JournalEntry;
use App\Models\OutgoingCheque;
use App\Models\PayableEntry;
use App\Models\ReceivableEntry;
use App\Models\SupplierPayment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Phase3IntegrityCheckCommand extends Command
{
    protected $signature = 'accounting:phase3-integrity-check';

    protected $description = 'Run non-mutating integrity checks on Phase 3 subledgers, allocations, cheques, bank reconciliations, and reports.';

    public function handle(
        CustomerStatementReportService $customerStatementService,
        SupplierStatementReportService $supplierStatementService,
        ArAgingReportService $arAgingService,
        ApAgingReportService $apAgingService,
        ChequeRegisterReportService $chequeRegisterService,
        BankReconciliationReportService $bankReconciliationService,
        ArToGlReconciliationReportService $arGlService,
        ApToGlReconciliationReportService $apGlService
    ): int {
        $this->info('Starting Phase 3 Financial Data & Report Integrity Audit...');
        $failures = [];

        // Check 1: Customer Receipt Math (allocated_minor + unapplied_minor = amount_minor)
        $invalidReceipts = CustomerReceipt::query()
            ->whereRaw('allocated_minor + unapplied_minor != amount_minor')
            ->count();
        if ($invalidReceipts > 0) {
            $failures[] = "Found {$invalidReceipts} CustomerReceipt rows where allocated_minor + unapplied_minor != amount_minor.";
        }

        // Check 2: Supplier Payment Math (allocated_minor + unapplied_minor = amount_minor)
        $invalidPayments = SupplierPayment::query()
            ->whereRaw('allocated_minor + unapplied_minor != amount_minor')
            ->count();
        if ($invalidPayments > 0) {
            $failures[] = "Found {$invalidPayments} SupplierPayment rows where allocated_minor + unapplied_minor != amount_minor.";
        }

        // Check 3: Non-negative unapplied balances
        $negReceipts = CustomerReceipt::query()->where('unapplied_minor', '<', 0)->count();
        if ($negReceipts > 0) {
            $failures[] = "Found {$negReceipts} CustomerReceipt rows with negative unapplied_minor.";
        }
        $negPayments = SupplierPayment::query()->where('unapplied_minor', '<', 0)->count();
        if ($negPayments > 0) {
            $failures[] = "Found {$negPayments} SupplierPayment rows with negative unapplied_minor.";
        }

        // Check 4: Receivable Allocation Cap Check
        $overallocatedReceivableEntries = DB::table('receivable_allocation')
            ->select('receivable_entry_id', DB::raw('SUM(amount_minor) as total_allocated'))
            ->where('status', 'active')
            ->groupBy('receivable_entry_id')
            ->get();

        foreach ($overallocatedReceivableEntries as $row) {
            $re = ReceivableEntry::find($row->receivable_entry_id);
            if ($re) {
                $maxAllocatable = (int) $re->debit_minor - (int) $re->credit_minor;
                if ((int) $row->total_allocated > $maxAllocatable) {
                    $failures[] = "ReceivableEntry [{$re->id}] has total allocations [{$row->total_allocated}] exceeding net debit [{$maxAllocatable}].";
                }
            }
        }

        // Check 5: Payable Allocation Cap Check
        $overallocatedPayableEntries = DB::table('payable_allocation')
            ->select('payable_entry_id', DB::raw('SUM(amount_minor) as total_allocated'))
            ->where('status', 'active')
            ->groupBy('payable_entry_id')
            ->get();

        foreach ($overallocatedPayableEntries as $row) {
            $pe = PayableEntry::find($row->payable_entry_id);
            if ($pe) {
                $maxAllocatable = (int) $pe->credit_minor - (int) $pe->debit_minor;
                if ((int) $row->total_allocated > $maxAllocatable) {
                    $failures[] = "PayableEntry [{$pe->id}] has total allocations [{$row->total_allocated}] exceeding net credit [{$maxAllocatable}].";
                }
            }
        }

        // Check 6: Document Source & Journal Integrity
        $orphanedReceiptJournals = CustomerReceipt::query()
            ->where('status', 'posted')
            ->whereNotNull('id')
            ->get()
            ->filter(fn ($r) => ! JournalEntry::where('source_type', 'customer_receipt')->where('source_id', $r->id)->exists())
            ->count();
        if ($orphanedReceiptJournals > 0) {
            $failures[] = "Found {$orphanedReceiptJournals} posted CustomerReceipts without a corresponding posted JournalEntry.";
        }

        $orphanedPaymentJournals = SupplierPayment::query()
            ->where('status', 'posted')
            ->whereNotNull('id')
            ->get()
            ->filter(fn ($p) => ! JournalEntry::where('source_type', 'supplier_payment')->where('source_id', $p->id)->exists())
            ->count();
        if ($orphanedPaymentJournals > 0) {
            $failures[] = "Found {$orphanedPaymentJournals} posted SupplierPayments without a corresponding posted JournalEntry.";
        }

        // Check 7: Cheque Journal Integrity
        $clearedIncomingOrphans = IncomingCheque::query()
            ->where('status', 'cleared')
            ->whereNull('clear_journal_entry_id')
            ->count();
        if ($clearedIncomingOrphans > 0) {
            $failures[] = "Found {$clearedIncomingOrphans} cleared IncomingCheques without clear_journal_entry_id.";
        }

        $clearedOutgoingOrphans = OutgoingCheque::query()
            ->where('status', 'cleared')
            ->whereNull('clear_journal_entry_id')
            ->count();
        if ($clearedOutgoingOrphans > 0) {
            $failures[] = "Found {$clearedOutgoingOrphans} cleared OutgoingCheques without clear_journal_entry_id.";
        }

        // Check 8: Bank Reconciliation Match Integrity
        $duplicateMatchedLedgerLines = BankReconciliationLine::query()
            ->whereNotNull('matched_ledger_entry_id')
            ->select('matched_ledger_entry_id', DB::raw('COUNT(*) as match_count'))
            ->groupBy('matched_ledger_entry_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();
        if ($duplicateMatchedLedgerLines > 0) {
            $failures[] = "Found {$duplicateMatchedLedgerLines} LedgerEntries matched into multiple BankReconciliationLines.";
        }

        // Check 9: AR / AP to GL Reconciliation Difference Check
        $arMappingExists = AccountingAccountMapping::where('key', 'ar_control')->exists();
        if ($arMappingExists) {
            $today = date('Y-m-d');
            $arRecon = $arGlService->generate($today, 'EGP');
            if ($arRecon['mapping_configured'] && $arRecon['difference_minor'] !== 0) {
                $failures[] = "AR to GL control account reconciliation shows a non-zero difference: {$arRecon['difference_minor']} minor units.";
            }
        }

        $apMappingExists = AccountingAccountMapping::where('key', 'ap_control')->exists();
        if ($apMappingExists) {
            $today = date('Y-m-d');
            $apRecon = $apGlService->generate($today, 'EGP');
            if ($apRecon['mapping_configured'] && $apRecon['difference_minor'] !== 0) {
                $failures[] = "AP to GL control account reconciliation shows a non-zero difference: {$apRecon['difference_minor']} minor units.";
            }
        }

        // Check 10: Report Read-Only & Non-Mutating Verification
        $tableCountsBefore = [
            'customer_receipt' => DB::table('customer_receipt')->count(),
            'supplier_payment' => DB::table('supplier_payment')->count(),
            'receivable_entry' => DB::table('receivable_entry')->count(),
            'payable_entry' => DB::table('payable_entry')->count(),
            'journal_entry' => DB::table('journal_entry')->count(),
            'ledger_entry' => DB::table('ledger_entry')->count(),
        ];

        // Execute report services
        $arAgingService->generate(date('Y-m-d'), null, 'EGP');
        $apAgingService->generate(date('Y-m-d'), null, 'EGP');
        $chequeRegisterService->generate('all');
        $bankReconciliationService->generateIndex(null, null);

        $tableCountsAfter = [
            'customer_receipt' => DB::table('customer_receipt')->count(),
            'supplier_payment' => DB::table('supplier_payment')->count(),
            'receivable_entry' => DB::table('receivable_entry')->count(),
            'payable_entry' => DB::table('payable_entry')->count(),
            'journal_entry' => DB::table('journal_entry')->count(),
            'ledger_entry' => DB::table('ledger_entry')->count(),
        ];

        if ($tableCountsBefore !== $tableCountsAfter) {
            $failures[] = 'Report service execution mutated database table counts! Report services must remain strictly read-only.';
        }

        // Check 11: Multi-tenant / Company scope violation audit
        $prohibitedColumns = ['company_id', 'branch_id', 'tenant_id'];
        $tables = DB::connection()->getSchemaBuilder()->getTableListing();

        foreach ($tables as $table) {
            foreach ($prohibitedColumns as $col) {
                if (Schema::hasColumn($table, $col)) {
                    $failures[] = "Prohibited tenancy column [{$col}] found in table [{$table}]. Owner decision rules forbid company/tenant scoping.";
                }
            }
        }

        // Final Report Evaluation
        if (count($failures) > 0) {
            $this->error('Phase 3 Financial Data Integrity Audit FAILED:');
            foreach ($failures as $f) {
                $this->line("  - [FAIL] {$f}");
            }

            return self::FAILURE;
        }

        $this->info('PASS: All Phase 3 Subledger, Allocation, Cheque, Bank Reconciliation, and Report Invariants Verified Successfully.');

        return self::SUCCESS;
    }
}
