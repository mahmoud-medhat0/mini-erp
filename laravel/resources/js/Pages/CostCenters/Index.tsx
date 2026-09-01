import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import { Button, Card, EmptyState, Modal, PageHeader, PaginationControls, SearchableSelect, StatusBadge, tableClasses, ToggleSwitch } from '../../Components/Primitives';
import { getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { CostCenterCategory, CostCenterRow, PaginationLink, SharedPageProps } from '../../Types';

type PaginatedData<T> = {
  data: T[];
  total: number;
  links: PaginationLink[];
};

type Props = SharedPageProps & {
  costCenters: PaginatedData<CostCenterRow>;
  filters: {
    search?: string;
    category?: string;
    status?: string;
  };
};

export default function CostCentersIndex({ locale, costCenters, filters }: Props) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.costCenters;
  const accDict = dict.app.accounting;
  const auditDict = dict.app.audit;
  const can = useCan();

  const [showModal, setShowModal] = useState(false);
  const [editingCostCenter, setEditingCostCenter] = useState<CostCenterRow | null>(null);
  const [search, setSearch] = useState(filters.search || '');

  const form = useForm({
    code: '',
    name: { en: '', ar: '' },
    description: '',
    category: '' as CostCenterCategory,
    is_active: true,
    lock_version: 1,
  });

  const categoryOptions = useMemo(
    () => [
      { value: 'administrative' as const, label: pageDict.categoryAdministrative },
      { value: 'sales' as const, label: pageDict.categorySales },
      { value: 'operations' as const, label: pageDict.categoryOperations },
      { value: 'finance' as const, label: pageDict.categoryFinance },
      { value: 'other' as const, label: pageDict.categoryOther },
    ],
    [
      pageDict.categoryAdministrative,
      pageDict.categoryFinance,
      pageDict.categoryOperations,
      pageDict.categoryOther,
      pageDict.categorySales,
    ],
  );

  const categoryFilterOptions = useMemo(
    () => [
      { value: '', label: pageDict.allCategories },
      ...categoryOptions,
    ],
    [categoryOptions, pageDict.allCategories],
  );

  const statusFilterOptions = useMemo(
    () => [
      { value: '', label: pageDict.allStatuses },
      { value: 'active', label: pageDict.active },
      { value: 'inactive', label: pageDict.inactive },
    ],
    [pageDict.active, pageDict.allStatuses, pageDict.inactive],
  );

  const activeFilterCount = [search, filters.category, filters.status].filter(Boolean).length;

  function applyFilters(overrides: Partial<typeof filters> = {}) {
    const current = {
      search,
      category: filters.category || '',
      status: filters.status || '',
      ...overrides,
    };
    router.get('/cost-centers', current, { preserveScroll: true, preserveState: true });
  }

  function clearFilters() {
    setSearch('');
    router.get('/cost-centers', {}, { preserveScroll: true, preserveState: true });
  }

  function openCreateModal() {
    setEditingCostCenter(null);
    form.setData({
      code: '',
      name: { en: '', ar: '' },
      description: '',
      category: null,
      is_active: true,
      lock_version: 1,
    });
    form.clearErrors();
    setShowModal(true);
  }

  function openEditModal(costCenter: CostCenterRow) {
    setEditingCostCenter(costCenter);
    form.setData({
      code: costCenter.code,
      name: {
        en: typeof costCenter.name === 'object' && costCenter.name ? costCenter.name.en || '' : String(costCenter.name || ''),
        ar: typeof costCenter.name === 'object' && costCenter.name ? costCenter.name.ar || '' : String(costCenter.name || ''),
      },
      description: costCenter.description || '',
      category: costCenter.category || null,
      is_active: costCenter.is_active,
      lock_version: costCenter.lock_version,
    });
    form.clearErrors();
    setShowModal(true);
  }

  function handleSubmit(event: FormEvent) {
    event.preventDefault();

    if (editingCostCenter) {
      form.patch(`/cost-centers/${editingCostCenter.id}`, {
        preserveScroll: true,
        onSuccess: () => setShowModal(false),
      });
      return;
    }

    form.post('/cost-centers', {
      preserveScroll: true,
      onSuccess: () => setShowModal(false),
    });
  }

  function handleDelete(costCenter: CostCenterRow) {
    const costCenterName = getLocalizedName(costCenter.name, locale) || costCenter.code;
    if (window.confirm(pageDict.confirmDeleteCostCenter.replace('{name}', costCenterName))) {
      router.delete(`/cost-centers/${costCenter.id}`, { preserveScroll: true });
    }
  }

  function getCategoryLabel(category: CostCenterCategory): string {
    switch (category) {
      case 'administrative':
        return pageDict.categoryAdministrative;
      case 'sales':
        return pageDict.categorySales;
      case 'operations':
        return pageDict.categoryOperations;
      case 'finance':
        return pageDict.categoryFinance;
      case 'other':
        return pageDict.categoryOther;
      default:
        return pageDict.noCategory;
    }
  }

  return (
    <AppLayout active="cost-centers.index" pagination="manual">
      <Head title={pageDict.headTitle} />

      <PageHeader
        title={pageDict.title}
        description={pageDict.description}
        actions={
          can('costCenters.create') ? (
            <Button
              onClick={openCreateModal}
              title={pageDict.createCostCenter}
              aria-label={pageDict.createCostCenter}
            >
              {pageDict.createCostCenter}
            </Button>
          ) : null
        }
      />

      <Card className="p-4 mb-6">
        <div className="flex flex-wrap items-center gap-3">
          <input
            type="text"
            placeholder={pageDict.search}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                applyFilters({ search });
              }
            }}
            className="w-72 rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2 text-xs text-[var(--text-primary)] outline-hidden focus:border-[var(--primary)]"
          />
          <SearchableSelect
            options={categoryFilterOptions}
            value={filters.category || ''}
            onChange={(value) => applyFilters({ category: value || '' })}
            className="w-48"
            isSearchable={false}
          />
          <SearchableSelect
            options={statusFilterOptions}
            value={filters.status || ''}
            onChange={(value) => applyFilters({ status: value || '' })}
            className="w-44"
            isSearchable={false}
          />
          <Button
            variant="secondary"
            onClick={clearFilters}
            disabled={activeFilterCount === 0}
            title={pageDict.clearFilter}
            aria-label={pageDict.clearFilter}
          >
            {pageDict.clearFilter}
          </Button>
        </div>
      </Card>

      {costCenters.data.length === 0 ? (
        <EmptyState
          title={pageDict.noCostCenters}
          description={pageDict.noCostCentersDescription}
        />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{pageDict.code}</th>
                <th className={tableClasses.th}>{pageDict.nameEn}</th>
                <th className={tableClasses.th}>{pageDict.descriptionLabel}</th>
                <th className={tableClasses.th}>{pageDict.category}</th>
                <th className={tableClasses.th}>{pageDict.active}</th>
                <th className={tableClasses.th}>{pageDict.actions}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[var(--border)]">
              {costCenters.data.map((costCenter) => (
                <tr key={costCenter.id} className="hover:bg-[var(--background)]/60 transition-colors">
                  <td className={`${tableClasses.td} font-mono text-xs font-bold`}>{costCenter.code}</td>
                  <td className={`${tableClasses.td} font-semibold`}>{getLocalizedName(costCenter.name, locale)}</td>
                  <td className={`${tableClasses.td} text-xs text-[var(--text-secondary)] max-w-xs truncate`}>
                    {costCenter.description || accDict.notAvailable}
                  </td>
                  <td className={tableClasses.td}>
                    {costCenter.category ? (
                      <span className="font-medium text-xs text-[var(--text-primary)]">
                        {getCategoryLabel(costCenter.category)}
                      </span>
                    ) : (
                      <span className="text-xs text-[var(--text-muted)]">
                        {pageDict.noCategory}
                      </span>
                    )}
                  </td>
                  <td className={tableClasses.td}>
                    <StatusBadge tone={costCenter.is_active ? 'ok' : 'muted'}>
                      {costCenter.is_active ? pageDict.active : pageDict.inactive}
                    </StatusBadge>
                  </td>
                  <td className={tableClasses.td}>
                    <div className="flex flex-wrap items-center gap-3">
                      {can('costCenters.edit') ? (
                        <button
                          type="button"
                          onClick={() => openEditModal(costCenter)}
                          title={pageDict.edit}
                          aria-label={pageDict.edit}
                          className="text-xs font-bold text-[var(--primary)] hover:underline cursor-pointer"
                        >
                          {pageDict.edit}
                        </button>
                      ) : null}
                      {can('costCenters.delete') ? (
                        <button
                          type="button"
                          onClick={() => handleDelete(costCenter)}
                          title={pageDict.delete}
                          aria-label={pageDict.delete}
                          className="text-xs font-bold text-red-500 hover:underline cursor-pointer"
                        >
                          {pageDict.delete}
                        </button>
                      ) : null}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Pagination Controls */}
      <PaginationControls
        links={costCenters.links}
        total={costCenters.total}
        totalLabel={auditDict.totalRecords}
      />

      {/* Create / Edit Modal */}
      <Modal
        isOpen={showModal}
        onClose={() => setShowModal(false)}
        title={editingCostCenter ? pageDict.editCostCenter : pageDict.createCostCenter}
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {pageDict.code} *
              </label>
              <input
                type="text"
                value={form.data.code}
                onChange={(e) => form.setData('code', e.target.value.toUpperCase())}
                required
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono uppercase text-[var(--text-primary)]"
              />
              {form.errors.code ? <p className="text-xs text-red-500 mt-1">{form.errors.code}</p> : null}
            </div>

            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {pageDict.category}
              </label>
              <SearchableSelect<string>
                options={categoryOptions}
                value={form.data.category || ''}
                onChange={(val) => form.setData('category', (val || null) as CostCenterCategory)}
                isClearable
                isSearchable={false}
              />
              {form.errors.category ? <p className="text-xs text-red-500 mt-1">{form.errors.category}</p> : null}
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {pageDict.nameEn} *
              </label>
              <input
                type="text"
                value={form.data.name.en}
                onChange={(e) => form.setData('name', { ...form.data.name, en: e.target.value })}
                required
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)] font-semibold"
              />
              {form.errors['name.en'] ? <p className="text-xs text-red-500 mt-1">{form.errors['name.en']}</p> : null}
            </div>

            <div>
              <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                {pageDict.nameAr}
              </label>
              <input
                type="text"
                value={form.data.name.ar}
                onChange={(e) => form.setData('name', { ...form.data.name, ar: e.target.value })}
                dir="rtl"
                className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)]"
              />
              {form.errors['name.ar'] ? <p className="text-xs text-red-500 mt-1">{form.errors['name.ar']}</p> : null}
            </div>
          </div>

          <div>
            <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
              {pageDict.descriptionLabel}
            </label>
            <textarea
              value={form.data.description}
              onChange={(e) => form.setData('description', e.target.value)}
              placeholder={pageDict.descriptionPlaceholder}
              rows={3}
              className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] p-3 text-xs text-[var(--text-primary)]"
            />
            {form.errors.description ? <p className="text-xs text-red-500 mt-1">{form.errors.description}</p> : null}
          </div>

          <div className="pt-2">
            <ToggleSwitch
              checked={form.data.is_active}
              onChange={(val) => form.setData('is_active', val)}
              label={pageDict.active}
            />
          </div>

          <div className="flex justify-end gap-2 pt-4 border-t border-[var(--border)]">
            <Button
              type="button"
              variant="secondary"
              onClick={() => setShowModal(false)}
              title={pageDict.cancel}
              aria-label={pageDict.cancel}
            >
              {pageDict.cancel}
            </Button>
            <Button
              type="submit"
              disabled={form.processing}
              title={pageDict.save}
              aria-label={pageDict.save}
            >
              {pageDict.save}
            </Button>
          </div>
        </form>
      </Modal>
    </AppLayout>
  );
}
