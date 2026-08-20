# DATABASE DESIGN — Domain Data Model

PostgreSQL + Prisma. Conventions below apply to **every** table. Separation of concerns is strict: **operational documents** (invoices, POs, rentals…) are distinct from **accounting ledger** rows; UI/services touch operational tables and call the posting service — **no client or module writes `journal_line`/`ledger_entry`/`stock_movement` directly**.

## Global conventions
- **PK:** `id` (uuid v7 / bigserial). **Tenancy/scope:** `company_id`, `branch_id` on business tables; `project_id`, `cost_center_id` nullable on financial/operational lines.
- **Money:** `*_minor BIGINT` (base currency) + `*_txn_minor BIGINT` + `currency CHAR(3)` + `fx_rate NUMERIC(18,8)` where multi-currency. **No float/double anywhere on money.**
- **Audit:** `created_by`, `created_at`, `updated_by`, `updated_at`; lifecycle stamps (`submitted/approved/posted/cancelled/reversed_by/at`) where applicable. Immutable financial rows: no `UPDATE`/`DELETE` after `posted` (enforced by trigger + app).
- **Soft delete:** `deleted_at`/`voided_at` on operational drafts only. **Posted financial docs never hard-deleted.**
- **Statuses:** Postgres `ENUM` or check-constrained text; every doc has a `status` enum matching WORKFLOW_CATALOG.
- **Uniqueness:** document `number` unique per `(company_id, doc_type, number)`; sequence allocation concurrency-safe (row lock).
- **Indexes:** FKs; `(company_id, branch_id, date)`; `(source_type, source_id)` on journal/stock; party+date on subledger tables; partial indexes on `status='posted'` for reporting.
- **i18n:** user-created master data carries `name_en` + `name_ar` (and `description_en/ar` where relevant).

## Core / Org & Security
| Table | Key fields | Relationships / constraints |
|---|---|---|
|`company`|name_en/ar, base_currency, settings|1‑N branch, users|
|`branch`|company_id, name_en/ar, code|FK company; unique(company,code)|
|`app_user`|email, name, locale, theme, status|N‑N role|
|`role`|name, is_template|N‑N permission via `role_permission`|
|`permission`|module, feature, action|seeded catalog|
|`role_permission`|role_id, permission_id, scope_json|scope: branch/wh/project/cc/doc_type|
|`user_role`|user_id, role_id, scope_json|—|
|`number_sequence`|doc_type, prefix, include_year, include_branch, padding, next_value, reset_policy|unique(company,doc_type,branch?)|
|`approval_flow` / `approval_step` / `approval_action`|doc_type, order, approver, condition_json / decision, reason|—|
|`audit_log`|entity_type, entity_id, action, actor, before_json, after_json, at, ip|append-only|
|`attachment`|entity_type, entity_id, file_ref, name|polymorphic|
|`notification`|type, target_ref, actor, read, at, user_id|—|

## Accounting
| Table | Key fields | Constraints |
|---|---|---|
|`account`|code, name_en/ar, type ENUM(asset,liability,equity,revenue,expense), group_id, parent_id, nature ENUM(debit,credit), is_control, currency?, status|unique(company,code); tree via parent_id|
|`account_group`|name_en/ar, statement_section|for statement rollups|
|`fiscal_year`|year, start, end, status|—|
|`financial_period`|fiscal_year_id, month, start, end, status ENUM(open,closed,reopened)|unique(fy,month)|
|`journal_entry`|number, date, description, reference, source_type, source_id, currency, fx_rate, status, lifecycle stamps|index(source_type,source_id); index(date)|
|`journal_line`|entry_id, account_id, debit_minor, credit_minor, debit_txn_minor, credit_txn_minor, cost_center_id, project_id, branch_id, tax_id, memo|**trigger: Σdebit=Σcredit per entry on post**; account not control-manual|
|`ledger_entry`|account_id, entry_id, date, debit_minor, credit_minor, running_balance?, dims|materialized from posted lines|
|`exchange_rate`|currency, date, rate|unique(currency,date)|
|`opening_balance`|account_id/party, amount, as_of|—|

## Parties & Subledgers
| Table | Key fields |
|---|---|
|`customer`|code, name_en/ar, contacts_json, opening_balance_minor, credit_limit_minor, payment_terms_id, status|
|`supplier`|code, name_en/ar, contacts_json, opening_balance_minor, payment_terms_id, status|
|`ar_entry` / `ap_entry`|party_id, doc_type, doc_id, date, debit_minor, credit_minor, allocation_ref|reconciles to AR/AP control account|
|`payment_terms`|name_en/ar, net_days, rules|
|`allocation`|source(receipt/payment), target(invoice), amount_minor|

## Sales / Purchasing
| Table | Key fields |
|---|---|
|`sales_doc`|number, type ENUM(quotation,order,delivery,invoice,credit_note), customer_id, warehouse_id, project_id, cost_center_id, currency, fx_rate, date, due_date, status, subtotal/discount/tax/total/paid/remaining _minor|
|`sales_doc_line`|doc_id, product_id, qty, uom_id, unit_price_txn_minor, discount, tax_id, line_total_minor|
|`receipt`|number, customer_id, method ENUM(cash,bank,cheque), account_id, amount_minor, date, status|
|`purchase_doc`|number, type ENUM(request,order,grn,invoice,debit_note), supplier_id, warehouse_id, dims, status, totals|
|`purchase_doc_line`|doc_id, product_id, qty, uom_id, unit_cost_txn_minor, discount, tax_id, line_total_minor|
|`payment`|number, supplier_id, method, account_id, amount_minor, withholding_minor, date, status|
|`landed_cost`|grn_id, type, amount_minor, allocation_basis|

