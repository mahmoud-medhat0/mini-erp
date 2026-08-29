import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Button, Card, EmptyState, PageHeader, SearchableSelect, SensitiveActionModal, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { PaginationLink, SharedPageProps } from '../../Types';

type CustomerReceiptRow = {
  id: string;
  number: string;
  customer_id: string;
  customer?: { id: string; code: string; name: string };
  receipt_date: string;
  currency: string;
  amount_minor: number;
  unapplied_minor: number;
};

type ReceivableEntryRow = {
  id: string;
  entry_date: string;
  due_date?: string | null;
  currency: string;
  original_amount_minor: number;
  unapplied_minor: number;
  description?: string | null;
};

type AllocationRow = {
  id: string;
  customerReceipt?: { id: string; number: string };
  receivableEntry?: { id: string; entry_date: string };
  customer?: { id: string; code: string; name: string };
  currency: string;
  amount_minor: number;
  created_at: string;
};

type ReceivableAllocationsProps = SharedPageProps & {
  receipts: CustomerReceiptRow[];
  selectedReceipt?: CustomerReceiptRow | null;
  openReceivables: ReceivableEntryRow[];
  existingAllocations: {
    data: AllocationRow[];
    links: PaginationLink[];
  };
  customers: Array<{ id: string; code: string; name: string }>;
  filters: {
    customer_id?: string;
    receipt_id?: string;
  };
};

