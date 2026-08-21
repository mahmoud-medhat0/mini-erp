# DATABASE DESIGN - Domain Data Model

> Current status: legacy/generated target design, not current Laravel source of truth.
>
> Post-audit correction rule, 2026-08-21: do not treat Company as a tenant and do not add Company/User/Branch ownership, `company_id`, `branch_id`, Spatie Teams, current-company/current-branch context, or company/branch numbering dimensions unless an explicit owner decision requires that exact relationship. FiscalYear ownership/context is explicitly `SINGLE-ERP CONTEXT`: global fiscal years, no Company/Tenant scope. Any older company/branch scoping claim in this file is stale unless re-approved.

PostgreSQL target design reference. The current Laravel foundation uses Eloquent / Laravel Query Builder migrations and application services. Old Prisma schema details are historical only and are not current target architecture. Separation of concerns remains the intended direction: operational documents are distinct from accounting ledger rows, and future UI/services must call application/domain services instead of writing ledger or stock rows directly.

## Global conventions
- **PK:** `id` (uuid / bigserial as appropriate). **Relationship rule:** no default tenancy/scope columns. Do not add `company_id` or `branch_id` to business tables unless an explicit owner decision establishes that relationship. `project_id` / `cost_center_id` / branch-like dimensions may be added only when their exact business meaning is confirmed.
- **Money:** `*_minor BIGINT` (base currency) + `*_txn_minor BIGINT` + `currency CHAR(3)` + `fx_rate NUMERIC(18,8)` where multi-currency. **No float/double anywhere on money.**
- **Audit:** `created_by`, `created_at`, `updated_by`, `updated_at`; lifecycle stamps (`submitted/approved/posted/cancelled/reversed_by/at`) where applicable. Immutable financial rows: no `UPDATE`/`DELETE` after `posted` (enforced by trigger + app).
- **Soft delete:** `deleted_at`/`voided_at` on operational drafts only. **Posted financial docs never hard-deleted.**
- **Statuses:** Postgres `ENUM` or check-constrained text; every doc has a `status` enum matching WORKFLOW_CATALOG.
- **Uniqueness:** document-number dimensions are unresolved. Current Laravel numbering is concurrency-safe by global sequence `key`; production document numbering dimensions need owner decision before implementation.
- **Indexes:** FKs and access-pattern indexes. Do not create company/branch scope indexes unless the relationship is explicitly approved.
- **i18n:** user-created master data uses multilingual stored business fields where required. In Laravel, use Spatie Laravel Translatable JSON where appropriate. Do not force `name_en`/`name_ar` columns unless a concrete schema decision requires them. Static UI translation remains separate.

## Core / Org & Security
| Table | Key fields | Relationships / constraints |
|---|---|---|
|`company`|multilingual name, base_currency, settings|Configuration/profile only; does not imply tenant, users, branches, roles, or permissions|
|`branch`|multilingual name, code if approved|Referenced/reporting concept only until owner defines exact model; no Company FK assumed|
|`app_user` / `users`|email, name, locale, theme, status|N-N role/permission; no Company or Branch relationship assumed|
|`role`|name, is_template|N‑N permission via `role_permission`|
|`permission`|module, feature, action|seeded catalog|
|`role_permission`|role_id, permission_id, scope_json|`scope_json` reserved/undefined; do not interpret as company/branch tenancy|
|`user_role`|user_id, role_id, scope_json|—|
|`number_sequence`|doc_type, prefix, include_year, padding, next_value, reset_policy|current Laravel unique by global `key`; no company/branch dimension|
|`approval_flow` / `approval_step` / `approval_action`|doc_type, order, approver, condition_json / decision, reason|—|
|`audit_log`|entity_type, entity_id, action, actor, before_json, after_json, at, ip|append-only|
|`attachment`|entity_type, entity_id, file_ref, name|polymorphic|
|`notification`|type, target_ref, actor, read, at, user_id|—|

