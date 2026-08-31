import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { AccountingAmount, Button, Card, EmptyState, PageHeader, SearchableSelect, SensitiveActionModal, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatDate, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { PaginationLink, CurrencyOption, SharedPageProps } from '../../Types';

type TranslatedName = Record<string, string> | string | null;
type Branch = { id: string; code: string; name: TranslatedName };
type Period = { id: string; year: number; month: number; start_date: string; end_date: string; payment_date: string; status: string };
type Employee = { id: string; code: string; name: TranslatedName };
type RunLineComponent = { id: string; code: string; name: TranslatedName; type: 'earning' | 'deduction'; amount_minor: number };
type RunLine = {
  id: string;
  employee?: Employee | null;
  branch_id?: string | null;
  base_salary_minor: number;
  earnings_minor: number;
  deductions_minor: number;
  gross_minor: number;
  net_minor: number;
  components?: RunLineComponent[];
};
type PayrollRun = {
  id: string;
  number?: string | null;
  payroll_period_id: string;
  branch_id?: string | null;
  payroll_date: string;
  run_type: string;
  currency: string;
  status: 'draft' | 'submitted' | 'approved' | 'posted' | 'cancelled';
  employee_count: number;
  gross_minor: number;
  deductions_minor: number;
  net_minor: number;
  reference?: string | null;
  description?: string | null;
  period?: Period | null;
  branch?: Branch | null;
  journal_entry?: { id: string; number?: string | null } | null;
  lines?: RunLine[];
};
type PaginatedData<T> = { data: T[]; total: number; links: PaginationLink[] };
type Props = SharedPageProps & {
  runs: PaginatedData<PayrollRun>;
  periods: Period[];
  branches: Branch[];
  currencies: CurrencyOption[];
  statuses: string[];
  runTypes: string[];
  filters: { search?: string; status?: string; branch_id?: string };
};

function currentYear(): number {
  return new Date().getFullYear();
}

function currentMonth(): number {
  return new Date().getMonth() + 1;
}

function monthEnd(year: number, month: number): string {
  return new Date(year, month, 0).toISOString().slice(0, 10);
}

function statusTone(value: string): 'ok' | 'muted' | 'danger' | 'warning' | 'info' {
  if (value === 'posted') return 'ok';
  if (value === 'cancelled') return 'danger';
  if (value === 'approved') return 'warning';
  if (value === 'submitted') return 'info';

  return 'muted';
}

export default function PayrollRunsIndex({
  locale,
  runs,
  periods = [],
  branches = [],
  currencies = [],
  statuses = [],
  runTypes = [],
  filters,
}: Props) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.payrollRuns;
  const shared = dict.app.pages.payrollShared;
  const statusLabels = pageDict.statuses as Record<string, string>;
  const runTypeLabels = pageDict.runTypes as Record<string, string>;
  const can = useCan();
  const canViewPayroll = can('view_payroll');
  const canCreatePayrollRuns = can('payroll.create') && canViewPayroll;
  const canRegeneratePayrollRuns = can('payroll.edit') && canViewPayroll;
  const canSubmitPayrollRuns = can('payroll.submit') && canViewPayroll;
  const canApprovePayrollRuns = can('payroll.approve') && canViewPayroll;
  const canPostPayrollRuns = can('payroll.post') && canViewPayroll && can('view_financials');
  const canCancelPayrollRuns = canRegeneratePayrollRuns;
  const defaultCurrency = currencies[0]?.code || '';
  const [showForm, setShowForm] = useState(false);
  const [selectedRun, setSelectedRun] = useState<PayrollRun | null>(runs.data[0] || null);
  const [search, setSearch] = useState(filters.search || '');
  const [status, setStatus] = useState(filters.status || '');
  const [branchId, setBranchId] = useState(filters.branch_id || '');
  const [postingRun, setPostingRun] = useState<PayrollRun | null>(null);
  const [postRunProcessing, setPostRunProcessing] = useState(false);

  const branchOptions = useMemo(() => branches.map((item) => ({ value: item.id, label: `${item.code} - ${getLocalizedName(item.name, locale)}` })), [branches, locale]);
  const currencyOptions = useMemo(() => currencies.map((item) => ({ value: item.code, label: `${item.code} - ${getLocalizedName(item.name, locale)}` })), [currencies, locale]);
  const statusOptions = statuses.map((item) => ({ value: item, label: statusLabels[item] || item }));
  const runTypeOptions = runTypes.map((item) => ({ value: item, label: runTypeLabels[item] || item }));
  const year = currentYear();
  const month = currentMonth();
  const activeFilterCount = [search, status, branchId].filter(Boolean).length;

  const form = useForm({
    year,
    month,
    payment_date: monthEnd(year, month),
    branch_id: '',
    run_type: 'regular',
    currency: defaultCurrency,
    reference: '',
    description: '',
  });

  function applyFilters() {
    router.get('/payroll/runs', { search, status, branch_id: branchId }, { preserveScroll: true, preserveState: true });
  }

  function clearFilters() {
    setSearch('');
    setStatus('');
    setBranchId('');
    router.get('/payroll/runs', {}, { preserveScroll: true, preserveState: true });
  }

  function submitForm(event: FormEvent) {
    event.preventDefault();
    router.post('/payroll/runs', {
      ...form.data,
      branch_id: form.data.branch_id || null,
    }, { preserveScroll: true, onSuccess: () => setShowForm(false) });
  }

  function action(url: string) {
    router.post(url, {}, { preserveScroll: true });
  }

  function postPayrollRun(payload: { confirm_action: string; reason?: string }) {
    if (!postingRun) return;

    setPostRunProcessing(true);
    router.post(`/payroll/runs/${postingRun.id}/post`, payload, {
      preserveScroll: true,
      onSuccess: () => setPostingRun(null),
      onFinish: () => setPostRunProcessing(false),
    });
  }

  const isPayrollRunLifecycleActionable = (run: PayrollRun) => ['draft', 'submitted', 'approved'].includes(run.status);

  const hasAvailablePayrollRunLifecycleAction = (run: PayrollRun) => (
    run.status === 'draft'
      ? canRegeneratePayrollRuns || canSubmitPayrollRuns || canCancelPayrollRuns
      : run.status === 'submitted'
        ? canApprovePayrollRuns || canCancelPayrollRuns
        : run.status === 'approved'
          ? canPostPayrollRuns || canCancelPayrollRuns
          : false
  );

  const getPayrollRunActionState = (run: PayrollRun) => {
    if (hasAvailablePayrollRunLifecycleAction(run)) return null;

    return isPayrollRunLifecycleActionable(run) ? dict.app.actions.restricted : null;
  };

  return (
    <AppLayout active="payroll.runs.index">
      <Head title={pageDict.headTitle} />
      <PageHeader
        title={pageDict.title}
        description={pageDict.description}
        actions={canCreatePayrollRuns ? <Button onClick={() => setShowForm(true)}>{pageDict.create}</Button> : null}
      />

      <div className="mb-5 grid gap-4 md:grid-cols-4">
        <Card className="p-4">
          <div className="text-xs font-semibold text-[var(--text-muted)]">{pageDict.totalRuns}</div>
          <div className="mt-2 text-2xl font-bold">{runs.total}</div>
        </Card>
        <Card className="p-4">
          <div className="text-xs font-semibold text-[var(--text-muted)]">{pageDict.totalEmployees}</div>
          <div className="mt-2 text-2xl font-bold">{runs.data.reduce((sum, run) => sum + (run.employee_count || 0), 0)}</div>
        </Card>
        <Card className="p-4">
          <div className="text-xs font-semibold text-[var(--text-muted)]">{pageDict.gross}</div>
          <AccountingAmount amountMinor={runs.data.reduce((sum, run) => sum + (run.gross_minor || 0), 0)} currency={runs.data[0]?.currency || pageDict.noCurrency} className="mt-2 block text-xl" />
        </Card>
        <Card className="p-4">
          <div className="text-xs font-semibold text-[var(--text-muted)]">{pageDict.net}</div>
          <AccountingAmount amountMinor={runs.data.reduce((sum, run) => sum + (run.net_minor || 0), 0)} currency={runs.data[0]?.currency || pageDict.noCurrency} className="mt-2 block text-xl" />
        </Card>
      </div>

      <Card className="mb-5 p-4">
        <div className="grid gap-3 lg:grid-cols-[1fr_220px_220px_auto_auto]">
          <input className="input" value={search} onChange={(event) => setSearch(event.target.value)} placeholder={pageDict.search} />
          <SearchableSelect options={[{ value: '', label: pageDict.allStatuses }, ...statusOptions]} value={status || null} onChange={(value) => setStatus(value || '')} label={pageDict.status} />
          <SearchableSelect options={[{ value: '', label: pageDict.allBranches }, ...branchOptions]} value={branchId || null} onChange={(value) => setBranchId(value || '')} label={pageDict.branch} />
          <Button onClick={applyFilters}>{shared.applyFilter}</Button>
          <Button variant="secondary" onClick={clearFilters} disabled={activeFilterCount === 0}>{shared.clearFilter}</Button>
        </div>
      </Card>

      {showForm ? (
        <Card className="mb-5 p-5">
          <form onSubmit={submitForm} className="grid gap-4 lg:grid-cols-4">
            <label className="space-y-1 text-sm font-semibold">
              <span>{pageDict.year}</span>
              <input className="input" type="number" min="2000" max="2100" value={form.data.year} onChange={(event) => form.setData('year', Number(event.target.value))} />
            </label>
            <label className="space-y-1 text-sm font-semibold">
              <span>{pageDict.month}</span>
              <input className="input" type="number" min="1" max="12" value={form.data.month} onChange={(event) => form.setData('month', Number(event.target.value))} />
            </label>
            <DatePicker value={form.data.payment_date} onChange={(value) => form.setData('payment_date', value || '')} label={pageDict.paymentDate} />
            <SearchableSelect options={currencyOptions} value={form.data.currency} onChange={(value) => form.setData('currency', value || '')} label={pageDict.currency} />
            <SearchableSelect options={[{ value: '', label: pageDict.allBranches }, ...branchOptions]} value={form.data.branch_id || null} onChange={(value) => form.setData('branch_id', value || '')} label={pageDict.branch} />
            <SearchableSelect options={runTypeOptions} value={form.data.run_type} onChange={(value) => form.setData('run_type', value || 'regular')} label={pageDict.runType} />
            <label className="space-y-1 text-sm font-semibold">
              <span>{pageDict.reference}</span>
              <input className="input" value={form.data.reference} onChange={(event) => form.setData('reference', event.target.value)} />
            </label>
            <label className="space-y-1 text-sm font-semibold">
              <span>{pageDict.descriptionField}</span>
              <input className="input" value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} />
            </label>
            <div className="flex items-end gap-2 lg:col-span-4">
              <Button type="submit" disabled={form.processing}>{pageDict.generate}</Button>
              <Button variant="secondary" onClick={() => setShowForm(false)}>{shared.cancel}</Button>
            </div>
          </form>
        </Card>
      ) : null}

      {runs.data.length === 0 ? (
        <EmptyState title={pageDict.emptyTitle} description={pageDict.emptyDescription} />
      ) : (
        <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_480px]">
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{pageDict.number}</th>
                  <th className={tableClasses.th}>{pageDict.period}</th>
                  <th className={tableClasses.th}>{pageDict.branch}</th>
                  <th className={tableClasses.th}>{pageDict.gross}</th>
                  <th className={tableClasses.th}>{pageDict.net}</th>
                  <th className={tableClasses.th}>{pageDict.status}</th>
                  <th className={tableClasses.th}>{shared.actions}</th>
                </tr>
              </thead>
              <tbody>
                {runs.data.map((run) => {
                  const actionState = getPayrollRunActionState(run);

                  return (
                    <tr key={run.id} className={selectedRun?.id === run.id ? 'bg-[var(--background)]' : ''}>
                      <td className={tableClasses.td}>{run.number || run.reference || run.id.slice(0, 8)}</td>
                      <td className={tableClasses.td}>{run.period ? `${run.period.year}-${String(run.period.month).padStart(2, '0')}` : formatDate(run.payroll_date)}</td>
                      <td className={tableClasses.td}>{run.branch ? `${run.branch.code} - ${getLocalizedName(run.branch.name, locale)}` : pageDict.allBranches}</td>
                      <td className={tableClasses.td}><AccountingAmount amountMinor={run.gross_minor} currency={run.currency} /></td>
                      <td className={tableClasses.td}><AccountingAmount amountMinor={run.net_minor} currency={run.currency} /></td>
                      <td className={tableClasses.td}><StatusBadge tone={statusTone(run.status)}>{statusLabels[run.status] || run.status}</StatusBadge></td>
                      <td className={`${tableClasses.td} text-end`}>
                        <div className="flex flex-wrap items-center justify-end gap-2">
                          <button type="button" onClick={() => setSelectedRun(run)} title={pageDict.details} aria-label={pageDict.details} className="inline-flex h-8 items-center rounded-md border border-slate-200 px-2.5 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-800 dark:text-slate-300 dark:hover:bg-slate-900/50">{pageDict.details}</button>
                          {run.status === 'draft' && canRegeneratePayrollRuns ? (
                            <button type="button" onClick={() => action(`/payroll/runs/${run.id}/regenerate`)} title={pageDict.regenerate} aria-label={pageDict.regenerate} className="inline-flex h-8 items-center rounded-md border border-blue-200 px-2.5 text-xs font-semibold text-blue-700 transition-colors hover:bg-blue-50 dark:border-blue-900/60 dark:text-blue-300 dark:hover:bg-blue-950/40">{pageDict.regenerate}</button>
                          ) : null}
                          {run.status === 'draft' && canSubmitPayrollRuns ? (
                            <button type="button" onClick={() => action(`/payroll/runs/${run.id}/submit`)} title={shared.submit} aria-label={shared.submit} className="inline-flex h-8 items-center rounded-md border border-indigo-200 px-2.5 text-xs font-semibold text-indigo-700 transition-colors hover:bg-indigo-50 dark:border-indigo-900/60 dark:text-indigo-300 dark:hover:bg-indigo-950/40">{shared.submit}</button>
                          ) : null}
                          {run.status === 'submitted' && canApprovePayrollRuns ? (
                            <button type="button" onClick={() => action(`/payroll/runs/${run.id}/approve`)} title={shared.approve} aria-label={shared.approve} className="inline-flex h-8 items-center rounded-md border border-amber-200 px-2.5 text-xs font-semibold text-amber-700 transition-colors hover:bg-amber-50 dark:border-amber-900/60 dark:text-amber-300 dark:hover:bg-amber-950/40">{shared.approve}</button>
                          ) : null}
                          {run.status === 'approved' && canPostPayrollRuns ? (
                            <button type="button" onClick={() => setPostingRun(run)} title={shared.post} aria-label={shared.post} className="inline-flex h-8 items-center rounded-md border border-emerald-200 px-2.5 text-xs font-semibold text-emerald-700 transition-colors hover:bg-emerald-50 dark:border-emerald-900/60 dark:text-emerald-300 dark:hover:bg-emerald-950/40">{shared.post}</button>
                          ) : null}
                          {isPayrollRunLifecycleActionable(run) && canCancelPayrollRuns ? (
                            <button type="button" onClick={() => action(`/payroll/runs/${run.id}/cancel`)} title={shared.cancel} aria-label={shared.cancel} className="inline-flex h-8 items-center rounded-md border border-red-200 px-2.5 text-xs font-semibold text-red-700 transition-colors hover:bg-red-50 dark:border-red-900/60 dark:text-red-300 dark:hover:bg-red-950/40">{shared.cancel}</button>
                          ) : null}
                          {actionState ? <StatusBadge tone="muted">{actionState}</StatusBadge> : null}
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          <Card className="p-4">
            <h2 className="text-base font-bold">{pageDict.details}</h2>
            {selectedRun ? (
              <div className="mt-4 space-y-4">
                <div className="grid grid-cols-3 gap-3 text-sm">
                  <div>
                    <div className="text-xs text-[var(--text-muted)]">{pageDict.employees}</div>
                    <div className="font-bold">{selectedRun.employee_count}</div>
                  </div>
                  <div>
                    <div className="text-xs text-[var(--text-muted)]">{pageDict.deductions}</div>
                    <AccountingAmount amountMinor={selectedRun.deductions_minor} currency={selectedRun.currency} />
                  </div>
                  <div>
                    <div className="text-xs text-[var(--text-muted)]">{pageDict.journal}</div>
                    <div className="font-bold">{selectedRun.journal_entry?.number || shared.none}</div>
                  </div>
                </div>
                <div className="space-y-2">
                  {(selectedRun.lines || []).map((line) => (
                    <div key={line.id} className="rounded-md border border-[var(--border)] p-3 text-sm">
                      <div className="flex items-start justify-between gap-3">
                        <div>
                          <div className="font-semibold">{line.employee ? `${line.employee.code} - ${getLocalizedName(line.employee.name, locale)}` : pageDict.employee}</div>
                          <div className="mt-1 text-xs text-[var(--text-secondary)]">{pageDict.baseSalary}: <AccountingAmount amountMinor={line.base_salary_minor} currency={selectedRun.currency} /></div>
                        </div>
                        <AccountingAmount amountMinor={line.net_minor} currency={selectedRun.currency} />
                      </div>
                      <div className="mt-3 grid gap-2">
                        {(line.components || []).map((component) => (
                          <div key={component.id} className="flex items-center justify-between rounded-md bg-[var(--background)] px-3 py-2">
                            <span>{component.code} - {getLocalizedName(component.name, locale)}</span>
                            <AccountingAmount amountMinor={component.amount_minor} currency={selectedRun.currency} tone={component.type === 'deduction' ? 'danger' : 'success'} />
                          </div>
                        ))}
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            ) : (
              <p className="mt-3 text-sm text-[var(--text-secondary)]">{pageDict.selectRun}</p>
            )}
          </Card>
        </div>
      )}

      <SensitiveActionModal
        isOpen={postingRun !== null}
        onClose={() => setPostingRun(null)}
        onConfirm={postPayrollRun}
        confirmCode="POST_PAYROLL_RUN"
        reasonRequired
        isProcessing={postRunProcessing}
        locale={locale}
      />
    </AppLayout>
  );
}
