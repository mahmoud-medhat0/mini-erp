import { Head, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, EmptyState, PageHeader, SearchableSelect, tableClasses } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types';

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
  amount_minor: number;
  created_at: string;
};

type PayableAllocationsProps = SharedPageProps & {
  payments: SupplierPaymentRow[];
  selectedPayment?: SupplierPaymentRow | null;
  openPayables: PayableEntryRow[];
  existingAllocations: {
    data: AllocationRow[];
    links: any[];
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
  const isAr = locale === 'ar';
  const dict = getDictionary(locale);

  const [allocationAmounts, setAllocationAmounts] = useState<Record<string, string>>({});

  const { post, transform, processing } = useForm({});

  const handlePaymentSelect = (paymentId: string | null) => {
    if (paymentId) {
      window.location.href = `/payable-allocations?payment_id=${paymentId}`;
    } else {
      window.location.href = '/payable-allocations';
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
      alert(isAr ? 'برجاء إدخال مبلغ تسوية واحد على الأقل.' : 'Please enter at least one valid allocation amount.');
      return;
    }

    transform(() => ({
      payment_id: selectedPayment.id,
      lines,
    }));

    post('/payable-allocations', {
      onSuccess: () => {
        setAllocationAmounts({});
      },
    });
  };

  const handleReverse = (id: string) => {
    if (confirm(isAr ? 'هل أنت تأكد من إلغاء وتفكيك التسوية؟' : 'Are you sure you want to reverse this allocation?')) {
      post(`/payable-allocations/${id}/reverse`);
    }
  };

  const paymentSelectOptions = payments.map((p) => ({
    value: p.id,
    label: `${p.number} - ${p.supplier?.name || ''} (${formatMoney(p.unapplied_minor, p.currency)} متبقي)`,
  }));

  return (
    <AppLayout active="payable-allocations.index">
      <Head title={isAr ? 'تسوية مستحقات الموردين - Mini ERP' : 'AP Allocations - Mini ERP'} />

      <PageHeader
        title={isAr ? 'تسوية مستحقات الموردين' : 'Payable Allocations'}
        description={isAr ? 'تسوية سندات الصرف مع قيود ومستحقات الموردين المفتوحة.' : 'Allocate posted payments against open supplier payable entries.'}
      />

      {/* Workspace Area */}
      <div className="grid gap-6 lg:grid-cols-3 mb-8">
        <Card className="p-5 lg:col-span-1">
          <h2 className="text-sm font-bold text-[var(--text-primary)] mb-3">
            {isAr ? '1. اختر سند الصرف غير المسوى' : '1. Select Unapplied Payment'}
          </h2>

          <div className="space-y-4">
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {isAr ? 'سند الصرف' : 'Payment Number'}
              </label>
              <SearchableSelect
                options={paymentSelectOptions}
                value={selectedPayment?.id || null}
                onChange={(val) => handlePaymentSelect(val)}
                placeholder={isAr ? 'اختر سند الصرف...' : 'Select payment...'}
              />
            </div>

            {selectedPayment ? (
              <div className="rounded-xl border border-[var(--border)] bg-[var(--background)] p-4 space-y-2 text-xs">
                <div className="flex justify-between">
                  <span className="text-[var(--text-secondary)]">{isAr ? 'رقم السند:' : 'Payment:'}</span>
                  <span className="font-mono font-bold">{selectedPayment.number}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-[var(--text-secondary)]">{isAr ? 'المورد:' : 'Supplier:'}</span>
                  <span className="font-semibold">{selectedPayment.supplier?.name}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-[var(--text-secondary)]">{isAr ? 'المبلغ الإجمالي:' : 'Total Amount:'}</span>
                  <span className="font-mono font-bold">{formatMoney(selectedPayment.amount_minor, selectedPayment.currency)}</span>
                </div>
                <div className="flex justify-between border-t border-[var(--border)] pt-2">
                  <span className="text-[var(--text-secondary)]">{isAr ? 'غير مسوى (المتاح للربط):' : 'Unapplied Amount:'}</span>
                  <span className="font-mono font-bold text-amber-600">{formatMoney(selectedPayment.unapplied_minor, selectedPayment.currency)}</span>
                </div>
              </div>
            ) : null}
          </div>
        </Card>

        <Card className="p-5 lg:col-span-2">
          <h2 className="text-sm font-bold text-[var(--text-primary)] mb-3">
            {isAr ? '2. المستحقات والقيود المفتوحة للمورد' : '2. Open Payable Entries'}
          </h2>

          {!selectedPayment ? (
            <div className="py-12 text-center text-xs text-[var(--text-muted)] border border-dashed border-[var(--border)] rounded-xl">
              {isAr ? 'قم باختيار سند صرف من اليسار لعرض المستحقات المتاحة.' : 'Select a payment on the left to view matching open payables.'}
            </div>
          ) : openPayables.length === 0 ? (
            <div className="py-12 text-center text-xs text-[var(--text-muted)] border border-dashed border-[var(--border)] rounded-xl">
              {isAr ? 'لا يوجد مستحقات مفتوحة لهذا المورد بنفس العملة.' : 'No open payable entries found for this supplier and currency.'}
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
                    {openPayables.map((pay) => (
                      <tr key={pay.id} className="hover:bg-[var(--background)]/50">
                        <td className={`${tableClasses.td} font-mono text-xs`}>{pay.entry_date}</td>
                        <td className={`${tableClasses.td} font-mono text-xs`}>{pay.due_date || '—'}</td>
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
                <th className={tableClasses.th}>{isAr ? 'سند الصرف' : 'Payment'}</th>
                <th className={tableClasses.th}>{isAr ? 'المورد' : 'Supplier'}</th>
                <th className={tableClasses.th}>{isAr ? 'مبلغ التسوية' : 'Allocated Amount'}</th>
                <th className={tableClasses.th}>{isAr ? 'تاريخ التسوية' : 'Date'}</th>
                <th className={tableClasses.th}>{isAr ? 'إجراءات' : 'Actions'}</th>
              </tr>
            </thead>
            <tbody>
              {existingAllocations.data.map((row) => (
                <tr key={row.id} className="hover:bg-[var(--background)]/50 transition-colors">
                  <td className={`${tableClasses.td} font-mono font-bold text-xs`}>{row.supplierPayment?.number || '—'}</td>
                  <td className={`${tableClasses.td} font-semibold`}>{row.supplier?.name || '—'}</td>
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