## Inventory
| Table | Key fields | Constraints |
|---|---|---|
|`product`|sku, barcode, name_en/ar, category_id, brand_id, base_uom_id, cost_method ENUM(wavg,fifo), standard_cost_minor, sell_price_minor, min_stock, reorder_level, is_stock_tracked|unique(company,sku); unique(barcode)|
|`product_category` / `brand`|name_en/ar, parent_id|
|`uom` / `uom_conversion`|code, name_en/ar / from_uom, to_uom, factor|
|`warehouse` / `location`|name_en/ar, code / warehouse_id, code|
|`stock_movement`|product_id, warehouse_id, location_id?, type ENUM(...), qty_signed, uom_id, unit_cost_minor, source_type, source_id, date|index(product,warehouse,date); index(source)|
|`stock_layer`|product_id, warehouse_id, qty_remaining, unit_cost_minor, in_date|FIFO layers|
|`stock_count` / `stock_count_line`|warehouse_id, status / product_id, counted_qty, system_qty, variance|

## Tools/Equipment & Rentals
| Table | Key fields |
|---|---|
|`equipment`|code, name_en/ar, category, serial, condition, value_minor, location, responsible_employee_id, status ENUM(available,assigned,rented,maintenance,damaged,lost,disposed), fixed_asset_id?|
|`custody_event`|equipment_id, type, from_ref, to_ref, date, notes|
|`maintenance_record`|equipment_id, date, cost_minor, notes, status|
|`rental_contract`|number, customer_id, start, end, daily_rate_minor, monthly_rate_minor, deposit_minor, discount, status, totals|
|`rental_line`|contract_id, equipment_id, qty|
|`rental_charge`|contract_id, type ENUM(extra,late,damage,additional), amount_minor|
|`rental_inspection`|contract_id, date, findings, damage_minor|

## Expenses / Prepaid / Accrual / Fixed Assets / Payroll
| Table | Key fields |
|---|---|
|`expense`|number, category_id, account_id, supplier_id?, employee_id?, dims, method, tax_id, amount_minor, status|
|`expense_category`|name_en/ar, default_account_id|
|`prepaid_schedule` / `prepaid_recognition`|total_minor, start, months, recognized_minor / period, amount_minor, posted|
|`accrual_schedule` / `accrual_entry`|—|
|`fixed_asset`|code, name_en/ar, category, purchase_date, cost_minor, residual_minor, useful_life_months, method, accum_deprec_minor, nbv_minor, location, responsible|
|`depreciation_entry`|asset_id, period, amount_minor, posted|
|`asset_disposal` / `asset_revaluation` / `asset_transfer`|asset_id, date, proceeds/gain/loss_minor …|
|`employee`|code, name_en/ar, dept, position, base_salary_minor, allowances_json, deductions_json|
|`payroll_run` / `payroll_line`|period, status / employee_id, gross, allowances, overtime, bonus, deductions, loan, advance, net _minor|
|`employee_loan` / `employee_advance`|employee_id, amount_minor, balance_minor, schedule|

## Tax / Equity / Projects / Budgeting / Recurring / Cheques / Banks
| Table | Key fields |
|---|---|
|`tax`|name_en/ar, kind ENUM(input_vat,output_vat,withholding), rate, account_id, is_compound, effective_from/to|
|`tax_period` / `tax_return`|period, status(draft,filed) / net_minor|
|`partner` / `capital_contribution` / `partner_withdrawal` / `partner_current_account` / `partner_loan` / `profit_distribution`|party, amount_minor, date, status|
|`project`|code, name_en/ar, status, budget_minor|
|`cost_center`|code, name_en/ar, type ENUM(dept,branch,project,unit), parent_id|
|`budget` / `budget_line`|year, version, status / account/project/cc, period, amount_minor|
|`forecast` / `forecast_line`|type ENUM(sales,expense,cash,profit), period, amount_minor|
|`recurring_template` / `recurring_run`|doc_type, frequency, start, end, next_run, payload_json, auto_create, approval_required / run_date, status, generated_doc_ref|
|`cash_account` / `cash_txn` / `petty_cash`|—|
|`bank_account` / `bank_txn` / `bank_reconciliation` / `reconciliation_line`|—|
|`cheque` / `cheque_event`|direction, number, bank, amount_minor, issue_date, due_date, party, status|

## Referential integrity highlights
- `journal_line.account_id` → `account`; posting trigger blocks manual posting to `is_control` accounts.
- `stock_movement.source_*`, `journal_entry.source_*` reference operational docs polymorphically (validated in service layer).
- Subledger control reconciliation checked by a scheduled integrity job (BUSINESS_RULES).
- Deleting a master row referenced by posted transactions is blocked (RESTRICT); masters are deactivated, not deleted.
