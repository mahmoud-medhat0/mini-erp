# REQUIREMENTS TRACEABILITY MATRIX v2

> **No Multi-Tenant Policy:** Active Laravel ERP is single-installation only. Do not add or infer tenant/company/branch ownership, currentCompany/currentBranch context, company_id, branch_id, tenant_id, or Spatie Teams scope. See root `NO_MULTI_TENANT_POLICY.md`.


Expands `REQUIREMENTS_TRACEABILITY.md` with the dimensions brief §42 requires: **Requirement → Module → Entity → Application Service → Workflow → Accounting Event → DB → Screen → Report → Permission → Test → Phase.** Below is the matrix for the load-bearing transactional requirements plus the newly-added infrastructure requirements. (The full requirement-by-requirement coverage list with COVERED/PARTIAL/DECISION status remains in v1; this v2 adds the service/test/phase columns and closes the infra gaps.)

## Transactional requirements
| Requirement | Module | Entity | App Service | Workflow | Acct Event | DB | Screen | Report | Permission | Test | Phase |
|---|---|---|---|---|---|---|---|---|---|---|---|
|Post sales invoice|Sales|sales_doc|`SalesInvoiceService.post`|WF §2|EM 1.1|sales_doc, journal_*, ledger, stock_movement, ar_entry|`/sales/invoices/:id`|Sales, AR Aging|`sales.post`|inv: Σdr=Σcr, AR=GL, COGS|4|
|Customer receipt + allocation|Sales/AR|receipt, allocation|`ReceiptService.post`|WF §2|EM 1.3|receipt, allocation, ar_entry, journal_*|`/sales/receipts`|Collections, Statement|`receipt.post`|alloc ≤ remaining|3–4|
|Post purchase invoice|Purchasing|purchase_doc|`PurchaseInvoiceService.post`|WF §3|EM 2.1/2.4|purchase_doc, journal_*, ap_entry, stock_movement|`/purchasing/invoices/:id`|Purchases, AP Aging|`purchasing.post`|AP=GL, InputVAT|4|
|Goods receipt|Purchasing/Inv|purchase_doc(grn)|`GRNService.confirm`|WF §3|EM 2.3|stock_movement, stock_layer, journal_*|`/purchasing/goods-received`|Stock Movement|`purchasing.grn`|stock-in cost, clearing|4–5|
|Inventory valuation|Inventory|stock_movement/layer|`ValuationService`|WF §4|EM 3.x|stock ledger, journal_*|`/inventory/valuation`|Inventory Valuation|`inventory.view`|valuation=ledger (BR-S3)|5|
|Stock count variance|Inventory|stock_count|`StockCountService.post`|WF §4|EM 3.3/3.4|stock_count*, journal_*|`/inventory/counts`|Stock Movement|`inventory.adjust`|variance JV|5|
|Rental invoice + deposit|Rentals|rental_contract|`RentalInvoiceService.post`|WF §6|EM 8.1/8.2|rental_*, journal_*, ar_entry|`/rentals/contracts/:id`|Rental Revenue|`rentals.post`|deposit liability|6|
|Depreciation run|Fixed Assets|depreciation_entry|`DepreciationJob`|WF §11|EM 7.2|fixed_asset, depreciation_entry, journal_*|`/assets/depreciation`|Depreciation|`assets.post`|monthly, idempotent|7|
|Prepaid recognition|Prepaid|prepaid_schedule|`PrepaidJob`|WF §10|EM 6.4|prepaid_*, journal_*|`/expenses/prepaids`|Prepaid Schedule|`expenses.post`|schedule sums, idempotent|7|
|Payroll post|Payroll|payroll_run|`PayrollService.post`|WF §12|EM 9.1|payroll_*, journal_*|`/payroll/runs`|Payroll Register|`payroll.post`|balanced JE|8|
|VAT settlement|Taxes|tax_return|`TaxSettlementService`|WF §13|EM 10.3/10.4|tax_*, journal_*|`/taxes/returns`|VAT Report|`taxes.post`|net=Out−In|8|
|Partner contribution/distribution|Equity|capital_*, distribution|`EquityService.post`|WF §1|EM 11.x|partner_*, journal_*|`/equity/*`|Partner Statement|`equity.post`|RE roll|8|
|Recurring generation|Recurring|recurring_template|`RecurringJob`|WF §15|(delegates)|recurring_*, target doc|`/recurring`|Run History|`recurring.manage`|no dup/period|9|
|Bank reconciliation|Banks|bank_reconciliation|`ReconciliationService`|WF §7|—|bank_*, reconciliation_line|`/banks/reconciliation`|Bank Reconciliation|`banks.reconcile`|matched ties|3|
|Document numbering|Numbering|number_sequence|`NumberingService.next`|—|—|number_sequence|`/settings/numbering`|—|`settings.configure`|unique under concurrency|1|
|Period close/reopen|Accounting|financial_period|`PeriodService`|WF §16|EM 11.5(year)|financial_period, journal_*|`/accounting/periods`|Trial Balance|`period.close`/`period.reopen`|closed rejects post|2|

## Newly-added infrastructure requirements (were untracked before this review)
| Requirement | Destination | Test | Phase |
|---|---|---|---|
|Transactional atomicity (multi-record post all-or-nothing)|§5 engines in DB transaction; BR (atomic)|integration: forced failure rolls back all|2+ (every post)|
|Background jobs idempotent + auditable|§14; DepreciationJob/PrepaidJob/RecurringJob/FXJob|job re-run produces no duplicate|1 (runner), 7/9 (jobs)|
|Concurrency safety (numbering, stock, posting, period, reconciliation)|§18/§23; row locks/sequences|parallel-load uniqueness/no-oversell|1–5|
|Security model (authN/Z, encryption, rate-limit, tenancy)|§18|permission + tenancy isolation tests|1|
|Deployment (web+worker+DB+queue, migrations, feature flags)|§20|CI pipeline green; migrate deploy|1|
|Backup / DR (PITR, RPO≤15m/RTO≤1h, restore drills)|§21|restore drill runbook|1 (infra)|
|Observability (structured logs, metrics, subledger-drift alarm)|§22|drift metric = 0 alarm|1 + ongoing|
|Performance (pagination, virtualization, indexes, materialized ledger)|§23|list P95 < 300ms @100k|1 + per module|
|Accounting-invariant CI suite (blocking)|§19|Σdr=Σcr, sub=GL, immutability, uniqueness|1 (harness) + all|

## Coverage summary
- **0 MISSING** transactional requirements (all mapped to service + test + phase).
- **0 MISSING** infrastructure requirements after this review (the 9 above were the gap; now tracked).
- **Decisions remaining:** 5, all non-blocking, each assigned to its pre-phase gate (see FINAL_ARCHITECTURE_REVIEW §26).
- **Blocking issues for Phase 1:** none.
