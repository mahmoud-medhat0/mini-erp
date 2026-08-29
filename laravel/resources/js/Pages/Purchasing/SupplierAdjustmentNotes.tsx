import { Head, Link, useForm, router } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { PaginationLink, SharedPageProps } from '../../Types';

type SupplierOption = {
  id: string;
  code: string;
  name: string;
};

type PostedBillOption = {
  id: string;
  number?: string | null;
  supplier_id: string;
  currency: string;
};

type AdjustmentLineForm = {
  description: string;
  quantity: number;
  unit_cost_minor: number | '';
};

type AdjustmentNoteRow = {
  id: string;
  number?: string | null;
  supplier_id: string;
  supplier_bill_id?: string | null;
  purchase_return_id?: string | null;
  payable_entry_id?: string | null;
  supplier?: { id: string; name: string } | null;
  supplierBill?: { id: string; number?: string | null } | null;
  purchaseReturn?: { id: string; number?: string | null } | null;
  adjustment_date: string;
  direction: 'decrease_payable' | 'increase_payable';
  ui_label?: string | null;
  currency: string;
  subtotal_minor: number;
  tax_minor: number;
  tax_amount_minor?: number | null;
  total_minor: number;
  tax_mode: 'none' | 'manual_rate' | 'manual_amount';
  tax_rate_bps: number;
  reason?: string | null;
  notes?: string | null;
  status: 'draft' | 'submitted' | 'approved' | 'posted' | 'cancelled';
  lock_version: number;
  lines?: Array<{
    id: string;
    description?: string | null;
    quantity_e6?: number | null;
    unit_cost_minor: number;
    line_subtotal_minor: number;
  }>;
};

type SupplierAdjustmentNotesProps = SharedPageProps & {
  supplierAdjustmentNotes: {
    data: AdjustmentNoteRow[];
    links: PaginationLink[];
  };
  activeSuppliers: SupplierOption[];
  postedSupplierBills: PostedBillOption[];
  postedPurchaseReturns: Array<{ id: string; number?: string | null; supplier_id: string }>;
  taxCodes?: Array<{ id: string; code: string; name: Record<string, string> | string; calculation_mode: string }>;
  filters: {
    search?: string;
    status?: string;
    supplier_id?: string;
  };
};

