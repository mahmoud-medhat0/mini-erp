import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { PageHeader, SearchableSelect, StatusBadge } from '../../Components/Primitives';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { formatAccountingAmount } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types/page';

type CategoryRow = {
  id: string;
  code: string;
  name: { en: string; ar: string } | string;
  useful_life_months: number;
  salvage_value_minor: number;
  is_active: boolean;
  fixed_assets_count?: number;
};

type CategoriesProps = SharedPageProps & {
  categories?: CategoryRow[];
  can: {
    create: boolean;
    edit: boolean;
    delete: boolean;
    view_financials: boolean;
  };
};

export default function FixedAssetCategories({ locale, can }: CategoriesProps) {
  const dict = getDictionary(locale);
  const appDict = dict.app.accounting;
  const formatAmount = (amountMinor: number) => formatAccountingAmount(amountMinor, '', { zeroAsDash: false, showCurrency: false });

  const [showModal, setShowModal] = useState(false);
  const [editingCategory, setEditingCategory] = useState<CategoryRow | null>(null);
  const [statusFilter, setStatusFilter] = useState('');
  const [reloadToken, setReloadToken] = useState(0);

  const { data, setData, post, put, transform, processing, errors, reset } = useForm({
    code: '',
    name_en: '',
    name_ar: '',
    useful_life_months: 60,
    salvage_value_minor: 0,
    is_active: true,
  });
  const categorySubmitLabel = processing ? appDict.saving : appDict.save;

  const statusOptions = useMemo(() => [
    { value: '', label: appDict.allStatuses },
    { value: 'active', label: appDict.fixedAssetStatusActive },
    { value: 'inactive', label: appDict.inactive },
  ], [appDict]);

  const extraFilters = useMemo(() => ({
    status: statusFilter,
  }), [statusFilter]);

  function openCreateModal() {
    setEditingCategory(null);
    reset();
    setShowModal(true);
  }

  function openEditModal(category: CategoryRow) {
    setEditingCategory(category);
    const nameObj = typeof category.name === 'object' && category.name !== null ? category.name : { en: String(category.name), ar: String(category.name) };
    setData({
      code: category.code,
      name_en: nameObj.en || '',
      name_ar: nameObj.ar || '',
      useful_life_months: category.useful_life_months,
      salvage_value_minor: category.salvage_value_minor,
      is_active: category.is_active,
    });
    setShowModal(true);
  }

  function handleSubmit(e: FormEvent) {
    e.preventDefault();
    transform((formData) => ({
      code: formData.code,
      name: { en: formData.name_en, ar: formData.name_ar },
      useful_life_months: formData.useful_life_months,
      salvage_value_minor: formData.salvage_value_minor,
      is_active: formData.is_active,
    }));

    if (editingCategory) {
      put(`/fixed-asset-categories/${editingCategory.id}`, {
        preserveScroll: true,
        onSuccess: () => {
          setShowModal(false);
          reset();
          setReloadToken((prev) => prev + 1);
        },
      });
    } else {
      post('/fixed-asset-categories', {
        preserveScroll: true,
        onSuccess: () => {
          setShowModal(false);
          reset();
          setReloadToken((prev) => prev + 1);
        },
      });
    }
  }

  function handleDelete(id: string) {
    if (confirm(appDict.confirmDeleteAssetCategory)) {
      router.delete(`/fixed-asset-categories/${id}`, {
        preserveScroll: true,
        onSuccess: () => {
          setReloadToken((prev) => prev + 1);
        },
      });
    }
  }

  function formatName(name: { en: string; ar: string } | string): string {
    if (typeof name === 'object' && name !== null) {
      return locale === 'ar' ? name.ar || name.en : name.en || name.ar;
    }
    return String(name);
  }

  const columns = useMemo(
    () => [
      {
        data: 'code',
        name: 'code',
        title: appDict.code,
        className: 'font-mono text-sm',
      },
      {
        data: 'name_text',
        name: 'name_text',
        title: appDict.name,
      },
      {
        data: 'useful_life_months',
        name: 'useful_life_months',
        title: appDict.usefulLifeMonths,
      },
      {
        data: 'salvage_value_minor',
        name: 'salvage_value_minor',
        title: appDict.salvageValue,
      },
      {
        data: 'is_active',
        name: 'is_active',
        title: appDict.status,
      },
      {
        data: 'id',
        name: 'id',
        title: appDict.actions,
        orderable: false,
        searchable: false,
      },
    ],
    [appDict],
  );

  const slots: DataTableSlots = useMemo(
    () => ({
      name_text: (_data: any, _type: any, row: CategoryRow) => formatName(row.name),
      salvage_value_minor: (_data: any, _type: any, row: CategoryRow) => (
        can.view_financials ? formatAmount(row.salvage_value_minor) : appDict.restrictedValue
      ),
      is_active: (_data: any, _type: any, row: CategoryRow) => (
        <StatusBadge tone={row.is_active ? 'ok' : 'muted'}>
          {row.is_active ? appDict.fixedAssetStatusActive : appDict.inactive}
        </StatusBadge>
      ),
      id: (_data: any, _type: any, row: CategoryRow) => (
        <div className="flex items-center gap-2.5">
          {can.edit && (
            <button
              type="button"
              onClick={() => openEditModal(row)}
              title={appDict.editAssetCategory}
              aria-label={appDict.editAssetCategory}
              className="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
            >
              <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
              <span>{appDict.editAssetCategory}</span>
            </button>
          )}
          {can.delete && (row.fixed_assets_count ?? 0) === 0 && (
            <button
              type="button"
              onClick={() => handleDelete(row.id)}
              title={appDict.delete}
              aria-label={appDict.delete}
              className="inline-flex items-center gap-1 text-xs font-medium text-rose-600 hover:text-rose-900 dark:text-rose-400 dark:hover:text-rose-300"
            >
              <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
              <span>{appDict.delete}</span>
            </button>
          )}
        </div>
      ),
    }),
    [locale, can, appDict],
  );

  const toolbar = (
    <div className="flex flex-wrap items-center gap-2">
      <div className="w-40 shrink-0">
        <SearchableSelect
          value={statusFilter}
          options={statusOptions}
          onChange={(val) => setStatusFilter(val || '')}
          placeholder={appDict.allStatuses}
          isClearable={false}
          isSearchable={false}
        />
      </div>
    </div>
  );

  return (
    <AppLayout active="fixed-asset-categories.index">
      <Head title={`${appDict.fixedAssetCategories} - ${appDict.appName}`} />

      <div className="space-y-6">
        <PageHeader
          title={appDict.fixedAssetCategories}
          description={appDict.fixedAssetCategories}
          actions={
            can.create ? (
              <button
                type="button"
                onClick={openCreateModal}
                title={appDict.createAssetCategory}
                aria-label={appDict.createAssetCategory}
                className="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700"
              >
                {appDict.createAssetCategory}
              </button>
            ) : null
          }
        />

        <ServerDataTable
          ajaxUrl="/fixed-asset-categories/data"
          columns={columns}
          slots={slots}
          toolbar={toolbar}
          filters={extraFilters}
          locale={locale}
          reloadToken={reloadToken}
        />
      </div>

      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50">
          <div className="w-full max-w-md p-6 bg-white rounded-lg shadow-xl dark:bg-slate-800">
            <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
              {editingCategory ? appDict.editAssetCategory : appDict.createAssetCategory}
            </h3>

            <form onSubmit={handleSubmit} className="mt-4 space-y-4">
              <div>
                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                  {appDict.code}
                </label>
                <input
                  type="text"
                  value={data.code}
                  onChange={(e) => setData('code', e.target.value)}
                  className="w-full mt-1 rounded-md border-slate-300 dark:bg-slate-900 dark:border-slate-700"
                  required
                />
                {errors.code && <p className="mt-1 text-xs text-rose-600">{errors.code}</p>}
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                  {appDict.englishName}
                </label>
                <input
                  type="text"
                  value={data.name_en}
                  onChange={(e) => setData('name_en', e.target.value)}
                  className="w-full mt-1 rounded-md border-slate-300 dark:bg-slate-900 dark:border-slate-700"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                  {appDict.arabicName}
                </label>
                <input
                  type="text"
                  value={data.name_ar}
                  onChange={(e) => setData('name_ar', e.target.value)}
                  className="w-full mt-1 rounded-md border-slate-300 dark:bg-slate-900 dark:border-slate-700"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                  {appDict.usefulLifeMonths}
                </label>
                <input
                  type="number"
                  min="1"
                  value={data.useful_life_months}
                  onChange={(e) => setData('useful_life_months', parseInt(e.target.value, 10) || 1)}
                  className="w-full mt-1 rounded-md border-slate-300 dark:bg-slate-900 dark:border-slate-700"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                  {appDict.salvageValue}
                </label>
                <input
                  type="number"
                  min="0"
                  value={data.salvage_value_minor}
                  onChange={(e) => setData('salvage_value_minor', parseInt(e.target.value, 10) || 0)}
                  className="w-full mt-1 rounded-md border-slate-300 dark:bg-slate-900 dark:border-slate-700"
                  required
                />
              </div>

              <div className="flex items-center gap-2">
                <input
                  type="checkbox"
                  id="cat_active"
                  checked={data.is_active}
                  onChange={(e) => setData('is_active', e.target.checked)}
                  className="rounded border-slate-300 text-indigo-600"
                />
                <label htmlFor="cat_active" className="text-sm font-medium text-slate-700 dark:text-slate-300">
                  {appDict.fixedAssetStatusActive}
                </label>
              </div>

              <div className="flex justify-end gap-2.5 pt-2">
                <button
                  type="button"
                  onClick={() => setShowModal(false)}
                  title={appDict.back}
                  aria-label={appDict.back}
                  className="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 rounded-md hover:bg-slate-200"
                >
                  {appDict.back}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  title={categorySubmitLabel}
                  aria-label={categorySubmitLabel}
                  className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50"
                >
                  {categorySubmitLabel}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </AppLayout>
  );
}
