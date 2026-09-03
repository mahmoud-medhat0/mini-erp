import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent, type ReactElement } from 'react';
import AppLayout from '../../Components/AppLayout';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { Card, PageHeader, SearchableSelect, StatusBadge } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import { getLocalizedName } from '../../lib/accountingHelpers';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';

type CustomerStatus = 'active' | 'inactive';

type CustomerRow = {
  id: string;
  code: string;
  name: Record<string, string> | string;
  status: CustomerStatus;
  email?: string | null;
  phone?: string | null;
  address?: string | null;
  tax_number?: string | null;
  lock_version: number;
};

type CustomersProps = SharedPageProps & {
  filters: { search?: string; status?: string };
};

function toCustomerStatus(v: string): CustomerStatus {
  return v === 'inactive' ? 'inactive' : 'active';
}

export default function CustomersIndex({ locale, filters }: CustomersProps) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.customers;
  const accDict = dict.app.accounting;
  const can = useCan();

  const [showModal, setShowModal]       = useState(false);
  const [editingCustomer, setEditing]   = useState<CustomerRow | null>(null);
  const [statusFilter, setStatusFilter] = useState(filters.status || '');

  const activeFilterCount = [filters.search, filters.status].filter(Boolean).length;

  function clearFilters() {
    setStatusFilter('');
    router.get('/customers', {}, { preserveScroll: true, preserveState: true });
  }

  // ── form ──────────────────────────────────────────────────────────────────
  const { data, setData, post, patch, transform, processing, errors, reset } = useForm<{
    code: string; name_en: string; name_ar: string; status: string;
    email: string; phone: string; address: string; tax_number: string; lock_version: number;
  }>({ code: '', name_en: '', name_ar: '', status: 'active', email: '', phone: '', address: '', tax_number: '', lock_version: 0 });

  const openCreate = () => { setEditing(null); reset(); setShowModal(true); };

  const openEdit = (row: CustomerRow) => {
    setEditing(row);
    const raw   = row.name;
    const nameEn = typeof raw === 'object' && raw ? (raw as Record<string,string>)['en'] ?? '' : typeof raw === 'string' ? raw : '';
    const nameAr = typeof raw === 'object' && raw ? (raw as Record<string,string>)['ar'] ?? '' : '';
    setData({ code: row.code, name_en: nameEn, name_ar: nameAr, status: row.status,
      email: row.email || '', phone: row.phone || '', address: (row as any).address || '',
      tax_number: row.tax_number || '', lock_version: row.lock_version });
    setShowModal(true);
  };

  const submit = (e: FormEvent) => {
    e.preventDefault();
    transform((d) => ({ ...d, name: JSON.stringify({ en: d.name_en, ar: d.name_ar || d.name_en }) } as any));
    if (editingCustomer) {
      patch(`/customers/${editingCustomer.id}`, { preserveScroll: true, onSuccess: () => { setShowModal(false); reset(); } });
    } else {
      post('/customers', { preserveScroll: true, onSuccess: () => { setShowModal(false); reset(); } });
    }
  };

  const statusOptions = [
    { value: 'active',   label: pageDict.active },
    { value: 'inactive', label: pageDict.inactive },
  ];

  // ── DataTables columns ────────────────────────────────────────────────────
  const columns = useMemo(() => [
    { data: 'code',       name: 'code',       title: pageDict.code,      className: 'font-mono font-bold text-xs', width: '120px' },
    { data: 'name',       name: 'name',       title: pageDict.name },
    { data: 'phone',      name: 'phone',      title: pageDict.phone },
    { data: 'email',      name: 'email',      title: pageDict.email },
    { data: 'tax_number', name: 'tax_number', title: pageDict.taxNumber },
    { data: 'status',     name: 'status',     title: pageDict.status, searchable: false, width: '90px' },
    ...(can('customers.edit') ? [{ data: 'actions', name: 'actions', title: pageDict.actions, orderable: false, searchable: false, width: '80px', className: 'text-end' }] : []),
  ], [pageDict, can]);

  // ── slot renderers ────────────────────────────────────────────────────────
  const slots = useMemo<DataTableSlots>(() => ({
    name:       (d: any)        => <span className="font-semibold">{getLocalizedName(d, locale)}</span>,
    phone:      (d: any)        => <span className="text-[var(--text-secondary)]">{d || accDict.notAvailable}</span>,
    email:      (d: any)        => <span className="text-[var(--text-secondary)]">{d || accDict.notAvailable}</span>,
    tax_number: (d: any)        => <span className="font-mono text-xs">{d || accDict.notAvailable}</span>,
    status:     (d: any)        => <StatusBadge tone={d === 'active' ? 'ok' : 'muted'}>{d === 'active' ? pageDict.active_2 : pageDict.inactive_2}</StatusBadge>,
    actions:    (_d: any, _type: any, row: any) => (
      <button type="button" onClick={() => openEdit(row as CustomerRow)}
        title={pageDict.edit} aria-label={pageDict.edit}
        className="text-xs font-bold text-[var(--primary)] hover:underline cursor-pointer">
        {pageDict.edit}
      </button>
    ),
  } as Record<string, (data: any, type: any, row: any) => ReactElement>), [pageDict, accDict, locale]);

  const tableFilters = useMemo(() => ({ status: statusFilter }), [statusFilter]);

  // ── filter toolbar ────────────────────────────────────────────────────────
  const toolbar = (
    <div className="flex items-center gap-2">
      <div className="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-[color-mix(in_srgb,var(--primary)_8%,transparent)] text-xs font-bold text-[var(--primary)] border border-[color-mix(in_srgb,var(--primary)_20%,transparent)] whitespace-nowrap shrink-0">
        <svg className="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
          <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
        </svg>
        <span>{dict.common.datatable.filterStatus}</span>
      </div>
      <SearchableSelect
        options={[{ value: '', label: pageDict.allStatuses }, ...statusOptions]}
        value={statusFilter}
        onChange={(v) => setStatusFilter(v || '')}
        className="w-44"
        isSearchable={false}
      />
      {activeFilterCount > 0 && (
        <button
          type="button"
          onClick={clearFilters}
          disabled={activeFilterCount === 0}
          title={accDict.clearFilters}
          aria-label={accDict.clearFilters}
          className="text-xs font-bold text-red-600 hover:underline"
        >
          {accDict.clearFilters}
        </button>
      )}
    </div>
  );

  return (
    <AppLayout active="customers.index">
      <Head title={dict.app.pages.customers.customersMiniErp} />

      <PageHeader
        title={dict.app.pages.customers.customerMasterData}
        description={dict.app.pages.customers.manageCustomerRecordsTaxNumbersAnd}
        actions={
          can('customers.create') ? (
            <button type="button" onClick={openCreate}
              title={pageDict.createCustomer} aria-label={pageDict.createCustomer}
              className="rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] transition-all cursor-pointer">
              {dict.app.pages.customers.createCustomer}
            </button>
          ) : null
        }
      />

      {/* ── DataTable card ── */}
      <Card className="overflow-hidden p-0">
        <ServerDataTable
          ajaxUrl="/customers/data"
          columns={columns}
          filters={tableFilters}
          locale={locale}
          order={[[0, 'asc']]}
          pageLength={25}
          slots={slots}
          tableId="customers-data-table"
          toolbar={toolbar}
        />
      </Card>

      {/* ── Modal ── */}
      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
          <div className="w-full max-w-lg rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <h2 className="text-lg font-bold text-[var(--text-primary)] mb-4">
              {editingCustomer ? pageDict.editCustomer : pageDict.createNewCustomer}
            </h2>

            <form onSubmit={submit} className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">{pageDict.code_2} *</label>
                  <input type="text" value={data.code} onChange={(e) => setData('code', e.target.value)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono text-[var(--text-primary)]" required />
                  {errors.code && <p className="text-xs text-red-500 mt-1">{errors.code}</p>}
                </div>
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">{pageDict.status_2} *</label>
                  <SearchableSelect options={statusOptions} value={data.status}
                    onChange={(v) => setData('status', toCustomerStatus(v || 'active'))}
                    isClearable={false} isSearchable={false} required />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">{pageDict.customerNameEn} *</label>
                  <input id="customer-name-en" type="text" dir="ltr" value={data.name_en}
                    onChange={(e) => setData('name_en', e.target.value)} placeholder="e.g. Al Rowad Trading"
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)] font-semibold" required />
                  {errors.name_en && <p className="text-xs text-red-500 mt-1">{errors.name_en}</p>}
                </div>
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">{pageDict.customerNameAr}</label>
                  <input id="customer-name-ar" type="text" dir="rtl" value={data.name_ar}
                    onChange={(e) => setData('name_ar', e.target.value)} placeholder="مثال: شركة الرواد"
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)] font-semibold" />
                  {errors.name_ar && <p className="text-xs text-red-500 mt-1">{errors.name_ar}</p>}
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">{pageDict.phone_2}</label>
                  <input type="text" value={data.phone} onChange={(e) => setData('phone', e.target.value)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)]" />
                </div>
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">{pageDict.email_2}</label>
                  <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)]" />
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">{pageDict.taxNumber_2}</label>
                <input type="text" value={data.tax_number} onChange={(e) => setData('tax_number', e.target.value)}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono text-[var(--text-primary)]" />
              </div>

              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">{pageDict.address}</label>
                <textarea value={data.address} onChange={(e) => setData('address', e.target.value)} rows={2}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] p-3 text-xs text-[var(--text-primary)]" />
              </div>

              <div className="flex justify-end gap-2 pt-4 border-t border-[var(--border)]">
                <button type="button" onClick={() => setShowModal(false)}
                  title={pageDict.cancel} aria-label={pageDict.cancel}
                  className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] cursor-pointer">
                  {pageDict.cancel}
                </button>
                <button type="submit" disabled={processing}
                  title={pageDict.saveCustomer} aria-label={pageDict.saveCustomer}
                  className="rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] cursor-pointer disabled:opacity-50">
                  {processing ? pageDict.saving : pageDict.saveCustomer}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </AppLayout>
  );
}
