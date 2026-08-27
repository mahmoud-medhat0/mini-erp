import { Head, Link, useForm, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, EmptyState, PageHeader, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';

type CustomerOption = {
  id: string;
  code: string;
  name: string;
};

type PostedInvoiceOption = {
  id: string;
  number?: string | null;
  customer_id: string;
  currency: string;
  lines: Array<{
    id: string;
    description?: string | null;
    quantity_e6: number;
    unit_price_minor: number;
  }>;
};

type PostedSalesReturnOption = {
  id: string;
  number?: string | null;
  customer_id: string;
};

type CreditLineForm = {
  customer_invoice_line_id?: string | null;
  description: string;
  quantity: number;
  unit_price_minor: number | '';
};

type CreditNoteRow = {
  id: string;
  number?: string | null;
  customer_id: string;
  customer_invoice_id?: string | null;
  sales_return_id?: string | null;
  receivable_entry_id?: string | null;
  customer?: { id: string; name: string } | null;
  customerInvoice?: { id: string; number?: string | null } | null;
  salesReturn?: { id: string; number?: string | null } | null;
  credit_date: string;
  currency: string;
  subtotal_minor: number;
  tax_minor: number;
  tax_minor_override?: number | null;
  total_minor: number;
  tax_mode: 'none' | 'manual_rate' | 'manual_amount';
  tax_rate_bps: number;
  reason?: string | null;
  notes?: string | null;
  status: 'draft' | 'submitted' | 'approved' | 'posted' | 'cancelled';
  lock_version: number;
  lines?: Array<{
    id: string;
    customer_invoice_line_id?: string | null;
    description?: string | null;
    quantity_e6?: number | null;
    unit_price_minor: number;
    line_subtotal_minor: number;
  }>;
};

type CustomerCreditNotesProps = SharedPageProps & {
  customerCreditNotes: {
    data: CreditNoteRow[];
    links: any[];
  };
  activeCustomers: CustomerOption[];
  postedCustomerInvoices: PostedInvoiceOption[];
  postedSalesReturns: PostedSalesReturnOption[];
  taxCodes?: any[];
  filters: {
    search?: string;
    status?: string;
    customer_id?: string;
  };
};

export default function CustomerCreditNotesIndex({
  locale,
  flash,
  customerCreditNotes,
  activeCustomers,
  postedCustomerInvoices,
  postedSalesReturns,
  filters,
}: CustomerCreditNotesProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const can = useCan();
  
  const [showModal, setShowModal] = useState(false);
  const [editingNote, setEditingNote] = useState<CreditNoteRow | null>(null);
  const [lineItems, setLineItems] = useState<CreditLineForm[]>([]);

  const todayStr = new Date().toISOString().split('T')[0];

  const { data, setData, post, put, processing, errors, reset } = useForm({
    customer_id: '',
    customer_invoice_id: '',
    sales_return_id: '',
    credit_date: todayStr,
    currency: '',
    tax_mode: 'none',
    tax_rate_bps: '' as number | '',
    tax_minor_override: '' as number | '',
    reason: '',
    notes: '',
    lock_version: 1,
  });

  const selectedInvoice = postedCustomerInvoices.find((inv) => inv.id === data.customer_invoice_id);

  const getProductName = (prod?: { code: string; name: { en?: string; ar?: string } | string } | null): string => {
    if (!prod) return '';
    if (typeof prod.name === 'string') return prod.name;
    return locale === 'ar' ? prod.name?.ar || prod.name?.en || '' : prod.name?.en || prod.name?.ar || '';
  };

  const handleCustomerSelect = (customerId: string) => {
    setData('customer_id', customerId);
    setData('customer_invoice_id', '');
    setData('sales_return_id', '');
  };

  const addLine = () => {
    setLineItems((prev) => [
      ...prev,
      { customer_invoice_line_id: null, description: '', quantity: 1, unit_price_minor: '' },
    ]);
  };

  const updateLineItem = <K extends keyof CreditLineForm>(index: number, field: K, value: CreditLineForm[K]) => {
    setLineItems((prev) => {
      const next = [...prev];
      const item = { ...next[index], [field]: value };
      if (field === 'customer_invoice_line_id' && selectedInvoice) {
        const invLine = selectedInvoice.lines.find((l) => l.id === String(value ?? ''));
        if (invLine) {
          item.description = item.description || invLine.description || '';
        }
      }
      next[index] = item;
      return next;
    });
  };

  const removeLine = (index: number) => {
    setLineItems((prev) => prev.filter((_, i) => i !== index));
  };

  const openCreateModal = () => {
    reset();
    setEditingNote(null);
    setLineItems([{ customer_invoice_line_id: null, description: '', quantity: 1, unit_price_minor: '' }]);
    setShowModal(true);
  };

  const openEditModal = (note: CreditNoteRow) => {
    setEditingNote(note);
    setData({
      customer_id: note.customer_id,
      customer_invoice_id: note.customer_invoice_id || '',
      sales_return_id: note.sales_return_id || '',
      credit_date: note.credit_date,
      currency: note.currency,
      tax_mode: note.tax_mode || 'none',
      tax_rate_bps: note.tax_mode === 'manual_rate' ? note.tax_rate_bps : '',
      tax_minor_override: note.tax_mode === 'manual_amount' ? note.tax_minor_override ?? note.tax_minor : '',
      reason: note.reason || '',
      notes: note.notes || '',
      lock_version: note.lock_version,
    });
    setLineItems(
      (note.lines || []).map((l) => ({
        customer_invoice_line_id: l.customer_invoice_line_id || null,
        description: l.description || '',
        quantity: (l.quantity_e6 || 0) / 1000000,
        unit_price_minor: l.unit_price_minor,
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

  const lineSubtotalMinor = (item: CreditLineForm) =>
    Math.floor((Math.round(Number(item.quantity || 0) * 1000000) * Number(item.unit_price_minor || 0)) / 1000000);

  const previewSubtotalMinor = lineItems.reduce((acc, item) => acc + lineSubtotalMinor(item), 0);

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();

    const formattedLines = lineItems.map((item) => ({
      customer_invoice_line_id: item.customer_invoice_line_id || null,
      description: item.description,
      quantity_e6: Math.round(Number(item.quantity || 0) * 1000000) || null,
      unit_price_minor: Number(item.unit_price_minor || 0),
      tax_rate_bps: null,
    }));

    const payload = {
      ...data,
      customer_invoice_id: data.customer_invoice_id || null,
      sales_return_id: data.sales_return_id || null,
      tax_rate_bps: data.tax_mode === 'manual_rate' && data.tax_rate_bps !== '' ? data.tax_rate_bps : null,
      tax_minor_override:
        data.tax_mode === 'manual_amount' && data.tax_minor_override !== '' ? data.tax_minor_override : null,
      lines: formattedLines,
    };

    if (editingNote) {
      router.put(`/sales/credit-notes/${editingNote.id}`, payload, {
        onSuccess: () => closeModal(),
      });
    } else {
      router.post('/sales/credit-notes', payload, {
        onSuccess: () => closeModal(),
      });
    }
  };

  const handleAction = (note: CreditNoteRow, action: 'submit' | 'approve' | 'post' | 'cancel') => {
    let confirmMsg = '';
    if (action === 'submit') confirmMsg = dict.app.pages.salesCustomerCreditNotes.submitThisCreditNote;
    if (action === 'approve') confirmMsg = dict.app.pages.salesCustomerCreditNotes.approveThisCreditNote;
    if (action === 'post') confirmMsg = dict.app.pages.salesCustomerCreditNotes.postThisCreditNoteToArGl;
    if (action === 'cancel') confirmMsg = dict.app.pages.salesCustomerCreditNotes.cancelThisCreditNote;

    if (confirm(confirmMsg)) {
      router.post(`/sales/credit-notes/${note.id}/${action}`);
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
        return dict.app.pages.salesCustomerCreditNotes.draft;
      case 'submitted':
        return dict.app.pages.salesCustomerCreditNotes.submitted;
      case 'approved':
        return dict.app.pages.salesCustomerCreditNotes.approved;
      case 'posted':
        return dict.app.pages.salesCustomerCreditNotes.posted;
      case 'cancelled':
        return dict.app.pages.salesCustomerCreditNotes.cancelled;
      default:
        return status;
    }
  };

  return (
    <AppLayout active="customer-credit-notes.index">
      <Head title={dict.app.pages.salesCustomerCreditNotes.customerCreditNotes} />

      <PageHeader
        title={dict.app.pages.salesCustomerCreditNotes.customerCreditNotes_2}
        description={dict.app.pages.salesCustomerCreditNotes.manageCustomerCreditNotesAndAdjustAr}
        actions={
          can('sales.create') ? (
            <button
              type="button"
              onClick={openCreateModal}
              className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-semibold text-white shadow-md hover:bg-blue-700 transition-all"
            >
              <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              <span>{dict.app.pages.salesCustomerCreditNotes.createCustomerCreditNote}</span>
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
              placeholder={dict.app.pages.salesCustomerCreditNotes.searchNumberReasonOrCustomer}
              defaultValue={filters.search || ''}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  const val = (e.target as HTMLInputElement).value;
                  router.get('/sales/credit-notes', { ...filters, search: val }, { preserveState: true });
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
              onChange={(e) => router.get('/sales/credit-notes', { ...filters, status: e.target.value }, { preserveState: true })}
              className="rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
            >
              <option value="">{dict.app.pages.salesCustomerCreditNotes.allStatuses}</option>
              <option value="draft">{dict.app.pages.salesCustomerCreditNotes.draft}</option>
              <option value="submitted">{dict.app.pages.salesCustomerCreditNotes.submitted}</option>
              <option value="approved">{dict.app.pages.salesCustomerCreditNotes.approved}</option>
              <option value="posted">{dict.app.pages.salesCustomerCreditNotes.posted}</option>
              <option value="cancelled">{dict.app.pages.salesCustomerCreditNotes.cancelled}</option>
            </select>
          </div>
        </div>

        {customerCreditNotes.data.length === 0 ? (
          <EmptyState
            title={dict.app.pages.salesCustomerCreditNotes.noCustomerCreditNotesFound}
            description={dict.app.pages.salesCustomerCreditNotes.createACreditNoteToAdjustCustomerBalances}
          />
        ) : (
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{dict.app.pages.salesCustomerCreditNotes.creditNote}</th>
                  <th className={tableClasses.th}>{dict.app.pages.salesCustomerCreditNotes.customer}</th>
                  <th className={tableClasses.th}>{dict.app.pages.salesCustomerCreditNotes.invoice_2}</th>
                  <th className={tableClasses.th}>{dict.app.pages.salesCustomerCreditNotes.salesReturn}</th>
                  <th className={tableClasses.th}>{dict.app.pages.salesCustomerCreditNotes.creditDate}</th>
                  <th className={tableClasses.th}>{dict.app.pages.salesCustomerCreditNotes.totalAmount}</th>
                  <th className={tableClasses.th}>{dict.app.pages.salesCustomerCreditNotes.status}</th>
                  <th className={`${tableClasses.th} text-end`}>{dict.app.pages.salesCustomerCreditNotes.actions}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border)]">
                {customerCreditNotes.data.map((note) => (
                  <tr key={note.id}>
                    <td className={`${tableClasses.td} font-mono font-bold text-blue-600`}>
                      {note.number || dict.app.pages.salesCustomerCreditNotes.draft_2}
                    </td>
                    <td className={`${tableClasses.td} font-medium`}>{note.customer?.name || accDict.notAvailable}</td>
                    <td className={`${tableClasses.td} font-mono`}>{note.customerInvoice?.number || accDict.notAvailable}</td>
                    <td className={`${tableClasses.td} font-mono`}>{note.salesReturn?.number || accDict.notAvailable}</td>
                    <td className={tableClasses.td}>{note.credit_date}</td>
                    <td className={`${tableClasses.td} font-mono font-semibold`}>
                      {formatMoney(note.total_minor, note.currency)}
                    </td>
                    <td className={tableClasses.td}>
                      <StatusBadge tone={getStatusTone(note.status)}>
                        {getStatusLabel(note.status)}
                      </StatusBadge>
                    </td>
                    <td className={`${tableClasses.td} text-end space-x-2 rtl:space-x-reverse`}>
                      {note.status === 'draft' && can('sales.credit_notes') ? (
                        <button
                          type="button"
                          onClick={() => openEditModal(note)}
                          className="text-xs font-semibold text-blue-600 hover:text-blue-800"
                        >
                          {dict.app.pages.salesCustomerCreditNotes.edit}
                        </button>
                      ) : null}

                      {note.status === 'draft' && can('sales.credit_notes') ? (
                        <button
                          type="button"
                          onClick={() => handleAction(note, 'submit')}
                          className="text-xs font-semibold text-indigo-600 hover:text-indigo-800"
                        >
                          {dict.app.pages.salesCustomerCreditNotes.submit}
                        </button>
                      ) : null}

                      {['draft', 'submitted'].includes(note.status) && can('sales.credit_notes') ? (
                        <button
                          type="button"
                          onClick={() => handleAction(note, 'approve')}
                          className="text-xs font-semibold text-amber-600 hover:text-amber-800"
                        >
                          {dict.app.pages.salesCustomerCreditNotes.approve}
                        </button>
                      ) : null}

                      {note.status === 'approved' && can('sales.credit_notes') && can('view_financials') ? (
                        <button
                          type="button"
                          onClick={() => handleAction(note, 'post')}
                          className="text-xs font-semibold text-emerald-600 hover:text-emerald-800"
                        >
                          {dict.app.pages.salesCustomerCreditNotes.postToArGl}
                        </button>
                      ) : null}

                      {note.status === 'posted' && note.receivable_entry_id && can('sales.credit_notes') ? (
                        <Link
                          href={`/sales/receivable-settlements?customer_id=${note.customer_id}&source_entry_id=${note.receivable_entry_id}`}
                          className="text-xs font-bold text-purple-600 hover:text-purple-800"
                        >
                          {dict.app.pages.salesCustomerCreditNotes.settle}
                        </Link>
                      ) : null}

                      {['draft', 'submitted', 'approved'].includes(note.status) && can('sales.credit_notes') ? (
                        <button
                          type="button"
                          onClick={() => handleAction(note, 'cancel')}
                          className="text-xs font-semibold text-red-600 hover:text-red-800"
                        >
                          {dict.app.pages.salesCustomerCreditNotes.cancel}
                        </button>
                      ) : null}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>

      {showModal ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-xs overflow-y-auto">
          <div className="w-full max-w-4xl rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-2xl my-8">
            <h3 className="text-base font-bold text-[var(--text-primary)] mb-4">
              {editingNote ? dict.app.pages.salesCustomerCreditNotes.editCustomerCreditNote : dict.app.pages.salesCustomerCreditNotes.createCustomerCreditNote_2}
            </h3>

            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">{dict.app.pages.salesCustomerCreditNotes.customer_2} *</label>
                  <select
                    disabled={Boolean(editingNote)}
                    value={data.customer_id}
                    onChange={(e) => handleCustomerSelect(e.target.value)}
                    required
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none disabled:opacity-50"
                  >
                    <option value="">{dict.app.pages.salesCustomerCreditNotes.selectCustomer}</option>
                    {activeCustomers.map((c) => (
                      <option key={c.id} value={c.id}>
                        {c.name} ({c.code})
                      </option>
                    ))}
                  </select>
                  {errors.customer_id ? <p className="mt-1 text-[10px] text-red-500">{errors.customer_id}</p> : null}
                </div>

                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">{dict.app.pages.salesCustomerCreditNotes.linkedPostedInvoice}</label>
                  <select
                    disabled={Boolean(editingNote)}
                    value={data.customer_invoice_id}
                    onChange={(e) => setData('customer_invoice_id', e.target.value)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none disabled:opacity-50"
                  >
                    <option value="">{dict.app.pages.salesCustomerCreditNotes.none}</option>
                    {postedCustomerInvoices
                      .filter((inv) => !data.customer_id || inv.customer_id === data.customer_id)
                      .map((inv) => (
                        <option key={inv.id} value={inv.id}>
                          {inv.number || dict.app.pages.salesCustomerCreditNotes.draft_2} - {inv.currency}
                        </option>
                      ))}
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">{dict.app.pages.salesCustomerCreditNotes.linkedSalesReturn}</label>
                  <select
                    disabled={Boolean(editingNote)}
                    value={data.sales_return_id}
                    onChange={(e) => setData('sales_return_id', e.target.value)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none disabled:opacity-50"
                  >
                    <option value="">{dict.app.pages.salesCustomerCreditNotes.none}</option>
                    {postedSalesReturns
                      .filter((ret) => !data.customer_id || ret.customer_id === data.customer_id)
                      .map((ret) => (
                        <option key={ret.id} value={ret.id}>
                          {ret.number || dict.app.pages.salesCustomerCreditNotes.draft_2}
                        </option>
                      ))}
                  </select>
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">{dict.app.pages.salesCustomerCreditNotes.creditDate_2} *</label>
                    <input
                      type="date"
                      value={data.credit_date}
                      onChange={(e) => setData('credit_date', e.target.value)}
                      required
                      className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
                    />
                    {errors.credit_date ? <p className="mt-1 text-[10px] text-red-500">{errors.credit_date}</p> : null}
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">{dict.app.pages.salesCustomerCreditNotes.currency} *</label>
                    <input
                      type="text"
                      disabled={Boolean(selectedInvoice)}
                      value={data.currency}
                      onChange={(e) => setData('currency', e.target.value.toUpperCase())}
                      maxLength={3}
                      required
                      className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono uppercase focus:border-blue-500 focus:outline-none disabled:opacity-50"
                    />
                  </div>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">{dict.app.pages.salesCustomerCreditNotes.taxMode}</label>
                  <select
                    value={data.tax_mode}
                    onChange={(e) => setData('tax_mode', e.target.value)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
                  >
                    <option value="none">{dict.app.pages.salesCustomerCreditNotes.taxNone}</option>
                    <option value="manual_rate">{dict.app.pages.salesCustomerCreditNotes.manualRate}</option>
                    <option value="manual_amount">{dict.app.pages.salesCustomerCreditNotes.manualAmount}</option>
                  </select>
                </div>

                {data.tax_mode === 'manual_rate' ? (
                  <div>
                    <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">{dict.app.pages.salesCustomerCreditNotes.taxRateBps}</label>
                    <input
                      type="number"
                      step="1"
                      min="0"
                      value={data.tax_rate_bps}
                      onChange={(e) => setData('tax_rate_bps', e.target.value === '' ? '' : parseInt(e.target.value, 10))}
                      className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono focus:border-blue-500 focus:outline-none"
                    />
                    <p className="mt-1 text-[10px] text-[var(--text-muted)]">{dict.app.pages.salesCustomerCreditNotes.taxRateBpsHint}</p>
                  </div>
                ) : null}

                {data.tax_mode === 'manual_amount' ? (
                  <div>
                    <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">{dict.app.pages.salesCustomerCreditNotes.taxOverrideAmountMinor}</label>
                    <input
                      type="number"
                      step="1"
                      min="0"
                      value={data.tax_minor_override}
                      onChange={(e) => setData('tax_minor_override', e.target.value === '' ? '' : parseInt(e.target.value, 10))}
                      className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono focus:border-blue-500 focus:outline-none"
                    />
                  </div>
                ) : null}
              </div>

              <div className="pt-4 border-t border-[var(--border)]">
                <div className="flex items-center justify-between mb-3">
                  <h4 className="text-xs font-bold uppercase tracking-wider text-[var(--text-primary)]">{dict.app.pages.salesCustomerCreditNotes.creditNoteLines}</h4>
                  <button type="button" onClick={addLine} className="text-xs font-semibold text-blue-600 hover:text-blue-800">
                    + {dict.app.pages.salesCustomerCreditNotes.addLine}
                  </button>
                </div>

                <div className="space-y-3">
                  {lineItems.map((item, idx) => (
                    <div key={idx} className="flex flex-col lg:flex-row items-start lg:items-end gap-2 p-3 rounded-xl border border-[var(--border)] bg-[var(--background)]/50">
                      {selectedInvoice ? (
                        <div className="w-full lg:w-56">
                          <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">{dict.app.pages.salesCustomerCreditNotes.invoiceLine}</label>
                          <select
                            value={item.customer_invoice_line_id || ''}
                            onChange={(e) => updateLineItem(idx, 'customer_invoice_line_id', e.target.value || null)}
                            className="w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                          >
                            <option value="">{dict.app.pages.salesCustomerCreditNotes.selectInvoiceLine}</option>
                            {selectedInvoice.lines.map((l) => (
                              <option key={l.id} value={l.id}>
                                {(l.description || '').slice(0, 40)} ({(l.quantity_e6 || 0) / 1000000})
                              </option>
                            ))}
                          </select>
                        </div>
                      ) : null}

                      <div className="flex-1 w-full min-w-0">
                        <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">{dict.app.pages.salesCustomerCreditNotes.description} *</label>
                        <input
                          type="text"
                          value={item.description}
                          onChange={(e) => updateLineItem(idx, 'description', e.target.value)}
                          required
                          className="w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                        />
                      </div>

                      <div className="w-full lg:w-28">
                        <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">{dict.app.pages.salesCustomerCreditNotes.qty}</label>
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
                        <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">{dict.app.pages.salesCustomerCreditNotes.unitPriceMinor}</label>
                        <input
                          type="number"
                          step="1"
                          min="0"
                          value={item.unit_price_minor}
                          onChange={(e) =>
                            updateLineItem(idx, 'unit_price_minor', e.target.value === '' ? '' : parseInt(e.target.value, 10))
                          }
                          required
                          className="w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-2 py-1.5 text-xs focus:border-blue-500 focus:outline-none font-mono"
                        />
                        <p className="mt-1 text-[10px] text-[var(--text-muted)]">{dict.app.pages.salesCustomerCreditNotes.unitPriceMinorHint}</p>
                      </div>

                      <div className="w-full lg:w-32 text-end">
                        <label className="block text-[10px] font-semibold text-[var(--text-muted)] mb-1">{dict.app.pages.salesCustomerCreditNotes.subtotal}</label>
                        <span className="block px-2 py-1.5 text-xs font-mono font-semibold text-[var(--text-primary)]">
                          {formatMoney(lineSubtotalMinor(item), data.currency)}
                        </span>
                      </div>

                      {lineItems.length > 1 ? (
                        <button
                          type="button"
                          onClick={() => removeLine(idx)}
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
                    <span className="text-xs font-semibold text-[var(--text-secondary)] me-2">{dict.app.pages.salesCustomerCreditNotes.subtotalTotal}</span>
                    <span className="text-sm font-bold font-mono text-blue-600">
                      {formatMoney(previewSubtotalMinor, data.currency)}
                    </span>
                  </div>
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">{dict.app.pages.salesCustomerCreditNotes.reason}</label>
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
                  className="rounded-xl border border-[var(--border)] px-4 py-2 text-xs font-semibold text-[var(--text-secondary)] hover:bg-[var(--background)]"
                >
                  {dict.app.pages.salesCustomerCreditNotes.cancel_2}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  className="rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                >
                  {processing ? dict.app.pages.salesCustomerCreditNotes.saving : dict.app.pages.salesCustomerCreditNotes.saveDraft}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
