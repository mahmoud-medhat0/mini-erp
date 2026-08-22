import { Head, useForm, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, EmptyState, PageHeader, StatusBadge, tableClasses } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types';

type CustomerOption = {
  id: string;
  code: string;
  name: string;
};

type SalesOrderOption = {
  id: string;
  number?: string | null;
  customer?: CustomerOption | null;
  lines: Array<{
    id: string;
    line_no: number;
    product_id: string;
    unit_of_measure_id: string;
    description?: string | null;
    quantity_e6: number;
    product?: {
      id: string;
      code: string;
      name: string;
    } | null;
    unitOfMeasure?: {
      id: string;
      code: string;
      name: string;
    } | null;
  }>;
};

type DeliveryNoteLineItem = {
  id?: string;
  sales_order_line_id: string;
  product_name: string;
  uom_name: string;
  description: string;
  quantity: number; // Decimal input on UI
};

type DeliveryNoteRow = {
  id: string;
  number?: string | null;
  sales_order_id: string;
  delivery_date: string;
  status: 'draft' | 'confirmed' | 'cancelled';
  reference?: string | null;
  notes?: string | null;
  lock_version: number;
  created_at: string;
  salesOrder?: {
    id: string;
    number?: string | null;
    customer?: CustomerOption | null;
  } | null;
  lines: Array<{
    id: string;
    line_no: number;
    sales_order_line_id: string;
    product_id: string;
    unit_of_measure_id: string;
    description?: string | null;
    quantity_e6: number;
    product?: {
      code: string;
      name: string;
    } | null;
    unitOfMeasure?: {
      code: string;
      name: string;
    } | null;
  }>;
};

type DeliveryNotesProps = SharedPageProps & {
  deliveryNotes: {
    data: DeliveryNoteRow[];
    links: any[];
  };
  confirmedSalesOrders: SalesOrderOption[];
  filters: {
    search?: string;
    status?: string;
  };
};

