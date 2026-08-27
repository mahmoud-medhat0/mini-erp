import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { AccountingAmount, Button, Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatDate, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { AccountOption, CurrencyOption, SharedPageProps } from '../../Types';

type TranslatedName = Record<string, string> | string | null;
type Branch = { id: string; code: string; name: TranslatedName };
type Category = { id: string; code: string; name: TranslatedName };
type ScheduleAccount = AccountOption & { currency?: string | null };
type Recognition = {
  id: string;
  recognition_date: string;
  amount_minor: number;
  status: 'pending' | 'posted' | 'reversed';
  journal_entry?: { id: string; number?: string | null } | null;
};
type Schedule = {
  id: string;
  number?: string | null;
  schedule_date: string;
  start_date: string;
  months: number;
  branch_id?: string | null;
  expense_category_id?: string | null;
  prepaid_asset_account_id: string;
  expense_account_id: string;
  currency: string;
  total_minor: number;
  recognized_minor: number;
  status: 'draft' | 'submitted' | 'approved' | 'active' | 'completed' | 'cancelled';
  reference?: string | null;
  description?: string | null;
  lock_version: number;
  branch?: Branch | null;
  category?: Category | null;
  prepaid_asset_account?: AccountOption | null;
  expense_account?: AccountOption | null;
  recognitions: Recognition[];
};
type PaginatedData<T> = { data: T[]; total: number; links: any[] };
type Props = SharedPageProps & {
  schedules: PaginatedData<Schedule>;
  categories: Category[];
  prepaidAssetAccounts: ScheduleAccount[];
  expenseAccounts: ScheduleAccount[];
  branches: Branch[];
  currencies: CurrencyOption[];
  statuses: Schedule['status'][];
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

function statusTone(value: string): 'ok' | 'muted' | 'danger' | 'warning' | 'info' {
  if (['completed', 'posted'].includes(value)) return 'ok';
  if (['cancelled', 'reversed'].includes(value)) return 'danger';
  if (['approved', 'active'].includes(value)) return 'warning';
  if (value === 'submitted') return 'info';

  return 'muted';
}

export default function PrepaidSchedulesIndex({
  locale,
  schedules,
  categories = [],
  prepaidAssetAccounts = [],
  expenseAccounts = [],
  branches = [],
  currencies = [],
  statuses = [],
  filters,
}: Props) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.prepaidSchedules;
  const can = useCan();
  const defaultCurrency = currencies[0]?.code || '';
  const [showForm, setShowForm] = useState(false);
  const [editing, setEditing] = useState<Schedule | null>(null);
  const [search, setSearch] = useState(filters.search || '');
  const [status, setStatus] = useState(filters.status || '');
  const [branchId, setBranchId] = useState(filters.branch_id || '');

  const form = useForm({
    schedule_date: today(),
    start_date: today(),
    months: 12,
    branch_id: '',
    expense_category_id: '',
    prepaid_asset_account_id: prepaidAssetAccounts[0]?.id || '',
    expense_account_id: expenseAccounts[0]?.id || '',
    currency: defaultCurrency,
    total_minor: 0,
    total_amount: '',
    reference: '',
    description: '',
    lock_version: 1,
  });

  const categoryOptions = useMemo(() => categories.map((item) => ({ value: item.id, label: `${item.code} - ${getLocalizedName(item.name, locale)}` })), [categories, locale]);
  const branchOptions = useMemo(() => branches.map((item) => ({ value: item.id, label: `${item.code} - ${getLocalizedName(item.name, locale)}` })), [branches, locale]);
  const prepaidOptions = useMemo(() => prepaidAssetAccounts.map((item) => ({ value: item.id, label: `${item.code} - ${getLocalizedName(item.name, locale)}`, sublabel: item.currency || undefined })), [prepaidAssetAccounts, locale]);
  const expenseOptions = useMemo(() => expenseAccounts.map((item) => ({ value: item.id, label: `${item.code} - ${getLocalizedName(item.name, locale)}`, sublabel: item.currency || undefined })), [expenseAccounts, locale]);
  const currencyOptions = useMemo(() => currencies.map((item) => ({ value: item.code, label: `${item.code} - ${getLocalizedName(item.name, locale)}` })), [currencies, locale]);
  const statusOptions = statuses.map((item) => ({ value: item, label: pageDict.statuses[item] || item }));
  const pendingCount = schedules.data.flatMap((item) => item.recognitions || []).filter((item) => item.status === 'pending').length;
  const activeFilterCount = [search, status, branchId].filter(Boolean).length;

  function applyFilters() {
    router.get('/expenses/prepaids', { search, status, branch_id: branchId }, { preserveScroll: true, preserveState: true });
  }

  function clearFilters() {
    setSearch('');
    setStatus('');
    setBranchId('');
    router.get('/expenses/prepaids', {}, { preserveScroll: true, preserveState: true });
  }

  function openCreate() {
    setEditing(null);
    form.reset();
    form.setData({
      schedule_date: today(),
      start_date: today(),
      months: 12,
      branch_id: '',
      expense_category_id: '',
      prepaid_asset_account_id: prepaidAssetAccounts[0]?.id || '',
      expense_account_id: expenseAccounts[0]?.id || '',
      currency: defaultCurrency,
      total_minor: 0,
      total_amount: '',
      reference: '',
      description: '',
      lock_version: 1,
    });
    setShowForm(true);
  }

  function openEdit(schedule: Schedule) {
    setEditing(schedule);
    form.setData({
      schedule_date: schedule.schedule_date,
      start_date: schedule.start_date,
      months: schedule.months,
      branch_id: schedule.branch_id || '',
      expense_category_id: schedule.expense_category_id || '',
      prepaid_asset_account_id: schedule.prepaid_asset_account_id,
      expense_account_id: schedule.expense_account_id,
      currency: schedule.currency,
      total_minor: schedule.total_minor,
      total_amount: minorToAmount(schedule.total_minor),
      reference: schedule.reference || '',
      description: schedule.description || '',
      lock_version: schedule.lock_version,
    });
    setShowForm(true);
  }

  function submitForm(event: FormEvent) {
    event.preventDefault();
    const payload = {
      ...form.data,
      branch_id: form.data.branch_id || null,
      expense_category_id: form.data.expense_category_id || null,
      total_minor: amountToMinor(form.data.total_amount),
      fx_rate_e6: 1000000,
    };

    if (editing) {
      router.put(`/expenses/prepaids/${editing.id}`, payload, { preserveScroll: true, onSuccess: () => setShowForm(false) });
      return;
    }

    router.post('/expenses/prepaids', payload, { preserveScroll: true, onSuccess: () => setShowForm(false) });
  }

  function action(url: string, confirmation?: string) {
    if (confirmation && !confirm(confirmation)) return;

    router.post(url, {}, { preserveScroll: true });
  }

  return (
    <AppLayout active="prepaid-schedules.index">
      <Head title={pageDict.headTitle} />
      <PageHeader
        title={pageDict.title}
        description={pageDict.description}
        actions={can('expenses.create') ? <Button onClick={openCreate}>{pageDict.create}</Button> : null}
      />

      <div className="mb-5 grid gap-4 md:grid-cols-3">
        <Card className="p-4">
          <div className="text-xs font-semibold text-[var(--text-muted)]">{pageDict.totalSchedules}</div>
          <div className="mt-2 text-2xl font-bold">{schedules.total}</div>
        </Card>
        <Card className="p-4">
          <div className="text-xs font-semibold text-[var(--text-muted)]">{pageDict.pendingRecognitions}</div>
          <div className="mt-2 text-2xl font-bold">{pendingCount}</div>
        </Card>
        <Card className="p-4">
          <div className="text-xs font-semibold text-[var(--text-muted)]">{pageDict.currentFilter}</div>
          <div className="mt-2 text-sm font-semibold">{status ? pageDict.statuses[status as keyof typeof pageDict.statuses] : pageDict.allStatuses}</div>
        </Card>
      </div>

      <Card className="mb-5 p-4">
        <div className="grid gap-3 lg:grid-cols-[1fr_220px_220px_auto_auto]">
          <input className="input" value={search} onChange={(event) => setSearch(event.target.value)} placeholder={pageDict.search} />
          <SearchableSelect options={[{ value: '', label: pageDict.allStatuses }, ...statusOptions]} value={status || null} onChange={(value) => setStatus(value || '')} label={pageDict.status} />
          <SearchableSelect options={[{ value: '', label: pageDict.allBranches }, ...branchOptions]} value={branchId || null} onChange={(value) => setBranchId(value || '')} label={pageDict.branch} />
          <Button onClick={applyFilters}>{pageDict.applyFilter}</Button>
          <Button variant="secondary" onClick={clearFilters} disabled={activeFilterCount === 0}>{pageDict.clearFilter}</Button>
        </div>
      </Card>

      {showForm ? (
        <Card className="mb-5 p-5">
          <form onSubmit={submitForm} className="grid gap-4 lg:grid-cols-2">
            <DatePicker label={pageDict.scheduleDate} value={form.data.schedule_date} onChange={(value) => form.setData('schedule_date', value || '')} />
            <DatePicker label={pageDict.startDate} value={form.data.start_date} onChange={(value) => form.setData('start_date', value || '')} />
            <label className="space-y-1 text-sm font-semibold text-[var(--text-secondary)]">
              <span>{pageDict.months}</span>
              <input className="input" type="number" min={1} max={120} value={form.data.months} onChange={(event) => form.setData('months', Number(event.target.value))} />
            </label>
            <label className="space-y-1 text-sm font-semibold text-[var(--text-secondary)]">
              <span>{pageDict.totalAmount}</span>
              <input className="input" type="number" min="0.01" step="0.01" value={form.data.total_amount} onChange={(event) => form.setData('total_amount', event.target.value)} />
            </label>
            <SearchableSelect options={currencyOptions} value={form.data.currency || null} onChange={(value) => form.setData('currency', value || '')} label={pageDict.currency} />
            <SearchableSelect options={[{ value: '', label: pageDict.noCategory }, ...categoryOptions]} value={form.data.expense_category_id || null} onChange={(value) => form.setData('expense_category_id', value || '')} label={pageDict.category} />
            <SearchableSelect options={[{ value: '', label: pageDict.noBranch }, ...branchOptions]} value={form.data.branch_id || null} onChange={(value) => form.setData('branch_id', value || '')} label={pageDict.branch} />
            <SearchableSelect options={prepaidOptions} value={form.data.prepaid_asset_account_id || null} onChange={(value) => form.setData('prepaid_asset_account_id', value || '')} label={pageDict.prepaidAssetAccount} />
            <SearchableSelect options={expenseOptions} value={form.data.expense_account_id || null} onChange={(value) => form.setData('expense_account_id', value || '')} label={pageDict.expenseAccount} />
            <label className="space-y-1 text-sm font-semibold text-[var(--text-secondary)]">
              <span>{pageDict.reference}</span>
              <input className="input" value={form.data.reference} onChange={(event) => form.setData('reference', event.target.value)} />
            </label>
            <label className="space-y-1 text-sm font-semibold text-[var(--text-secondary)] lg:col-span-2">
              <span>{pageDict.descriptionField}</span>
              <textarea className="input min-h-20" value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} />
            </label>
            <div className="flex gap-2 lg:col-span-2">
              <Button type="submit">{pageDict.save}</Button>
              <Button type="button" variant="secondary" onClick={() => setShowForm(false)}>{pageDict.cancel}</Button>
            </div>
          </form>
        </Card>
      ) : null}

      {schedules.data.length === 0 ? (
        <EmptyState title={pageDict.emptyTitle} description={pageDict.emptyDescription} />
      ) : (
        <div className="space-y-4">
          {schedules.data.map((schedule) => (
            <Card key={schedule.id} className="overflow-hidden">
              <div className="grid gap-3 border-b border-[var(--border)] p-4 lg:grid-cols-[1fr_auto]">
                <div>
                  <div className="flex flex-wrap items-center gap-2">
                    <h3 className="text-base font-bold">{schedule.number || pageDict.notNumbered}</h3>
                    <StatusBadge tone={statusTone(schedule.status)}>{pageDict.statuses[schedule.status]}</StatusBadge>
                  </div>
                  <div className="mt-2 grid gap-2 text-xs text-[var(--text-muted)] md:grid-cols-4">
                    <span>{pageDict.scheduleDate}: {formatDate(schedule.schedule_date)}</span>
                    <span>{pageDict.startDate}: {formatDate(schedule.start_date)}</span>
                    <span>{pageDict.months}: {schedule.months}</span>
                    <span>{pageDict.branch}: {schedule.branch ? `${schedule.branch.code} - ${getLocalizedName(schedule.branch.name, locale)}` : pageDict.noBranch}</span>
                  </div>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                  <AccountingAmount amountMinor={schedule.total_minor} currency={schedule.currency} />
                  {can('expenses.edit') && schedule.status === 'draft' ? <Button variant="secondary" onClick={() => openEdit(schedule)}>{pageDict.edit}</Button> : null}
                  {can('expenses.submit') && schedule.status === 'draft' ? <Button variant="secondary" onClick={() => action(`/expenses/prepaids/${schedule.id}/submit`, pageDict.confirmations.submit)}>{pageDict.submit}</Button> : null}
                  {can('expenses.approve') && schedule.status === 'submitted' ? <Button variant="secondary" onClick={() => action(`/expenses/prepaids/${schedule.id}/approve`, pageDict.confirmations.approve)}>{pageDict.approve}</Button> : null}
                  {can('expenses.edit') && ['draft', 'submitted', 'approved'].includes(schedule.status) ? <Button variant="danger" onClick={() => action(`/expenses/prepaids/${schedule.id}/cancel`, pageDict.confirmations.cancel)}>{pageDict.cancelSchedule}</Button> : null}
                </div>
              </div>
              <div className="overflow-x-auto">
                <table className={tableClasses.table}>
                  <thead>
                    <tr>
                      <th className={tableClasses.th}>{pageDict.recognitionDate}</th>
                      <th className={tableClasses.th}>{pageDict.amount}</th>
                      <th className={tableClasses.th}>{pageDict.status}</th>
                      <th className={tableClasses.th}>{pageDict.journal}</th>
                      <th className={tableClasses.th}>{pageDict.actions}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {schedule.recognitions.map((recognition) => (
                      <tr key={recognition.id}>
                        <td className={tableClasses.td}>{formatDate(recognition.recognition_date)}</td>
                        <td className={tableClasses.td}><AccountingAmount amountMinor={recognition.amount_minor} currency={schedule.currency} /></td>
                        <td className={tableClasses.td}><StatusBadge tone={statusTone(recognition.status)}>{pageDict.entryStatuses[recognition.status]}</StatusBadge></td>
                        <td className={tableClasses.td}>{recognition.journal_entry?.number || pageDict.notPosted}</td>
                        <td className={tableClasses.td}>
                          {can('expenses.post') && can('view_financials') && recognition.status === 'pending' && ['approved', 'active'].includes(schedule.status) ? (
                            <Button className="px-3 py-1.5" onClick={() => action(`/expenses/prepaids/${schedule.id}/recognitions/${recognition.id}/post`, pageDict.confirmations.postRecognition)}>{pageDict.post}</Button>
                          ) : null}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </Card>
          ))}
        </div>
      )}
    </AppLayout>
  );
}
