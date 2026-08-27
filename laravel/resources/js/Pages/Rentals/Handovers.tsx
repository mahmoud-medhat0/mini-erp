import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Button, Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatDate, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';

type TranslatedName = Record<string, string> | string | null;
type Customer = { id: string; code: string; name: TranslatedName };
type Branch = { id: string; code: string; name: TranslatedName };
type RentableItem = { id: string; code: string; name: TranslatedName; status: string };
type ContractLine = { id: string; line_no: number; rentable_item_id: string; rentable_item?: RentableItem | null };
type Contract = {
  id: string;
  number?: string | null;
  status: string;
  customer?: Customer | null;
  branch?: Branch | null;
  lines?: ContractLine[];
};
type HandoverLine = {
  id: string;
  rental_contract_line_id: string;
  rentable_item_id: string;
  condition_out: string;
  accessories_out?: string[] | null;
  notes?: string | null;
  rentable_item?: RentableItem | null;
};
type Handover = {
  id: string;
  number?: string | null;
  status: string;
  handover_date: string;
  notes?: string | null;
  contract?: Contract | null;
  customer?: Customer | null;
  branch?: Branch | null;
  lines?: HandoverLine[];
};
type EditableLine = {
  rental_contract_line_id: string;
  condition_out: string;
  accessories_out: string;
  notes: string;
};
type Props = SharedPageProps & {
  handovers: { data: Handover[]; total: number };
  contracts: Contract[];
  statuses: string[];
  conditions: string[];
  filters: { search?: string; status?: string };
};

function today(): string {
  return new Date().toISOString().slice(0, 10);
}

function statusTone(value: string): 'ok' | 'muted' | 'danger' | 'warning' | 'info' {
  if (value === 'confirmed') return 'ok';
  if (value === 'cancelled') return 'danger';
  return 'muted';
}

function namePart(name: TranslatedName, locale: 'en' | 'ar'): string {
  return getLocalizedName(name, locale);
}

