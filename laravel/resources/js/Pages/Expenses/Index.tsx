import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { AccountingAmount, Button, Card, EmptyState, MetricCard, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatDate, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { AccountOption, CurrencyOption, SharedPageProps } from '../../Types';

type TranslatedName = Record<string, string> | string | null;
type Branch = { id: string; code: string; name: TranslatedName };
type Supplier = { id: string; code: string; name: TranslatedName };
type SettlementAccount = { id: string; code: string; name: TranslatedName; branch_id?: string | null; currency: string; branch?: Branch | null };
type TaxCode = { id: string; code: string; name: TranslatedName };
type ExpenseAccount = AccountOption & { currency?: string | null };

type ExpenseCategory = {
  id: string;
  code: string;
  name: TranslatedName;
  default_expense_account_id?: string | null;
  default_tax_code_id?: string | null;
  requires_attachment?: boolean;
  default_expense_account?: ExpenseAccount | null;
  default_tax_code?: TaxCode | null;
};

type ExpenseLine = {
  id: string;
  line_no: number;
  expense_category_id: string;
  expense_account_id: string;
  description?: string | null;
  quantity_e6: number;
  unit_amount_minor: number;
  line_total_minor: number;
  tax_code_id?: string | null;
  tax_rate_bps: number;
  tax_amount_minor: number;
  gross_amount_minor: number;
  category?: ExpenseCategory | null;
  expense_account?: ExpenseAccount | null;
};

type ExpenseRow = {
  id: string;
  number?: string | null;
  expense_date: string;
  due_date?: string | null;
  branch_id?: string | null;
  supplier_id?: string | null;
  payee_name?: string | null;
  settlement_method: 'payable' | 'cash' | 'bank';
  cash_account_id?: string | null;
  bank_account_id?: string | null;
  currency: string;
  subtotal_minor: number;
  tax_amount_minor: number;
  total_minor: number;
  status: 'draft' | 'submitted' | 'approved' | 'posted' | 'cancelled';
  reference?: string | null;
  description?: string | null;
  lock_version: number;
  branch?: Branch | null;
  supplier?: Supplier | null;
  cash_account?: SettlementAccount | null;
  bank_account?: SettlementAccount | null;
  lines: ExpenseLine[];
};

type PaginatedData<T> = { data: T[]; total: number; links: any[] };
type LineForm = {
  expense_category_id: string;
  expense_account_id: string;
  description: string;
  quantity: string;
  unit_amount: string;
  tax_code_id: string;
};
type ExpenseForm = {
  expense_date: string;
  due_date: string;
  branch_id: string;
  supplier_id: string;
  payee_name: string;
  settlement_method: 'payable' | 'cash' | 'bank';
  cash_account_id: string;
  bank_account_id: string;
  currency: string;
  reference: string;
  description: string;
  lock_version: number;
  lines: LineForm[];
};

type Props = SharedPageProps & {
  expenses: PaginatedData<ExpenseRow>;
  categories: ExpenseCategory[];
  expenseAccounts: ExpenseAccount[];
  suppliers: Supplier[];
  cashAccounts: SettlementAccount[];
  bankAccounts: SettlementAccount[];
  branches: Branch[];
  currencies: CurrencyOption[];
  taxCodes: TaxCode[];
  statuses: Array<ExpenseRow['status']>;
  settlementMethods: Array<ExpenseRow['settlement_method']>;
  filters: { search?: string; status?: string; branch_id?: string };
};

function today(): string {
  return new Date().toISOString().slice(0, 10);
}

function amountToMinor(value: string): number {
  return Math.round(Number(value || 0) * 100);
}

function minorToAmount(value: number): string {
  return (Number(value || 0) / 100).toFixed(2);
}

function parseQuantityToE6(value: string): number {
  const normalized = value.trim().replace(/,/g, '');
  if (!/^\d+(\.\d{0,6})?$/.test(normalized)) return 0;
  const [wholeRaw, fractionRaw = ''] = normalized.split('.');
  const whole = Number(wholeRaw || '0');
  const fraction = Number(fractionRaw.padEnd(6, '0').slice(0, 6) || '0');

  if (!Number.isSafeInteger(whole) || !Number.isSafeInteger(fraction)) return 0;

  return whole * 1000000 + fraction;
}

function quantityFromE6(value: number): string {
  const absolute = Math.max(0, Math.trunc(Number(value || 0)));
  const whole = Math.floor(absolute / 1000000);
  const fraction = String(absolute % 1000000).padStart(6, '0').replace(/0+$/, '');

  return `${whole}${fraction ? `.${fraction}` : ''}`;
}

function statusTone(value: string): 'ok' | 'muted' | 'danger' | 'warning' | 'info' {
  if (value === 'posted') return 'ok';
  if (value === 'cancelled') return 'danger';
  if (value === 'approved') return 'warning';
  if (value === 'submitted') return 'info';

  return 'muted';
}

export default function ExpensesIndex({
  locale,
  expenses,
  categories = [],
  expenseAccounts = [],
  suppliers = [],
  cashAccounts = [],
  bankAccounts = [],
  branches = [],
  currencies = [],
  taxCodes = [],
  statuses = [],
  settlementMethods = [],
  filters,
}: Props) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.expenses;
  const can = useCan();
  const defaultCurrency = currencies[0]?.code || '';
  const [showForm, setShowForm] = useState(false);
  const [editing, setEditing] = useState<ExpenseRow | null>(null);
  const [search, setSearch] = useState(filters.search || '');
  const [status, setStatus] = useState(filters.status || '');
  const [branchId, setBranchId] = useState(filters.branch_id || '');

  const form = useForm<ExpenseForm>({
    expense_date: today(),
    due_date: '',
    branch_id: '',
    supplier_id: '',
    payee_name: '',
    settlement_method: 'payable',
    cash_account_id: '',
    bank_account_id: '',
    currency: defaultCurrency,
    reference: '',
    description: '',
    lock_version: 1,
    lines: [{ expense_category_id: '', expense_account_id: '', description: '', quantity: '1', unit_amount: '', tax_code_id: '' }],
  });

  const categoryOptions = useMemo(() => categories.map((category) => ({
    value: category.id,
    label: `${category.code} - ${getLocalizedName(category.name, locale)}`,
    sublabel: category.requires_attachment ? pageDict.requiresAttachment : undefined,
  })), [categories, locale, pageDict.requiresAttachment]);

  const accountOptions = useMemo(() => expenseAccounts.map((account) => ({
    value: account.id,
    label: `${account.code} - ${getLocalizedName(account.name, locale)}`,
    sublabel: account.currency || account.currency_code || undefined,
  })), [expenseAccounts, locale]);

  const supplierOptions = useMemo(() => suppliers.map((supplier) => ({
    value: supplier.id,
    label: `${supplier.code} - ${getLocalizedName(supplier.name, locale)}`,
  })), [suppliers, locale]);

  const branchOptions = useMemo(() => branches.map((branch) => ({
    value: branch.id,
    label: `${branch.code} - ${getLocalizedName(branch.name, locale)}`,
  })), [branches, locale]);

  const cashOptions = useMemo(() => cashAccounts.map((account) => ({
    value: account.id,
    label: `${account.code} - ${getLocalizedName(account.name, locale)}`,
    sublabel: account.branch ? `${account.branch.code} - ${getLocalizedName(account.branch.name, locale)}` : account.currency,
  })), [cashAccounts, locale]);

  const bankOptions = useMemo(() => bankAccounts.map((account) => ({
    value: account.id,
    label: `${account.code} - ${getLocalizedName(account.name, locale)}`,
    sublabel: account.branch ? `${account.branch.code} - ${getLocalizedName(account.branch.name, locale)}` : account.currency,
  })), [bankAccounts, locale]);

  const currencyOptions = useMemo(() => currencies.map((currency) => ({
    value: currency.code,
    label: `${currency.code} - ${getLocalizedName(currency.name, locale)}`,
  })), [currencies, locale]);

  const taxOptions = useMemo(() => taxCodes.map((taxCode) => ({
    value: taxCode.id,
    label: `${taxCode.code} - ${getLocalizedName(taxCode.name, locale)}`,
  })), [taxCodes, locale]);

  const statusOptions = statuses.map((item) => ({ value: item, label: pageDict.statuses[item] || item }));
  const methodOptions = settlementMethods.map((item) => ({ value: item, label: pageDict.methods[item] || item }));
  const visibleCurrencies = Array.from(new Set(expenses.data.map((row) => row.currency).filter(Boolean)));
  const visibleTotal = visibleCurrencies.length <= 1
    ? expenses.data.reduce((sum, row) => sum + Number(row.total_minor || 0), 0)
    : null;
  const postedCount = expenses.data.filter((row) => row.status === 'posted').length;
  const openPipeline = expenses.data.filter((row) => ['draft', 'submitted', 'approved'].includes(row.status)).length;
  const formSubtotal = form.data.lines.reduce((sum, line) => sum + amountToMinor(line.unit_amount) * (parseQuantityToE6(line.quantity) / 1000000), 0);
  const activeFilterCount = [search, status, branchId].filter(Boolean).length;

  function labelForStatus(value: string): string {
    return pageDict.statuses[value as keyof typeof pageDict.statuses] || value;
  }

  function labelForMethod(value: string): string {
    return pageDict.methods[value as keyof typeof pageDict.methods] || value;
  }

  function applyFilters() {
    router.get('/expenses', { search, status, branch_id: branchId }, { preserveScroll: true, preserveState: true });
  }

  function clearFilters() {
    setSearch('');
    setStatus('');
    setBranchId('');
    router.get('/expenses', {}, { preserveScroll: true, preserveState: true });
  }

  function blankLine(): LineForm {
    return { expense_category_id: '', expense_account_id: '', description: '', quantity: '1', unit_amount: '', tax_code_id: '' };
  }

  function openCreate() {
    setEditing(null);
    form.setData({
      expense_date: today(),
      due_date: '',
      branch_id: '',
      supplier_id: suppliers[0]?.id || '',
      payee_name: '',
      settlement_method: 'payable',
      cash_account_id: '',
      bank_account_id: '',
      currency: defaultCurrency,
      reference: '',
      description: '',
      lock_version: 1,
      lines: [blankLine()],
    });
    form.clearErrors();
    setShowForm(true);
  }

  function openEdit(expense: ExpenseRow) {
    setEditing(expense);
    form.setData({
      expense_date: formatDate(expense.expense_date),
      due_date: formatDate(expense.due_date),
      branch_id: expense.branch_id || '',
      supplier_id: expense.supplier_id || '',
      payee_name: expense.payee_name || '',
      settlement_method: expense.settlement_method,
      cash_account_id: expense.cash_account_id || '',
      bank_account_id: expense.bank_account_id || '',
      currency: expense.currency,
      reference: expense.reference || '',
      description: expense.description || '',
      lock_version: expense.lock_version,
      lines: expense.lines.map((line) => ({
        expense_category_id: line.expense_category_id,
        expense_account_id: line.expense_account_id,
        description: line.description || '',
        quantity: quantityFromE6(line.quantity_e6),
        unit_amount: minorToAmount(line.unit_amount_minor),
        tax_code_id: line.tax_code_id || '',
      })),
    });
    form.clearErrors();
    setShowForm(true);
  }

  function setLine(index: number, patch: Partial<LineForm>) {
    form.setData('lines', form.data.lines.map((line, lineIndex) => (lineIndex === index ? { ...line, ...patch } : line)));
  }

  function selectCategory(index: number, categoryId: string | null) {
    const category = categories.find((item) => item.id === categoryId);
    setLine(index, {
      expense_category_id: categoryId || '',
      expense_account_id: category?.default_expense_account_id || '',
      tax_code_id: category?.default_tax_code_id || '',
    });
  }

  function addLine() {
    form.setData('lines', [...form.data.lines, blankLine()]);
  }

  function removeLine(index: number) {
    form.setData('lines', form.data.lines.filter((_, lineIndex) => lineIndex !== index));
  }

  function submitForm(event: FormEvent) {
    event.preventDefault();
    const payload = {
      ...form.data,
      due_date: form.data.due_date || null,
      branch_id: form.data.branch_id || null,
      supplier_id: form.data.settlement_method === 'payable' ? form.data.supplier_id || null : null,
      cash_account_id: form.data.settlement_method === 'cash' ? form.data.cash_account_id || null : null,
      bank_account_id: form.data.settlement_method === 'bank' ? form.data.bank_account_id || null : null,
      payee_name: form.data.settlement_method === 'payable' ? null : form.data.payee_name || null,
      fx_rate_e6: 1000000,
      lines: form.data.lines.map((line) => ({
        expense_category_id: line.expense_category_id,
        expense_account_id: line.expense_account_id || null,
        description: line.description || null,
        quantity_e6: parseQuantityToE6(line.quantity),
        unit_amount_minor: amountToMinor(line.unit_amount),
        tax_code_id: line.tax_code_id || null,
      })),
    };

    if (editing) {
      router.put(`/expenses/${editing.id}`, payload, { preserveScroll: true, onSuccess: () => setShowForm(false) });
      return;
    }

    router.post('/expenses', payload, { preserveScroll: true, onSuccess: () => setShowForm(false) });
  }

  function transition(id: string, action: 'submit' | 'approve' | 'post' | 'cancel') {
    const message = pageDict.confirmations[action];
    if (message && !confirm(message)) return;

    router.post(`/expenses/${id}/${action}`, {}, { preserveScroll: true });
  }

  return (
    <AppLayout active="expenses.index">
      <Head title={pageDict.headTitle} />

      <PageHeader
        title={pageDict.title}
        description={pageDict.description}
        actions={can('expenses.create') ? <Button onClick={openCreate}>{pageDict.createExpense}</Button> : null}
      />

      <div className="mb-5 grid gap-4 md:grid-cols-3">
        <MetricCard
          label={pageDict.totalVisible}
          value={visibleTotal === null ? pageDict.mixedCurrency : <AccountingAmount amountMinor={visibleTotal} currency={visibleCurrencies[0] || form.data.currency || pageDict.noCurrency} />}
          tone="blue"
        />
        <MetricCard label={pageDict.postedCount} value={postedCount} tone="emerald" />
        <MetricCard label={pageDict.openPipeline} value={openPipeline} tone="amber" />
      </div>

      <Card className="mb-5 p-4">
        <div className="grid gap-3 lg:grid-cols-[1fr_220px_220px_auto_auto]">
          <input
            type="text"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            onKeyDown={(event) => {
              if (event.key === 'Enter') applyFilters();
            }}
            placeholder={pageDict.search}
            className="rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2 text-sm text-[var(--text-primary)] outline-hidden focus:border-[var(--primary)]"
          />
          <SearchableSelect options={statusOptions} value={status || null} onChange={(value) => setStatus(value || '')} placeholder={pageDict.allStatuses} />
          <SearchableSelect options={branchOptions} value={branchId || null} onChange={(value) => setBranchId(value || '')} placeholder={pageDict.allBranches} />
          <Button onClick={applyFilters}>{pageDict.applyFilter}</Button>
          <Button variant="secondary" onClick={clearFilters} disabled={activeFilterCount === 0}>{pageDict.clearFilter}</Button>
        </div>
      </Card>

      {showForm ? (
        <Card className="mb-5 p-5">
          <form onSubmit={submitForm} className="space-y-5">
            <div className="flex items-center justify-between gap-3 border-b border-[var(--border)] pb-3">
              <h2 className="text-base font-bold text-[var(--text-primary)]">
                {editing ? pageDict.editExpense : pageDict.createExpense}
              </h2>
              <Button variant="secondary" onClick={() => setShowForm(false)}>{pageDict.cancel}</Button>
            </div>

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
              <DatePicker label={pageDict.date} value={form.data.expense_date} onChange={(value) => form.setData('expense_date', value || today())} required />
              <DatePicker label={pageDict.dueDate} value={form.data.due_date} onChange={(value) => form.setData('due_date', value || '')} />
              <SearchableSelect options={branchOptions} value={form.data.branch_id || null} onChange={(value) => form.setData('branch_id', value || '')} label={pageDict.branch} />
              <SearchableSelect options={currencyOptions} value={form.data.currency} onChange={(value) => form.setData('currency', value || '')} label={pageDict.currency} isClearable={false} required />
              <SearchableSelect options={methodOptions} value={form.data.settlement_method} onChange={(value) => form.setData('settlement_method', (value as ExpenseForm['settlement_method']) || 'payable')} label={pageDict.settlementMethod} isClearable={false} required />
              {form.data.settlement_method === 'payable' ? (
                <SearchableSelect options={supplierOptions} value={form.data.supplier_id || null} onChange={(value) => form.setData('supplier_id', value || '')} label={pageDict.supplier} required />
              ) : null}
              {form.data.settlement_method === 'cash' ? (
                <SearchableSelect options={cashOptions} value={form.data.cash_account_id || null} onChange={(value) => form.setData('cash_account_id', value || '')} label={pageDict.cashAccount} required />
              ) : null}
              {form.data.settlement_method === 'bank' ? (
                <SearchableSelect options={bankOptions} value={form.data.bank_account_id || null} onChange={(value) => form.setData('bank_account_id', value || '')} label={pageDict.bankAccount} required />
              ) : null}
              {form.data.settlement_method !== 'payable' ? (
                <label className="block">
                  <span className="mb-1 block text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.payeeName}</span>
                  <input
                    type="text"
                    value={form.data.payee_name}
                    onChange={(event) => form.setData('payee_name', event.target.value)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2 text-sm text-[var(--text-primary)] outline-hidden focus:border-[var(--primary)]"
                  />
                </label>
              ) : null}
              <label className="block">
                <span className="mb-1 block text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.reference}</span>
                <input
                  type="text"
                  value={form.data.reference}
                  onChange={(event) => form.setData('reference', event.target.value)}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2 text-sm text-[var(--text-primary)] outline-hidden focus:border-[var(--primary)]"
                />
              </label>
              <label className="block md:col-span-2">
                <span className="mb-1 block text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.descriptionField}</span>
                <input
                  type="text"
                  value={form.data.description}
                  onChange={(event) => form.setData('description', event.target.value)}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2 text-sm text-[var(--text-primary)] outline-hidden focus:border-[var(--primary)]"
                />
              </label>
            </div>

            <div className="space-y-3">
              <div className="flex items-center justify-between gap-3">
                <h3 className="text-sm font-bold text-[var(--text-primary)]">{pageDict.lines}</h3>
                <Button variant="secondary" onClick={addLine}>{pageDict.addLine}</Button>
              </div>

              <div className="space-y-3">
                {form.data.lines.map((line, index) => (
                  <div key={index} className="grid gap-3 rounded-md border border-[var(--border)] bg-[var(--background)] p-3 md:grid-cols-2 xl:grid-cols-6">
                    <SearchableSelect options={categoryOptions} value={line.expense_category_id || null} onChange={(value) => selectCategory(index, value)} label={pageDict.category} required />
                    <SearchableSelect options={accountOptions} value={line.expense_account_id || null} onChange={(value) => setLine(index, { expense_account_id: value || '' })} label={pageDict.expenseAccount} />
                    <label className="block">
                      <span className="mb-1 block text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.quantity}</span>
                      <input
                        type="text"
                        value={line.quantity}
                        onChange={(event) => setLine(index, { quantity: event.target.value })}
                        className="w-full rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3.5 py-2 text-sm text-[var(--text-primary)] outline-hidden focus:border-[var(--primary)]"
                      />
                    </label>
                    <label className="block">
                      <span className="mb-1 block text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.unitAmount}</span>
                      <input
                        type="number"
                        step="0.01"
                        value={line.unit_amount}
                        onChange={(event) => setLine(index, { unit_amount: event.target.value })}
                        className="w-full rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3.5 py-2 text-sm text-[var(--text-primary)] outline-hidden focus:border-[var(--primary)]"
                      />
                    </label>
                    <SearchableSelect options={taxOptions} value={line.tax_code_id || null} onChange={(value) => setLine(index, { tax_code_id: value || '' })} label={pageDict.taxCode} />
                    <div className="flex items-end">
                      <Button variant="secondary" onClick={() => removeLine(index)} disabled={form.data.lines.length === 1}>{pageDict.removeLine}</Button>
                    </div>
                    <label className="block md:col-span-2 xl:col-span-6">
                      <span className="mb-1 block text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.lineDescription}</span>
                      <input
                        type="text"
                        value={line.description}
                        onChange={(event) => setLine(index, { description: event.target.value })}
                        className="w-full rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3.5 py-2 text-sm text-[var(--text-primary)] outline-hidden focus:border-[var(--primary)]"
                      />
                    </label>
                  </div>
                ))}
              </div>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-[var(--border)] pt-4">
              <AccountingAmount amountMinor={Math.round(formSubtotal)} currency={form.data.currency} />
              <Button type="submit">{pageDict.save}</Button>
            </div>
          </form>
        </Card>
      ) : null}

      {expenses.data.length === 0 ? (
        <EmptyState title={pageDict.noExpenses} description={pageDict.noExpensesDescription} />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{pageDict.number}</th>
                <th className={tableClasses.th}>{pageDict.date}</th>
                <th className={tableClasses.th}>{pageDict.settlementMethod}</th>
                <th className={tableClasses.th}>{pageDict.branch}</th>
                <th className={tableClasses.th}>{pageDict.supplier}</th>
                <th className={tableClasses.th}>{pageDict.total}</th>
                <th className={tableClasses.th}>{pageDict.status}</th>
                <th className={tableClasses.th}>{pageDict.actions}</th>
              </tr>
            </thead>
            <tbody>
              {expenses.data.map((expense) => (
                <tr key={expense.id} className="hover:bg-[var(--background)]/60">
                  <td className={`${tableClasses.td} font-mono text-xs font-bold`}>{expense.number || expense.reference || expense.id.slice(0, 8)}</td>
                  <td className={tableClasses.td}>{formatDate(expense.expense_date)}</td>
                  <td className={tableClasses.td}>{labelForMethod(expense.settlement_method)}</td>
                  <td className={tableClasses.td}>{expense.branch ? `${expense.branch.code} - ${getLocalizedName(expense.branch.name, locale)}` : pageDict.unassignedBranch}</td>
                  <td className={tableClasses.td}>
                    {expense.supplier ? `${expense.supplier.code} - ${getLocalizedName(expense.supplier.name, locale)}` : expense.payee_name || pageDict.noSupplier}
                  </td>
                  <td className={tableClasses.td}>
                    <AccountingAmount amountMinor={expense.total_minor} currency={expense.currency} />
                  </td>
                  <td className={tableClasses.td}>
                    <StatusBadge tone={statusTone(expense.status)}>{labelForStatus(expense.status)}</StatusBadge>
                  </td>
                  <td className={tableClasses.td}>
                    <div className="flex flex-wrap items-center gap-3">
                      {expense.status === 'draft' && can('expenses.edit') ? (
                        <button type="button" onClick={() => openEdit(expense)} className="text-xs font-bold text-[var(--primary)] hover:underline">
                          {pageDict.edit}
                        </button>
                      ) : null}
                      {expense.status === 'draft' && can('expenses.submit') ? (
                        <button type="button" onClick={() => transition(expense.id, 'submit')} className="text-xs font-bold text-blue-500 hover:underline">
                          {pageDict.submit}
                        </button>
                      ) : null}
                      {expense.status === 'submitted' && can('expenses.approve') ? (
                        <button type="button" onClick={() => transition(expense.id, 'approve')} className="text-xs font-bold text-amber-500 hover:underline">
                          {pageDict.approve}
                        </button>
                      ) : null}
                      {expense.status === 'approved' && can('expenses.post') && can('view_financials') ? (
                        <button type="button" onClick={() => transition(expense.id, 'post')} className="text-xs font-bold text-emerald-500 hover:underline">
                          {pageDict.post}
                        </button>
                      ) : null}
                      {['draft', 'submitted', 'approved'].includes(expense.status) && can('expenses.edit') ? (
                        <button type="button" onClick={() => transition(expense.id, 'cancel')} className="text-xs font-bold text-red-500 hover:underline">
                          {pageDict.cancelExpense}
                        </button>
                      ) : null}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </AppLayout>
  );
}
