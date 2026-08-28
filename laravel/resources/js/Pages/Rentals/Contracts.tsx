import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { AccountingAmount, Button, Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatDate, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { PaginationLink, CurrencyOption, SharedPageProps } from '../../Types';

type TranslatedName = Record<string, string> | string | null;
type Customer = { id: string; code: string; name: TranslatedName };
type Branch = { id: string; code: string; name: TranslatedName };
type RentableItem = {
  id: string;
  code: string;
  name: TranslatedName;
  status: string;
  currency: string;
  branch_id?: string | null;
  warehouse_id?: string | null;
  daily_rate_minor?: number | null;
  monthly_rate_minor?: number | null;
};
type ContractLine = {
  id: string;
  line_no: number;
  rentable_item_id: string;
  description?: TranslatedName;
  start_date: string;
  end_date: string;
  rate_type: string;
  rate_minor: number;
  estimated_units: number;
  estimated_amount_minor: number;
  deposit_minor: number;
  notes?: string | null;
  rentable_item?: RentableItem | null;
};
type Contract = {
  id: string;
  number?: string | null;
  customer_id: string;
  branch_id?: string | null;
  status: string;
  contract_date: string;
  start_date: string;
  expected_end_date: string;
  currency: string;
  billing_cycle: string;
  estimated_rent_minor: number;
  deposit_minor: number;
  total_estimated_minor: number;
  reference?: string | null;
  notes?: string | null;
  lock_version: number;
  customer?: Customer | null;
  branch?: Branch | null;
  lines?: ContractLine[];
};
type EditableLine = {
  rentable_item_id: string;
  description: { en: string; ar: string };
  start_date: string;
  end_date: string;
  rate_type: string;
  rate_amount: string;
  estimated_units: number;
  deposit_amount: string;
  notes: string;
};
type Props = SharedPageProps & {
  contracts: { data: Contract[]; total: number; links?: PaginationLink[] };
  customers: Customer[];
  branches: Branch[];
  rentableItems: RentableItem[];
  currencies: CurrencyOption[];
  statuses: string[];
  billingCycles: string[];
  rateTypes: string[];
  filters: { search?: string; status?: string; customer_id?: string; branch_id?: string };
};

function today(): string {
  return new Date().toISOString().slice(0, 10);
}

function amountToMinor(value: string): number {
  const normalized = value.trim().replace(/,/g, '');
  if (normalized === '') return 0;
  const match = normalized.match(/^(\d+)(?:\.(\d{0,2}))?$/);
  if (!match) return 0;
  return Number(`${match[1]}${(match[2] || '').padEnd(2, '0').slice(0, 2)}`);
}

function minorToAmount(value?: number | null): string {
  const amount = Math.abs(Number(value || 0));
  const sign = Number(value || 0) < 0 ? '-' : '';
  return `${sign}${Math.trunc(amount / 100)}.${String(amount % 100).padStart(2, '0')}`;
}

function statusTone(value: string): 'ok' | 'muted' | 'danger' | 'warning' | 'info' {
  if (value === 'active' || value === 'completed') return 'ok';
  if (value === 'submitted' || value === 'approved') return 'info';
  if (value === 'cancelled') return 'danger';
  return 'muted';
}

function namePart(name: TranslatedName, locale: 'en' | 'ar'): string {
  return getLocalizedName(name, locale);
}

export default function RentalContractsIndex({
  locale,
  contracts,
  customers = [],
  branches = [],
  rentableItems = [],
  currencies = [],
  statuses = [],
  billingCycles = [],
  rateTypes = [],
  filters,
}: Props) {
  const dict = getDictionary(locale);
  const activeLocale = locale === 'ar' ? 'ar' : 'en';
  const pageDict = dict.app.pages.rentalContracts;
  const can = useCan();
  const canCreateRentalContracts = can('rentals.create');
  const canEditRentalContracts = can('rentals.edit');
  const canSubmitRentalContracts = can('rentals.submit');
  const canApproveRentalContracts = can('rentals.approve');
  const canActivateRentalContracts = can('rentals.deliver');
  const canCancelRentalContracts = can('rentals.cancel');
  const defaultCurrency = currencies[0]?.code || '';
  const [search, setSearch] = useState(filters.search || '');
  const [status, setStatus] = useState(filters.status || '');
  const [customerId, setCustomerId] = useState(filters.customer_id || '');
  const [branchId, setBranchId] = useState(filters.branch_id || '');
  const [showForm, setShowForm] = useState(false);
  const [editing, setEditing] = useState<Contract | null>(null);

  const defaultStartDate = today();
  const defaultEndDate = today();
  const [lineItems, setLineItems] = useState<EditableLine[]>([]);

  const form = useForm({
    customer_id: customers[0]?.id || '',
    branch_id: '',
    contract_date: today(),
    start_date: defaultStartDate,
    expected_end_date: defaultEndDate,
    currency: defaultCurrency,
    billing_cycle: 'monthly',
    reference: '',
    notes: '',
    reason: '',
    lock_version: 1,
  });

  const customerOptions = useMemo(() => customers.map((item) => ({
    value: item.id,
    label: `${item.code} - ${namePart(item.name, activeLocale)}`,
  })), [customers, activeLocale]);
  const branchOptions = useMemo(() => branches.map((item) => ({
    value: item.id,
    label: `${item.code} - ${namePart(item.name, activeLocale)}`,
  })), [branches, activeLocale]);
  const currencyOptions = useMemo(() => currencies.map((item) => ({
    value: item.code,
    label: `${item.code} - ${getLocalizedName(item.name, activeLocale)}`,
  })), [currencies, activeLocale]);
  const itemOptions = useMemo(() => rentableItems.map((item) => ({
    value: item.id,
    label: `${item.code} - ${namePart(item.name, activeLocale)}`,
    sublabel: `${item.currency} / ${pageDict.itemStatuses[item.status as keyof typeof pageDict.itemStatuses] || item.status}`,
  })), [rentableItems, activeLocale, pageDict.itemStatuses]);
  const statusOptions = statuses.map((item) => ({ value: item, label: pageDict.statuses[item as keyof typeof pageDict.statuses] || item }));
  const billingCycleOptions = billingCycles.map((item) => ({ value: item, label: pageDict.billingCycles[item as keyof typeof pageDict.billingCycles] || item }));
  const rateTypeOptions = rateTypes.map((item) => ({ value: item, label: pageDict.rateTypes[item as keyof typeof pageDict.rateTypes] || item }));

  function emptyLine(): EditableLine {
    const item = rentableItems.find((candidate) => candidate.currency === form.data.currency) || rentableItems[0];
    const rateMinor = form.data.billing_cycle === 'daily' ? item?.daily_rate_minor : item?.monthly_rate_minor;

    return {
      rentable_item_id: item?.id || '',
      description: { en: '', ar: '' },
      start_date: form.data.start_date,
      end_date: form.data.expected_end_date,
      rate_type: form.data.billing_cycle,
      rate_amount: minorToAmount(rateMinor || 0),
      estimated_units: 1,
      deposit_amount: '0.00',
      notes: '',
    };
  }

  function applyFilters() {
    router.get('/rentals/contracts', { search, status, customer_id: customerId, branch_id: branchId }, { preserveScroll: true, preserveState: true });
  }

  const activeFilterCount = [search, status, customerId, branchId].filter(Boolean).length;

  function clearFilters() {
    setSearch('');
    setStatus('');
    setCustomerId('');
    setBranchId('');
    router.get('/rentals/contracts', {}, { preserveScroll: true, preserveState: true });
  }

  function openCreate() {
    setEditing(null);
    form.setData({
      customer_id: customers[0]?.id || '',
      branch_id: '',
      contract_date: today(),
      start_date: today(),
      expected_end_date: today(),
      currency: defaultCurrency,
      billing_cycle: 'monthly',
      reference: '',
      notes: '',
      reason: '',
      lock_version: 1,
    });
    form.clearErrors();
    setLineItems([emptyLine()]);
    setShowForm(true);
  }

  function openEdit(contract: Contract) {
    setEditing(contract);
    form.setData({
      customer_id: contract.customer_id,
      branch_id: contract.branch_id || '',
      contract_date: contract.contract_date,
      start_date: contract.start_date,
      expected_end_date: contract.expected_end_date,
      currency: contract.currency,
      billing_cycle: contract.billing_cycle,
      reference: contract.reference || '',
      notes: contract.notes || '',
      reason: '',
      lock_version: contract.lock_version,
    });
    setLineItems((contract.lines || []).map((line) => ({
      rentable_item_id: line.rentable_item_id,
      description: { en: namePart(line.description || null, 'en'), ar: namePart(line.description || null, 'ar') },
      start_date: line.start_date,
      end_date: line.end_date,
      rate_type: line.rate_type,
      rate_amount: minorToAmount(line.rate_minor),
      estimated_units: line.estimated_units,
      deposit_amount: minorToAmount(line.deposit_minor),
      notes: line.notes || '',
    })));
    form.clearErrors();
    setShowForm(true);
  }

  function updateLine(index: number, patch: Partial<EditableLine>) {
    setLineItems((current) => current.map((line, lineIndex) => (lineIndex === index ? { ...line, ...patch } : line)));
  }

  function addLine() {
    setLineItems((current) => [...current, emptyLine()]);
  }

  function removeLine(index: number) {
    setLineItems((current) => current.length === 1 ? current : current.filter((_, lineIndex) => lineIndex !== index));
  }

  function submitForm(event: FormEvent) {
    event.preventDefault();
    const payload = {
      ...form.data,
      branch_id: form.data.branch_id || null,
      reference: form.data.reference || null,
      notes: form.data.notes || null,
      reason: form.data.reason || null,
      lines: lineItems.map((line) => ({
        rentable_item_id: line.rentable_item_id,
        description: line.description,
        start_date: line.start_date || form.data.start_date,
        end_date: line.end_date || form.data.expected_end_date,
        rate_type: line.rate_type,
        rate_minor: amountToMinor(line.rate_amount),
        estimated_units: Number(line.estimated_units || 1),
        deposit_minor: amountToMinor(line.deposit_amount),
        notes: line.notes || null,
      })),
    };

    if (editing) {
      router.put(`/rentals/contracts/${editing.id}`, payload, { preserveScroll: true, onSuccess: () => setShowForm(false) });
      return;
    }

    router.post('/rentals/contracts', payload, { preserveScroll: true, onSuccess: () => setShowForm(false) });
  }

  function runAction(contract: Contract, action: 'submit' | 'approve' | 'activate' | 'cancel') {
    const message = pageDict.confirmations[action];
    if (message && !confirm(message)) return;

    router.post(`/rentals/contracts/${contract.id}/${action}`, {}, { preserveScroll: true });
  }

  const isRentalContractActionable = (contract: Contract) => ['draft', 'submitted', 'approved'].includes(contract.status);

  const hasAvailableRentalContractAction = (contract: Contract) => (
    contract.status === 'draft'
      ? canEditRentalContracts || canSubmitRentalContracts || canCancelRentalContracts
      : contract.status === 'submitted'
        ? canApproveRentalContracts || canCancelRentalContracts
        : contract.status === 'approved'
          ? canActivateRentalContracts || canCancelRentalContracts
          : false
  );

  const getRentalContractActionState = (contract: Contract) => {
    if (hasAvailableRentalContractAction(contract)) return null;

    return isRentalContractActionable(contract) ? dict.app.actions.restricted : dict.app.actions.noActions;
  };

  return (
    <AppLayout active="rentals.contracts.index">
      <Head title={pageDict.headTitle} />
      <PageHeader
        title={pageDict.title}
        description={pageDict.description}
        actions={canCreateRentalContracts ? <Button onClick={openCreate}>{pageDict.create}</Button> : null}
      />

      <Card className="mb-5 p-4">
        <div className="grid gap-3 xl:grid-cols-[1fr_180px_260px_220px_auto_auto]">
          <input className="input" value={search} onChange={(event) => setSearch(event.target.value)} placeholder={pageDict.search} />
          <SearchableSelect options={[{ value: '', label: pageDict.allStatuses }, ...statusOptions]} value={status || null} onChange={(value) => setStatus(value || '')} label={pageDict.status} />
          <SearchableSelect options={[{ value: '', label: pageDict.allCustomers }, ...customerOptions]} value={customerId || null} onChange={(value) => setCustomerId(value || '')} label={pageDict.customer} />
          <SearchableSelect options={[{ value: '', label: pageDict.allBranches }, ...branchOptions]} value={branchId || null} onChange={(value) => setBranchId(value || '')} label={pageDict.branch} />
          <Button onClick={applyFilters}>{pageDict.applyFilter}</Button>
          <Button variant="secondary" onClick={clearFilters} disabled={activeFilterCount === 0}>{pageDict.clearFilter}</Button>
        </div>
      </Card>

      {showForm ? (
        <Card className="mb-5 p-5">
          <form onSubmit={submitForm} className="space-y-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h2 className="m-0 text-base font-bold text-[var(--text-primary)]">{editing ? pageDict.editTitle : pageDict.createTitle}</h2>
                <p className="mt-1 text-xs text-[var(--text-secondary)]">{pageDict.formHint}</p>
              </div>
              <Button type="button" variant="secondary" onClick={() => setShowForm(false)}>{pageDict.close}</Button>
            </div>

            <div className="grid gap-4 xl:grid-cols-4">
              <SearchableSelect options={customerOptions} value={form.data.customer_id || null} onChange={(value) => form.setData('customer_id', value || '')} label={pageDict.customer} />
              <SearchableSelect options={branchOptions} value={form.data.branch_id || null} onChange={(value) => form.setData('branch_id', value || '')} label={pageDict.branch} />
              <SearchableSelect options={currencyOptions} value={form.data.currency} onChange={(value) => form.setData('currency', value || '')} label={pageDict.currency} />
              <SearchableSelect options={billingCycleOptions} value={form.data.billing_cycle} onChange={(value) => form.setData('billing_cycle', value || 'monthly')} label={pageDict.billingCycle} />
              <DatePicker value={form.data.contract_date} onChange={(value) => form.setData('contract_date', value || today())} label={pageDict.contractDate} required />
              <DatePicker value={form.data.start_date} onChange={(value) => form.setData('start_date', value || today())} label={pageDict.startDate} required />
              <DatePicker value={form.data.expected_end_date} onChange={(value) => form.setData('expected_end_date', value || today())} label={pageDict.expectedEndDate} required />
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.reference}
                <input className="input mt-1" value={form.data.reference} onChange={(event) => form.setData('reference', event.target.value)} />
              </label>
            </div>

            <div className="space-y-3">
              <div className="flex items-center justify-between">
                <h3 className="m-0 text-sm font-bold text-[var(--text-primary)]">{pageDict.lines}</h3>
                <Button type="button" variant="secondary" onClick={addLine}>{pageDict.addLine}</Button>
              </div>
              {lineItems.map((line, index) => (
                <div key={index} className="grid gap-3 rounded-md border border-[var(--border)] bg-[var(--background)] p-3 xl:grid-cols-[minmax(260px,1.4fr)_130px_120px_120px_120px_120px_auto]">
                  <SearchableSelect options={itemOptions} value={line.rentable_item_id || null} onChange={(value) => updateLine(index, { rentable_item_id: value || '' })} label={pageDict.rentableItem} />
                  <SearchableSelect options={rateTypeOptions} value={line.rate_type} onChange={(value) => updateLine(index, { rate_type: value || 'monthly' })} label={pageDict.rateType} />
                  <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                    {pageDict.rate}
                    <input className="input mt-1" inputMode="decimal" value={line.rate_amount} onChange={(event) => updateLine(index, { rate_amount: event.target.value })} />
                  </label>
                  <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                    {pageDict.units}
                    <input className="input mt-1" type="number" min={1} value={line.estimated_units} onChange={(event) => updateLine(index, { estimated_units: Number(event.target.value || 1) })} />
                  </label>
                  <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                    {pageDict.deposit}
                    <input className="input mt-1" inputMode="decimal" value={line.deposit_amount} onChange={(event) => updateLine(index, { deposit_amount: event.target.value })} />
                  </label>
                  <DatePicker value={line.start_date} onChange={(value) => updateLine(index, { start_date: value || form.data.start_date })} label={pageDict.lineStart} required />
                  <div className="flex items-end justify-end">
                    <Button type="button" variant="danger" onClick={() => removeLine(index)} disabled={lineItems.length === 1}>{pageDict.removeLine}</Button>
                  </div>
                  <DatePicker value={line.end_date} onChange={(value) => updateLine(index, { end_date: value || form.data.expected_end_date })} label={pageDict.lineEnd} required />
                  <label className="block text-xs font-bold uppercase text-[var(--text-secondary)] xl:col-span-3">
                    {pageDict.descriptionEn}
                    <input className="input mt-1" value={line.description.en} onChange={(event) => updateLine(index, { description: { ...line.description, en: event.target.value } })} />
                  </label>
                  <label className="block text-xs font-bold uppercase text-[var(--text-secondary)] xl:col-span-3">
                    {pageDict.descriptionAr}
                    <input className="input mt-1" value={line.description.ar} onChange={(event) => updateLine(index, { description: { ...line.description, ar: event.target.value } })} />
                  </label>
                </div>
              ))}
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.notes}
                <textarea className="input mt-1 min-h-20" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
              </label>
              <label className="block text-xs font-bold uppercase text-[var(--text-secondary)]">
                {pageDict.reason}
                <textarea className="input mt-1 min-h-20" value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} />
              </label>
            </div>

            <div className="flex justify-end gap-2">
              <Button type="button" variant="secondary" onClick={() => setShowForm(false)}>{pageDict.cancel}</Button>
              <Button type="submit">{editing ? pageDict.update : pageDict.save}</Button>
            </div>
          </form>
        </Card>
      ) : null}

      {contracts.data.length === 0 ? (
        <EmptyState title={pageDict.emptyTitle} description={pageDict.emptyDescription} />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{pageDict.number}</th>
                <th className={tableClasses.th}>{pageDict.customer}</th>
                <th className={tableClasses.th}>{pageDict.period}</th>
                <th className={tableClasses.th}>{pageDict.status}</th>
                <th className={tableClasses.th}>{pageDict.items}</th>
                <th className={tableClasses.th}>{pageDict.total}</th>
                <th className={tableClasses.th}>{pageDict.actions}</th>
              </tr>
            </thead>
            <tbody>
              {contracts.data.map((contract) => {
                const actionState = getRentalContractActionState(contract);

                return (
                  <tr key={contract.id}>
                    <td className={tableClasses.td}>
                      <div className="font-mono text-sm font-bold">{contract.number || pageDict.notNumbered}</div>
                      {contract.reference ? <div className="mt-1 text-xs text-[var(--text-muted)]">{contract.reference}</div> : null}
                    </td>
                    <td className={tableClasses.td}>
                      <div className="font-semibold">{contract.customer ? `${contract.customer.code} - ${namePart(contract.customer.name, activeLocale)}` : pageDict.noCustomer}</div>
                      <div className="mt-1 text-xs text-[var(--text-muted)]">{contract.branch ? `${contract.branch.code} - ${namePart(contract.branch.name, activeLocale)}` : pageDict.noBranch}</div>
                    </td>
                    <td className={tableClasses.td}>
                      <div className="font-semibold">{formatDate(contract.start_date)} - {formatDate(contract.expected_end_date)}</div>
                      <div className="mt-1 text-xs text-[var(--text-muted)]">{pageDict.contractDate}: {formatDate(contract.contract_date)}</div>
                    </td>
                    <td className={tableClasses.td}>
                      <StatusBadge tone={statusTone(contract.status)}>{pageDict.statuses[contract.status as keyof typeof pageDict.statuses] || contract.status}</StatusBadge>
                    </td>
                    <td className={tableClasses.td}>
                      <div className="space-y-1 text-xs">
                        {(contract.lines || []).map((line) => (
                          <div key={line.id}>{line.rentable_item ? `${line.rentable_item.code} - ${namePart(line.rentable_item.name, activeLocale)}` : line.rentable_item_id}</div>
                        ))}
                      </div>
                    </td>
                    <td className={tableClasses.td}>
                      <div className="grid gap-1 text-xs">
                        <span>{pageDict.rent}: <AccountingAmount amountMinor={contract.estimated_rent_minor} currency={contract.currency} /></span>
                        <span>{pageDict.deposit}: <AccountingAmount amountMinor={contract.deposit_minor} currency={contract.currency} /></span>
                        <span>{pageDict.total}: <AccountingAmount amountMinor={contract.total_estimated_minor} currency={contract.currency} /></span>
                      </div>
                    </td>
                    <td className={`${tableClasses.td} text-end`}>
                      <div className="flex flex-wrap items-center justify-end gap-2">
                        {canEditRentalContracts && contract.status === 'draft' ? (
                          <button type="button" onClick={() => openEdit(contract)} title={pageDict.edit} aria-label={pageDict.edit} className="inline-flex h-8 items-center rounded-md border border-blue-200 px-2.5 text-xs font-semibold text-blue-700 transition-colors hover:bg-blue-50 dark:border-blue-900/60 dark:text-blue-300 dark:hover:bg-blue-950/40">{pageDict.edit}</button>
                        ) : null}
                        {canSubmitRentalContracts && contract.status === 'draft' ? (
                          <button type="button" onClick={() => runAction(contract, 'submit')} title={pageDict.submit} aria-label={pageDict.submit} className="inline-flex h-8 items-center rounded-md border border-indigo-200 px-2.5 text-xs font-semibold text-indigo-700 transition-colors hover:bg-indigo-50 dark:border-indigo-900/60 dark:text-indigo-300 dark:hover:bg-indigo-950/40">{pageDict.submit}</button>
                        ) : null}
                        {canApproveRentalContracts && contract.status === 'submitted' ? (
                          <button type="button" onClick={() => runAction(contract, 'approve')} title={pageDict.approve} aria-label={pageDict.approve} className="inline-flex h-8 items-center rounded-md border border-amber-200 px-2.5 text-xs font-semibold text-amber-700 transition-colors hover:bg-amber-50 dark:border-amber-900/60 dark:text-amber-300 dark:hover:bg-amber-950/40">{pageDict.approve}</button>
                        ) : null}
                        {canActivateRentalContracts && contract.status === 'approved' ? (
                          <button type="button" onClick={() => runAction(contract, 'activate')} title={pageDict.activate} aria-label={pageDict.activate} className="inline-flex h-8 items-center rounded-md border border-emerald-200 px-2.5 text-xs font-semibold text-emerald-700 transition-colors hover:bg-emerald-50 dark:border-emerald-900/60 dark:text-emerald-300 dark:hover:bg-emerald-950/40">{pageDict.activate}</button>
                        ) : null}
                        {canCancelRentalContracts && isRentalContractActionable(contract) ? (
                          <button type="button" onClick={() => runAction(contract, 'cancel')} title={pageDict.cancelContract} aria-label={pageDict.cancelContract} className="inline-flex h-8 items-center rounded-md border border-red-200 px-2.5 text-xs font-semibold text-red-700 transition-colors hover:bg-red-50 dark:border-red-900/60 dark:text-red-300 dark:hover:bg-red-950/40">{pageDict.cancelContract}</button>
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
      )}
    </AppLayout>
  );
}
