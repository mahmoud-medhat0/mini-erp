import { Head, useForm, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, EmptyState, PageHeader, StatusBadge, tableClasses } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import { getLocalizedName } from '../../lib/accountingHelpers';
import { useCan } from '../../lib/permissions';
import type { PaginationLink, SharedPageProps, TranslatedName } from '../../Types';

type UnitOfMeasureRow = {
  id: string;
  code: string;
  name: TranslatedName;
  symbol: string;
  is_active: boolean;
  lock_version: number;
  created_at: string;
};

type UomsProps = SharedPageProps & {
  uoms: {
    data: UnitOfMeasureRow[];
    links: PaginationLink[];
  };
  filters: {
    search?: string;
  };
};

export default function UnitsOfMeasureIndex({ locale, uoms, filters }: UomsProps) {
  const dict = getDictionary(locale);
  const can = useCan();

  const [showModal, setShowModal] = useState(false);
  const [editingUom, setEditingUom] = useState<UnitOfMeasureRow | null>(null);

  const { data, setData, post, put, delete: destroy, processing, errors, reset } = useForm({
    code: '',
    name: '',
    symbol: '',
    is_active: true,
    lock_version: 1,
  });
  const uomSubmitLabel = processing
    ? dict.app.pages.catalogUnitsOfMeasure.saving
    : dict.app.pages.catalogUnitsOfMeasure.save;

  const openCreateModal = () => {
    reset();
    setEditingUom(null);
    setShowModal(true);
  };

  const openEditModal = (uom: UnitOfMeasureRow) => {
    setEditingUom(uom);
    setData({
      code: uom.code,
      name: getLocalizedName(uom.name, locale),
      symbol: uom.symbol,
      is_active: uom.is_active,
      lock_version: uom.lock_version,
    });
    setShowModal(true);
  };

  const closeModal = () => {
    setShowModal(false);
    setEditingUom(null);
    reset();
  };

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    if (editingUom) {
      put(`/catalog/uoms/${editingUom.id}`, {
        preserveScroll: true,
        onSuccess: () => closeModal(),
      });
    } else {
      post('/catalog/uoms', {
        preserveScroll: true,
        onSuccess: () => closeModal(),
      });
    }
  };

  const handleDelete = (uom: UnitOfMeasureRow) => {
    if (confirm(dict.app.pages.catalogUnitsOfMeasure.confirmDeleteUom.replace('{name}', getLocalizedName(uom.name, locale) || uom.code))) {
      destroy(`/catalog/uoms/${uom.id}`, { preserveScroll: true });
    }
  };

  return (
    <AppLayout active="uoms.index">
      <Head title={dict.app.pages.catalogUnitsOfMeasure.unitsOfMeasure} />

      <PageHeader
        title={dict.app.pages.catalogUnitsOfMeasure.unitsOfMeasure_2}
        description={dict.app.pages.catalogUnitsOfMeasure.manageUnitsOfMeasureForProducts}
        actions={
          can('uom.create') ? (
            <button
              type="button"
              onClick={openCreateModal}
              title={dict.app.pages.catalogUnitsOfMeasure.addUnitOfMeasure}
              aria-label={dict.app.pages.catalogUnitsOfMeasure.addUnitOfMeasure}
              className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-semibold text-white shadow-md hover:bg-blue-700 transition-all"
            >
              <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              <span>{dict.app.pages.catalogUnitsOfMeasure.addUnitOfMeasure}</span>
            </button>
          ) : null
        }
      />

      <Card className="p-6">
        <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative flex-1 max-w-md">
            <input
              type="text"
              placeholder={dict.app.pages.catalogUnitsOfMeasure.searchCodeOrName}
              defaultValue={filters.search || ''}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  const val = (e.target as HTMLInputElement).value;
                  router.get('/catalog/uoms', { search: val }, { preserveState: true, preserveScroll: true });
                }
              }}
              className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] py-2.5 ps-10 pe-4 text-xs focus:border-blue-500 focus:outline-none"
            />
            <svg className="absolute start-3 top-3 size-4 text-[var(--text-muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
        </div>

        {uoms.data.length === 0 ? (
          <EmptyState
            title={dict.app.pages.catalogUnitsOfMeasure.noUnitsOfMeasureFound}
            description={dict.app.pages.catalogUnitsOfMeasure.getStartedByCreatingYourFirst}
          />
        ) : (
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{dict.app.pages.catalogUnitsOfMeasure.code}</th>
                  <th className={tableClasses.th}>{dict.app.pages.catalogUnitsOfMeasure.name}</th>
                  <th className={tableClasses.th}>{dict.app.pages.catalogUnitsOfMeasure.symbol}</th>
                  <th className={tableClasses.th}>{dict.app.pages.catalogUnitsOfMeasure.status}</th>
                  <th className={`${tableClasses.th} text-end`}>{dict.app.pages.catalogUnitsOfMeasure.actions}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border)]">
                {uoms.data.map((uom) => (
                  <tr key={uom.id}>
                    <td className={`${tableClasses.td} font-mono font-bold text-blue-600`}>{uom.code}</td>
                    <td className={`${tableClasses.td} font-medium`}>{getLocalizedName(uom.name, locale)}</td>
                    <td className={tableClasses.td}>{uom.symbol}</td>
                    <td className={tableClasses.td}>
                      <StatusBadge tone={uom.is_active ? 'ok' : 'muted'}>
                        {uom.is_active ? dict.app.pages.catalogUnitsOfMeasure.active_2 : dict.app.pages.catalogUnitsOfMeasure.inactive}
                      </StatusBadge>
                    </td>
                    <td className={`${tableClasses.td} text-end space-x-2 rtl:space-x-reverse`}>
                      {can('uom.edit') ? (
                        <button
                          type="button"
                          onClick={() => openEditModal(uom)}
                          title={dict.app.pages.catalogUnitsOfMeasure.edit}
                          aria-label={dict.app.pages.catalogUnitsOfMeasure.edit}
                          className="text-xs font-semibold text-blue-600 hover:text-blue-800"
                        >
                          {dict.app.pages.catalogUnitsOfMeasure.edit}
                        </button>
                      ) : null}
                      {can('uom.delete') ? (
                        <button
                          type="button"
                          onClick={() => handleDelete(uom)}
                          title={dict.app.pages.catalogUnitsOfMeasure.delete}
                          aria-label={dict.app.pages.catalogUnitsOfMeasure.delete}
                          className="text-xs font-semibold text-red-600 hover:text-red-800"
                        >
                          {dict.app.pages.catalogUnitsOfMeasure.delete}
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

      {/* Create / Edit Modal */}
      {showModal ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-xs">
          <div className="w-full max-w-md rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-2xl">
            <h3 className="text-base font-bold text-[var(--text-primary)] mb-4">
              {editingUom
                ? dict.app.pages.catalogUnitsOfMeasure.editUnitOfMeasure
                : dict.app.pages.catalogUnitsOfMeasure.createUnitOfMeasure}
            </h3>

            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                  {dict.app.pages.catalogUnitsOfMeasure.code_2} *
                </label>
                <input
                  type="text"
                  value={data.code}
                  onChange={(e) => setData('code', e.target.value.toUpperCase())}
                  required
                  placeholder={dict.app.pages.catalogUnitsOfMeasure.codePlaceholder}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none uppercase font-mono"
                />
                {errors.code ? <p className="mt-1 text-[10px] text-red-500">{errors.code}</p> : null}
              </div>

              <div>
                <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                  {dict.app.pages.catalogUnitsOfMeasure.name_2} *
                </label>
                <input
                  type="text"
                  value={data.name}
                  onChange={(e) => setData('name', e.target.value)}
                  required
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
                />
                {errors.name ? <p className="mt-1 text-[10px] text-red-500">{errors.name}</p> : null}
              </div>

              <div>
                <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                  {dict.app.pages.catalogUnitsOfMeasure.symbol_2}
                </label>
                <input
                  type="text"
                  value={data.symbol}
                  onChange={(e) => setData('symbol', e.target.value)}
                  placeholder={dict.app.pages.catalogUnitsOfMeasure.symbolPlaceholder}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
                />
              </div>

              <div className="flex items-center gap-2 pt-2">
                <input
                  type="checkbox"
                  id="is_active"
                  checked={data.is_active}
                  onChange={(e) => setData('is_active', e.target.checked)}
                  className="rounded border-[var(--border)] text-blue-600 focus:ring-blue-500"
                />
                <label htmlFor="is_active" className="text-xs font-medium text-[var(--text-primary)]">
                  {dict.app.pages.catalogUnitsOfMeasure.active}
                </label>
              </div>

              <div className="flex items-center justify-end gap-3 pt-4 border-t border-[var(--border)]">
                <button
                  type="button"
                  onClick={closeModal}
                  title={dict.app.pages.catalogUnitsOfMeasure.cancel}
                  aria-label={dict.app.pages.catalogUnitsOfMeasure.cancel}
                  className="rounded-xl border border-[var(--border)] px-4 py-2 text-xs font-semibold text-[var(--text-secondary)] hover:bg-[var(--background)]"
                >
                  {dict.app.pages.catalogUnitsOfMeasure.cancel}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  title={uomSubmitLabel}
                  aria-label={uomSubmitLabel}
                  className="rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                >
                  {uomSubmitLabel}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