## Accounting
| Table | Key fields | Constraints |
|---|---|---|
|`account`|code, multilingual name, type ENUM(asset,liability,equity,revenue,expense), group_id, parent_id, nature ENUM(debit,credit), is_control, currency?, status|account code uniqueness scope needs owner decision; tree via parent_id|
|`account_group`|multilingual name, statement_section|for statement rollups|
|`fiscal_year`|year, start, end, status|single-ERP context; no company_id; global unique(year)|
|`financial_period`|fiscal_year_id, month, start, end, status ENUM(open,closed,reopened)|unique(fy,month)|
|`journal_entry`|number, date, description, reference, source_type, source_id, currency, fx_rate, status, lifecycle stamps|index(source_type,source_id); index(date)|
|`journal_line`|entry_id, account_id, debit_minor, credit_minor, debit_txn_minor, credit_txn_minor, confirmed accounting dimensions, tax_id, memo|Branch/Department dimensions are OWNER DECISION REQUIRED and must not be specified as confirmed `branch_id`; account not control-manual; balance enforcement on post|
|`ledger_entry`|account_id, entry_id, date, debit_minor, credit_minor, running_balance?, dims|materialized from posted lines|
|`exchange_rate`|currency, date, rate|unique(currency,date)|
|`opening_balance`|account_id/party, amount, as_of|—|

## Parties & Subledgers
| Table | Key fields |
|---|---|
|`customer`|code, multilingual name, contacts_json, opening_balance_minor, credit_limit_minor, payment_terms_id, status|
|`supplier`|code, multilingual name, contacts_json, opening_balance_minor, payment_terms_id, status|
|`ar_entry` / `ap_entry`|party_id, doc_type, doc_id, date, debit_minor, credit_minor, allocation_ref|reconciles to AR/AP control account|
|`payment_terms`|multilingual name, net_days, rules|
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
|`product`|sku, barcode, multilingual name, category_id, brand_id, base_uom_id, cost_method ENUM(wavg,fifo), standard_cost_minor, sell_price_minor, min_stock, reorder_level, is_stock_tracked|SKU uniqueness scope needs owner decision; unique(barcode) if required|
|`product_category` / `brand`|multilingual name, parent_id|
|`uom` / `uom_conversion`|code, multilingual name / from_uom, to_uom, factor|
|`warehouse` / `location`|multilingual name, code / warehouse_id, code|
|`stock_movement`|product_id, warehouse_id, location_id?, type ENUM(...), qty_signed, uom_id, unit_cost_minor, source_type, source_id, date|index(product,warehouse,date); index(source)|
|`stock_layer`|product_id, warehouse_id, qty_remaining, unit_cost_minor, in_date|FIFO layers|
|`stock_count` / `stock_count_line`|warehouse_id, status / product_id, counted_qty, system_qty, variance|

## Tools/Equipment & Rentals
| Table | Key fields |
|---|---|
|`equipment`|code, multilingual name, category, serial, condition, value_minor, location, responsible_employee_id, status ENUM(available,assigned,rented,maintenance,damaged,lost,disposed), fixed_asset_id?|
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
|`expense_category`|multilingual name, default_account_id|
|`prepaid_schedule` / `prepaid_recognition`|total_minor, start, months, recognized_minor / period, amount_minor, posted|
|`accrual_schedule` / `accrual_entry`|—|
|`fixed_asset`|code, multilingual name, category, purchase_date, cost_minor, residual_minor, useful_life_months, method, accum_deprec_minor, nbv_minor, location, responsible|
|`depreciation_entry`|asset_id, period, amount_minor, posted|
|`asset_disposal` / `asset_revaluation` / `asset_transfer`|asset_id, date, proceeds/gain/loss_minor …|
|`employee`|code, multilingual name, department concept if approved, position, base_salary_minor, allowances_json, deductions_json|
|`payroll_run` / `payroll_line`|period, status / employee_id, gross, allowances, overtime, bonus, deductions, loan, advance, net _minor|
|`employee_loan` / `employee_advance`|employee_id, amount_minor, balance_minor, schedule|

## Tax / Equity / Projects / Budgeting / Recurring / Cheques / Banks
| Table | Key fields |
|---|---|
|`tax`|multilingual name, kind ENUM(input_vat,output_vat,withholding), rate, account_id, is_compound, effective_from/to|
|`tax_period` / `tax_return`|period, status(draft,filed) / net_minor|
|`partner` / `capital_contribution` / `partner_withdrawal` / `partner_current_account` / `partner_loan` / `profit_distribution`|party, amount_minor, date, status|
|`project`|code, multilingual name, status, budget_minor|
|`cost_center`|code, multilingual name, classification/type if approved, parent_id if approved|Do not infer CostCenter -> Company, CostCenter -> Branch, or Department hierarchy|
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
