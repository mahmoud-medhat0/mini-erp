import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import { Button, Card, PageHeader, SearchableSelect, StatusBadge } from '../../Components/Primitives';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import DatePicker from '../../Components/DatePicker';
import { formatMoney } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';

type TranslatedName = Record<string, string> | string | null;
type EmployeeOption = { id: string; code: string; name: TranslatedName; currency: string };
type CashAccountOption = { id: string; code: string; currency: string };
type BankAccountOption = { id: string; code: string; currency: string };

type Loan = {
  id: string;
  loan_type: string;
  currency: string;
  principal_minor: number;
  remaining_balance_minor: number;
  installment_amount_minor: number;
  disbursement_date: string;
  status: string;
  employee?: EmployeeOption | null;
};

type Props = SharedPageProps & {
  employees: EmployeeOption[];
  cashAccounts: CashAccountOption[];
  bankAccounts: BankAccountOption[];
  loanTypes: string[];
  disbursementMethods: string[];
  filters: { status?: string; employee_id?: string };
};

function namePart(name: TranslatedName, locale: 'en' | 'ar'): string {
  if (name && typeof name === 'object') return name[locale] || name.en || name.ar || '';
  return name || '';
}

function statusTone(value: string): 'ok' | 'muted' | 'danger' | 'warning' | 'info' {
  if (value === 'active') return 'info';
  if (value === 'completed') return 'ok';
  if (value === 'cancelled') return 'danger';
  return 'muted';
}

