import { Head, Link, useForm, router } from '@inertiajs/react';
import { useMemo, useState, type FormEvent, type ReactElement } from 'react';
import AppLayout from '../../Components/AppLayout';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { Button, SearchableSelect, StatusBadge } from '../../Components/Primitives';
import SensitiveActionModal from '../../Components/SensitiveActionModal';
import { formatMoney, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';

type Customer = {
  id: string;
  code: string;
  name: string;
};

type ReceivableEntry = {
  id: string;
  customer_id: string;
  source_type: string;
  source_id: string;
  entry_date: string;
  due_date?: string;
  description?: string;
  currency: string;
  debit_minor: number;
  credit_minor: number;
  remaining_minor: number;
  customer?: Customer;
};

type Settlement = {
  id: string;
  customer_id: string;
  source_receivable_entry_id: string;
  target_receivable_entry_id: string;
  currency: string;
  amount_minor: number;
  status: 'active' | 'reversed';
  settled_at: string;
  reversed_at?: string;
  reason?: string;
  reversed_reason?: string;
  customer?: Customer;
  source_receivable_entry?: ReceivableEntry;
  target_receivable_entry?: ReceivableEntry;
  creator?: { id: number; name: string };
  reverser?: { id: number; name: string };
};

type Props = SharedPageProps & {
  creditEntries: ReceivableEntry[];
  selectedSourceEntry: ReceivableEntry | null;
  openTargetDebits: ReceivableEntry[];
  existingSettlements: {
    data: Settlement[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
  };
  customers: Customer[];
  filters: {
    customer_id?: string;
    source_entry_id?: string;
  };
};

export default function ReceivableSettlements({
  locale,
  creditEntries,
  selectedSourceEntry,
  openTargetDebits,
  customers,
  filters,
}: Props) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.receivableSettlements;
  const can = useCan();
  const canManageSettlements = can('sales.credit_notes');
  const [selectedCustomerId, setSelectedCustomerId] = useState(filters.customer_id || '');
  const [selectedSourceId, setSelectedSourceId] = useState(filters.source_entry_id || '');
  const [reversingId, setReversingId] = useState<string | null>(null);
  const [settleError, setSettleError] = useState<string | null>(null);
  const [reloadToken, setReloadToken] = useState(0);

  const settleForm = useForm({
    source_receivable_entry_id: selectedSourceEntry?.id || '',
    lines: openTargetDebits.map((item) => ({
      target_receivable_entry_id: item.id,
      amount_minor: 0,
      reason: '',
    })),
  });

  const activeFilterCount = [filters.customer_id, filters.source_entry_id].filter(Boolean).length;

  function handleFilterChange(custId: string, sourceId: string) {
    setSelectedCustomerId(custId);
    setSelectedSourceId(sourceId);
    const params: Record<string, string> = {};

    if (custId) params.customer_id = custId;
    if (sourceId) params.source_entry_id = sourceId;

    router.get('/sales/receivable-settlements', params, { preserveScroll: true, preserveState: true });
  }

  function clearFilters() {
    setSelectedCustomerId('');
    setSelectedSourceId('');
    router.get('/sales/receivable-settlements', {}, { preserveScroll: true, preserveState: true });
  }

  function handleSettleSubmit(e: FormEvent) {
    e.preventDefault();
    setSettleError(null);
    if (!canManageSettlements) {
      setSettleError(dict.app.actions.restricted);
      return;
    }
    if (!selectedSourceEntry) return;

    const validLines = settleForm.data.lines.filter((l) => l.amount_minor > 0);
    if (validLines.length === 0) {
      setSettleError(pageDict.positiveAmountRequired);
      return;
    }

    router.post(
      '/sales/receivable-settlements',
      {
        source_receivable_entry_id: selectedSourceEntry.id,
        lines: validLines,
      },
      {
        preserveScroll: true,
        onSuccess: () => setReloadToken((token) => token + 1),
        onError: (errs) => {
          if (errs.lines) setSettleError(errs.lines);
        },
      }
    );
  }

  const fmtMoney = (amount: number, curr: string) => formatMoney(amount, curr);
  const customerSelectOptions = customers.map((customer) => ({
    value: customer.id,
    label: `${customer.code} - ${getLocalizedName(customer.name, locale)}`,
  }));
  const sourceEntrySelectOptions = creditEntries.map((entry) => ({
    value: entry.id,
    label: `${getLocalizedName(entry.customer?.name, locale) || pageDict.customer} | ${pageDict.dateLabel}: ${entry.entry_date} | ${pageDict.sourceLabel}: ${entry.source_type} | ${pageDict.remainingCredit}: ${fmtMoney(entry.remaining_minor, entry.currency)}`,
  }));

  const columns = useMemo(() => [
    { data: 'settled_at', name: 'settled_at', title: pageDict.settledAt },
    { data: 'customer_name', name: 'customer_name', title: pageDict.customer },
    { data: 'source_receivable_entry_id', name: 'source_receivable_entry_id', title: pageDict.sourceEntry },
    { data: 'target_receivable_entry_id', name: 'target_receivable_entry_id', title: pageDict.targetEntry },
    { data: 'amount_minor', name: 'amount_minor', title: pageDict.amount, searchable: false },
    { data: 'status', name: 'status', title: pageDict.status },
    { data: 'actions', name: 'actions', title: pageDict.actions, orderable: false, searchable: false },
  ], [pageDict]);

  const slots = useMemo<DataTableSlots>(() => ({
    settled_at: (data: string): ReactElement => <span className="whitespace-nowrap font-mono text-xs">{new Date(data).toLocaleString()}</span>,
    customer_name: (data: string | Record<string, string>): ReactElement => <span className="font-semibold">{getLocalizedName(data, locale)}</span>,
    source_receivable_entry_id: (data: string): ReactElement => <span className="font-mono text-[10px]">{data.substring(0, 8)}</span>,
    target_receivable_entry_id: (data: string): ReactElement => <span className="font-mono text-[10px]">{data.substring(0, 8)}</span>,
    amount_minor: (data: number, _type: unknown, settlement: Settlement): ReactElement => (
      <span className="font-extrabold">{fmtMoney(data, settlement.currency)}</span>
    ),
    status: (data: Settlement['status']): ReactElement => (
      <span className={`inline-flex rounded-full px-2 py-0.5 text-[10px] font-extrabold uppercase ${data === 'active' ? 'bg-emerald-500/15 text-emerald-600' : 'bg-red-500/15 text-red-600'}`}>
        {data === 'active' ? pageDict.active : pageDict.reversed}
      </span>
    ),
    actions: (_data: unknown, _type: unknown, settlement: Settlement): ReactElement => (
      <div className="flex justify-end">
        {settlement.status === 'active' ? (
          canManageSettlements ? (
            <button
              type="button"
              onClick={() => setReversingId(settlement.id)}
              title={pageDict.reverse}
              aria-label={pageDict.reverse}
              className="rounded-lg border border-red-500/30 bg-red-500/10 px-2.5 py-1 text-[10px] font-bold text-red-600 hover:bg-red-500/20 transition-all cursor-pointer"
            >
              {pageDict.reverse}
            </button>
          ) : <StatusBadge tone="muted">{dict.app.actions.restricted}</StatusBadge>
        ) : <span className="text-[10px] text-[var(--text-muted)]">{pageDict.reversed}</span>}
      </div>
    ),
  }), [canManageSettlements, dict.app.actions.restricted, locale, pageDict]);

  return (
    <AppLayout active="customer-credit-notes.index">
      <Head title={pageDict.headTitle} />

      <div className="mx-auto max-w-7xl px-4 py-6 space-y-6">
        <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
          <div>
            <h1 className="text-2xl font-extrabold tracking-tight text-[var(--text-primary)]">
              {pageDict.title}
            </h1>
            <p className="text-sm text-[var(--text-muted)]">
              {pageDict.description}
            </p>
          </div>
          <Link
            href="/sales/credit-notes"
            title={pageDict.backToCreditNotes}
            aria-label={pageDict.backToCreditNotes}
            className="inline-flex items-center gap-2 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-semibold text-[var(--text-primary)] hover:bg-[var(--background)] transition-all"
          >
            {pageDict.backToCreditNotes}
          </Link>
        </div>

        <div className="rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-5 shadow-xs space-y-4">
          <h2 className="text-sm font-bold uppercase tracking-wider text-[var(--text-muted)]">
            {pageDict.stepSelectCredit}
          </h2>

          <div className="grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)_auto] gap-4">
            <div>
              <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                {pageDict.filterCustomer}
              </label>
              <SearchableSelect
                options={[{ value: '', label: pageDict.allCustomers }, ...customerSelectOptions]}
                value={selectedCustomerId}
                onChange={(value) => handleFilterChange(value || '', '')}
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                {pageDict.selectCreditEntry}
              </label>
              <SearchableSelect
                options={[{ value: '', label: pageDict.selectOpenCreditEntry }, ...sourceEntrySelectOptions]}
                value={selectedSourceId}
                onChange={(value) => handleFilterChange(selectedCustomerId, value || '')}
              />
            </div>

            <div className="self-end">
              <Button variant="secondary" onClick={clearFilters} disabled={activeFilterCount === 0}>{dict.app.accounting.clearFilters}</Button>
            </div>
          </div>
        </div>

        {selectedSourceEntry ? (
          <div className="rounded-2xl border border-blue-500/30 bg-blue-500/5 p-5 space-y-4">
            <div className="flex flex-col md:flex-row md:items-center justify-between border-b border-blue-500/20 pb-3 gap-2">
              <div>
                <h3 className="text-base font-bold text-[var(--text-primary)]">
                  {pageDict.settlingCreditFor} {getLocalizedName(selectedSourceEntry.customer?.name, locale)}
                </h3>
                <p className="text-xs text-[var(--text-muted)]">
                  {pageDict.sourceId}: {selectedSourceEntry.id} | {pageDict.entryDate}: {selectedSourceEntry.entry_date} | {pageDict.currency}: {selectedSourceEntry.currency}
                </p>
              </div>
              <div className="rounded-xl bg-blue-600 px-4 py-2 text-white font-extrabold text-sm">
                {pageDict.availableCredit}: {fmtMoney(selectedSourceEntry.remaining_minor, selectedSourceEntry.currency)}
              </div>
            </div>

            {settleError ? (
              <div className="rounded-xl border border-red-500/30 bg-red-500/10 p-3 text-xs font-semibold text-red-600 dark:text-red-400">
                {settleError}
              </div>
            ) : null}

            <form onSubmit={handleSettleSubmit} className="space-y-4">
              <h4 className="text-xs font-bold uppercase tracking-wider text-[var(--text-muted)]">
                {pageDict.stepAllocateCredit}
              </h4>

              {openTargetDebits.length === 0 ? (
                <p className="text-xs text-[var(--text-muted)] py-4 text-center">
                  {pageDict.noOpenTargets}
                </p>
              ) : (
                <div className="overflow-x-auto rounded-xl border border-[var(--border)] bg-[var(--surface)]">
                  <table className="w-full text-start text-xs">
                    <thead className="bg-[var(--background)] text-[var(--text-muted)] font-bold">
                      <tr>
                        <th className="p-3 text-start">{pageDict.entryDateHeader}</th>
                        <th className="p-3 text-start">{pageDict.sourceRef}</th>
                        <th className="p-3 text-start">{pageDict.descriptionHeader}</th>
                        <th className="p-3 text-end">{pageDict.originalDebit}</th>
                        <th className="p-3 text-end">{pageDict.remainingOpenDebit}</th>
                        <th className="p-3 text-end w-48">{pageDict.settlementAmount}</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-[var(--border)]">
                      {openTargetDebits.map((target, idx) => {
                        const line = settleForm.data.lines.find((l) => l.target_receivable_entry_id === target.id);
                        return (
                          <tr key={target.id} className="hover:bg-[var(--background)]/50">
                            <td className="p-3">{target.entry_date}</td>
                            <td className="p-3 font-mono">{target.source_type} ({target.id.substring(0, 8)})</td>
                            <td className="p-3">{target.description || pageDict.invoiceDebitFallback}</td>
                            <td className="p-3 text-end font-semibold">{fmtMoney(target.debit_minor, target.currency)}</td>
                            <td className="p-3 text-end font-bold text-emerald-600">{fmtMoney(target.remaining_minor, target.currency)}</td>
                            <td className="p-3 text-end">
                              <input
                                type="number"
                                min="0"
                                max={Math.min(target.remaining_minor, selectedSourceEntry.remaining_minor)}
                                value={line?.amount_minor || ''}
                                onChange={(e) => {
                                  const val = parseInt(e.target.value, 10) || 0;
                                  const newLines = [...settleForm.data.lines];
                                  const targetLineIdx = newLines.findIndex((l) => l.target_receivable_entry_id === target.id);
                                  if (targetLineIdx >= 0) {
                                    newLines[targetLineIdx].amount_minor = val;
                                  }
                                  settleForm.setData('lines', newLines);
                                }}
                                placeholder="0"
                                className="w-full text-end rounded-lg border border-[var(--border)] bg-[var(--background)] px-2.5 py-1 text-xs text-[var(--text-primary)] font-mono font-bold focus:outline-hidden focus:ring-2 focus:ring-blue-500"
                              />
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              )}

              {openTargetDebits.length > 0 ? (
                <div className="flex justify-end gap-3 pt-2">
                  {canManageSettlements ? (
                    <button
                      type="submit"
                      disabled={settleForm.processing}
                      title={pageDict.confirmSettlement}
                      aria-label={pageDict.confirmSettlement}
                      className="rounded-xl bg-blue-600 px-6 py-2.5 text-xs font-bold text-white shadow-md shadow-blue-500/20 hover:bg-blue-700 disabled:opacity-50 transition-all cursor-pointer"
                    >
                      {settleForm.processing ? pageDict.processingSettlement : pageDict.confirmSettlement}
                    </button>
                  ) : (
                    <StatusBadge tone="muted">{dict.app.actions.restricted}</StatusBadge>
                  )}
                </div>
              ) : null}
            </form>
          </div>
        ) : null}

        <div className="rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-5 shadow-xs space-y-4">
          <h2 className="text-base font-bold text-[var(--text-primary)]">
            {pageDict.settlementAuditLog}
          </h2>

          <div className="overflow-hidden rounded-xl border border-[var(--border)]">
            <ServerDataTable
              ajaxUrl="/sales/receivable-settlements/data"
              columns={columns}
              filters={{ customer_id: selectedCustomerId }}
              locale={locale}
              order={[[0, 'desc']]}
              pageLength={25}
              reloadToken={reloadToken}
              slots={slots}
              tableId="receivable-settlements-table"
            />
          </div>
        </div>

        <SensitiveActionModal
          isOpen={reversingId !== null}
          onClose={() => setReversingId(null)}
          onConfirm={(payload) => {
            if (!reversingId) return;
            router.post(`/sales/receivable-settlements/${reversingId}/reverse`, payload, {
              preserveScroll: true,
              onSuccess: () => {
                setReversingId(null);
                setReloadToken((token) => token + 1);
              },
            });
          }}
          confirmCode="REVERSE_RECEIVABLE_SETTLEMENT"
          reasonRequired={true}
          locale={locale}
        />
      </div>
    </AppLayout>
  );
}
