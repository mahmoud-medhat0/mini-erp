import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { AccountingAmount, Button, Card, PageHeader, SearchableSelect, StatusBadge } from '../../Components/Primitives';
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
  currency: string;
  customer?: Customer | null;
  branch?: Branch | null;
  lines?: ContractLine[];
};
type ReturnLine = {
  id: string;
  rental_contract_line_id: string;
  rentable_item_id: string;
  condition_in: string;
  outcome: string;
  estimated_damage_charge_minor: number;
  accessories_in?: string[] | null;
  inspection_notes?: string | null;
  rentable_item?: RentableItem | null;
};
type RentalReturn = {
  id: string;
  number?: string | null;
  status: string;
  return_date: string;
  contract?: Contract | null;
  customer?: Customer | null;
  branch?: Branch | null;
  lines?: ReturnLine[];
  damage_total_minor?: number | null;
};
type EditableReturnLine = {
  rental_contract_line_id: string;
  condition_in: string;
  outcome: string;
  estimated_damage_charge: string;
  accessories_in: string;
  inspection_notes: string;
};
type Props = SharedPageProps & {
  returns?: RentalReturn[];
  contracts: Contract[];
  statuses: string[];
  conditions: string[];
  outcomes: string[];
  filters: { search?: string; status?: string };
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

function statusTone(value: string): 'ok' | 'muted' | 'danger' | 'warning' | 'info' {
  if (value === 'completed') return 'ok';
  if (value === 'submitted') return 'info';
  if (value === 'cancelled') return 'danger';
  return 'muted';
}

function namePart(name: TranslatedName, locale: 'en' | 'ar'): string {
  return getLocalizedName(name, locale);
}

export default function RentalReturnsIndex({
  locale,
  contracts = [],
  statuses = [],
  conditions = [],
  outcomes = [],
  filters,
}: Props) {
  const dict = getDictionary(locale);
  const activeLocale = locale === 'ar' ? 'ar' : 'en';
  const pageDict = dict.app.pages.rentalReturns;
  const can = useCan();
  const [initialSearch, setInitialSearch] = useState(filters.search || '');
  const [status, setStatus] = useState(filters.status || '');
  const [showForm, setShowForm] = useState(false);
  const [tableReloadToken, setTableReloadToken] = useState(0);
  const [tableResetToken, setTableResetToken] = useState(0);

  const form = useForm({
    rental_contract_id: contracts[0]?.id || '',
    return_date: today(),
    notes: '',
    lines: [] as EditableReturnLine[],
  });

  const contractOptions = useMemo(() => contracts.map((contract) => ({
    value: contract.id,
    label: `${contract.number || pageDict.notNumbered} - ${contract.customer ? `${contract.customer.code} - ${namePart(contract.customer.name, activeLocale)}` : ''}`,
    sublabel: contract.branch ? `${contract.branch.code} - ${namePart(contract.branch.name, activeLocale)}` : pageDict.noBranch,
  })), [contracts, activeLocale, pageDict.notNumbered, pageDict.noBranch]);

  const selectedContract = contracts.find((contract) => contract.id === form.data.rental_contract_id);
  const selectedLines = selectedContract?.lines?.filter((line) => line.rentable_item?.status === 'rented') || [];
  const statusOptions = statuses.map((item) => ({ value: item, label: pageDict.statuses[item as keyof typeof pageDict.statuses] || item }));
  const conditionOptions = conditions.map((item) => ({ value: item, label: pageDict.conditions[item as keyof typeof pageDict.conditions] || item }));
  const outcomeOptions = outcomes.map((item) => ({ value: item, label: pageDict.outcomes[item as keyof typeof pageDict.outcomes] || item }));

  function resetForm(contractId = contracts[0]?.id || '') {
    const contract = contracts.find((item) => item.id === contractId);
    form.setData({
      rental_contract_id: contractId,
      return_date: today(),
      notes: '',
      lines: (contract?.lines || [])
        .filter((line) => line.rentable_item?.status === 'rented')
        .map((line) => ({
          rental_contract_line_id: line.id,
          condition_in: 'good',
          outcome: 'returned',
          estimated_damage_charge: '0.00',
          accessories_in: '',
          inspection_notes: '',
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

  function updateLine(index: number, patch: Partial<EditableReturnLine>) {
    form.setData('lines', form.data.lines.map((line, lineIndex) => (lineIndex === index ? { ...line, ...patch } : line)));
  }

  const activeFilterCount = [initialSearch, status].filter(Boolean).length;

  function clearFilters() {
    setInitialSearch('');
    setStatus('');
    setTableResetToken((value) => value + 1);
  }

  function submit(e: FormEvent) {
    e.preventDefault();
    form.transform((data) => ({
      ...data,
      lines: data.lines.map((line) => ({
        rental_contract_line_id: line.rental_contract_line_id,
        condition_in: line.condition_in,
        outcome: line.outcome,
        estimated_damage_charge_minor: amountToMinor(line.estimated_damage_charge),
        accessories_in: line.accessories_in,
        inspection_notes: line.inspection_notes,
      })),
    }));
    form.post('/rentals/returns', {
      preserveScroll: true,
      onSuccess: () => {
        setShowForm(false);
        form.reset();
        setTableReloadToken((value) => value + 1);
      },
    });
  }

  function action(path: string, confirmation?: string) {
    if (confirmation && !confirm(confirmation)) return;

    router.post(path, {}, {
      preserveScroll: true,
      onSuccess: () => setTableReloadToken((value) => value + 1),
    });
  }

  const columns = useMemo(() => [
    { data: 'number', name: 'rental_return.number', title: pageDict.number },
    { data: 'contract_number', name: 'contract_number', title: pageDict.contract },
    { data: 'return_date', name: 'rental_return.return_date', title: pageDict.returnDate },
    { data: 'status', name: 'rental_return.status', title: pageDict.status },
    { data: 'items', name: 'items', title: pageDict.items, orderable: false, searchable: false },
    { data: 'damage_total_minor', name: 'damage_total_minor', title: pageDict.estimatedDamageCharge, searchable: false, className: 'text-end' },
    { data: 'actions', name: 'actions', title: pageDict.actions, orderable: false, searchable: false },
    { data: 'created_at', name: 'rental_return.created_at', title: '', visible: false, searchable: false },
  ], [pageDict]);

  const slots = useMemo<DataTableSlots>(() => ({
    // datatables.net-react targets a slot by the column's DataTables `name`
    // (as `<name>:name`), not its `data` key - these columns declare a
    // dot-qualified `name` for server-side ordering, so the slot keys must
    // match that qualified name exactly or they silently never attach.
    'rental_return.number': (value: string | null) => <span className="font-mono font-bold">{value || pageDict.notNumbered}</span>,
    contract_number: (_value: unknown, _type: unknown, rentalReturn: RentalReturn) => (
      <div>
        <div className="font-semibold">{rentalReturn.contract?.number || pageDict.notNumbered}</div>
        <div className="mt-1 text-xs text-[var(--text-muted)]">{rentalReturn.customer ? `${rentalReturn.customer.code} - ${namePart(rentalReturn.customer.name, activeLocale)}` : ''}</div>
      </div>
    ),
    'rental_return.return_date': (value: string) => formatDate(value),
    'rental_return.status': (value: string) => <StatusBadge tone={statusTone(value)}>{pageDict.statuses[value as keyof typeof pageDict.statuses] || value}</StatusBadge>,
    items: (_value: unknown, _type: unknown, rentalReturn: RentalReturn) => rentalReturn.lines?.map((line) => line.rentable_item?.code).filter(Boolean).join(', '),
    damage_total_minor: (value: number | null, _type: unknown, rentalReturn: RentalReturn) => (
      <AccountingAmount amountMinor={Number(value || 0)} currency={rentalReturn.contract?.currency || pageDict.noCurrency} />
    ),
    actions: (_value: unknown, _type: unknown, rentalReturn: RentalReturn) => (
      <div className="flex flex-wrap gap-2">
        {rentalReturn.status === 'draft' && can('rentals.return') ? <Button onClick={() => action(`/rentals/returns/${rentalReturn.id}/submit`, pageDict.confirmSubmit)}>{pageDict.submit}</Button> : null}
        {rentalReturn.status === 'submitted' && can('rentals.inspect') ? <Button onClick={() => action(`/rentals/returns/${rentalReturn.id}/complete`, pageDict.confirmComplete)}>{pageDict.complete}</Button> : null}
        {['draft', 'submitted'].includes(rentalReturn.status) && can('rentals.cancel') ? <Button variant="danger" onClick={() => action(`/rentals/returns/${rentalReturn.id}/cancel`, pageDict.confirmCancel)}>{pageDict.cancelReturn}</Button> : null}
      </div>
    ),
  }), [activeLocale, can, pageDict]);

  const tableFilters = useMemo(() => ({ status }), [status]);

  const toolbar = (
    <div className="flex flex-wrap items-center gap-2">
      <div className="w-48">
        <SearchableSelect options={[{ value: '', label: pageDict.allStatuses }, ...statusOptions]} value={status || null} onChange={(value) => setStatus(value || '')} />
      </div>
      <Button variant="secondary" onClick={clearFilters} disabled={activeFilterCount === 0}>{pageDict.clearFilter}</Button>
    </div>
  );

  return (
    <AppLayout active="rentals.returns.index">
      <Head title={pageDict.headTitle} />
      <PageHeader
        title={pageDict.title}
        description={pageDict.description}
        actions={can('rentals.return') ? <Button onClick={openCreate}>{pageDict.create}</Button> : null}
      />

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
              <DatePicker label={pageDict.returnDate} value={form.data.return_date} onChange={(value) => form.setData('return_date', value || today())} required />
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
                          <SearchableSelect
                            label={pageDict.conditionIn}
                            value={line.condition_in}
                            onChange={(value) => updateLine(index, { condition_in: value || 'good' })}
                            options={conditionOptions}
                            isClearable={false}
                          />
                          <SearchableSelect
                            label={pageDict.outcome}
                            value={line.outcome}
                            onChange={(value) => updateLine(index, { outcome: value || 'returned' })}
                            options={outcomeOptions}
                            isClearable={false}
                          />
                          <label className="block">
                            <span className="mb-1 block text-xs font-bold text-[var(--text-secondary)]">{pageDict.estimatedDamageCharge}</span>
                            <input className="w-full rounded-md border border-[var(--border)] bg-[var(--surface)] px-3 py-2 text-sm text-[var(--text-primary)]" value={line.estimated_damage_charge} onChange={(e) => updateLine(index, { estimated_damage_charge: e.target.value })} />
                          </label>
                          <label className="block">
                            <span className="mb-1 block text-xs font-bold text-[var(--text-secondary)]">{pageDict.accessoriesIn}</span>
                            <input className="w-full rounded-md border border-[var(--border)] bg-[var(--surface)] px-3 py-2 text-sm text-[var(--text-primary)]" value={line.accessories_in} onChange={(e) => updateLine(index, { accessories_in: e.target.value })} />
                          </label>
                          <label className="block md:col-span-2">
                            <span className="mb-1 block text-xs font-bold text-[var(--text-secondary)]">{pageDict.inspectionNotes}</span>
                            <input className="w-full rounded-md border border-[var(--border)] bg-[var(--surface)] px-3 py-2 text-sm text-[var(--text-primary)]" value={line.inspection_notes} onChange={(e) => updateLine(index, { inspection_notes: e.target.value })} />
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

      <Card className="overflow-hidden p-0">
        <ServerDataTable
          key={tableResetToken}
          ajaxUrl="/rentals/returns/data"
          columns={columns}
          filters={tableFilters}
          initialSearch={initialSearch}
          locale={locale}
          order={[[7, 'desc']]}
          reloadToken={tableReloadToken}
          slots={slots}
          tableId="rental-returns-data-table"
          toolbar={toolbar}
        />
      </Card>
    </AppLayout>
  );
}
