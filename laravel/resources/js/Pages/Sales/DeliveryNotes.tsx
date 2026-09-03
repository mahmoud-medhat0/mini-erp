import { Head, useForm, router } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { PaginationLink, SharedPageProps } from '../../Types';

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

type WarehouseOption = {
  id: string;
  code: string;
  name: { en?: string; ar?: string } | string;
  is_default?: boolean;
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
  warehouse_id: string;
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
  warehouse?: WarehouseOption | null;
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
    links: PaginationLink[];
  };
  confirmedSalesOrders: SalesOrderOption[];
  warehouses: WarehouseOption[];
  filters: {
    search?: string;
    status?: string;
    warehouse_id?: string;
  };
};

export default function DeliveryNotesIndex({ locale, deliveryNotes, confirmedSalesOrders, warehouses, filters }: DeliveryNotesProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const pageDict = dict.app.pages.salesDeliveryNotes;
  const can = useCan();

  const [showModal, setShowModal] = useState(false);
  const [editingNote, setEditingNote] = useState<DeliveryNoteRow | null>(null);

  const todayStr = new Date().toISOString().split('T')[0];

  const [lineItems, setLineItems] = useState<DeliveryNoteLineItem[]>([]);

  const { data, setData, post, put, processing, errors, reset } = useForm({
    sales_order_id: confirmedSalesOrders[0]?.id || '',
    warehouse_id: warehouses[0]?.id || '',
    delivery_date: todayStr,
    reference: '',
    notes: '',
    lock_version: 1,
  });
  const warehouseOptions = useMemo(() => warehouses.map((warehouse) => ({
    value: warehouse.id,
    label: `${warehouse.code} - ${getLocalizedName(warehouse.name, locale)}`,
    badge: warehouse.is_default ? pageDict.defaultWarehouse : undefined,
  })), [warehouses, locale, pageDict.defaultWarehouse]);
  const warehouseFilterOptions = useMemo(() => [
    { value: '', label: pageDict.allWarehouses },
    ...warehouseOptions,
  ], [pageDict.allWarehouses, warehouseOptions]);
  const statusFilterOptions = useMemo(() => [
    { value: '', label: pageDict.allStatuses },
    { value: 'draft', label: pageDict.draft },
    { value: 'confirmed', label: pageDict.confirmed },
    { value: 'cancelled', label: pageDict.cancelled },
  ], [pageDict.allStatuses, pageDict.draft, pageDict.confirmed, pageDict.cancelled]);
  const salesOrderOptions = useMemo(() => confirmedSalesOrders.map((salesOrder) => ({
    value: salesOrder.id,
    label: salesOrder.number || accDict.notAvailable,
    sublabel: salesOrder.customer?.name || accDict.notAvailable,
  })), [confirmedSalesOrders, accDict.notAvailable]);
  const canEditDeliveryNotes = can('sales.edit');
  const canConfirmDeliveryNotes = can('sales.approve');
  const canCancelDeliveryNotes = can('sales.cancel');
  const deliveryNoteSubmitLabel = processing ? pageDict.saving : pageDict.saveDraft;

  const handleSalesOrderSelect = (salesOrderId: string) => {
    setData('sales_order_id', salesOrderId);
    const selectedSo = confirmedSalesOrders.find((so) => so.id === salesOrderId);
    if (selectedSo && selectedSo.lines) {
      setLineItems(
        selectedSo.lines.map((l) => ({
          sales_order_line_id: l.id,
          product_name: l.product?.name || '',
          uom_name: l.unitOfMeasure?.name || dict.app.pages.salesDeliveryNotes.noUom,
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
      warehouse_id: note.warehouse_id || warehouses[0]?.id || '',
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
          uom_name: l.unitOfMeasure?.name || dict.app.pages.salesDeliveryNotes.noUom,
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
        preserveScroll: true,
        onSuccess: () => closeModal(),
      });
    } else {
      router.post('/sales/delivery-notes', payload, {
        preserveScroll: true,
        onSuccess: () => closeModal(),
      });
    }
  };

  const handleAction = (noteId: string, action: 'confirm' | 'cancel') => {
    let confirmMsg = '';
    if (action === 'confirm') confirmMsg = dict.app.pages.salesDeliveryNotes.confirmThisDeliveryNote;
    if (action === 'cancel') confirmMsg = dict.app.pages.salesDeliveryNotes.cancelThisDeliveryNote;

    if (confirm(confirmMsg)) {
      router.post(`/sales/delivery-notes/${noteId}/${action}`, {}, { preserveScroll: true });
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
        return dict.app.pages.salesDeliveryNotes.draft;
      case 'confirmed':
        return dict.app.pages.salesDeliveryNotes.confirmed;
      case 'cancelled':
        return dict.app.pages.salesDeliveryNotes.cancelled;
      default:
        return status;
    }
  };

  const hasAvailableDeliveryNoteAction = (note: DeliveryNoteRow) => (
    note.status === 'draft' && (canEditDeliveryNotes || canConfirmDeliveryNotes || canCancelDeliveryNotes)
  );

  const getDeliveryNoteActionState = (note: DeliveryNoteRow) => {
    if (hasAvailableDeliveryNoteAction(note)) return null;

    return note.status === 'draft' ? dict.app.actions.restricted : dict.app.actions.noActions;
  };

  return (
    <AppLayout active="delivery-notes.index">
      <Head title={dict.app.pages.salesDeliveryNotes.deliveryNotes} />

      <PageHeader
        title={dict.app.pages.salesDeliveryNotes.deliveryNotes_2}
        description={dict.app.pages.salesDeliveryNotes.manageCustomerSalesDeliveryNotes}
        actions={
          can('sales.create') ? (
            <button
              type="button"
              onClick={openCreateModal}
              disabled={confirmedSalesOrders.length === 0}
              title={pageDict.createDeliveryNote}
              aria-label={pageDict.createDeliveryNote}
              className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-semibold text-white shadow-md hover:bg-blue-700 disabled:opacity-50 transition-all"
            >
              <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              <span>{dict.app.pages.salesDeliveryNotes.createDeliveryNote}</span>
            </button>
          ) : null
        }
      />

      <Card className="p-6">
        <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative flex-1 max-w-md">
            <input
              type="text"
              placeholder={dict.app.pages.salesDeliveryNotes.searchNumberReferenceOrCustomer}
              defaultValue={filters.search || ''}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  const val = (e.target as HTMLInputElement).value;
                  router.get('/sales/delivery-notes', { ...filters, search: val }, { preserveState: true, preserveScroll: true });
                }
              }}
              className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] py-2.5 ps-10 pe-4 text-xs focus:border-blue-500 focus:outline-none"
            />
            <svg className="absolute start-3 top-3 size-4 text-[var(--text-muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            <SearchableSelect
              options={warehouseFilterOptions}
              value={filters.warehouse_id || null}
              onChange={(value) => router.get('/sales/delivery-notes', { ...filters, warehouse_id: value || '' }, { preserveState: true, preserveScroll: true })}
              label={dict.app.pages.salesDeliveryNotes.warehouse}
            />

            <SearchableSelect
              options={statusFilterOptions}
              value={filters.status || null}
              onChange={(value) => router.get('/sales/delivery-notes', { ...filters, status: value || '' }, { preserveState: true, preserveScroll: true })}
              label={dict.app.pages.salesDeliveryNotes.status}
            />
          </div>
        </div>

        {deliveryNotes.data.length === 0 ? (
          <EmptyState
            title={dict.app.pages.salesDeliveryNotes.noDeliveryNotesFound}
            description={dict.app.pages.salesDeliveryNotes.confirmASalesOrderFirstThen}
          />
        ) : (
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{dict.app.pages.salesDeliveryNotes.deliveryNote}</th>
                  <th className={tableClasses.th}>{dict.app.pages.salesDeliveryNotes.salesOrder}</th>
                  <th className={tableClasses.th}>{dict.app.pages.salesDeliveryNotes.customer}</th>
                  <th className={tableClasses.th}>{dict.app.pages.salesDeliveryNotes.warehouse}</th>
                  <th className={tableClasses.th}>{dict.app.pages.salesDeliveryNotes.deliveryDate}</th>
                  <th className={tableClasses.th}>{dict.app.pages.salesDeliveryNotes.status}</th>
                  <th className={`${tableClasses.th} text-end`}>{dict.app.pages.salesDeliveryNotes.actions}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border)]">
                {deliveryNotes.data.map((note) => {
                  const actionState = getDeliveryNoteActionState(note);

                  return (
                    <tr key={note.id}>
                      <td className={`${tableClasses.td} font-mono font-bold text-blue-600`}>
                        {note.number || dict.app.pages.salesDeliveryNotes.draft_2}
                      </td>
                      <td className={`${tableClasses.td} font-mono`}>{note.salesOrder?.number || accDict.notAvailable}</td>
                      <td className={`${tableClasses.td} font-medium`}>{getLocalizedName(note.salesOrder?.customer?.name, locale) || accDict.notAvailable}</td>
                      <td className={tableClasses.td}>{note.warehouse ? `${note.warehouse.code} - ${getLocalizedName(note.warehouse.name, locale)}` : accDict.notAvailable}</td>
                      <td className={tableClasses.td}>{note.delivery_date}</td>
                      <td className={tableClasses.td}>
                        <StatusBadge tone={getStatusTone(note.status)}>
                          {getStatusLabel(note.status)}
                        </StatusBadge>
                      </td>
                      <td className={`${tableClasses.td} text-end`}>
                        <div className="flex flex-wrap items-center justify-end gap-2">
                          {note.status === 'draft' && canEditDeliveryNotes ? (
                            <button
                              type="button"
                              onClick={() => openEditModal(note)}
                              title={dict.app.pages.salesDeliveryNotes.edit}
                              aria-label={dict.app.pages.salesDeliveryNotes.edit}
                              className="inline-flex h-8 items-center rounded-md border border-blue-200 px-2.5 text-xs font-semibold text-blue-700 transition-colors hover:bg-blue-50 dark:border-blue-900/60 dark:text-blue-300 dark:hover:bg-blue-950/40"
                            >
                              {dict.app.pages.salesDeliveryNotes.edit}
                            </button>
                          ) : null}

                          {note.status === 'draft' && canConfirmDeliveryNotes ? (
                            <button
                              type="button"
                              onClick={() => handleAction(note.id, 'confirm')}
                              title={dict.app.pages.salesDeliveryNotes.confirm}
                              aria-label={dict.app.pages.salesDeliveryNotes.confirm}
                              className="inline-flex h-8 items-center rounded-md border border-emerald-200 px-2.5 text-xs font-semibold text-emerald-700 transition-colors hover:bg-emerald-50 dark:border-emerald-900/60 dark:text-emerald-300 dark:hover:bg-emerald-950/40"
                            >
                              {dict.app.pages.salesDeliveryNotes.confirm}
                            </button>
                          ) : null}

                          {note.status === 'draft' && canCancelDeliveryNotes ? (
                            <button
                              type="button"
                              onClick={() => handleAction(note.id, 'cancel')}
                              title={dict.app.pages.salesDeliveryNotes.cancel}
                              aria-label={dict.app.pages.salesDeliveryNotes.cancel}
                              className="inline-flex h-8 items-center rounded-md border border-red-200 px-2.5 text-xs font-semibold text-red-700 transition-colors hover:bg-red-50 dark:border-red-900/60 dark:text-red-300 dark:hover:bg-red-950/40"
                            >
                              {dict.app.pages.salesDeliveryNotes.cancel}
                            </button>
                          ) : null}

                          {actionState ? (
                            <StatusBadge tone="muted">{actionState}</StatusBadge>
                          ) : null}
                        </div>
                      </td>
                    </tr>
                  );
                })}
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
                ? dict.app.pages.salesDeliveryNotes.editDeliveryNote
                : dict.app.pages.salesDeliveryNotes.createDeliveryNote_2}
            </h3>

            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <SearchableSelect
                  label={dict.app.pages.salesDeliveryNotes.confirmedSalesOrder}
                  value={data.sales_order_id || null}
                  onChange={(value) => handleSalesOrderSelect(value || '')}
                  options={salesOrderOptions}
                  placeholder={dict.app.pages.salesDeliveryNotes.selectSalesOrder}
                  disabled={Boolean(editingNote)}
                  isClearable={false}
                  required
                  error={errors.sales_order_id}
                />

                <SearchableSelect
                  label={dict.app.pages.salesDeliveryNotes.warehouse}
                  value={data.warehouse_id || null}
                  onChange={(value) => setData('warehouse_id', value || '')}
                  options={warehouseOptions}
                  placeholder={dict.app.pages.salesDeliveryNotes.selectWarehouse}
                  isClearable={false}
                  required
                  error={errors.warehouse_id}
                />

                <DatePicker
                  label={dict.app.pages.salesDeliveryNotes.deliveryDate_2}
                  value={data.delivery_date}
                  onChange={(value) => setData('delivery_date', value || '')}
                  required
                  error={errors.delivery_date}
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                  {dict.app.pages.salesDeliveryNotes.reference}
                </label>
                <input
                  type="text"
                  value={data.reference}
                  onChange={(e) => setData('reference', e.target.value)}
                  placeholder={dict.app.pages.salesDeliveryNotes.referencePlaceholder}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
                />
              </div>

              {/* Delivery Note Lines */}
              <div className="pt-4 border-t border-[var(--border)]">
                <h4 className="text-xs font-bold uppercase tracking-wider text-[var(--text-primary)] mb-3">
                  {dict.app.pages.salesDeliveryNotes.deliveryLines}
                </h4>

                <div className="space-y-3">
                  {lineItems.map((item, idx) => (
                    <div key={idx} className="flex flex-col sm:flex-row items-start sm:items-center gap-2 p-3 rounded-xl border border-[var(--border)] bg-[var(--background)]/50">
                      <div className="flex-1 w-full sm:w-auto">
                        <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">
                          {dict.app.pages.salesDeliveryNotes.product}
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
                          {dict.app.pages.salesDeliveryNotes.uom}
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
                          {dict.app.pages.salesDeliveryNotes.deliveredQty}
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
                  {dict.app.pages.salesDeliveryNotes.notes}
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
                  title={pageDict.cancel_2}
                  aria-label={pageDict.cancel_2}
                  className="rounded-xl border border-[var(--border)] px-4 py-2 text-xs font-semibold text-[var(--text-secondary)] hover:bg-[var(--background)]"
                >
                  {dict.app.pages.salesDeliveryNotes.cancel_2}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  title={deliveryNoteSubmitLabel}
                  aria-label={deliveryNoteSubmitLabel}
                  className="rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                >
                  {deliveryNoteSubmitLabel}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