export default function RentalHandoversIndex({
  locale,
  handovers,
  contracts = [],
  statuses = [],
  conditions = [],
  filters,
}: Props) {
  const dict = getDictionary(locale);
  const activeLocale = locale === 'ar' ? 'ar' : 'en';
  const pageDict = dict.app.pages.rentalHandovers;
  const can = useCan();
  const [search, setSearch] = useState(filters.search || '');
  const [status, setStatus] = useState(filters.status || '');
  const [showForm, setShowForm] = useState(false);

  const form = useForm({
    rental_contract_id: contracts[0]?.id || '',
    handover_date: today(),
    notes: '',
    lines: [] as EditableLine[],
  });

  const contractOptions = useMemo(() => contracts.map((contract) => ({
    value: contract.id,
    label: `${contract.number || pageDict.notNumbered} - ${contract.customer ? `${contract.customer.code} - ${namePart(contract.customer.name, activeLocale)}` : ''}`,
    sublabel: contract.branch ? `${contract.branch.code} - ${namePart(contract.branch.name, activeLocale)}` : pageDict.noBranch,
  })), [contracts, activeLocale, pageDict.notNumbered, pageDict.noBranch]);

  const selectedContract = contracts.find((contract) => contract.id === form.data.rental_contract_id);
  const selectedLines = selectedContract?.lines?.filter((line) => ['allocated', 'rented'].includes(line.rentable_item?.status || '')) || [];
  const statusOptions = statuses.map((item) => ({ value: item, label: pageDict.statuses[item as keyof typeof pageDict.statuses] || item }));
  const conditionOptions = conditions.map((item) => ({ value: item, label: pageDict.conditions[item as keyof typeof pageDict.conditions] || item }));

  function resetForm(contractId = contracts[0]?.id || '') {
    const contract = contracts.find((item) => item.id === contractId);
    form.setData({
      rental_contract_id: contractId,
      handover_date: today(),
      notes: '',
      lines: (contract?.lines || [])
        .filter((line) => ['allocated', 'rented'].includes(line.rentable_item?.status || ''))
        .map((line) => ({
          rental_contract_line_id: line.id,
          condition_out: 'good',
          accessories_out: '',
          notes: '',
        })),
    });
    form.clearErrors();
  }

  function openCreate() {
    resetForm();
    setShowForm(true);
  }

  function changeContract(value: string | null) {
    resetForm(value || '');
  }

  function updateLine(index: number, patch: Partial<EditableLine>) {
    form.setData('lines', form.data.lines.map((line, lineIndex) => (lineIndex === index ? { ...line, ...patch } : line)));
  }

  function applyFilters() {
    router.get('/rentals/handovers', { search, status }, { preserveScroll: true, preserveState: true });
  }

  const activeFilterCount = [search, status].filter(Boolean).length;

  function clearFilters() {
    setSearch('');
    setStatus('');
    router.get('/rentals/handovers', {}, { preserveScroll: true, preserveState: true });
  }

  function submit(e: FormEvent) {
    e.preventDefault();
    form.post('/rentals/handovers', {
      preserveScroll: true,
      onSuccess: () => {
        setShowForm(false);
        form.reset();
      },
    });
  }

  function action(path: string, confirmation?: string) {
    if (confirmation && !confirm(confirmation)) return;

    router.post(path, {}, { preserveScroll: true });
  }

  return (
    <AppLayout active="rentals.handovers.index">
      <Head title={pageDict.headTitle} />
      <PageHeader
        title={pageDict.title}
        description={pageDict.description}
        actions={can('rentals.deliver') ? <Button onClick={openCreate}>{pageDict.create}</Button> : null}
      />

      <Card className="mb-4 p-4">
        <div className="grid gap-3 md:grid-cols-[1fr_220px_auto_auto]">
          <input className="rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-sm text-[var(--text-primary)]" value={search} onChange={(e) => setSearch(e.target.value)} placeholder={pageDict.search} />
          <SearchableSelect options={[{ value: '', label: pageDict.allStatuses }, ...statusOptions]} value={status || null} onChange={(value) => setStatus(value || '')} label={pageDict.status} />
          <Button onClick={applyFilters}>{pageDict.applyFilter}</Button>
          <Button variant="secondary" onClick={clearFilters} disabled={activeFilterCount === 0}>{pageDict.clearFilter}</Button>
        </div>
      </Card>

      {showForm ? (
        <Card className="mb-4 p-4">
          <form onSubmit={submit} className="space-y-4">
            <div className="flex items-start justify-between gap-3">
              <div>
                <h2 className="m-0 text-lg font-bold text-[var(--text-primary)]">{pageDict.createTitle}</h2>
                <p className="mt-1 text-sm text-[var(--text-secondary)]">{pageDict.formHint}</p>
              </div>
              <Button variant="secondary" onClick={() => setShowForm(false)}>{pageDict.close}</Button>
            </div>

            <div className="grid gap-3 md:grid-cols-2">
              <SearchableSelect label={pageDict.contract} value={form.data.rental_contract_id} onChange={changeContract} options={contractOptions} />
              <DatePicker label={pageDict.handoverDate} value={form.data.handover_date} onChange={(value) => form.setData('handover_date', value || today())} required />
            </div>

            <label className="block">
              <span className="mb-1 block text-xs font-bold text-[var(--text-secondary)]">{pageDict.notes}</span>
              <textarea className="min-h-20 w-full rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-sm text-[var(--text-primary)]" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
            </label>

            <div>
              <h3 className="m-0 text-sm font-bold text-[var(--text-primary)]">{pageDict.lines}</h3>
              {selectedLines.length === 0 ? (
                <p className="mt-2 text-sm text-[var(--text-secondary)]">{pageDict.noContracts}</p>
              ) : (
                <div className="mt-3 grid gap-3">
                  {form.data.lines.map((line, index) => {
                    const contractLine = selectedLines.find((item) => item.id === line.rental_contract_line_id);
                    return (
                      <div key={line.rental_contract_line_id} className="rounded-md border border-[var(--border)] bg-[var(--background)] p-3">
                        <div className="mb-3 text-sm font-bold text-[var(--text-primary)]">
                          {contractLine?.rentable_item ? `${contractLine.rentable_item.code} - ${namePart(contractLine.rentable_item.name, activeLocale)}` : pageDict.rentableItem}
                        </div>
                        <div className="grid gap-3 md:grid-cols-3">
                          <label className="block">
                            <span className="mb-1 block text-xs font-bold text-[var(--text-secondary)]">{pageDict.conditionOut}</span>
                            <select className="w-full rounded-md border border-[var(--border)] bg-[var(--surface)] px-3 py-2 text-sm text-[var(--text-primary)]" value={line.condition_out} onChange={(e) => updateLine(index, { condition_out: e.target.value })}>
                              {conditionOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                            </select>
                          </label>
                          <label className="block">
                            <span className="mb-1 block text-xs font-bold text-[var(--text-secondary)]">{pageDict.accessoriesOut}</span>
                            <input className="w-full rounded-md border border-[var(--border)] bg-[var(--surface)] px-3 py-2 text-sm text-[var(--text-primary)]" value={line.accessories_out} onChange={(e) => updateLine(index, { accessories_out: e.target.value })} />
                          </label>
                          <label className="block">
                            <span className="mb-1 block text-xs font-bold text-[var(--text-secondary)]">{pageDict.notes}</span>
                            <input className="w-full rounded-md border border-[var(--border)] bg-[var(--surface)] px-3 py-2 text-sm text-[var(--text-primary)]" value={line.notes} onChange={(e) => updateLine(index, { notes: e.target.value })} />
                          </label>
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>

            <div className="flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setShowForm(false)}>{pageDict.cancel}</Button>
              <Button type="submit" disabled={form.processing || form.data.lines.length === 0}>{pageDict.save}</Button>
            </div>
          </form>
        </Card>
      ) : null}

      {handovers.data.length === 0 ? (
        <EmptyState title={pageDict.emptyTitle} description={pageDict.emptyDescription} />
      ) : (
        <Card className="overflow-hidden">
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{pageDict.number}</th>
                <th className={tableClasses.th}>{pageDict.contract}</th>
                <th className={tableClasses.th}>{pageDict.handoverDate}</th>
                <th className={tableClasses.th}>{pageDict.status}</th>
                <th className={tableClasses.th}>{pageDict.items}</th>
                <th className={tableClasses.th}>{pageDict.actions}</th>
              </tr>
            </thead>
            <tbody>
              {handovers.data.map((handover) => (
                <tr key={handover.id}>
                  <td className={tableClasses.td}><span className="font-mono font-bold">{handover.number || pageDict.notNumbered}</span></td>
                  <td className={tableClasses.td}>
                    <div className="font-semibold">{handover.contract?.number || pageDict.notNumbered}</div>
                    <div className="mt-1 text-xs text-[var(--text-muted)]">{handover.customer ? `${handover.customer.code} - ${namePart(handover.customer.name, activeLocale)}` : ''}</div>
                  </td>
                  <td className={tableClasses.td}>{formatDate(handover.handover_date)}</td>
                  <td className={tableClasses.td}><StatusBadge tone={statusTone(handover.status)}>{pageDict.statuses[handover.status as keyof typeof pageDict.statuses] || handover.status}</StatusBadge></td>
                  <td className={tableClasses.td}>{handover.lines?.map((line) => line.rentable_item?.code).filter(Boolean).join(', ')}</td>
                  <td className={tableClasses.td}>
                    <div className="flex flex-wrap gap-2">
                      {handover.status === 'draft' && can('rentals.deliver') ? <Button onClick={() => action(`/rentals/handovers/${handover.id}/confirm`, pageDict.confirmConfirm)}>{pageDict.confirm}</Button> : null}
                      {handover.status === 'draft' && can('rentals.cancel') ? <Button variant="danger" onClick={() => action(`/rentals/handovers/${handover.id}/cancel`, pageDict.confirmCancel)}>{pageDict.cancelHandover}</Button> : null}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </AppLayout>
  );
}
