import { Head, useForm, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, EmptyState, PageHeader, StatusBadge, tableClasses } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';

type ProductCategoryRow = {
  id: string;
  code: string;
  name: string;
  description?: string | null;
  is_active: boolean;
  lock_version: number;
  created_at: string;
};

type CategoriesProps = SharedPageProps & {
  categories: {
    data: ProductCategoryRow[];
    links: any[];
  };
  filters: {
    search?: string;
  };
};

export default function ProductCategoriesIndex({ locale, categories, filters }: CategoriesProps) {
  const dict = getDictionary(locale);
  const can = useCan();

  const [showModal, setShowModal] = useState(false);
  const [editingCategory, setEditingCategory] = useState<ProductCategoryRow | null>(null);

  const { data, setData, post, put, delete: destroy, processing, errors, reset } = useForm({
    code: '',
    name: '',
    description: '',
    is_active: true,
    lock_version: 1,
  });

  const openCreateModal = () => {
    reset();
    setEditingCategory(null);
    setShowModal(true);
  };

  const openEditModal = (category: ProductCategoryRow) => {
    setEditingCategory(category);
    setData({
      code: category.code,
      name: category.name,
      description: category.description || '',
      is_active: category.is_active,
      lock_version: category.lock_version,
    });
    setShowModal(true);
  };

  const closeModal = () => {
    setShowModal(false);
    setEditingCategory(null);
    reset();
  };

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    if (editingCategory) {
      put(`/catalog/categories/${editingCategory.id}`, {
        onSuccess: () => closeModal(),
      });
    } else {
      post('/catalog/categories', {
        onSuccess: () => closeModal(),
      });
    }
  };

  const handleDelete = (category: ProductCategoryRow) => {
    if (confirm(dict.app.pages.catalogProductCategories.areYouSureYouWantTo)) {
      destroy(`/catalog/categories/${category.id}`);
    }
  };

  return (
    <AppLayout active="product-categories.index">
      <Head title={dict.app.pages.catalogProductCategories.productCategories} />

      <PageHeader
        title={dict.app.pages.catalogProductCategories.productCategories_2}
        description={dict.app.pages.catalogProductCategories.manageProductAndServiceCategories}
        actions={
          can('products.create') ? (
            <button
              type="button"
              onClick={openCreateModal}
              className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-semibold text-white shadow-md hover:bg-blue-700 transition-all"
            >
              <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              <span>{dict.app.pages.catalogProductCategories.addCategory}</span>
            </button>
          ) : null
        }
      />

      <Card className="p-6">
        <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative flex-1 max-w-md">
            <input
              type="text"
              placeholder={dict.app.pages.catalogProductCategories.searchCodeOrName}
              defaultValue={filters.search || ''}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  const val = (e.target as HTMLInputElement).value;
                  router.get('/catalog/categories', { search: val }, { preserveState: true });
                }
              }}
              className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] py-2.5 ps-10 pe-4 text-xs focus:border-blue-500 focus:outline-none"
            />
            <svg className="absolute start-3 top-3 size-4 text-[var(--text-muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
        </div>

        {categories.data.length === 0 ? (
          <EmptyState
            title={dict.app.pages.catalogProductCategories.noProductCategoriesFound}
            description={dict.app.pages.catalogProductCategories.getStartedByCreatingYourFirst}
          />
        ) : (
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{dict.app.pages.catalogProductCategories.code}</th>
                  <th className={tableClasses.th}>{dict.app.pages.catalogProductCategories.name}</th>
                  <th className={tableClasses.th}>{dict.app.pages.catalogProductCategories.description}</th>
                  <th className={tableClasses.th}>{dict.app.pages.catalogProductCategories.status}</th>
                  <th className={`${tableClasses.th} text-end`}>{dict.app.pages.catalogProductCategories.actions}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border)]">
                {categories.data.map((cat) => (
                  <tr key={cat.id}>
                    <td className={`${tableClasses.td} font-mono font-bold text-blue-600`}>{cat.code}</td>
                    <td className={`${tableClasses.td} font-medium`}>{cat.name}</td>
                    <td className={`${tableClasses.td} text-[var(--text-muted)]`}>{cat.description || '-'}</td>
                    <td className={tableClasses.td}>
                      <StatusBadge tone={cat.is_active ? 'ok' : 'muted'}>
                        {cat.is_active ? dict.app.pages.catalogProductCategories.active_2 : dict.app.pages.catalogProductCategories.inactive}
                      </StatusBadge>
                    </td>
                    <td className={`${tableClasses.td} text-end space-x-2 rtl:space-x-reverse`}>
                      {can('products.edit') ? (
                        <button
                          type="button"
                          onClick={() => openEditModal(cat)}
                          className="text-xs font-semibold text-blue-600 hover:text-blue-800"
                        >
                          {dict.app.pages.catalogProductCategories.edit}
                        </button>
                      ) : null}
                      {can('products.delete') ? (
                        <button
                          type="button"
                          onClick={() => handleDelete(cat)}
                          className="text-xs font-semibold text-red-600 hover:text-red-800"
                        >
                          {dict.app.pages.catalogProductCategories.delete}
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
              {editingCategory
                ? dict.app.pages.catalogProductCategories.editProductCategory
                : dict.app.pages.catalogProductCategories.createProductCategory}
            </h3>

            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                  {dict.app.pages.catalogProductCategories.code_2} *
                </label>
                <input
                  type="text"
                  value={data.code}
                  onChange={(e) => setData('code', e.target.value.toUpperCase())}
                  required
                  placeholder="e.g. RAW, FG, SERV"
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none uppercase font-mono"
                />
                {errors.code ? <p className="mt-1 text-[10px] text-red-500">{errors.code}</p> : null}
              </div>

              <div>
                <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                  {dict.app.pages.catalogProductCategories.name_2} *
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
                  {dict.app.pages.catalogProductCategories.description_2}
                </label>
                <textarea
                  rows={3}
                  value={data.description}
                  onChange={(e) => setData('description', e.target.value)}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none resize-none"
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
                  {dict.app.pages.catalogProductCategories.active}
                </label>
              </div>

              <div className="flex items-center justify-end gap-3 pt-4 border-t border-[var(--border)]">
                <button
                  type="button"
                  onClick={closeModal}
                  className="rounded-xl border border-[var(--border)] px-4 py-2 text-xs font-semibold text-[var(--text-secondary)] hover:bg-[var(--background)]"
                >
                  {dict.app.pages.catalogProductCategories.cancel}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  className="rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                >
                  {processing
                    ? dict.app.pages.catalogProductCategories.saving
                    : dict.app.pages.catalogProductCategories.save}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