export default function DeliveryNotesIndex({ locale, deliveryNotes, confirmedSalesOrders, filters }: DeliveryNotesProps) {
  const isAr = locale === 'ar';
  const dict = getDictionary(locale);

  const [showModal, setShowModal] = useState(false);
  const [editingNote, setEditingNote] = useState<DeliveryNoteRow | null>(null);

  const todayStr = new Date().toISOString().split('T')[0];

  const [lineItems, setLineItems] = useState<DeliveryNoteLineItem[]>([]);

  const { data, setData, post, put, processing, errors, reset } = useForm({
    sales_order_id: confirmedSalesOrders[0]?.id || '',
    delivery_date: todayStr,
    reference: '',
    notes: '',
    lock_version: 1,
  });

  const handleSalesOrderSelect = (salesOrderId: string) => {
    setData('sales_order_id', salesOrderId);
    const selectedSo = confirmedSalesOrders.find((so) => so.id === salesOrderId);
    if (selectedSo && selectedSo.lines) {
      setLineItems(
        selectedSo.lines.map((l) => ({
          sales_order_line_id: l.id,
          product_name: l.product?.name || '',
          uom_name: l.unitOfMeasure?.name || 'PCS',
          description: l.description || '',
          quantity: l.quantity_e6 / 1000000,
        }))
      );
    } else {
      setLineItems([]);
    }
  };

  const openCreateModal = () => {
    reset();
    setEditingNote(null);
    const defaultSo = confirmedSalesOrders[0];
    if (defaultSo) {
      handleSalesOrderSelect(defaultSo.id);
    } else {
      setLineItems([]);
    }
    setShowModal(true);
  };

  const openEditModal = (note: DeliveryNoteRow) => {
    setEditingNote(note);
    setData({
      sales_order_id: note.sales_order_id,
      delivery_date: note.delivery_date,
      reference: note.reference || '',
      notes: note.notes || '',
      lock_version: note.lock_version,
    });

    if (note.lines && note.lines.length > 0) {
      setLineItems(
        note.lines.map((l) => ({
          id: l.id,
          sales_order_line_id: l.sales_order_line_id,
          product_name: l.product?.name || '',
          uom_name: l.unitOfMeasure?.name || 'PCS',
          description: l.description || '',
          quantity: l.quantity_e6 / 1000000,
        }))
      );
    }
    setShowModal(true);
  };

  const closeModal = () => {
    setShowModal(false);
    setEditingNote(null);
    reset();
  };

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();

    const formattedLines = lineItems.map((item) => ({
      sales_order_line_id: item.sales_order_line_id,
      description: item.description,
      quantity_e6: Math.round(Number(item.quantity) * 1000000),
    }));

    const payload = {
      ...data,
      lines: formattedLines,
    };

    if (editingNote) {
      router.put(`/sales/delivery-notes/${editingNote.id}`, payload, {
        onSuccess: () => closeModal(),
      });
    } else {
      router.post('/sales/delivery-notes', payload, {
        onSuccess: () => closeModal(),
      });
    }
  };

  const handleAction = (noteId: string, action: 'confirm' | 'cancel') => {
    let confirmMsg = '';
    if (action === 'confirm') confirmMsg = isAr ? 'هل أنت تأكد من تأكيد إذن التسليم؟' : 'Confirm this Delivery Note?';
    if (action === 'cancel') confirmMsg = isAr ? 'هل أنت تأكد من إلغاء إذن التسليم؟' : 'Cancel this Delivery Note?';

    if (confirm(confirmMsg)) {
      router.post(`/sales/delivery-notes/${noteId}/${action}`);
    }
  };

  const getStatusTone = (status: string): 'muted' | 'ok' | 'danger' => {
    switch (status) {
      case 'draft':
        return 'muted';
      case 'confirmed':
        return 'ok';
      case 'cancelled':
        return 'danger';
      default:
        return 'muted';
    }
  };

  const getStatusLabel = (status: string) => {
    switch (status) {
      case 'draft':
        return isAr ? 'مسودة' : 'Draft';
      case 'confirmed':
        return isAr ? 'مؤكد' : 'Confirmed';
      case 'cancelled':
        return isAr ? 'ملغى' : 'Cancelled';
      default:
        return status;
    }
  };

  return (
    <AppLayout active="delivery-notes.index">
      <Head title={isAr ? 'أذون التسليم' : 'Delivery Notes'} />

      <PageHeader
        title={isAr ? 'أذون التسليم' : 'Delivery Notes'}
        description={isAr ? 'إدارة أذون تسليم مبيعات العملاء' : 'Manage customer sales delivery notes'}
        actions={
          <button
            type="button"
            onClick={openCreateModal}
            disabled={confirmedSalesOrders.length === 0}
            className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-semibold text-white shadow-md hover:bg-blue-700 disabled:opacity-50 transition-all"
          >
            <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            <span>{isAr ? 'إنشاء إذن تسليم' : 'Create Delivery Note'}</span>
          </button>
        }
      />

      <Card className="p-6">
        <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative flex-1 max-w-md">
            <input
              type="text"
              placeholder={isAr ? 'بحث بالرقم، المرجع، أو العميل...' : 'Search number, reference, or customer...'}
              defaultValue={filters.search || ''}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  const val = (e.target as HTMLInputElement).value;
                  router.get('/sales/delivery-notes', { ...filters, search: val }, { preserveState: true });
                }
              }}
              className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] py-2.5 ps-10 pe-4 text-xs focus:border-blue-500 focus:outline-none"
            />
            <svg className="absolute start-3 top-3 size-4 text-[var(--text-muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            <select
              value={filters.status || ''}
              onChange={(e) => router.get('/sales/delivery-notes', { ...filters, status: e.target.value }, { preserveState: true })}
              className="rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
            >
              <option value="">{isAr ? 'جميع الحالات' : 'All Statuses'}</option>
              <option value="draft">{isAr ? 'مسودة' : 'Draft'}</option>
              <option value="confirmed">{isAr ? 'مؤكد' : 'Confirmed'}</option>
              <option value="cancelled">{isAr ? 'ملغى' : 'Cancelled'}</option>
            </select>
          </div>
        </div>

        {deliveryNotes.data.length === 0 ? (
          <EmptyState
            title={isAr ? 'لا توجد أذون تسليم' : 'No Delivery Notes found'}
            description={isAr ? 'قم بإنشاء أمر بيع مؤكد أولاً ثم أنشئ إذن تسليم' : 'Confirm a sales order first then create a delivery note'}
          />
        ) : (
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{isAr ? 'رقم الإذن' : 'Delivery Note #'}</th>
                  <th className={tableClasses.th}>{isAr ? 'رقم أمر البيع' : 'Sales Order #'}</th>
                  <th className={tableClasses.th}>{isAr ? 'العميل' : 'Customer'}</th>
                  <th className={tableClasses.th}>{isAr ? 'تاريخ التسليم' : 'Delivery Date'}</th>
                  <th className={tableClasses.th}>{isAr ? 'الحالة' : 'Status'}</th>
                  <th className={`${tableClasses.th} text-end`}>{isAr ? 'الإجراءات' : 'Actions'}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border)]">
                {deliveryNotes.data.map((note) => (
                  <tr key={note.id}>
                    <td className={`${tableClasses.td} font-mono font-bold text-blue-600`}>
                      {note.number || (isAr ? '(مسودة)' : '(Draft)')}
                    </td>
                    <td className={`${tableClasses.td} font-mono`}>{note.salesOrder?.number || '-'}</td>
                    <td className={`${tableClasses.td} font-medium`}>{note.salesOrder?.customer?.name || '-'}</td>
                    <td className={tableClasses.td}>{note.delivery_date}</td>
                    <td className={tableClasses.td}>
                      <StatusBadge tone={getStatusTone(note.status)}>
                        {getStatusLabel(note.status)}
                      </StatusBadge>
                    </td>
                    <td className={`${tableClasses.td} text-end space-x-2 rtl:space-x-reverse`}>
                      {note.status === 'draft' ? (
                        <>
                          <button
                            type="button"
                            onClick={() => openEditModal(note)}
                            className="text-xs font-semibold text-blue-600 hover:text-blue-800"
                          >
                            {isAr ? 'تعديل' : 'Edit'}
                          </button>
                          <button
                            type="button"
                            onClick={() => handleAction(note.id, 'confirm')}
                            className="text-xs font-semibold text-emerald-600 hover:text-emerald-800"
                          >
                            {isAr ? 'تأكيد' : 'Confirm'}
                          </button>
                          <button
                            type="button"
                            onClick={() => handleAction(note.id, 'cancel')}
                            className="text-xs font-semibold text-red-600 hover:text-red-800"
                          >
                            {isAr ? 'إلغاء' : 'Cancel'}
                          </button>
                        </>
                      ) : null}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>

      {/* Create / Edit Modal */}
      {showModal ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-xs overflow-y-auto">
          <div className="w-full max-w-3xl rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-2xl my-8">
            <h3 className="text-base font-bold text-[var(--text-primary)] mb-4">
              {editingNote
                ? isAr ? 'تعديل إذن التسليم' : 'Edit Delivery Note'
                : isAr ? 'إنشاء إذن تسليم جديد' : 'Create Delivery Note'}
            </h3>

            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                    {isAr ? 'أمر البيع المؤكد' : 'Confirmed Sales Order'} *
                  </label>
                  <select
                    disabled={Boolean(editingNote)}
                    value={data.sales_order_id}
                    onChange={(e) => handleSalesOrderSelect(e.target.value)}
                    required
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none disabled:opacity-50"
                  >
                    <option value="">{isAr ? 'اختر أمر البيع' : 'Select Sales Order'}</option>
                    {confirmedSalesOrders.map((so) => (
                      <option key={so.id} value={so.id}>
                        {so.number} - {so.customer?.name}
                      </option>
                    ))}
                  </select>
                  {errors.sales_order_id ? <p className="mt-1 text-[10px] text-red-500">{errors.sales_order_id}</p> : null}
                </div>

                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                    {isAr ? 'تاريخ التسليم' : 'Delivery Date'} *
                  </label>
                  <input
                    type="date"
                    value={data.delivery_date}
                    onChange={(e) => setData('delivery_date', e.target.value)}
                    required
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
                  />
                  {errors.delivery_date ? <p className="mt-1 text-[10px] text-red-500">{errors.delivery_date}</p> : null}
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                  {isAr ? 'المرجع' : 'Reference'}
                </label>
                <input
                  type="text"
                  value={data.reference}
                  onChange={(e) => setData('reference', e.target.value)}
                  placeholder="e.g. TRUCK-DELIV-01"
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
                />
              </div>

              {/* Delivery Note Lines */}
              <div className="pt-4 border-t border-[var(--border)]">
                <h4 className="text-xs font-bold uppercase tracking-wider text-[var(--text-primary)] mb-3">
                  {isAr ? 'بنود إذن التسليم' : 'Delivery Lines'}
                </h4>

                <div className="space-y-3">
                  {lineItems.map((item, idx) => (
                    <div key={idx} className="flex flex-col sm:flex-row items-start sm:items-center gap-2 p-3 rounded-xl border border-[var(--border)] bg-[var(--background)]/50">
                      <div className="flex-1 w-full sm:w-auto">
                        <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">
                          {isAr ? 'المنتج' : 'Product'}
                        </label>
                        <input
                          type="text"
                          disabled
                          value={item.product_name}
                          className="w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2 py-1.5 text-xs text-[var(--text-primary)] font-medium"
                        />
                      </div>

                      <div className="w-full sm:w-24">
                        <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">
                          {isAr ? 'الوحدة' : 'UOM'}
                        </label>
                        <input
                          type="text"
                          disabled
                          value={item.uom_name}
                          className="w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2 py-1.5 text-xs text-[var(--text-muted)] font-medium"
                        />
                      </div>

                      <div className="w-full sm:w-32">
                        <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">
                          {isAr ? 'الكمية المسلمة' : 'Delivered Qty'}
                        </label>
                        <input
                          type="number"
                          step="0.000001"
                          min="0.000001"
                          value={item.quantity}
                          onChange={(e) => {
                            const val = parseFloat(e.target.value) || 0;
                            setLineItems((prev) => {
                              const next = [...prev];
                              next[idx] = { ...next[idx], quantity: val };
                              return next;
                            });
                          }}
                          required
                          className="w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none font-mono"
                        />
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                  {isAr ? 'ملاحظات' : 'Notes'}
                </label>
                <textarea
                  rows={2}
                  value={data.notes}
                  onChange={(e) => setData('notes', e.target.value)}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none resize-none"
                />
              </div>

              <div className="flex items-center justify-end gap-3 pt-4 border-t border-[var(--border)]">
                <button
                  type="button"
                  onClick={closeModal}
                  className="rounded-xl border border-[var(--border)] px-4 py-2 text-xs font-semibold text-[var(--text-secondary)] hover:bg-[var(--background)]"
                >
                  {isAr ? 'إلغاء' : 'Cancel'}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  className="rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                >
                  {processing
                    ? isAr ? 'جاري الحفظ...' : 'Saving...'
                    : isAr ? 'حفظ المسودة' : 'Save Draft'}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