export default function SupplierAdjustmentNotesIndex({
  locale,
  flash,
  supplierAdjustmentNotes,
  activeSuppliers,
  postedSupplierBills,
  postedPurchaseReturns,
  taxCodes = [],
  filters,
}: SupplierAdjustmentNotesProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const pageDict = dict.app.pages.purchasingSupplierAdjustmentNotes;
  const can = useCan();
  
  const [showModal, setShowModal] = useState(false);
  const [editingNote, setEditingNote] = useState<AdjustmentNoteRow | null>(null);
  const [lineItems, setLineItems] = useState<AdjustmentLineForm[]>([]);

  const todayStr = new Date().toISOString().split('T')[0];

  const { data, setData, post, put, processing, errors, reset } = useForm({
    supplier_id: '',
    supplier_bill_id: '',
    purchase_return_id: '',
    adjustment_date: todayStr,
    direction: 'decrease_payable',
    ui_label: '',
    currency: '',
    tax_mode: 'none',
    tax_rate_bps: '' as number | '',
    tax_amount_minor: '' as number | '',
    reason: '',
    notes: '',
    lock_version: 1,
  });

  const selectedBill = postedSupplierBills.find((bill) => bill.id === data.supplier_bill_id);

  const filteredBillOptions = useMemo(() => postedSupplierBills
    .filter((bill) => !data.supplier_id || bill.supplier_id === data.supplier_id)
    .map((bill) => ({
      value: bill.id,
      label: bill.number || pageDict.draft_2,
      sublabel: bill.currency,
    })), [postedSupplierBills, data.supplier_id, pageDict.draft_2]);

  const statusFilterOptions = useMemo(() => [
    { value: '', label: pageDict.allStatuses },
    { value: 'draft', label: pageDict.draft },
    { value: 'submitted', label: pageDict.submitted },
    { value: 'approved', label: pageDict.approved },
    { value: 'posted', label: pageDict.posted },
    { value: 'cancelled', label: pageDict.cancelled },
  ], [pageDict.allStatuses, pageDict.draft, pageDict.submitted, pageDict.approved, pageDict.posted, pageDict.cancelled]);

  const supplierOptions = useMemo(() => activeSuppliers.map((supplier) => ({
    value: supplier.id,
    label: supplier.name,
    sublabel: supplier.code,
  })), [activeSuppliers]);

  const directionOptions = useMemo(() => [
    { value: 'decrease_payable', label: pageDict.decreasePayable },
    { value: 'increase_payable', label: pageDict.increasePayable },
  ], [pageDict.decreasePayable, pageDict.increasePayable]);

  const taxModeOptions = useMemo(() => [
    { value: 'none', label: pageDict.taxNone },
    { value: 'manual_rate', label: pageDict.manualRate },
    { value: 'manual_amount', label: pageDict.manualAmount },
  ], [pageDict.taxNone, pageDict.manualRate, pageDict.manualAmount]);
  const canManageSupplierAdjustmentNotes = can('purchasing.adjustment_notes');
  const canPostSupplierAdjustmentNotes = canManageSupplierAdjustmentNotes && can('view_financials');
  const supplierAdjustmentNoteSubmitLabel = processing ? pageDict.saving : pageDict.saveDraft;

  const handleSupplierSelect = (supplierId: string) => {
    setData('supplier_id', supplierId);
    setData('supplier_bill_id', '');
    setData('purchase_return_id', '');
  };

  const handleSupplierBillSelect = (billId: string) => {
    setData('supplier_bill_id', billId);
    const bill = postedSupplierBills.find((item) => item.id === billId);
    if (bill?.currency) {
      setData('currency', bill.currency);
    }
  };

  const addLine = () => {
    setLineItems((prev) => [...prev, { description: '', quantity: 1, unit_cost_minor: '' }]);
  };

  const updateLineItem = <K extends keyof AdjustmentLineForm>(index: number, field: K, value: AdjustmentLineForm[K]) => {
    setLineItems((prev) => {
      const next = [...prev];
      next[index] = { ...next[index], [field]: value };
      return next;
    });
  };

  const removeLine = (index: number) => {
    setLineItems((prev) => prev.filter((_, i) => i !== index));
  };

  const openCreateModal = () => {
    reset();
    setEditingNote(null);
    setLineItems([{ description: '', quantity: 1, unit_cost_minor: '' }]);
    setShowModal(true);
  };

  const openEditModal = (note: AdjustmentNoteRow) => {
    setEditingNote(note);
    setData({
      supplier_id: note.supplier_id,
      supplier_bill_id: note.supplier_bill_id || '',
      purchase_return_id: note.purchase_return_id || '',
      adjustment_date: note.adjustment_date,
      direction: note.direction,
      ui_label: note.ui_label || '',
      currency: note.currency,
      tax_mode: note.tax_mode || 'none',
      tax_rate_bps: note.tax_mode === 'manual_rate' ? note.tax_rate_bps : '',
      tax_amount_minor: note.tax_mode === 'manual_amount' ? note.tax_amount_minor ?? note.tax_minor : '',
      reason: note.reason || '',
      notes: note.notes || '',
      lock_version: note.lock_version,
    });
    setLineItems(
      (note.lines || []).map((l) => ({
        description: l.description || '',
        quantity: (l.quantity_e6 || 0) / 1000000,
        unit_cost_minor: l.unit_cost_minor,
      }))
    );
    setShowModal(true);
  };

  const closeModal = () => {
    setShowModal(false);
    setEditingNote(null);
    reset();
    setLineItems([]);
  };

  const lineSubtotalMinor = (item: AdjustmentLineForm) =>
    Math.floor((Math.round(Number(item.quantity || 0) * 1000000) * Number(item.unit_cost_minor || 0)) / 1000000);

  const previewSubtotalMinor = lineItems.reduce((acc, item) => acc + lineSubtotalMinor(item), 0);

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();

    const formattedLines = lineItems.map((item) => ({
      supplier_bill_line_id: null,
      purchase_return_line_id: null,
      description: item.description,
      quantity_e6: Math.round(Number(item.quantity || 0) * 1000000) || null,
      unit_cost_minor: Number(item.unit_cost_minor || 0),
      tax_rate_bps: null,
    }));

    const payload = {
      ...data,
      supplier_bill_id: data.supplier_bill_id || null,
      purchase_return_id: data.purchase_return_id || null,
      ui_label: data.ui_label || null,
      tax_rate_bps: data.tax_mode === 'manual_rate' && data.tax_rate_bps !== '' ? data.tax_rate_bps : null,
      tax_amount_minor:
        data.tax_mode === 'manual_amount' && data.tax_amount_minor !== '' ? data.tax_amount_minor : null,
      lines: formattedLines,
    };

    if (editingNote) {
      router.put(`/purchasing/adjustment-notes/${editingNote.id}`, payload, {
        preserveScroll: true,
        onSuccess: () => closeModal(),
      });
    } else {
      router.post('/purchasing/adjustment-notes', payload, {
        preserveScroll: true,
        onSuccess: () => closeModal(),
      });
    }
  };

  const handleAction = (noteId: string, action: 'submit' | 'approve' | 'post' | 'cancel') => {
    let confirmMsg = '';
    if (action === 'submit') confirmMsg = dict.app.pages.purchasingSupplierAdjustmentNotes.submitThisAdjustmentNote;
    if (action === 'approve') confirmMsg = dict.app.pages.purchasingSupplierAdjustmentNotes.approveThisAdjustmentNote;
    if (action === 'post') confirmMsg = dict.app.pages.purchasingSupplierAdjustmentNotes.postThisAdjustmentNoteToApGl;
    if (action === 'cancel') confirmMsg = dict.app.pages.purchasingSupplierAdjustmentNotes.cancelThisAdjustmentNote;

    if (confirm(confirmMsg)) {
      const payload = action === 'post' ? { confirm_action: 'POST_SUPPLIER_ADJUSTMENT_NOTE' } : {};
      router.post(`/purchasing/adjustment-notes/${noteId}/${action}`, payload, { preserveScroll: true });
    }
  };

  const getStatusTone = (status: string): 'muted' | 'info' | 'warning' | 'ok' | 'danger' => {
    switch (status) {
      case 'draft':
        return 'muted';
      case 'submitted':
        return 'info';
      case 'approved':
        return 'warning';
      case 'posted':
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
        return dict.app.pages.purchasingSupplierAdjustmentNotes.draft;
      case 'submitted':
        return dict.app.pages.purchasingSupplierAdjustmentNotes.submitted;
      case 'approved':
        return dict.app.pages.purchasingSupplierAdjustmentNotes.approved;
      case 'posted':
        return dict.app.pages.purchasingSupplierAdjustmentNotes.posted;
      case 'cancelled':
        return dict.app.pages.purchasingSupplierAdjustmentNotes.cancelled;
      default:
        return status;
    }
  };

  const canSettleSupplierAdjustmentNote = (note: AdjustmentNoteRow) => (
    note.status === 'posted'
      && note.direction === 'decrease_payable'
      && Boolean(note.payable_entry_id)
      && canManageSupplierAdjustmentNotes
  );

  const isSupplierAdjustmentNoteActionable = (note: AdjustmentNoteRow) => (
    ['draft', 'submitted', 'approved'].includes(note.status)
      || (note.status === 'posted' && note.direction === 'decrease_payable' && Boolean(note.payable_entry_id))
  );

  const hasAvailableSupplierAdjustmentNoteAction = (note: AdjustmentNoteRow) => (
    note.status === 'draft'
      ? canManageSupplierAdjustmentNotes
      : note.status === 'submitted'
        ? canManageSupplierAdjustmentNotes
        : note.status === 'approved'
          ? canManageSupplierAdjustmentNotes || canPostSupplierAdjustmentNotes
          : canSettleSupplierAdjustmentNote(note)
  );

  const getSupplierAdjustmentNoteActionState = (note: AdjustmentNoteRow) => {
    if (hasAvailableSupplierAdjustmentNoteAction(note)) return null;

    return isSupplierAdjustmentNoteActionable(note) ? dict.app.actions.restricted : dict.app.actions.noActions;
  };

  const getDirectionLabel = (direction: string) =>
    direction === 'increase_payable' ? dict.app.pages.purchasingSupplierAdjustmentNotes.increasePayable : dict.app.pages.purchasingSupplierAdjustmentNotes.decreasePayable;

  return (
    <AppLayout active="supplier-adjustment-notes.index">
      <Head title={dict.app.pages.purchasingSupplierAdjustmentNotes.supplierAdjustmentNotes} />

      <PageHeader
        title={dict.app.pages.purchasingSupplierAdjustmentNotes.supplierAdjustmentNotes_2}
        description={dict.app.pages.purchasingSupplierAdjustmentNotes.manageSupplierDebitCreditAdjustmentNotes}
        actions={
          can('purchasing.create') ? (
            <button
              type="button"
              onClick={openCreateModal}
              title={pageDict.createAdjustmentNote}
              aria-label={pageDict.createAdjustmentNote}
              className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-semibold text-white shadow-md hover:bg-blue-700 transition-all"
            >
              <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              <span>{dict.app.pages.purchasingSupplierAdjustmentNotes.createAdjustmentNote}</span>
            </button>
          ) : null
        }
      />

      {flash?.success ? (
        <div className="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
          {flash.success}
        </div>
      ) : null}

      <Card className="p-6">
        <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative flex-1 max-w-md">
            <input
              type="text"
              placeholder={dict.app.pages.purchasingSupplierAdjustmentNotes.searchNumberLabelOrSupplier}
              defaultValue={filters.search || ''}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  const val = (e.target as HTMLInputElement).value;
                  router.get('/purchasing/adjustment-notes', { ...filters, search: val }, { preserveState: true, preserveScroll: true });
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
              options={statusFilterOptions}
              value={filters.status || null}
              onChange={(value) => router.get('/purchasing/adjustment-notes', { ...filters, status: value || '' }, { preserveState: true, preserveScroll: true })}
              label={dict.app.pages.purchasingSupplierAdjustmentNotes.status}
            />
          </div>
        </div>

        {supplierAdjustmentNotes.data.length === 0 ? (
          <EmptyState
            title={dict.app.pages.purchasingSupplierAdjustmentNotes.noSupplierAdjustmentNotesFound}
            description={dict.app.pages.purchasingSupplierAdjustmentNotes.createAnAdjustmentNoteToCorrectSupplier}
          />
        ) : (
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{dict.app.pages.purchasingSupplierAdjustmentNotes.noteNumber}</th>
                  <th className={tableClasses.th}>{dict.app.pages.purchasingSupplierAdjustmentNotes.supplier}</th>
                  <th className={tableClasses.th}>{dict.app.pages.purchasingSupplierAdjustmentNotes.label}</th>
                  <th className={tableClasses.th}>{dict.app.pages.purchasingSupplierAdjustmentNotes.bill_2}</th>
                  <th className={tableClasses.th}>{dict.app.pages.purchasingSupplierAdjustmentNotes.direction}</th>
                  <th className={tableClasses.th}>{dict.app.pages.purchasingSupplierAdjustmentNotes.adjustmentDate}</th>
                  <th className={tableClasses.th}>{dict.app.pages.purchasingSupplierAdjustmentNotes.totalAmount}</th>
                  <th className={tableClasses.th}>{dict.app.pages.purchasingSupplierAdjustmentNotes.status}</th>
                  <th className={`${tableClasses.th} text-end`}>{dict.app.pages.purchasingSupplierAdjustmentNotes.actions}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border)]">
                {supplierAdjustmentNotes.data.map((note) => {
                  const actionState = getSupplierAdjustmentNoteActionState(note);

                  return (
                    <tr key={note.id}>
                    <td className={`${tableClasses.td} font-mono font-bold text-blue-600`}>
                      {note.number || dict.app.pages.purchasingSupplierAdjustmentNotes.draft_2}
                    </td>
                    <td className={`${tableClasses.td} font-medium`}>{note.supplier?.name || accDict.notAvailable}</td>
                    <td className={tableClasses.td}>{note.ui_label || accDict.notAvailable}</td>
                    <td className={`${tableClasses.td} font-mono`}>{note.supplierBill?.number || accDict.notAvailable}</td>
                    <td className={`${tableClasses.td} font-semibold ${note.direction === 'increase_payable' ? 'text-red-600' : 'text-emerald-600'}`}>
                      {getDirectionLabel(note.direction)}
                    </td>
                    <td className={tableClasses.td}>{note.adjustment_date}</td>
                    <td className={`${tableClasses.td} font-mono font-semibold`}>
                      {formatMoney(note.total_minor, note.currency)}
                    </td>
                    <td className={tableClasses.td}>
                      <StatusBadge tone={getStatusTone(note.status)}>
                        {getStatusLabel(note.status)}
                      </StatusBadge>
                    </td>
                      <td className={`${tableClasses.td} text-end`}>
                        <div className="flex flex-wrap items-center justify-end gap-2">
                          {note.status === 'draft' && canManageSupplierAdjustmentNotes ? (
                            <button
                              type="button"
                              onClick={() => openEditModal(note)}
                              title={dict.app.pages.purchasingSupplierAdjustmentNotes.edit}
                              aria-label={dict.app.pages.purchasingSupplierAdjustmentNotes.edit}
                              className="inline-flex h-8 items-center rounded-md border border-blue-200 px-2.5 text-xs font-semibold text-blue-700 transition-colors hover:bg-blue-50 dark:border-blue-900/60 dark:text-blue-300 dark:hover:bg-blue-950/40"
                            >
                              {dict.app.pages.purchasingSupplierAdjustmentNotes.edit}
                            </button>
                          ) : null}

                          {note.status === 'draft' && canManageSupplierAdjustmentNotes ? (
                            <button
                              type="button"
                              onClick={() => handleAction(note.id, 'submit')}
                              title={dict.app.pages.purchasingSupplierAdjustmentNotes.submit}
                              aria-label={dict.app.pages.purchasingSupplierAdjustmentNotes.submit}
                              className="inline-flex h-8 items-center rounded-md border border-indigo-200 px-2.5 text-xs font-semibold text-indigo-700 transition-colors hover:bg-indigo-50 dark:border-indigo-900/60 dark:text-indigo-300 dark:hover:bg-indigo-950/40"
                            >
                              {dict.app.pages.purchasingSupplierAdjustmentNotes.submit}
                            </button>
                          ) : null}

                          {['draft', 'submitted'].includes(note.status) && canManageSupplierAdjustmentNotes ? (
                            <button
                              type="button"
                              onClick={() => handleAction(note.id, 'approve')}
                              title={dict.app.pages.purchasingSupplierAdjustmentNotes.approve}
                              aria-label={dict.app.pages.purchasingSupplierAdjustmentNotes.approve}
                              className="inline-flex h-8 items-center rounded-md border border-amber-200 px-2.5 text-xs font-semibold text-amber-700 transition-colors hover:bg-amber-50 dark:border-amber-900/60 dark:text-amber-300 dark:hover:bg-amber-950/40"
                            >
                              {dict.app.pages.purchasingSupplierAdjustmentNotes.approve}
                            </button>
                          ) : null}

                          {note.status === 'approved' && canPostSupplierAdjustmentNotes ? (
                            <button
                              type="button"
                              onClick={() => handleAction(note.id, 'post')}
                              title={dict.app.pages.purchasingSupplierAdjustmentNotes.postToApGl}
                              aria-label={dict.app.pages.purchasingSupplierAdjustmentNotes.postToApGl}
                              className="inline-flex h-8 items-center rounded-md border border-emerald-200 px-2.5 text-xs font-semibold text-emerald-700 transition-colors hover:bg-emerald-50 dark:border-emerald-900/60 dark:text-emerald-300 dark:hover:bg-emerald-950/40"
                            >
                              {dict.app.pages.purchasingSupplierAdjustmentNotes.postToApGl}
                            </button>
                          ) : null}

                          {canSettleSupplierAdjustmentNote(note) ? (
                            <Link
                              href={`/purchasing/payable-settlements?supplier_id=${note.supplier_id}&source_entry_id=${note.payable_entry_id}`}
                              title={dict.app.pages.purchasingSupplierAdjustmentNotes.settle}
                              aria-label={dict.app.pages.purchasingSupplierAdjustmentNotes.settle}
                              className="inline-flex h-8 items-center rounded-md border border-purple-200 px-2.5 text-xs font-semibold text-purple-700 transition-colors hover:bg-purple-50 dark:border-purple-900/60 dark:text-purple-300 dark:hover:bg-purple-950/40"
                            >
                              {dict.app.pages.purchasingSupplierAdjustmentNotes.settle}
                            </Link>
                          ) : null}

                          {['draft', 'submitted', 'approved'].includes(note.status) && canManageSupplierAdjustmentNotes ? (
                            <button
                              type="button"
                              onClick={() => handleAction(note.id, 'cancel')}
                              title={dict.app.pages.purchasingSupplierAdjustmentNotes.cancel}
                              aria-label={dict.app.pages.purchasingSupplierAdjustmentNotes.cancel}
                              className="inline-flex h-8 items-center rounded-md border border-red-200 px-2.5 text-xs font-semibold text-red-700 transition-colors hover:bg-red-50 dark:border-red-900/60 dark:text-red-300 dark:hover:bg-red-950/40"
                            >
                              {dict.app.pages.purchasingSupplierAdjustmentNotes.cancel}
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

      {showModal ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-xs overflow-y-auto">
          <div className="w-full max-w-4xl rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-2xl my-8">
            <h3 className="text-base font-bold text-[var(--text-primary)] mb-4">
              {editingNote ? dict.app.pages.purchasingSupplierAdjustmentNotes.editAdjustmentNote : dict.app.pages.purchasingSupplierAdjustmentNotes.createAdjustmentNote_2}
            </h3>

            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <SearchableSelect
                  label={dict.app.pages.purchasingSupplierAdjustmentNotes.supplier_2}
                  disabled={Boolean(editingNote)}
                  value={data.supplier_id || null}
                  onChange={(value) => handleSupplierSelect(value || '')}
                  options={supplierOptions}
                  placeholder={dict.app.pages.purchasingSupplierAdjustmentNotes.selectSupplier}
                  isClearable={false}
                  required
                  error={errors.supplier_id}
                />

                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">{dict.app.pages.purchasingSupplierAdjustmentNotes.linkedPostedBill}</label>
                  <SearchableSelect
                    disabled={Boolean(editingNote)}
                    value={data.supplier_bill_id || null}
                    onChange={(value) => handleSupplierBillSelect(value || '')}
                    options={filteredBillOptions}
                    placeholder={dict.app.pages.purchasingSupplierAdjustmentNotes.none}
                    isClearable
                  />
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <DatePicker
                    label={dict.app.pages.purchasingSupplierAdjustmentNotes.adjustmentDate_2}
                    value={data.adjustment_date}
                    onChange={(value) => setData('adjustment_date', value || '')}
                    required
                    error={errors.adjustment_date}
                  />

                  <div>
                    <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">{dict.app.pages.purchasingSupplierAdjustmentNotes.currency} *</label>
                    <input
                      type="text"
                      disabled={Boolean(selectedBill)}
                      value={data.currency}
                      onChange={(e) => setData('currency', e.target.value.toUpperCase())}
                      maxLength={3}
                      required
                      className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono uppercase focus:border-blue-500 focus:outline-none disabled:opacity-50"
                    />
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">{dict.app.pages.purchasingSupplierAdjustmentNotes.direction} *</label>
                    <SearchableSelect
                      value={data.direction || null}
                      onChange={(value) => setData('direction', value || 'decrease_payable')}
                      options={directionOptions}
                      isClearable={false}
                      required
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">{dict.app.pages.purchasingSupplierAdjustmentNotes.uiLabelText}</label>
                    <input
                      type="text"
                      value={data.ui_label}
                      onChange={(e) => setData('ui_label', e.target.value)}
                      maxLength={255}
                      placeholder={dict.app.pages.purchasingSupplierAdjustmentNotes.uiLabelPlaceholder}
                      className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
                    />
                  </div>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                <SearchableSelect
                  label={dict.app.pages.purchasingSupplierAdjustmentNotes.taxMode}
                  value={data.tax_mode || null}
                  onChange={(value) => setData('tax_mode', value || 'none')}
                  options={taxModeOptions}
                  isClearable={false}
                  required
                />

                {data.tax_mode === 'manual_rate' ? (
                  <div>
                    <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">{dict.app.pages.purchasingSupplierAdjustmentNotes.taxRateBps}</label>
                    <input
                      type="number"
                      step="1"
                      min="0"
                      value={data.tax_rate_bps}
                      onChange={(e) => setData('tax_rate_bps', e.target.value === '' ? '' : parseInt(e.target.value, 10))}
                      className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono focus:border-blue-500 focus:outline-none"
                    />
                    <p className="mt-1 text-[10px] text-[var(--text-muted)]">{dict.app.pages.purchasingSupplierAdjustmentNotes.taxRateBpsHint}</p>
                  </div>
                ) : null}

                {data.tax_mode === 'manual_amount' ? (
                  <div>
                    <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">{dict.app.pages.purchasingSupplierAdjustmentNotes.taxOverrideAmountMinor}</label>
                    <input
                      type="number"
                      step="1"
                      min="0"
                      value={data.tax_amount_minor}
                      onChange={(e) => setData('tax_amount_minor', e.target.value === '' ? '' : parseInt(e.target.value, 10))}
                      className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono focus:border-blue-500 focus:outline-none"
                    />
                  </div>
                ) : null}
              </div>

              <div className="pt-4 border-t border-[var(--border)]">
                <div className="flex items-center justify-between mb-3">
                  <h4 className="text-xs font-bold uppercase tracking-wider text-[var(--text-primary)]">{dict.app.pages.purchasingSupplierAdjustmentNotes.adjustmentLines}</h4>
                  <button
                    type="button"
                    onClick={addLine}
                    title={pageDict.addLine}
                    aria-label={pageDict.addLine}
                    className="text-xs font-semibold text-blue-600 hover:text-blue-800"
                  >
                    + {dict.app.pages.purchasingSupplierAdjustmentNotes.addLine}
                  </button>
                </div>

                <div className="space-y-3">
                  {lineItems.map((item, idx) => (
                    <div key={idx} className="flex flex-col lg:flex-row items-start lg:items-end gap-2 p-3 rounded-xl border border-[var(--border)] bg-[var(--background)]/50">
                      <div className="flex-1 w-full min-w-0">
                        <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">{dict.app.pages.purchasingSupplierAdjustmentNotes.description} *</label>
                        <input
                          type="text"
                          value={item.description}
                          onChange={(e) => updateLineItem(idx, 'description', e.target.value)}
                          required
                          className="w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                        />
                      </div>

                      <div className="w-full lg:w-28">
                        <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">{dict.app.pages.purchasingSupplierAdjustmentNotes.qty}</label>
                        <input
                          type="number"
                          step="0.000001"
                          min="0"
                          value={item.quantity}
                          onChange={(e) => updateLineItem(idx, 'quantity', parseFloat(e.target.value) || 0)}
                          className="w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none font-mono"
                        />
                      </div>

                      <div className="w-full lg:w-32">
                        <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">{dict.app.pages.purchasingSupplierAdjustmentNotes.unitCostMinor}</label>
                        <input
                          type="number"
                          step="1"
                          min="0"
                          value={item.unit_cost_minor}
                          onChange={(e) =>
                            updateLineItem(idx, 'unit_cost_minor', e.target.value === '' ? '' : parseInt(e.target.value, 10))
                          }
                          required
                          className="w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none font-mono"
                        />
                        <p className="mt-1 text-[10px] text-[var(--text-muted)]">{dict.app.pages.purchasingSupplierAdjustmentNotes.unitCostMinorHint}</p>
                      </div>

                      <div className="w-full lg:w-32 text-end">
                        <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">{dict.app.pages.purchasingSupplierAdjustmentNotes.subtotal}</label>
                        <span className="block px-2 py-1.5 text-xs font-mono font-semibold text-[var(--text-primary)]">
                          {formatMoney(lineSubtotalMinor(item), data.currency)}
                        </span>
                      </div>

                      {lineItems.length > 1 ? (
                        <button
                          type="button"
                          onClick={() => removeLine(idx)}
                          title={pageDict.removeLine}
                          aria-label={pageDict.removeLine}
                          className="text-red-500 hover:text-red-700 text-xs font-bold pb-2"
                        >
                          ✕
                        </button>
                      ) : null}
                    </div>
                  ))}
                </div>

                <div className="mt-4 flex justify-end">
                  <div className="text-end">
                    <span className="text-xs font-semibold text-[var(--text-secondary)] me-2">{dict.app.pages.purchasingSupplierAdjustmentNotes.subtotalTotal}</span>
                    <span className="text-sm font-bold font-mono text-blue-600">
                      {formatMoney(previewSubtotalMinor, data.currency)}
                    </span>
                  </div>
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">{dict.app.pages.purchasingSupplierAdjustmentNotes.reason}</label>
                <textarea
                  rows={2}
                  value={data.reason}
                  onChange={(e) => setData('reason', e.target.value)}
                  maxLength={255}
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
                  {dict.app.pages.purchasingSupplierAdjustmentNotes.cancel_2}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  title={supplierAdjustmentNoteSubmitLabel}
                  aria-label={supplierAdjustmentNoteSubmitLabel}
                  className="rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                >
                  {supplierAdjustmentNoteSubmitLabel}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
