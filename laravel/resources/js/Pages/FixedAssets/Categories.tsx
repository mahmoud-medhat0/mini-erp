import { Head, useForm, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, EmptyState, PageHeader, tableClasses } from '../../Components/Primitives';
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
  categories: CategoryRow[];
  can: {
    create: boolean;
    edit: boolean;
    delete: boolean;
    view_financials: boolean;
  };
};

export default function FixedAssetCategories({ locale, categories, can }: CategoriesProps) {
  const dict = getDictionary(locale);
  const appDict = dict.app.accounting;
  const formatAmount = (amountMinor: number) => formatAccountingAmount(amountMinor, '', { zeroAsDash: false, showCurrency: false });

  const [showModal, setShowModal] = useState(false);
  const [editingCategory, setEditingCategory] = useState<CategoryRow | null>(null);

  const { data, setData, post, put, transform, processing, errors, reset } = useForm({
    code: '',
    name_en: '',
    name_ar: '',
    useful_life_months: 60,
    salvage_value_minor: 0,
    is_active: true,
  });

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
        onSuccess: () => {
          setShowModal(false);
          reset();
        },
      });
    } else {
      post('/fixed-asset-categories', {
        onSuccess: () => {
          setShowModal(false);
          reset();
        },
      });
    }
  }

  function handleDelete(id: string) {
    if (confirm(appDict.confirmDeleteAssetCategory)) {
      router.delete(`/fixed-asset-categories/${id}`);
    }
  }

  function formatName(name: { en: string; ar: string } | string): string {
    if (typeof name === 'object' && name !== null) {
      return locale === 'ar' ? name.ar || name.en : name.en || name.ar;
    }
    return String(name);
  }

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
                className="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700"
              >
                {appDict.createAssetCategory}
              </button>
            ) : null
          }
        />

        <Card>
          {categories.length === 0 ? (
            <EmptyState
              title={appDict.noFixedAssetCategories}
              description={appDict.noFixedAssetCategories}
            />
          ) : (
            <div className="overflow-x-auto">
              <table className={tableClasses.table}>
                <thead>
                  <tr>
                    <th className={tableClasses.th}>{appDict.code}</th>
                    <th className={tableClasses.th}>{appDict.name}</th>
                    <th className={tableClasses.th}>{appDict.usefulLifeMonths}</th>
                    <th className={tableClasses.th}>{appDict.salvageValue}</th>
                    <th className={tableClasses.th}>{appDict.status}</th>
                    <th className={tableClasses.th}>{appDict.actions}</th>
                  </tr>
                </thead>
                <tbody>
                  {categories.map((cat) => (
                    <tr key={cat.id}>
                      <td className={`${tableClasses.td} font-mono`}>{cat.code}</td>
                      <td className={tableClasses.td}>{formatName(cat.name)}</td>
                      <td className={tableClasses.td}>{cat.useful_life_months}</td>
                      <td className={tableClasses.td}>
                        {can.view_financials ? formatAmount(cat.salvage_value_minor) : appDict.restrictedValue}
                      </td>
                      <td className={tableClasses.td}>
                        <span className={`inline-flex px-2 py-0.5 text-xs font-semibold rounded-full ${cat.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-800'}`}>
                          {cat.is_active ? appDict.fixedAssetStatusActive : appDict.inactive}
                        </span>
                      </td>
                      <td className={tableClasses.td}>
                        <div className="flex items-center space-x-2 rtl:space-x-reverse">
                          {can.edit && (
                            <button
                              type="button"
                              onClick={() => openEditModal(cat)}
                              className="text-xs font-medium text-indigo-600 hover:text-indigo-900"
                            >
                              {appDict.editAssetCategory}
                            </button>
                          )}
                          {can.delete && (cat.fixed_assets_count ?? 0) === 0 && (
                            <button
                              type="button"
                              onClick={() => handleDelete(cat.id)}
                              className="text-xs font-medium text-rose-600 hover:text-rose-900"
                            >
                              {appDict.delete}
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Card>
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

              <div className="flex items-center space-x-2 rtl:space-x-reverse">
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

              <div className="flex justify-end space-x-2 rtl:space-x-reverse pt-2">
                <button
                  type="button"
                  onClick={() => setShowModal(false)}
                  className="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 rounded-md hover:bg-slate-200"
                >
                  {appDict.back}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50"
                >
                  {appDict.save}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </AppLayout>
  );
}
