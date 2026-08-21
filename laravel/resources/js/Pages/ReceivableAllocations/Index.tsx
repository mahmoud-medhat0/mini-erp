import { Head, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, EmptyState, PageHeader, SearchableSelect, tableClasses } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types';

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
  amount_minor: number;
  created_at: string;
};

type ReceivableAllocationsProps = SharedPageProps & {
  receipts: CustomerReceiptRow[];
  selectedReceipt?: CustomerReceiptRow | null;
  openReceivables: ReceivableEntryRow[];
  existingAllocations: {
    data: AllocationRow[];
    links: any[];
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
  const isAr = locale === 'ar';
  const dict = getDictionary(locale);

  const [allocationAmounts, setAllocationAmounts] = useState<Record<string, string>>({});

  const { post, transform, processing } = useForm({});

  const handleReceiptSelect = (receiptId: string | null) => {
    if (receiptId) {
      window.location.href = `/receivable-allocations?receipt_id=${receiptId}`;
    } else {
      window.location.href = '/receivable-allocations';
    }
  };

  const handleAmountChange = (entryId: string, val: string) => {
    setAllocationAmounts((prev) => ({
      ...prev,
      [entryId]: val,
    }));
  };

  const submitAllocation = (e: FormEvent) => {
    e.preventDefault();
    if (!selectedReceipt) return;

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
      alert(isAr ? 'برجاء إدخال مبلغ تسوية واحد على الأقل.' : 'Please enter at least one valid allocation amount.');
      return;
    }

    transform(() => ({
      receipt_id: selectedReceipt.id,
      lines,
    }));

    post('/receivable-allocations', {
      onSuccess: () => {
        setAllocationAmounts({});
      },
    });
  };

  const handleReverse = (id: string) => {
    if (confirm(isAr ? 'هل أنت تأكد من إلغاء وتفكيك التسوية؟' : 'Are you sure you want to reverse this allocation?')) {
      post(`/receivable-allocations/${id}/reverse`);
    }
  };

  const receiptSelectOptions = receipts.map((r) => ({
    value: r.id,
    label: `${r.number} - ${r.customer?.name || ''} (${formatMoney(r.unapplied_minor, r.currency)} متبقي)`,
  }));

  return (
    <AppLayout active="receivable-allocations.index">
      <Head title={isAr ? 'تسوية مستحقات العملاء - Mini ERP' : 'AR Allocations - Mini ERP'} />

      <PageHeader
        title={isAr ? 'تسوية مستحقات العملاء' : 'Receivable Allocations'}
        description={isAr ? 'تسوية سندات القبض مع قيود ومستحقات العملاء المفتوحة.' : 'Allocate posted receipts against open customer receivable entries.'}
      />

      {/* Workspace Area */}
      <div className="grid gap-6 lg:grid-cols-3 mb-8">
        <Card className="p-5 lg:col-span-1">
          <h2 className="text-sm font-bold text-[var(--text-primary)] mb-3">
            {isAr ? '1. اختر سند القبض غير المسوى' : '1. Select Unapplied Receipt'}
          </h2>

          <div className="space-y-4">
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {isAr ? 'سند القبض' : 'Receipt Number'}
              </label>
              <SearchableSelect
                options={receiptSelectOptions}
                value={selectedReceipt?.id || null}
                onChange={(val) => handleReceiptSelect(val)}
                placeholder={isAr ? 'اختر سند القبض...' : 'Select receipt...'}
              />
            </div>

            {selectedReceipt ? (
              <div className="rounded-xl border border-[var(--border)] bg-[var(--background)] p-4 space-y-2 text-xs">
                <div className="flex justify-between">
                  <span className="text-[var(--text-secondary)]">{isAr ? 'رقم السند:' : 'Receipt:'}</span>
                  <span className="font-mono font-bold">{selectedReceipt.number}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-[var(--text-secondary)]">{isAr ? 'العميل:' : 'Customer:'}</span>
                  <span className="font-semibold">{selectedReceipt.customer?.name}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-[var(--text-secondary)]">{isAr ? 'المبلغ الإجمالي:' : 'Total Amount:'}</span>
                  <span className="font-mono font-bold">{formatMoney(selectedReceipt.amount_minor, selectedReceipt.currency)}</span>
                </div>
                <div className="flex justify-between border-t border-[var(--border)] pt-2">
                  <span className="text-[var(--text-secondary)]">{isAr ? 'غير مسوى (المتاح للربط):' : 'Unapplied Amount:'}</span>
                  <span className="font-mono font-bold text-amber-600">{formatMoney(selectedReceipt.unapplied_minor, selectedReceipt.currency)}</span>
                </div>
              </div>
            ) : null}
          </div>
        </Card>

        <Card className="p-5 lg:col-span-2">
          <h2 className="text-sm font-bold text-[var(--text-primary)] mb-3">
            {isAr ? '2. المستحقات والقيود المفتوحة للعميل' : '2. Open Receivable Entries'}
          </h2>

          {!selectedReceipt ? (
            <div className="py-12 text-center text-xs text-[var(--text-muted)] border border-dashed border-[var(--border)] rounded-xl">
              {isAr ? 'قم باختيار سند قبض من اليسار لعرض المستحقات المتاحة.' : 'Select a receipt on the left to view matching open receivables.'}
            </div>
          ) : openReceivables.length === 0 ? (
            <div className="py-12 text-center text-xs text-[var(--text-muted)] border border-dashed border-[var(--border)] rounded-xl">
              {isAr ? 'لا يوجد مستحقات مفتوحة لهذا العميل بنفس العملة.' : 'No open receivable entries found for this customer and currency.'}
            </div>
          ) : (
            <form onSubmit={submitAllocation}>
              <div className="overflow-x-auto rounded-xl border border-[var(--border)] mb-4">
                <table className={tableClasses.table}>
                  <thead>
                    <tr>
                      <th className={tableClasses.th}>{isAr ? 'التاريخ' : 'Date'}</th>
                      <th className={tableClasses.th}>{isAr ? 'تاريخ الاستحقاق' : 'Due Date'}</th>
                      <th className={tableClasses.th}>{isAr ? 'المبلغ الأصلي' : 'Original Amount'}</th>
                      <th className={tableClasses.th}>{isAr ? 'المتبقي' : 'Open Balance'}</th>
                      <th className={tableClasses.th}>{isAr ? 'مبلغ التسوية' : 'Allocate Amount'}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {openReceivables.map((rec) => (
                      <tr key={rec.id} className="hover:bg-[var(--background)]/50">
                        <td className={`${tableClasses.td} font-mono text-xs`}>{rec.entry_date}</td>
                        <td className={`${tableClasses.td} font-mono text-xs`}>{rec.due_date || '—'}</td>
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

              <div className="flex justify-end">
                <button
                  type="submit"
                  disabled={processing}
                  className="rounded-xl bg-[var(--primary)] px-6 py-2.5 text-xs font-bold text-white shadow-md hover:bg-[var(--primary-hover)] cursor-pointer disabled:opacity-50"
                >
                  {processing ? (isAr ? 'جاري الربط والتسوية...' : 'Processing...') : (isAr ? 'إجراء التسوية الآن' : 'Execute Allocation')}
                </button>
              </div>
            </form>
          )}
        </Card>
      </div>

      {/* Existing Allocations Log Table */}
      <h2 className="text-sm font-bold text-[var(--text-primary)] mb-3">
        {isAr ? 'سجل التسويات المنفذة' : 'Executed Allocations History'}
      </h2>

      {existingAllocations.data.length === 0 ? (
        <EmptyState
          title={isAr ? 'لا يوجد تسويات سابقة' : 'No Previous Allocations'}
          description={isAr ? 'سوف تظهر التسويات المكتملة هنا عند تنفيذها.' : 'Executed allocations will appear here.'}
        />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{isAr ? 'سند القبض' : 'Receipt'}</th>
                <th className={tableClasses.th}>{isAr ? 'العميل' : 'Customer'}</th>
                <th className={tableClasses.th}>{isAr ? 'مبلغ التسوية' : 'Allocated Amount'}</th>
                <th className={tableClasses.th}>{isAr ? 'تاريخ التسوية' : 'Date'}</th>
                <th className={tableClasses.th}>{isAr ? 'إجراءات' : 'Actions'}</th>
              </tr>
            </thead>
            <tbody>
              {existingAllocations.data.map((row) => (
                <tr key={row.id} className="hover:bg-[var(--background)]/50 transition-colors">
                  <td className={`${tableClasses.td} font-mono font-bold text-xs`}>{row.customerReceipt?.number || '—'}</td>
                  <td className={`${tableClasses.td} font-semibold`}>{row.customer?.name || '—'}</td>
                  <td className={`${tableClasses.td} font-mono font-bold text-xs text-emerald-600`}>
                    {formatMoney(row.amount_minor, 'EGP')}
                  </td>
                  <td className={`${tableClasses.td} font-mono text-xs`}>{new Date(row.created_at).toLocaleString()}</td>
                  <td className={tableClasses.td}>
                    <button
                      type="button"
                      onClick={() => handleReverse(row.id)}
                      className="text-xs font-bold text-red-600 hover:underline cursor-pointer"
                    >
                      {isAr ? 'إلغاء التسوية' : 'Reverse'}
                    </button>
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
