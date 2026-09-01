import { Head, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Button, Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import { getLocalizedName } from '../../lib/accountingHelpers';
import type { PaginationLink, SharedPageProps, TranslatedName } from '../../Types';

type SupplierRow = {
  id: string;
  code: string;
  name: TranslatedName;
  status: 'active' | 'inactive';
  email?: string | null;
  phone?: string | null;
  address?: string | null;
  tax_number?: string | null;
  lock_version: number;
  created_at: string;
};

type SupplierStatus = SupplierRow['status'];

type SuppliersProps = SharedPageProps & {
  suppliers: {
    data: SupplierRow[];
    links: PaginationLink[];
  };
  filters: {
    search?: string;
    status?: string;
  };
};

function toSupplierStatus(value: string): SupplierStatus {
  return value === 'inactive' ? 'inactive' : 'active';
}

export default function SuppliersIndex({ locale, suppliers, filters }: SuppliersProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const pageDict = dict.app.pages.suppliers;
  const can = useCan();

  const [showModal, setShowModal] = useState(false);
  const [editingSupplier, setEditingSupplier] = useState<SupplierRow | null>(null);

  const { data, setData, post, patch, processing, errors, reset } = useForm({
    code: '',
    name: '',
    status: 'active',
    email: '',
    phone: '',
    address: '',
    tax_number: '',
    lock_version: 0,
  });

  const openCreateModal = () => {
    setEditingSupplier(null);
    reset();
    setShowModal(true);
  };

  const openEditModal = (supplier: SupplierRow) => {
    setEditingSupplier(supplier);
    setData({
      code: supplier.code,
      name: getLocalizedName(supplier.name, locale),
      status: supplier.status,
      email: supplier.email || '',
      phone: supplier.phone || '',
      address: supplier.address || '',
      tax_number: supplier.tax_number || '',
      lock_version: supplier.lock_version,
    });
    setShowModal(true);
  };

  const submit = (e: FormEvent) => {
    e.preventDefault();
    if (editingSupplier) {
      patch(`/suppliers/${editingSupplier.id}`, {
        preserveScroll: true,
        onSuccess: () => {
          setShowModal(false);
          reset();
        },
      });
    } else {
      post('/suppliers', {
        preserveScroll: true,
        onSuccess: () => {
          setShowModal(false);
          reset();
        },
      });
    }
  };

  const statusOptions = [
    { value: 'active', label: pageDict.active },
    { value: 'inactive', label: pageDict.inactive },
  ];
  const activeFilterCount = [filters.search, filters.status].filter(Boolean).length;

  const applyFilters = (next: Record<string, string>) => {
    const search = next.search ?? filters.search ?? '';
    const status = next.status ?? filters.status ?? '';
    const params: Record<string, string> = {};

    if (search) params.search = search;
    if (status) params.status = status;

    router.get('/suppliers', params, { preserveScroll: true, preserveState: true });
  };

  function clearFilters() {
    router.get('/suppliers', {}, { preserveScroll: true, preserveState: true });
  }

  return (
    <AppLayout active="suppliers.index">
      <Head title={dict.app.pages.suppliers.suppliersMiniErp} />

      <PageHeader
        title={dict.app.pages.suppliers.supplierMasterData}
        description={dict.app.pages.suppliers.manageSupplierRecordsTaxNumbersAnd}
        actions={
          can('suppliers.create') ? (
            <button
              type="button"
              onClick={openCreateModal}
              title={pageDict.createSupplier}
              aria-label={pageDict.createSupplier}
              className="rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] transition-all cursor-pointer"
            >
              {dict.app.pages.suppliers.createSupplier}
            </button>
          ) : null
        }
      />

      <Card className="p-4 mb-6">
        <div className="flex flex-wrap items-center gap-3">
          <input
            type="text"
            placeholder={dict.app.pages.suppliers.searchByCodeNamePhone}
            defaultValue={filters.search || ''}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                const target = e.target as HTMLInputElement;
                applyFilters({ search: target.value });
              }
            }}
            className="w-72 rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2 text-xs text-[var(--text-primary)] outline-hidden focus:border-[var(--primary)]"
          />
          <SearchableSelect
            options={[{ value: '', label: pageDict.allStatuses }, ...statusOptions]}
            value={filters.status || ''}
            onChange={(value) => applyFilters({ status: value || '' })}
            className="w-44"
            isSearchable={false}
          />
          <Button variant="secondary" onClick={clearFilters} disabled={activeFilterCount === 0}>{accDict.clearFilters}</Button>
        </div>
      </Card>

      {suppliers.data.length === 0 ? (
        <EmptyState
          title={dict.app.pages.suppliers.noSuppliersFound}
          description={dict.app.pages.suppliers.getStartedByCreatingYourFirst}
        />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{dict.app.pages.suppliers.code}</th>
                <th className={tableClasses.th}>{dict.app.pages.suppliers.name}</th>
                <th className={tableClasses.th}>{dict.app.pages.suppliers.phone}</th>
                <th className={tableClasses.th}>{dict.app.pages.suppliers.email}</th>
                <th className={tableClasses.th}>{dict.app.pages.suppliers.taxNumber}</th>
                <th className={tableClasses.th}>{dict.app.pages.suppliers.status}</th>
                <th className={tableClasses.th}>{dict.app.pages.suppliers.actions}</th>
              </tr>
            </thead>
            <tbody>
              {suppliers.data.map((supplier) => (
                <tr key={supplier.id} className="hover:bg-[var(--background)]/50 transition-colors">
                  <td className={`${tableClasses.td} font-mono font-bold text-xs`}>{supplier.code}</td>
                  <td className={`${tableClasses.td} font-semibold`}>{getLocalizedName(supplier.name, locale)}</td>
                  <td className={tableClasses.td}>{supplier.phone || accDict.notAvailable}</td>
                  <td className={tableClasses.td}>{supplier.email || accDict.notAvailable}</td>
                  <td className={`${tableClasses.td} font-mono text-xs`}>{supplier.tax_number || accDict.notAvailable}</td>
                  <td className={tableClasses.td}>
                    <StatusBadge tone={supplier.status === 'active' ? 'ok' : 'muted'}>
                      {supplier.status === 'active' ? dict.app.pages.suppliers.active_2 : dict.app.pages.suppliers.inactive_2}
                    </StatusBadge>
                  </td>
                  <td className={tableClasses.td}>
                    <div className="flex flex-wrap items-center justify-end gap-2">
                      {can('suppliers.edit') ? (
                        <button
                          type="button"
                          onClick={() => openEditModal(supplier)}
                          title={pageDict.edit}
                          aria-label={pageDict.edit}
                          className="text-xs font-bold text-[var(--primary)] hover:underline cursor-pointer"
                        >
                          {dict.app.pages.suppliers.edit}
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

      {/* Modal Form */}
      {showModal ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
          <div className="w-full max-w-lg rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <h2 className="text-lg font-bold text-[var(--text-primary)] mb-4">
              {editingSupplier ? dict.app.pages.suppliers.editSupplier : dict.app.pages.suppliers.createNewSupplier}
            </h2>

            <form onSubmit={submit} className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {dict.app.pages.suppliers.code_2} *
                  </label>
                  <input
                    type="text"
                    value={data.code}
                    onChange={(e) => setData('code', e.target.value)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono text-[var(--text-primary)]"
                    required
                  />
                  {errors.code && <p className="text-xs text-red-500 mt-1">{errors.code}</p>}
                </div>
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {dict.app.pages.suppliers.status_2} *
                  </label>
                  <SearchableSelect
                    options={statusOptions}
                    value={data.status}
                    onChange={(value) => setData('status', toSupplierStatus(value || 'active'))}
                    isClearable={false}
                    isSearchable={false}
                    required
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                  {dict.app.pages.suppliers.supplierName} *
                </label>
                <input
                  type="text"
                  value={data.name}
                  onChange={(e) => setData('name', e.target.value)}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)] font-semibold"
                  required
                />
                {errors.name && <p className="text-xs text-red-500 mt-1">{errors.name}</p>}
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {dict.app.pages.suppliers.phone_2}
                  </label>
                  <input
                    type="text"
                    value={data.phone}
                    onChange={(e) => setData('phone', e.target.value)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)]"
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {dict.app.pages.suppliers.email_2}
                  </label>
                  <input
                    type="email"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)]"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                  {dict.app.pages.suppliers.taxNumber_2}
                </label>
                <input
                  type="text"
                  value={data.tax_number}
                  onChange={(e) => setData('tax_number', e.target.value)}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono text-[var(--text-primary)]"
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                  {dict.app.pages.suppliers.address}
                </label>
                <textarea
                  value={data.address}
                  onChange={(e) => setData('address', e.target.value)}
                  rows={2}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] p-3 text-xs text-[var(--text-primary)]"
                />
              </div>

              <div className="flex justify-end gap-2 pt-4 border-t border-[var(--border)]">
                <button
                  type="button"
                  onClick={() => setShowModal(false)}
                  title={pageDict.cancel}
                  aria-label={pageDict.cancel}
                  className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] cursor-pointer"
                >
                  {dict.app.pages.suppliers.cancel}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  title={pageDict.saveSupplier}
                  aria-label={pageDict.saveSupplier}
                  className="rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] cursor-pointer disabled:opacity-50"
                >
                  {processing ? dict.app.pages.suppliers.saving : dict.app.pages.suppliers.saveSupplier}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