export default function ReceivableAllocationsIndex({
  locale,
  receipts = [],
  selectedReceipt,
  openReceivables = [],
  existingAllocations,
  customers = [],
  filters,
}: ReceivableAllocationsProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const can = useCan();
  const canManageReceivableAllocations = can('customers.allocations');

  const [allocationAmounts, setAllocationAmounts] = useState<Record<string, string>>({});
  const [allocationError, setAllocationError] = useState<string | null>(null);
  const [reversingId, setReversingId] = useState<string | null>(null);

  const { post, transform, processing } = useForm({});

  const activeFilterCount = [filters.customer_id, filters.receipt_id].filter(Boolean).length;

  const applyFilters = (next: Record<string, string>) => {
    const customerId = next.customer_id ?? filters.customer_id ?? '';
    const receiptId = next.receipt_id ?? filters.receipt_id ?? '';
    const params: Record<string, string> = {};

    if (customerId) params.customer_id = customerId;
    if (receiptId) params.receipt_id = receiptId;

    router.get('/receivable-allocations', params, { preserveScroll: true, preserveState: true });
  };

  function clearFilters() {
    router.get('/receivable-allocations', {}, { preserveScroll: true, preserveState: true });
  }

  const handleCustomerSelect = (customerId: string | null) => {
    applyFilters({ customer_id: customerId || '', receipt_id: '' });
  };

  const handleReceiptSelect = (receiptId: string | null) => {
    applyFilters({ receipt_id: receiptId || '' });
  };

  const handleAmountChange = (entryId: string, val: string) => {
    setAllocationError(null);
    setAllocationAmounts((prev) => ({
      ...prev,
      [entryId]: val,
    }));
  };

  const submitAllocation = (e: FormEvent) => {
    e.preventDefault();
    if (!selectedReceipt) return;
    if (!canManageReceivableAllocations) {
      setAllocationError(dict.app.actions.restricted);
      return;
    }

    const lines = Object.entries(allocationAmounts)
      .map(([receivable_entry_id, val]) => {
        const num = parseFloat(val || '0');
        return {
          receivable_entry_id,
          amount_minor: Math.round(num * 100),
        };
      })
      .filter((line) => line.amount_minor > 0);

    if (lines.length === 0) {
      setAllocationError(dict.app.pages.receivableAllocations.pleaseEnterAtLeastOneValid);
      return;
    }

    transform(() => ({
      receipt_id: selectedReceipt.id,
      lines,
    }));

    post('/receivable-allocations', {
      preserveScroll: true,
      onSuccess: () => {
        setAllocationAmounts({});
        setAllocationError(null);
      },
    });
  };

  const handleReverse = (id: string) => {
    setReversingId(id);
  };

  const receiptSelectOptions = receipts.map((r) => ({
    value: r.id,
    label: `${r.number} - ${r.customer?.name || accDict.notAvailable} (${dict.app.pages.receivableAllocations.unappliedAmount} ${formatMoney(r.unapplied_minor, r.currency)})`,
  }));
  const customerSelectOptions = customers.map((c) => ({
    value: c.id,
    label: `${c.code} - ${c.name}`,
  }));

  return (
    <AppLayout active="receivable-allocations.index">
      <Head title={dict.app.pages.receivableAllocations.arAllocationsMiniErp} />

      <PageHeader
        title={dict.app.pages.receivableAllocations.receivableAllocations}
        description={dict.app.pages.receivableAllocations.allocatePostedReceiptsAgainstOpenCustomer}
      />

      {/* Workspace Area */}
      <div className="grid gap-6 lg:grid-cols-3 mb-8">
        <Card className="p-5 lg:col-span-1">
          <h2 className="text-sm font-bold text-[var(--text-primary)] mb-3">
            {dict.app.pages.receivableAllocations.text1SelectUnappliedReceipt}
          </h2>

          <div className="space-y-4">
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {dict.app.pages.receivableAllocations.filterCustomer}
              </label>
              <SearchableSelect
                options={[{ value: '', label: dict.app.pages.receivableAllocations.allCustomers }, ...customerSelectOptions]}
                value={filters.customer_id || ''}
                onChange={(val) => handleCustomerSelect(val)}
              />
            </div>

            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {dict.app.pages.receivableAllocations.receiptNumber}
              </label>
              <SearchableSelect
                options={receiptSelectOptions}
                value={selectedReceipt?.id || null}
                onChange={(val) => handleReceiptSelect(val)}
                placeholder={dict.app.pages.receivableAllocations.selectReceipt}
              />
            </div>

            <Button variant="secondary" onClick={clearFilters} disabled={activeFilterCount === 0}>{accDict.clearFilters}</Button>

            {selectedReceipt ? (
              <div className="rounded-xl border border-[var(--border)] bg-[var(--background)] p-4 space-y-2 text-xs">
                <div className="flex justify-between">
                  <span className="text-[var(--text-secondary)]">{dict.app.pages.receivableAllocations.receipt}</span>
                  <span className="font-mono font-bold">{selectedReceipt.number}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-[var(--text-secondary)]">{dict.app.pages.receivableAllocations.customer}</span>
                  <span className="font-semibold">{selectedReceipt.customer?.name}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-[var(--text-secondary)]">{dict.app.pages.receivableAllocations.totalAmount}</span>
                  <span className="font-mono font-bold">{formatMoney(selectedReceipt.amount_minor, selectedReceipt.currency)}</span>
                </div>
                <div className="flex justify-between border-t border-[var(--border)] pt-2">
                  <span className="text-[var(--text-secondary)]">{dict.app.pages.receivableAllocations.unappliedAmount}</span>
                  <span className="font-mono font-bold text-amber-600">{formatMoney(selectedReceipt.unapplied_minor, selectedReceipt.currency)}</span>
                </div>
              </div>
            ) : null}
          </div>
        </Card>

        <Card className="p-5 lg:col-span-2">
          <h2 className="text-sm font-bold text-[var(--text-primary)] mb-3">
            {dict.app.pages.receivableAllocations.text2OpenReceivableEntries}
          </h2>

          {!selectedReceipt ? (
            <div className="py-12 text-center text-xs text-[var(--text-muted)] border border-dashed border-[var(--border)] rounded-xl">
              {dict.app.pages.receivableAllocations.selectAReceiptOnTheLeft}
            </div>
          ) : openReceivables.length === 0 ? (
            <div className="py-12 text-center text-xs text-[var(--text-muted)] border border-dashed border-[var(--border)] rounded-xl">
              {dict.app.pages.receivableAllocations.noOpenReceivableEntriesFoundFor}
            </div>
          ) : (
            <form onSubmit={submitAllocation}>
              <div className="overflow-x-auto rounded-xl border border-[var(--border)] mb-4">
                <table className={tableClasses.table}>
                  <thead>
                    <tr>
                      <th className={tableClasses.th}>{dict.app.pages.receivableAllocations.date}</th>
                      <th className={tableClasses.th}>{dict.app.pages.receivableAllocations.dueDate}</th>
                      <th className={tableClasses.th}>{dict.app.pages.receivableAllocations.originalAmount}</th>
                      <th className={tableClasses.th}>{dict.app.pages.receivableAllocations.openBalance}</th>
                      <th className={tableClasses.th}>{dict.app.pages.receivableAllocations.allocateAmount}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {openReceivables.map((rec) => (
                      <tr key={rec.id} className="hover:bg-[var(--background)]/50">
                        <td className={`${tableClasses.td} font-mono text-xs`}>{rec.entry_date}</td>
                        <td className={`${tableClasses.td} font-mono text-xs`}>{rec.due_date || accDict.notAvailable}</td>
                        <td className={`${tableClasses.td} font-mono text-xs`}>{formatMoney(rec.original_amount_minor, rec.currency)}</td>
                        <td className={`${tableClasses.td} font-mono font-bold text-xs text-amber-600`}>{formatMoney(rec.unapplied_minor, rec.currency)}</td>
                        <td className={tableClasses.td}>
                          <input
                            type="number"
                            step="0.01"
                            max={(rec.unapplied_minor / 100).toFixed(2)}
                            value={allocationAmounts[rec.id] || ''}
                            onChange={(e) => handleAmountChange(rec.id, e.target.value)}
                            placeholder="0.00"
                            className="w-28 rounded-lg border border-[var(--border)] bg-[var(--background)] px-2.5 py-1 text-xs font-mono font-bold text-[var(--text-primary)]"
                          />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              {allocationError ? (
                <div className="mb-4 rounded-lg border border-red-500/20 bg-red-500/10 px-3 py-2 text-xs font-semibold text-red-700 dark:text-red-300" role="alert">
                  {allocationError}
                </div>
              ) : null}

              <div className="flex justify-end">
                {canManageReceivableAllocations ? (
                  <button
                    type="submit"
                    disabled={processing}
                    title={dict.app.pages.receivableAllocations.executeAllocation}
                    aria-label={dict.app.pages.receivableAllocations.executeAllocation}
                    className="rounded-xl bg-[var(--primary)] px-6 py-2.5 text-xs font-bold text-white shadow-md hover:bg-[var(--primary-hover)] cursor-pointer disabled:opacity-50"
                  >
                    {processing ? dict.app.pages.receivableAllocations.processing : dict.app.pages.receivableAllocations.executeAllocation}
                  </button>
                ) : (
                  <StatusBadge tone="muted">{dict.app.actions.restricted}</StatusBadge>
                )}
              </div>
            </form>
          )}
        </Card>
      </div>

      {/* Existing Allocations Log Table */}
      <h2 className="text-sm font-bold text-[var(--text-primary)] mb-3">
        {dict.app.pages.receivableAllocations.executedAllocationsHistory}
      </h2>

      {existingAllocations.data.length === 0 ? (
        <EmptyState
          title={dict.app.pages.receivableAllocations.noPreviousAllocations}
          description={dict.app.pages.receivableAllocations.executedAllocationsWillAppearHere}
        />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{dict.app.pages.receivableAllocations.receipt_2}</th>
                <th className={tableClasses.th}>{dict.app.pages.receivableAllocations.customer_2}</th>
                <th className={tableClasses.th}>{dict.app.pages.receivableAllocations.allocatedAmount}</th>
                <th className={tableClasses.th}>{dict.app.pages.receivableAllocations.date_2}</th>
                <th className={tableClasses.th}>{dict.app.pages.receivableAllocations.actions}</th>
              </tr>
            </thead>
            <tbody>
              {existingAllocations.data.map((row) => (
                <tr key={row.id} className="hover:bg-[var(--background)]/50 transition-colors">
                  <td className={`${tableClasses.td} font-mono font-bold text-xs`}>{row.customerReceipt?.number || accDict.notAvailable}</td>
                  <td className={`${tableClasses.td} font-semibold`}>{row.customer?.name || accDict.notAvailable}</td>
                  <td className={`${tableClasses.td} font-mono font-bold text-xs text-emerald-600`}>
                    {formatMoney(row.amount_minor, row.currency)}
                  </td>
                  <td className={`${tableClasses.td} font-mono text-xs`}>{new Date(row.created_at).toLocaleString()}</td>
                  <td className={tableClasses.td}>
                    <div className="flex flex-wrap items-center justify-end gap-2">
                    {canManageReceivableAllocations ? (
                      <button
                        type="button"
                        onClick={() => handleReverse(row.id)}
                        title={dict.app.pages.receivableAllocations.reverse}
                        aria-label={dict.app.pages.receivableAllocations.reverse}
                        className="inline-flex h-8 items-center rounded-md border border-red-200 px-2.5 text-xs font-semibold text-red-700 transition-colors hover:bg-red-50 dark:border-red-900/60 dark:text-red-300 dark:hover:bg-red-950/40"
                      >
                        {dict.app.pages.receivableAllocations.reverse}
                      </button>
                    ) : (
                      <StatusBadge tone="muted">{dict.app.actions.restricted}</StatusBadge>
                    )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <SensitiveActionModal
        isOpen={reversingId !== null}
        onClose={() => setReversingId(null)}
        onConfirm={(payload) => {
          if (!reversingId) return;
          router.post(`/receivable-allocations/${reversingId}/reverse`, payload, {
            preserveScroll: true,
            onSuccess: () => setReversingId(null),
          });
        }}
        confirmCode="REVERSE_RECEIVABLE_ALLOCATION"
        reasonRequired={true}
        locale={locale}
      />
    </AppLayout>
  );
}
