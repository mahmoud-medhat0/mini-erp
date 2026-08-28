import { Head, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Button, Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { PaginationLink, SharedPageProps } from '../../Types';

type SupplierPaymentRow = {
  id: string;
  number: string;
  supplier_id: string;
  supplier?: { id: string; code: string; name: string };
  payment_date: string;
  currency: string;
  amount_minor: number;
  unapplied_minor: number;
};

type PayableEntryRow = {
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
  supplierPayment?: { id: string; number: string };
  payableEntry?: { id: string; entry_date: string };
  supplier?: { id: string; code: string; name: string };
  currency: string;
  amount_minor: number;
  created_at: string;
};

type PayableAllocationsProps = SharedPageProps & {
  payments: SupplierPaymentRow[];
  selectedPayment?: SupplierPaymentRow | null;
  openPayables: PayableEntryRow[];
  existingAllocations: {
    data: AllocationRow[];
    links: PaginationLink[];
  };
  suppliers: Array<{ id: string; code: string; name: string }>;
  filters: {
    supplier_id?: string;
    payment_id?: string;
  };
};

export default function PayableAllocationsIndex({
  locale,
  payments = [],
  selectedPayment,
  openPayables = [],
  existingAllocations,
  suppliers = [],
  filters,
}: PayableAllocationsProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const can = useCan();
  const canManagePayableAllocations = can('suppliers.allocations');

  const [allocationAmounts, setAllocationAmounts] = useState<Record<string, string>>({});

  const { post, transform, processing } = useForm({});

  const activeFilterCount = [filters.supplier_id, filters.payment_id].filter(Boolean).length;

  const applyFilters = (next: Record<string, string>) => {
    const supplierId = next.supplier_id ?? filters.supplier_id ?? '';
    const paymentId = next.payment_id ?? filters.payment_id ?? '';
    const params: Record<string, string> = {};

    if (supplierId) params.supplier_id = supplierId;
    if (paymentId) params.payment_id = paymentId;

    router.get('/payable-allocations', params, { preserveScroll: true, preserveState: true });
  };

  function clearFilters() {
    router.get('/payable-allocations', {}, { preserveScroll: true, preserveState: true });
  }

  const handleSupplierSelect = (supplierId: string | null) => {
    applyFilters({ supplier_id: supplierId || '', payment_id: '' });
  };

  const handlePaymentSelect = (paymentId: string | null) => {
    applyFilters({ payment_id: paymentId || '' });
  };

  const handleAmountChange = (entryId: string, val: string) => {
    setAllocationAmounts((prev) => ({
      ...prev,
      [entryId]: val,
    }));
  };

  const submitAllocation = (e: FormEvent) => {
    e.preventDefault();
    if (!selectedPayment) return;

    const lines = Object.entries(allocationAmounts)
      .map(([payable_entry_id, val]) => {
        const num = parseFloat(val || '0');
        return {
          payable_entry_id,
          amount_minor: Math.round(num * 100),
        };
      })
      .filter((line) => line.amount_minor > 0);

    if (lines.length === 0) {
      alert(dict.app.pages.payableAllocations.pleaseEnterAtLeastOneValid);
      return;
    }

    transform(() => ({
      payment_id: selectedPayment.id,
      lines,
    }));

    post('/payable-allocations', {
      preserveScroll: true,
      onSuccess: () => {
        setAllocationAmounts({});
      },
    });
  };

  const handleReverse = (id: string) => {
    if (confirm(dict.app.pages.payableAllocations.areYouSureYouWantTo)) {
      post(`/payable-allocations/${id}/reverse`, { preserveScroll: true });
    }
  };

  const paymentSelectOptions = payments.map((p) => ({
    value: p.id,
    label: `${p.number} - ${p.supplier?.name || accDict.notAvailable} (${dict.app.pages.payableAllocations.unappliedAmount} ${formatMoney(p.unapplied_minor, p.currency)})`,
  }));
  const supplierSelectOptions = suppliers.map((s) => ({
    value: s.id,
    label: `${s.code} - ${s.name}`,
  }));

  return (
    <AppLayout active="payable-allocations.index">
      <Head title={dict.app.pages.payableAllocations.apAllocationsMiniErp} />

      <PageHeader
        title={dict.app.pages.payableAllocations.payableAllocations}
        description={dict.app.pages.payableAllocations.allocatePostedPaymentsAgainstOpenSupplier}
      />

      {/* Workspace Area */}
      <div className="grid gap-6 lg:grid-cols-3 mb-8">
        <Card className="p-5 lg:col-span-1">
          <h2 className="text-sm font-bold text-[var(--text-primary)] mb-3">
            {dict.app.pages.payableAllocations.text1SelectUnappliedPayment}
          </h2>

          <div className="space-y-4">
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {dict.app.pages.payableAllocations.filterSupplier}
              </label>
              <SearchableSelect
                options={[{ value: '', label: dict.app.pages.payableAllocations.allSuppliers }, ...supplierSelectOptions]}
                value={filters.supplier_id || ''}
                onChange={(val) => handleSupplierSelect(val)}
              />
            </div>

            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {dict.app.pages.payableAllocations.paymentNumber}
              </label>
              <SearchableSelect
                options={paymentSelectOptions}
                value={selectedPayment?.id || null}
                onChange={(val) => handlePaymentSelect(val)}
                placeholder={dict.app.pages.payableAllocations.selectPayment}
              />
            </div>

            <Button variant="secondary" onClick={clearFilters} disabled={activeFilterCount === 0}>{accDict.clearFilters}</Button>

            {selectedPayment ? (
              <div className="rounded-xl border border-[var(--border)] bg-[var(--background)] p-4 space-y-2 text-xs">
                <div className="flex justify-between">
                  <span className="text-[var(--text-secondary)]">{dict.app.pages.payableAllocations.payment}</span>
                  <span className="font-mono font-bold">{selectedPayment.number}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-[var(--text-secondary)]">{dict.app.pages.payableAllocations.supplier}</span>
                  <span className="font-semibold">{selectedPayment.supplier?.name}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-[var(--text-secondary)]">{dict.app.pages.payableAllocations.totalAmount}</span>
                  <span className="font-mono font-bold">{formatMoney(selectedPayment.amount_minor, selectedPayment.currency)}</span>
                </div>
                <div className="flex justify-between border-t border-[var(--border)] pt-2">
                  <span className="text-[var(--text-secondary)]">{dict.app.pages.payableAllocations.unappliedAmount}</span>
                  <span className="font-mono font-bold text-amber-600">{formatMoney(selectedPayment.unapplied_minor, selectedPayment.currency)}</span>
                </div>
              </div>
            ) : null}
          </div>
        </Card>

        <Card className="p-5 lg:col-span-2">
          <h2 className="text-sm font-bold text-[var(--text-primary)] mb-3">
            {dict.app.pages.payableAllocations.text2OpenPayableEntries}
          </h2>

          {!selectedPayment ? (
            <div className="py-12 text-center text-xs text-[var(--text-muted)] border border-dashed border-[var(--border)] rounded-xl">
              {dict.app.pages.payableAllocations.selectAPaymentOnTheLeft}
            </div>
          ) : openPayables.length === 0 ? (
            <div className="py-12 text-center text-xs text-[var(--text-muted)] border border-dashed border-[var(--border)] rounded-xl">
              {dict.app.pages.payableAllocations.noOpenPayableEntriesFoundFor}
            </div>
          ) : (
            <form onSubmit={submitAllocation}>
              <div className="overflow-x-auto rounded-xl border border-[var(--border)] mb-4">
                <table className={tableClasses.table}>
                  <thead>
                    <tr>
                      <th className={tableClasses.th}>{dict.app.pages.payableAllocations.date}</th>
                      <th className={tableClasses.th}>{dict.app.pages.payableAllocations.dueDate}</th>
                      <th className={tableClasses.th}>{dict.app.pages.payableAllocations.originalAmount}</th>
                      <th className={tableClasses.th}>{dict.app.pages.payableAllocations.openBalance}</th>
                      <th className={tableClasses.th}>{dict.app.pages.payableAllocations.allocateAmount}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {openPayables.map((pay) => (
                      <tr key={pay.id} className="hover:bg-[var(--background)]/50">
                        <td className={`${tableClasses.td} font-mono text-xs`}>{pay.entry_date}</td>
                        <td className={`${tableClasses.td} font-mono text-xs`}>{pay.due_date || accDict.notAvailable}</td>
                        <td className={`${tableClasses.td} font-mono text-xs`}>{formatMoney(pay.original_amount_minor, pay.currency)}</td>
                        <td className={`${tableClasses.td} font-mono font-bold text-xs text-amber-600`}>{formatMoney(pay.unapplied_minor, pay.currency)}</td>
                        <td className={tableClasses.td}>
                          <input
                            type="number"
                            step="0.01"
                            max={(pay.unapplied_minor / 100).toFixed(2)}
                            value={allocationAmounts[pay.id] || ''}
                            onChange={(e) => handleAmountChange(pay.id, e.target.value)}
                            placeholder="0.00"
                            className="w-28 rounded-lg border border-[var(--border)] bg-[var(--background)] px-2.5 py-1 text-xs font-mono font-bold text-[var(--text-primary)]"
                          />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              <div className="flex justify-end">
                {canManagePayableAllocations ? (
                  <button
                    type="submit"
                    disabled={processing}
                    title={dict.app.pages.payableAllocations.executeAllocation}
                    aria-label={dict.app.pages.payableAllocations.executeAllocation}
                    className="rounded-xl bg-[var(--primary)] px-6 py-2.5 text-xs font-bold text-white shadow-md hover:bg-[var(--primary-hover)] cursor-pointer disabled:opacity-50"
                  >
                    {processing ? dict.app.pages.payableAllocations.processing : dict.app.pages.payableAllocations.executeAllocation}
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
        {dict.app.pages.payableAllocations.executedAllocationsHistory}
      </h2>

      {existingAllocations.data.length === 0 ? (
        <EmptyState
          title={dict.app.pages.payableAllocations.noPreviousAllocations}
          description={dict.app.pages.payableAllocations.executedAllocationsWillAppearHere}
        />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{dict.app.pages.payableAllocations.payment_2}</th>
                <th className={tableClasses.th}>{dict.app.pages.payableAllocations.supplier_2}</th>
                <th className={tableClasses.th}>{dict.app.pages.payableAllocations.allocatedAmount}</th>
                <th className={tableClasses.th}>{dict.app.pages.payableAllocations.date_2}</th>
                <th className={tableClasses.th}>{dict.app.pages.payableAllocations.actions}</th>
              </tr>
            </thead>
            <tbody>
              {existingAllocations.data.map((row) => (
                <tr key={row.id} className="hover:bg-[var(--background)]/50 transition-colors">
                  <td className={`${tableClasses.td} font-mono font-bold text-xs`}>{row.supplierPayment?.number || accDict.notAvailable}</td>
                  <td className={`${tableClasses.td} font-semibold`}>{row.supplier?.name || accDict.notAvailable}</td>
                  <td className={`${tableClasses.td} font-mono font-bold text-xs text-emerald-600`}>
                    {formatMoney(row.amount_minor, row.currency)}
                  </td>
                  <td className={`${tableClasses.td} font-mono text-xs`}>{new Date(row.created_at).toLocaleString()}</td>
                  <td className={tableClasses.td}>
                    <div className="flex flex-wrap items-center justify-end gap-2">
                    {canManagePayableAllocations ? (
                      <button
                        type="button"
                        onClick={() => handleReverse(row.id)}
                        title={dict.app.pages.payableAllocations.reverse}
                        aria-label={dict.app.pages.payableAllocations.reverse}
                        className="inline-flex h-8 items-center rounded-md border border-red-200 px-2.5 text-xs font-semibold text-red-700 transition-colors hover:bg-red-50 dark:border-red-900/60 dark:text-red-300 dark:hover:bg-red-950/40"
                      >
                        {dict.app.pages.payableAllocations.reverse}
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
    </AppLayout>
  );
}
