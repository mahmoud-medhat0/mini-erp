import { Head, useForm, router } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { PaginationLink, SharedPageProps } from '../../Types';

type UnitOfMeasureOption = {
  id: string;
  code: string;
  name: string;
  symbol: string;
};

type ProductCategoryOption = {
  id: string;
  code: string;
  name: string;
};

type ProductRow = {
  id: string;
  code: string;
  name: string;
  description?: string | null;
  type: 'stock' | 'service' | 'non_stock';
  unit_of_measure_id: string;
  product_category_id?: string | null;
  status: 'active' | 'inactive';
  is_sales_enabled: boolean;
  is_purchase_enabled: boolean;
  lock_version: number;
  created_at: string;
  unit_of_measure?: UnitOfMeasureOption | null;
  category?: ProductCategoryOption | null;
};

type ProductType = ProductRow['type'];
type ProductStatus = ProductRow['status'];

type ProductsProps = SharedPageProps & {
  products: {
    data: ProductRow[];
    links: PaginationLink[];
  };
  uoms: UnitOfMeasureOption[];
  categories: ProductCategoryOption[];
  filters: {
    search?: string;
    type?: string;
    status?: string;
    product_category_id?: string;
  };
};

function toProductType(value: string): ProductType {
  if (value === 'service' || value === 'non_stock') {
    return value;
  }

  return 'stock';
}

function toProductStatus(value: string): ProductStatus {
  return value === 'inactive' ? 'inactive' : 'active';
}

