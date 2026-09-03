import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent, type ReactElement } from 'react';
import AppLayout from '../../Components/AppLayout';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { Card, PageHeader, SearchableSelect, SensitiveActionModal, StatusBadge } from '../../Components/Primitives';
import { formatMoney, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';

type CustomerReceiptOption = {
  id: string;
  number: string;
  currency: string;
  unapplied_minor: number;
  customer?: { id: string; code: string; name: string };
};

type OpenReceivableRow = {
  id: string;
  entry_date: string;
  due_date?: string | null;
  currency: string;
  original_amount_minor: number;
  unapplied_minor: number;
};

type ReceivableAllocationsProps = SharedPageProps & {
  receipts: CustomerReceiptOption[];
  selectedReceipt?: CustomerReceiptOption | null;
  openReceivables: OpenReceivableRow[];
  customers: Array<{ id: string; code: string; name: string }>;
  filters: { customer_id?: string; receipt_id?: string };
};

export default function ReceivableAllocationsIndex({
  locale,
  receipts = [],
  selectedReceipt,
  openReceivables = [],
  customers = [],
  filters,
}: ReceivableAllocationsProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const can = useCan();
  const canManageReceivableAllocations = can('customers.allocations');

  const [selectedCustomerId, setSelectedCustomerId] = useState<string>(filters.customer_id || '');
  const [selectedReceiptId, setSelectedReceiptId] = useState<string>(filters.receipt_id || '');
  const [allocationAmounts, setAllocationAmounts] = useState<Record<string, string>>({});
  const [allocationError, setAllocationError] = useState<string | null>(null);
  const [reversingId, setReversingId] = useState<string | null>(null);

  const { post, processing } = useForm();

  const activeFilterCount = [filters.customer_id, filters.receipt_id].filter(Boolean).length;

  function clearFilters() {
    setSelectedCustomerId('');
    setSelectedReceiptId('');
    router.get('/receivable-allocations', {}, { preserveScroll: true, preserveState: true });
  }

  const handleCustomerChange = (val: string | null) => {
    const custId = val || '';
    setSelectedCustomerId(custId);
    setSelectedReceiptId('');
    router.get('/receivable-allocations', { customer_id: custId }, { preserveState: true, preserveScroll: true });
  };

  const handleReceiptChange = (val: string | null) => {
    const rId = val || '';
    setSelectedReceiptId(rId);
    setAllocationAmounts({});
    setAllocationError(null);
    router.get(
      '/receivable-allocations',
      { customer_id: selectedCustomerId, receipt_id: rId },
      { preserveState: true, preserveScroll: true },
    );
  };

  const handleAmountChange = (receivableId: string, val: string) => {
    setAllocationAmounts((prev) => ({ ...prev, [receivableId]: val }));
    setAllocationError(null);
  };

  const submitAllocation = (e: FormEvent) => {
    e.preventDefault();
    if (!selectedReceipt) return;

    const linesToSubmit: Array<{ receivable_entry_id: string; amount_minor: number }> = [];
    let totalAllocatingMinor = 0;

    for (const rec of openReceivables) {
      const inputVal = allocationAmounts[rec.id];
      if (inputVal && parseFloat(inputVal) > 0) {
        const minor = Math.round(parseFloat(inputVal) * 100);
        if (minor > rec.unapplied_minor) {
          setAllocationError(dict.app.pages.receivableAllocations.cannotAllocateMoreThanOpenBalance);
          return;
        }
        linesToSubmit.push({ receivable_entry_id: rec.id, amount_minor: minor });
        totalAllocatingMinor += minor;
      }
    }

    if (linesToSubmit.length === 0) {
      setAllocationError(dict.app.pages.receivableAllocations.pleaseEnterAtLeastOneAmountToAllocate);
      return;
    }

    if (totalAllocatingMinor > selectedReceipt.unapplied_minor) {
      setAllocationError(dict.app.pages.receivableAllocations.totalAllocatedAmountExceedsReceiptUnapplied);
      return;
    }

    router.post(
      '/receivable-allocations',
      {
        receipt_id: selectedReceipt.id,
        lines: linesToSubmit,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          setAllocationAmounts({});
          setAllocationError(null);
        },
      },
    );
  };

  const handleReverse = (allocationId: string) => {
    setReversingId(allocationId);
  };

  const customerSelectOptions = customers.map((c) => ({ value: c.id, label: `${c.code} - ${getLocalizedName(c.name, locale)}` }));
  const receiptSelectOptions = receipts.map((r) => ({
    value: r.id,
    label: `${r.number} | ${getLocalizedName(r.customer?.name, locale) || dict.app.pages.receivableAllocations.customer} | ${formatMoney(r.unapplied_minor, r.currency)}`,
  }));

  const historyColumns = useMemo(() => [
    { data: 'receipt_number', name: 'receipt_number', title: dict.app.pages.receivableAllocations.receipt_2, className: 'font-mono font-bold text-xs', width: '140px' },
    { data: 'customer_name', name: 'customer_name', title: dict.app.pages.receivableAllocations.customer_2 },
    { data: 'amount_minor', name: 'amount_minor', title: dict.app.pages.receivableAllocations.allocatedAmount, width: '130px' },
    { data: 'created_at', name: 'created_at', title: dict.app.pages.receivableAllocations.date_2, width: '160px' },
    { data: 'actions', name: 'actions', title: dict.app.pages.receivableAllocations.actions, orderable: false, searchable: false, width: '90px', className: 'text-end' },
  ], [dict]);

  const historySlots = useMemo<DataTableSlots>(() => ({
    receipt_number: (d: any) => <span className="font-mono font-bold text-xs">{d || accDict.notAvailable}</span>,
    customer_name: (d: any, _type: any, row: any) => (
      <span className="font-semibold">
        {row?.customer_code ? `${row.customer_code} - ${getLocalizedName(d, locale)}` : getLocalizedName(d, locale) || accDict.notAvailable}
      </span>
    ),
    amount_minor: (d: any, _type: any, row: any) => <span className="font-mono font-bold text-xs text-emerald-600">{formatMoney(d, row?.currency || 'EGP')}</span>,
    created_at: (d: any) => <span className="font-mono text-xs">{new Date(d).toLocaleString()}</span>,
    actions: (_d: any, _type: any, row: any) => (
      <div className="flex items-center justify-end">
        {canManageReceivableAllocations ? (
          <button
            type="button"
            onClick={() => handleReverse(row?.id)}
            title={dict.app.pages.receivableAllocations.reverse}
            aria-label={dict.app.pages.receivableAllocations.reverse}
            className="inline-flex h-8 items-center rounded-md border border-red-200 px-2.5 text-xs font-semibold text-red-700 transition-colors hover:bg-red-50 dark:border-red-900/60 dark:text-red-300 dark:hover:bg-red-950/40 cursor-pointer"
          >
            {dict.app.pages.receivableAllocations.reverse}
          </button>
        ) : (
          <StatusBadge tone="muted">{dict.app.actions.restricted}</StatusBadge>
        )}
      </div>
    ),
  } as Record<string, (data: any, type: any, row: any) => ReactElement>), [dict, accDict, locale, canManageReceivableAllocations]);

  return (
    <AppLayout active="receivable-allocations.index">
      <Head title={dict.app.pages.receivableAllocations.arAllocationsMiniErp} />

      <PageHeader
        title={dict.app.pages.receivableAllocations.receivableAllocations}
        description={dict.app.pages.receivableAllocations.allocatePostedReceiptsAgainstOpenCustomer}
      />

      <div className="grid grid-cols-1 gap-6 mb-8">
        <Card className="p-6">
          <h2 className="text-sm font-bold text-[var(--text-primary)] mb-4">
            {dict.app.pages.receivableAllocations.executeAllocation_2}
          </h2>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div>
              <div className="flex items-center justify-between mb-1">
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase">
                  {dict.app.pages.receivableAllocations.filterCustomer}
                </label>
                {activeFilterCount > 0 && (
                  <button type="button" onClick={clearFilters} disabled={activeFilterCount === 0} className="text-xs font-bold text-red-600 hover:underline">
                    {accDict.clearFilters}
                  </button>
                )}
              </div>
              <SearchableSelect
                options={customerSelectOptions}
                value={selectedCustomerId}
                onChange={handleCustomerChange}
                placeholder={dict.app.pages.receivableAllocations.allCustomers}
              />
            </div>
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {dict.app.pages.receivableAllocations.selectPostedReceiptToAllocate} *
              </label>
              <SearchableSelect
                options={receiptSelectOptions}
                value={selectedReceiptId}
                onChange={handleReceiptChange}
                placeholder={dict.app.pages.receivableAllocations.chooseReceipt}
                isClearable={false}
              />
            </div>
          </div>

          {selectedReceipt && (
            <div className="mb-6 rounded-xl border border-[var(--primary)]/20 bg-[var(--primary)]/5 p-4">
              <div className="flex flex-wrap items-center justify-between gap-4 text-xs">
                <div>
                  <span className="text-[var(--text-muted)]">{dict.app.pages.receivableAllocations.selectedReceiptNo}:</span>{' '}
                  <span className="font-mono font-bold text-[var(--text-primary)]">{selectedReceipt.number}</span>
                </div>
                <div>
                  <span className="text-[var(--text-muted)]">{dict.app.pages.receivableAllocations.customer}:</span>{' '}
                  <span className="font-semibold text-[var(--text-primary)]">
                    {getLocalizedName(selectedReceipt.customer?.name, locale)}
                  </span>
                </div>
                <div>
                  <span className="text-[var(--text-muted)]">{dict.app.pages.receivableAllocations.unappliedReceiptCredit}:</span>{' '}
                  <span className="font-mono font-bold text-amber-600">
                    {formatMoney(selectedReceipt.unapplied_minor, selectedReceipt.currency)}
                  </span>
                </div>
              </div>
            </div>
          )}

          {selectedReceiptId && openReceivables.length === 0 && (
            <p className="text-xs text-[var(--text-muted)] py-4 text-center">
              {dict.app.pages.receivableAllocations.noOpenReceivablesInvoicesFoundForThisCustomerIn}
            </p>
          )}

          {openReceivables.length > 0 && (
            <form onSubmit={submitAllocation}>
              <h3 className="text-xs font-bold text-[var(--text-primary)] mb-3">
                {dict.app.pages.receivableAllocations.openReceivablesForAllocation}
              </h3>

              <div className="overflow-x-auto rounded-xl border border-[var(--border)] mb-4">
                <table className="w-full text-start text-xs border-collapse">
                  <thead>
                    <tr className="bg-[var(--surface-hover)] border-b border-[var(--border)] text-[var(--text-secondary)] font-bold">
                      <th className="p-3 text-start">{dict.app.pages.receivableAllocations.date}</th>
                      <th className="p-3 text-start">{dict.app.pages.receivableAllocations.dueDate}</th>
                      <th className="p-3 text-start">{dict.app.pages.receivableAllocations.originalAmount}</th>
                      <th className="p-3 text-start">{dict.app.pages.receivableAllocations.openBalance}</th>
                      <th className="p-3 text-start">{dict.app.pages.receivableAllocations.allocateAmount}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {openReceivables.map((rec) => (
                      <tr key={rec.id} className="border-b border-[var(--border)]/50 hover:bg-[var(--background)]/50">
                        <td className="p-3 font-mono text-xs">{rec.entry_date}</td>
                        <td className="p-3 font-mono text-xs">{rec.due_date || accDict.notAvailable}</td>
                        <td className="p-3 font-mono text-xs">{formatMoney(rec.original_amount_minor, rec.currency)}</td>
                        <td className="p-3 font-mono font-bold text-xs text-amber-600">{formatMoney(rec.unapplied_minor, rec.currency)}</td>
                        <td className="p-3">
                          <input
                            type="number"
                            step="0.01"
                            max={(rec.unapplied_minor / 100).toFixed(2)}
                            value={allocationAmounts[rec.id] || ''}
                            onChange={(e) => handleAmountChange(rec.id, e.target.value)}
                            placeholder="0.00"
                            className="w-24 rounded-lg border border-[var(--border)] bg-[var(--background)] px-2 py-1 text-xs font-mono font-bold text-[var(--text-primary)]"
                          />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              {allocationError && (
                <div className="mb-4 text-xs font-bold text-red-600 bg-red-50 p-3 rounded-lg border border-red-200">
                  {allocationError}
                </div>
              )}

              <div className="flex justify-end">
                <button
                  type="submit"
                  disabled={processing}
                  className="bg-[var(--primary)] text-white px-6 py-2 rounded-lg text-xs font-bold hover:bg-[var(--primary-hover)] transition-colors disabled:opacity-50"
                >
                  {processing ? dict.app.pages.receivableAllocations.processing : dict.app.pages.receivableAllocations.executeAllocation}
                </button>
              </div>
            </form>
          )}
        </Card>
      </div>

      <h2 className="text-sm font-bold text-[var(--text-primary)] mb-3">
        {dict.app.pages.receivableAllocations.executedAllocationsHistory}
      </h2>

      <Card className="overflow-hidden p-0">
        <ServerDataTable
          ajaxUrl="/receivable-allocations/data"
          columns={historyColumns}
          locale={locale}
          order={[[3, 'desc']]}
          pageLength={25}
          slots={historySlots}
          tableId="receivable-allocations-history-table"
        />
      </Card>

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