export default function PayrollLoans({ locale, employees, cashAccounts, bankAccounts, loanTypes, disbursementMethods, filters }: Props) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.payrollLoans;
  const activeLocale = locale === 'ar' ? 'ar' : 'en';
  const can = useCan();

  const todayStr = new Date().toISOString().split('T')[0];
  const [statusFilter, setStatusFilter] = useState(filters.status || '');
  const [showForm, setShowForm] = useState(false);
  const [settleTarget, setSettleTarget] = useState<Loan | null>(null);
  const [tableReloadToken, setTableReloadToken] = useState(0);

  const form = useForm({
    employee_id: employees[0]?.id || '',
    loan_type: 'loan',
    currency: employees[0]?.currency || '',
    principal_amount: '',
    installment_amount: '',
    disbursement_date: todayStr,
    disbursement_method: 'cash',
    cash_account_id: cashAccounts[0]?.id || '',
    bank_account_id: '',
    notes: '',
  });

  const settleForm = useForm({ reason: '' });

  const employeeOptions = useMemo(() => employees.map((item) => ({ value: item.id, label: `${item.code} - ${namePart(item.name, activeLocale)}` })), [employees, activeLocale]);
  const cashAccountOptions = useMemo(() => cashAccounts.map((item) => ({ value: item.id, label: `${item.code} (${item.currency})` })), [cashAccounts]);
  const bankAccountOptions = useMemo(() => bankAccounts.map((item) => ({ value: item.id, label: `${item.code} (${item.currency})` })), [bankAccounts]);
  const loanTypeOptions = loanTypes.map((item) => ({ value: item, label: pageDict.loanTypes[item as keyof typeof pageDict.loanTypes] || item }));
  const statusOptions = ['active', 'completed', 'cancelled'].map((item) => ({ value: item, label: pageDict.statuses[item as keyof typeof pageDict.statuses] || item }));

  function openCreate() {
    form.reset();
    form.setData({
      employee_id: employees[0]?.id || '',
      loan_type: 'loan',
      currency: employees[0]?.currency || '',
      principal_amount: '',
      installment_amount: '',
      disbursement_date: todayStr,
      disbursement_method: 'cash',
      cash_account_id: cashAccounts[0]?.id || '',
      bank_account_id: '',
      notes: '',
    });
    setShowForm(true);
  }

  function submitForm(event: FormEvent) {
    event.preventDefault();
    form.transform((data) => ({
      employee_id: data.employee_id,
      loan_type: data.loan_type,
      currency: data.currency,
      principal_minor: Math.round(Number(data.principal_amount || 0) * 100),
      installment_amount_minor: Math.round(Number(data.installment_amount || 0) * 100),
      disbursement_date: data.disbursement_date,
      disbursement_method: data.disbursement_method,
      cash_account_id: data.disbursement_method === 'cash' ? data.cash_account_id : null,
      bank_account_id: data.disbursement_method === 'bank' ? data.bank_account_id : null,
      notes: data.notes || null,
    }));

    form.post('/payroll/loans', {
      preserveScroll: true,
      onSuccess: () => {
        setShowForm(false);
        setTableReloadToken((value) => value + 1);
      },
    });
  }

  function cancelLoan(loan: Loan) {
    if (!confirm(pageDict.confirmCancel)) return;
    router.post(`/payroll/loans/${loan.id}/cancel`, {}, {
      preserveScroll: true,
      onSuccess: () => setTableReloadToken((value) => value + 1),
    });
  }

  function submitSettle(event: FormEvent) {
    event.preventDefault();
    if (!settleTarget) return;
    settleForm.post(`/payroll/loans/${settleTarget.id}/settle`, {
      preserveScroll: true,
      onSuccess: () => {
        setSettleTarget(null);
        settleForm.reset();
        setTableReloadToken((value) => value + 1);
      },
    });
  }

  const columns = useMemo(() => [
    { data: 'employee', name: 'employee', title: pageDict.employee, orderable: false, searchable: false },
    { data: 'loan_type', name: 'payroll_employee_loan.loan_type', title: pageDict.loanType },
    { data: 'principal_minor', name: 'payroll_employee_loan.principal_minor', title: pageDict.principal, className: 'text-end' },
    { data: 'remaining_balance_minor', name: 'payroll_employee_loan.remaining_balance_minor', title: pageDict.remaining, className: 'text-end' },
    { data: 'installment_amount_minor', name: 'payroll_employee_loan.installment_amount_minor', title: pageDict.installment, className: 'text-end' },
    { data: 'status', name: 'payroll_employee_loan.status', title: pageDict.status },
    { data: 'actions', name: 'actions', title: pageDict.actions, orderable: false, searchable: false, className: 'text-end' },
  ], [pageDict]);

  const slots = useMemo<DataTableSlots>(() => ({
    employee: (_v: unknown, _t: unknown, row: Loan) => <span className="font-medium">{row.employee ? `${row.employee.code} - ${namePart(row.employee.name, activeLocale)}` : ''}</span>,
    'payroll_employee_loan.loan_type': (value: string) => pageDict.loanTypes[value as keyof typeof pageDict.loanTypes] || value,
    'payroll_employee_loan.principal_minor': (value: number, _t: unknown, row: Loan) => <span>{formatMoney(value, row.currency)}</span>,
    'payroll_employee_loan.remaining_balance_minor': (value: number, _t: unknown, row: Loan) => <span className="font-semibold">{formatMoney(value, row.currency)}</span>,
    'payroll_employee_loan.installment_amount_minor': (value: number, _t: unknown, row: Loan) => <span>{formatMoney(value, row.currency)}</span>,
    'payroll_employee_loan.status': (value: string) => <StatusBadge tone={statusTone(value)}>{pageDict.statuses[value as keyof typeof pageDict.statuses] || value}</StatusBadge>,
    actions: (_v: unknown, _t: unknown, row: Loan) => (
      <div className="flex flex-wrap justify-end gap-2">
        {row.status === 'active' && can('payroll.edit') ? <Button variant="secondary" onClick={() => { settleForm.reset(); setSettleTarget(row); }}>{pageDict.settle}</Button> : null}
        {row.status === 'active' && row.remaining_balance_minor === row.principal_minor && can('payroll.edit') ? <Button variant="danger" onClick={() => cancelLoan(row)}>{pageDict.cancel}</Button> : null}
      </div>
    ),
  }), [activeLocale, can, pageDict]);

  const tableFilters = useMemo(() => ({ status: statusFilter }), [statusFilter]);

  const toolbar = (
    <div className="flex flex-wrap items-center gap-2">
      <div className="w-44"><SearchableSelect options={[{ value: '', label: pageDict.allStatuses }, ...statusOptions]} value={statusFilter || null} onChange={(value) => setStatusFilter(value || '')} /></div>
    </div>
  );

  return (
    <AppLayout active="payroll.loans.index">
      <Head title={pageDict.headTitle} />
      <PageHeader title={pageDict.title} description={pageDict.description} actions={can('payroll.create') ? <Button onClick={openCreate}>{pageDict.create}</Button> : null} />

      {showForm ? (
        <Card className="mb-5 p-5">
          <form onSubmit={submitForm} className="space-y-4">
            <div className="flex items-center justify-between">
              <h2 className="m-0 text-base font-bold text-[var(--text-primary)]">{pageDict.createTitle}</h2>
              <Button type="button" variant="secondary" onClick={() => setShowForm(false)}>{pageDict.close}</Button>
            </div>
            <div className="grid gap-4 sm:grid-cols-3">
              <SearchableSelect label={pageDict.employee} value={form.data.employee_id || null} onChange={(value) => form.setData('employee_id', value || '')} options={employeeOptions} isClearable={false} required error={form.errors.employee_id} />
              <SearchableSelect label={pageDict.loanType} value={form.data.loan_type} onChange={(value) => form.setData('loan_type', value || 'loan')} options={loanTypeOptions} isClearable={false} required />
              <DatePicker label={pageDict.disbursementDate} value={form.data.disbursement_date} onChange={(value) => form.setData('disbursement_date', value || '')} required />
            </div>
            <div className="grid gap-4 sm:grid-cols-3">
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.principal}
                <input className="input mt-1" type="number" step="0.01" min="0.01" value={form.data.principal_amount} onChange={(event) => form.setData('principal_amount', event.target.value)} required />
                {form.errors['principal_minor' as keyof typeof form.errors] ? <p className="mt-1 text-xs text-rose-600">{form.errors['principal_minor' as keyof typeof form.errors]}</p> : null}
              </label>
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.installment}
                <input className="input mt-1" type="number" step="0.01" min="0.01" value={form.data.installment_amount} onChange={(event) => form.setData('installment_amount', event.target.value)} required />
                {form.errors['installment_amount_minor' as keyof typeof form.errors] ? <p className="mt-1 text-xs text-rose-600">{form.errors['installment_amount_minor' as keyof typeof form.errors]}</p> : null}
              </label>
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.currency}
                <input className="input mt-1" value={form.data.currency} onChange={(event) => form.setData('currency', event.target.value.toUpperCase())} maxLength={3} required />
              </label>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <SearchableSelect
                label={pageDict.disbursementMethod}
                value={form.data.disbursement_method}
                onChange={(value) => form.setData('disbursement_method', value || 'cash')}
                options={disbursementMethods.map((item) => ({ value: item, label: pageDict.disbursementMethods[item as keyof typeof pageDict.disbursementMethods] || item }))}
                isClearable={false}
                required
              />
              {form.data.disbursement_method === 'cash' ? (
                <SearchableSelect label={pageDict.cashAccount} value={form.data.cash_account_id || null} onChange={(value) => form.setData('cash_account_id', value || '')} options={cashAccountOptions} isClearable={false} required error={form.errors.cash_account_id} />
              ) : (
                <SearchableSelect label={pageDict.bankAccount} value={form.data.bank_account_id || null} onChange={(value) => form.setData('bank_account_id', value || '')} options={bankAccountOptions} isClearable={false} required error={form.errors.bank_account_id} />
              )}
            </div>
            <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
              {pageDict.notes}
              <textarea className="input mt-1 min-h-20" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
            </label>
            <div className="flex justify-end gap-2">
              <Button type="button" variant="secondary" onClick={() => setShowForm(false)}>{pageDict.cancelAction}</Button>
              <Button type="submit" disabled={form.processing}>{pageDict.disburse}</Button>
            </div>
          </form>
        </Card>
      ) : null}

      <Card className="overflow-hidden p-0">
        <ServerDataTable
          ajaxUrl="/payroll/loans/data"
          columns={columns}
          filters={tableFilters}
          locale={locale}
          order={[[0, 'asc']]}
          reloadToken={tableReloadToken}
          slots={slots}
          tableId="payroll-loans-data-table"
          toolbar={toolbar}
        />
      </Card>

      {settleTarget ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
          <div className="w-full max-w-md rounded-lg bg-white p-6 shadow-xl dark:bg-slate-800">
            <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">{pageDict.settleTitle}</h3>
            <p className="mt-1 text-xs text-[var(--text-secondary)]">{pageDict.settleHint}</p>
            <form onSubmit={submitSettle} className="mt-4 space-y-4">
              <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                {pageDict.settleReason}
                <textarea className="input mt-1 min-h-20" value={settleForm.data.reason} onChange={(event) => settleForm.setData('reason', event.target.value)} required />
              </label>
              <div className="flex justify-end gap-2.5 pt-2">
                <Button type="button" variant="secondary" onClick={() => setSettleTarget(null)}>{pageDict.cancelAction}</Button>
                <Button type="submit" disabled={settleForm.processing}>{pageDict.confirmSettle}</Button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