export default function ProductsIndex({ locale, products, uoms, categories, filters }: ProductsProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const pageDict = dict.app.pages.catalogProducts;
  const can = useCan();

  const [showModal, setShowModal] = useState(false);
  const [editingProduct, setEditingProduct] = useState<ProductRow | null>(null);

  const { data, setData, post, put, delete: destroy, processing, errors, reset } = useForm({
    code: '',
    name: '',
    description: '',
    type: 'stock' as 'stock' | 'service' | 'non_stock',
    unit_of_measure_id: uoms[0]?.id || '',
    product_category_id: '',
    status: 'active' as 'active' | 'inactive',
    is_sales_enabled: true,
    is_purchase_enabled: true,
    lock_version: 1,
  });
  const productSubmitLabel = processing ? pageDict.saving : pageDict.save;

  const openCreateModal = () => {
    reset();
    setEditingProduct(null);
    setData({
      code: '',
      name: '',
      description: '',
      type: 'stock',
      unit_of_measure_id: uoms[0]?.id || '',
      product_category_id: '',
      status: 'active',
      is_sales_enabled: true,
      is_purchase_enabled: true,
      lock_version: 1,
    });
    setShowModal(true);
  };

  const openEditModal = (product: ProductRow) => {
    setEditingProduct(product);
    setData({
      code: product.code,
      name: product.name,
      description: product.description || '',
      type: product.type,
      unit_of_measure_id: product.unit_of_measure_id,
      product_category_id: product.product_category_id || '',
      status: product.status,
      is_sales_enabled: product.is_sales_enabled,
      is_purchase_enabled: product.is_purchase_enabled,
      lock_version: product.lock_version,
    });
    setShowModal(true);
  };

  const closeModal = () => {
    setShowModal(false);
    setEditingProduct(null);
    reset();
  };

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    if (editingProduct) {
      put(`/catalog/products/${editingProduct.id}`, {
        preserveScroll: true,
        onSuccess: () => closeModal(),
      });
    } else {
      post('/catalog/products', {
        preserveScroll: true,
        onSuccess: () => closeModal(),
      });
    }
  };

  const handleDelete = (product: ProductRow) => {
    if (confirm(pageDict.confirmDeleteProduct.replace('{name}', product.name || product.code))) {
      destroy(`/catalog/products/${product.id}`, { preserveScroll: true });
    }
  };

  const getTypeBadgeClass = (type: string) => {
    switch (type) {
      case 'stock':
        return 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300';
      case 'service':
        return 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300';
      case 'non_stock':
        return 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  const getTypeLabel = (type: string) => {
    switch (type) {
      case 'stock':
        return pageDict.stock;
      case 'service':
        return pageDict.service;
      case 'non_stock':
        return pageDict.nonStock;
      default:
        return type;
    }
  };

  const typeOptions = useMemo(
    () => [
      { value: 'stock' as const, label: pageDict.stock_2 },
      { value: 'service' as const, label: pageDict.service_2 },
      { value: 'non_stock' as const, label: pageDict.nonStock_2 },
    ],
    [pageDict.nonStock_2, pageDict.service_2, pageDict.stock_2],
  );

  const typeFilterOptions = useMemo(
    () => [
      { value: '', label: pageDict.allTypes },
      { value: 'stock', label: pageDict.stock },
      { value: 'service', label: pageDict.service },
      { value: 'non_stock', label: pageDict.nonStock },
    ],
    [pageDict.allTypes, pageDict.nonStock, pageDict.service, pageDict.stock],
  );

  const statusOptions = useMemo(
    () => [
      { value: 'active' as const, label: pageDict.active_2 },
      { value: 'inactive' as const, label: pageDict.inactive_2 },
    ],
    [pageDict.active_2, pageDict.inactive_2],
  );

  const statusFilterOptions = useMemo(
    () => [
      { value: '', label: pageDict.allStatuses },
      { value: 'active', label: pageDict.active },
      { value: 'inactive', label: pageDict.inactive },
    ],
    [pageDict.active, pageDict.allStatuses, pageDict.inactive],
  );

  const uomOptions = useMemo(
    () => uoms.map((uom) => ({
      value: uom.id,
      label: `${uom.name} (${uom.code})`,
      sublabel: uom.symbol,
    })),
    [uoms],
  );

  const categoryOptions = useMemo(
    () => categories.map((category) => ({
      value: category.id,
      label: `${category.name} (${category.code})`,
      sublabel: category.code,
    })),
    [categories],
  );

  const categoryFilterOptions = useMemo(
    () => [
      { value: '', label: pageDict.allCategories },
      ...categoryOptions,
    ],
    [categoryOptions, pageDict.allCategories],
  );

  return (
    <AppLayout active="products.index">
      <Head title={pageDict.productsServices} />

      <PageHeader
        title={pageDict.productServiceCatalog}
        description={pageDict.manageMasterCatalogForProductsAnd}
        actions={
          can('products.create') ? (
            <button
              type="button"
              onClick={openCreateModal}
              title={pageDict.addProduct}
              aria-label={pageDict.addProduct}
              className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-semibold text-white shadow-md hover:bg-blue-700 transition-all"
            >
              <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              <span>{pageDict.addProduct}</span>
            </button>
          ) : null
        }
      />

      <Card className="p-6">
        <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative flex-1 max-w-md">
            <input
              type="text"
              placeholder={pageDict.searchCodeSkuOrName}
              defaultValue={filters.search || ''}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  const val = (e.target as HTMLInputElement).value;
                  router.get('/catalog/products', { ...filters, search: val }, { preserveState: true, preserveScroll: true });
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
              value={filters.type || ''}
              options={typeFilterOptions}
              onChange={(value) => router.get('/catalog/products', { ...filters, type: value || '' }, { preserveState: true, preserveScroll: true })}
              placeholder={pageDict.allTypes}
              isSearchable={false}
              className="min-w-[150px]"
            />

            <SearchableSelect
              value={filters.status || ''}
              options={statusFilterOptions}
              onChange={(value) => router.get('/catalog/products', { ...filters, status: value || '' }, { preserveState: true, preserveScroll: true })}
              placeholder={pageDict.allStatuses}
              isSearchable={false}
              className="min-w-[150px]"
            />

            <SearchableSelect
              value={filters.product_category_id || ''}
              options={categoryFilterOptions}
              onChange={(value) => router.get('/catalog/products', { ...filters, product_category_id: value || '' }, { preserveState: true, preserveScroll: true })}
              placeholder={pageDict.allCategories}
              className="min-w-[210px]"
            />
          </div>
        </div>

        {products.data.length === 0 ? (
          <EmptyState
            title={pageDict.noProductsOrServicesFound}
            description={pageDict.getStartedByAddingYourFirst}
          />
        ) : (
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{pageDict.codeSku}</th>
                  <th className={tableClasses.th}>{pageDict.name}</th>
                  <th className={tableClasses.th}>{pageDict.type}</th>
                  <th className={tableClasses.th}>{pageDict.uom}</th>
                  <th className={tableClasses.th}>{pageDict.category}</th>
                  <th className={tableClasses.th}>{pageDict.status}</th>
                  <th className={`${tableClasses.th} text-end`}>{pageDict.actions}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border)]">
                {products.data.map((prod) => (
                  <tr key={prod.id}>
                    <td className={`${tableClasses.td} font-mono font-bold text-blue-600`}>{prod.code}</td>
                    <td className={`${tableClasses.td} font-medium`}>{prod.name}</td>
                    <td className={tableClasses.td}>
                      <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-semibold ${getTypeBadgeClass(prod.type)}`}>
                        {getTypeLabel(prod.type)}
                      </span>
                    </td>
                    <td className={tableClasses.td}>{prod.unit_of_measure?.name || accDict.notAvailable}</td>
                    <td className={`${tableClasses.td} text-[var(--text-muted)]`}>{prod.category?.name || accDict.notAvailable}</td>
                    <td className={tableClasses.td}>
                      <StatusBadge tone={prod.status === 'active' ? 'ok' : 'muted'}>
                        {prod.status === 'active' ? pageDict.active_3 : pageDict.inactive_3}
                      </StatusBadge>
                    </td>
                    <td className={`${tableClasses.td} text-end space-x-2 rtl:space-x-reverse`}>
                      {can('products.edit') ? (
                        <button
                          type="button"
                          onClick={() => openEditModal(prod)}
                          title={pageDict.edit}
                          aria-label={pageDict.edit}
                          className="text-xs font-semibold text-blue-600 hover:text-blue-800"
                        >
                          {pageDict.edit}
                        </button>
                      ) : null}
                      {can('products.delete') ? (
                        <button
                          type="button"
                          onClick={() => handleDelete(prod)}
                          title={pageDict.delete}
                          aria-label={pageDict.delete}
                          className="text-xs font-semibold text-red-600 hover:text-red-800"
                        >
                          {pageDict.delete}
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
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-xs overflow-y-auto">
          <div className="w-full max-w-lg rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-2xl my-8">
            <h3 className="text-base font-bold text-[var(--text-primary)] mb-4">
              {editingProduct
                ? pageDict.editProductService
                : pageDict.createProductService}
            </h3>

            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                    {pageDict.codeSku_2} *
                  </label>
                  <input
                    type="text"
                    value={data.code}
                    onChange={(e) => setData('code', e.target.value.toUpperCase())}
                    required
                    placeholder={pageDict.codePlaceholder}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none uppercase font-mono"
                  />
                  {errors.code ? <p className="mt-1 text-[10px] text-red-500">{errors.code}</p> : null}
                </div>

                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                    {pageDict.type_2} *
                  </label>
                  <SearchableSelect<ProductType>
                    value={data.type}
                    options={typeOptions}
                    onChange={(value) => setData('type', toProductType(value || 'stock'))}
                    placeholder={pageDict.type_2}
                    isClearable={false}
                    isSearchable={false}
                    error={errors.type}
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                  {pageDict.name_2} *
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

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                    {pageDict.unitOfMeasure} *
                  </label>
                  <SearchableSelect
                    value={data.unit_of_measure_id}
                    options={uomOptions}
                    onChange={(value) => setData('unit_of_measure_id', value || '')}
                    placeholder={pageDict.selectUom}
                    required
                    error={errors.unit_of_measure_id}
                  />
                  {errors.unit_of_measure_id ? <p className="mt-1 text-[10px] text-red-500">{errors.unit_of_measure_id}</p> : null}
                </div>

                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                    {pageDict.category_2}
                  </label>
                  <SearchableSelect
                    value={data.product_category_id}
                    options={[{ value: '', label: pageDict.none }, ...categoryOptions]}
                    onChange={(value) => setData('product_category_id', value || '')}
                    placeholder={pageDict.none}
                    error={errors.product_category_id}
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                  {pageDict.description}
                </label>
                <textarea
                  rows={2}
                  value={data.description}
                  onChange={(e) => setData('description', e.target.value)}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none resize-none"
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                <div className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    id="is_sales_enabled"
                    checked={data.is_sales_enabled}
                    onChange={(e) => setData('is_sales_enabled', e.target.checked)}
                    className="rounded border-[var(--border)] text-blue-600 focus:ring-blue-500"
                  />
                  <label htmlFor="is_sales_enabled" className="text-xs font-medium text-[var(--text-primary)]">
                    {pageDict.salesEnabled}
                  </label>
                </div>

                <div className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    id="is_purchase_enabled"
                    checked={data.is_purchase_enabled}
                    onChange={(e) => setData('is_purchase_enabled', e.target.checked)}
                    className="rounded border-[var(--border)] text-blue-600 focus:ring-blue-500"
                  />
                  <label htmlFor="is_purchase_enabled" className="text-xs font-medium text-[var(--text-primary)]">
                    {pageDict.purchaseEnabled}
                  </label>
                </div>
              </div>

              <div className="pt-2">
                <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                  {pageDict.status_2}
                </label>
                <SearchableSelect<ProductStatus>
                  value={data.status}
                  options={statusOptions}
                  onChange={(value) => setData('status', toProductStatus(value || 'active'))}
                  placeholder={pageDict.status_2}
                  isClearable={false}
                  isSearchable={false}
                  error={errors.status}
                />
              </div>

              <div className="flex items-center justify-end gap-3 pt-4 border-t border-[var(--border)]">
                <button
                  type="button"
                  onClick={closeModal}
                  title={pageDict.cancel}
                  aria-label={pageDict.cancel}
                  className="rounded-xl border border-[var(--border)] px-4 py-2 text-xs font-semibold text-[var(--text-secondary)] hover:bg-[var(--background)]"
                >
                  {pageDict.cancel}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  title={productSubmitLabel}
                  aria-label={productSubmitLabel}
                  className="rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                >
                  {productSubmitLabel}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
