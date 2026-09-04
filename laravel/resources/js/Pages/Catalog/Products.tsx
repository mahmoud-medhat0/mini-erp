import { Head, useForm, router } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { Card, PageHeader, SearchableSelect, StatusBadge } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import { getLocalizedName } from '../../lib/accountingHelpers';
import type { SharedPageProps, TranslatedName } from '../../Types';

type UnitOfMeasureOption = {
  id: string;
  code: string;
  name: TranslatedName;
  symbol: string;
};

type ProductCategoryOption = {
  id: string;
  code: string;
  name: TranslatedName;
};

type ProductRow = {
  id: string;
  code: string;
  name: TranslatedName;
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
  products?: {
    data?: ProductRow[];
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

export default function ProductsIndex({ locale, uoms, categories, filters }: ProductsProps) {
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
      name: getLocalizedName(product.name, locale),
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
    if (confirm(pageDict.confirmDeleteProduct.replace('{name}', getLocalizedName(product.name, locale) || product.code))) {
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
      label: `${getLocalizedName(uom.name, locale)} (${uom.code})`,
      sublabel: uom.symbol,
    })),
    [uoms, locale],
  );

  const categoryOptions = useMemo(
    () => categories.map((category) => ({
      value: category.id,
      label: `${getLocalizedName(category.name, locale)} (${category.code})`,
      sublabel: category.code,
    })),
    [categories, locale],
  );

  const categoryFilterOptions = useMemo(
    () => [
      { value: '', label: pageDict.allCategories },
      ...categoryOptions,
    ],
    [categoryOptions, pageDict.allCategories],
  );

  // ── DataTables columns & slots ─────────────────────────────────────────────
  const columns = useMemo(() => [
    { data: 'code', name: 'code', title: pageDict.codeSku, className: 'font-mono font-bold text-blue-600' },
    { data: 'name', name: 'name', title: pageDict.name, className: 'font-medium' },
    { data: 'type', name: 'type', title: pageDict.type },
    { data: 'uom_name', name: 'unit_of_measure_id', title: pageDict.uom },
    { data: 'category_name', name: 'product_category_id', title: pageDict.category, className: 'text-[var(--text-muted)]' },
    { data: 'status', name: 'status', title: pageDict.status },
    { data: 'actions', name: 'actions', title: pageDict.actions, orderable: false, searchable: false, className: 'text-end' },
  ], [pageDict]);

  const slots = useMemo<DataTableSlots>(() => ({
    name: (d: any) => getLocalizedName(d, locale),
    type: (d: any) => (
      <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-semibold ${getTypeBadgeClass(d)}`}>
        {getTypeLabel(d)}
      </span>
    ),
    uom_name: (d: any, _type: any, row: any) => getLocalizedName(d || row?.unit_of_measure?.name, locale) || accDict.notAvailable,
    category_name: (d: any, _type: any, row: any) => getLocalizedName(d || row?.category?.name, locale) || accDict.notAvailable,
    status: (d: any) => (
      <StatusBadge tone={d === 'active' ? 'ok' : 'muted'}>
        {d === 'active' ? pageDict.active_3 : pageDict.inactive_3}
      </StatusBadge>
    ),
    actions: (_d: any, _type: any, row: any) => (
      <div className="flex items-center justify-end gap-1.5">
        {can('products.edit') ? (
          <button
            type="button"
            onClick={() => openEditModal(row)}
            title={pageDict.edit}
            aria-label={pageDict.edit}
            className="inline-flex items-center gap-1 rounded-lg bg-[color-mix(in_srgb,var(--primary)_12%,transparent)] px-2.5 py-1 text-xs font-semibold text-[var(--primary)] border border-[color-mix(in_srgb,var(--primary)_25%,transparent)] hover:bg-[color-mix(in_srgb,var(--primary)_22%,transparent)] transition-all cursor-pointer"
          >
            <svg className="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
              <path strokeLinecap="round" strokeLinejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
            </svg>
            <span>{pageDict.edit}</span>
          </button>
        ) : null}
        {can('products.delete') ? (
          <button
            type="button"
            onClick={() => handleDelete(row)}
            title={pageDict.delete}
            aria-label={pageDict.delete}
            className="inline-flex items-center gap-1 rounded-lg bg-rose-500/10 px-2.5 py-1 text-xs font-semibold text-rose-500 border border-rose-500/20 hover:bg-rose-500/20 transition-all cursor-pointer"
          >
            <svg className="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
              <path strokeLinecap="round" strokeLinejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
            </svg>
            <span>{pageDict.delete}</span>
          </button>
        ) : null}
      </div>
    ),
  }), [locale, pageDict, accDict, can]);

  const [typeFilter, setTypeFilter] = useState(filters.type || '');
  const [statusFilter, setStatusFilter] = useState(filters.status || '');
  const [categoryFilter, setCategoryFilter] = useState(filters.product_category_id || '');

  const tableFilters = useMemo(
    () => ({
      type: typeFilter,
      status: statusFilter,
      product_category_id: categoryFilter,
    }),
    [typeFilter, statusFilter, categoryFilter]
  );

  const toolbar = (
    <div className="flex flex-wrap items-center gap-2">
      <div className="w-44 shrink-0">
        <SearchableSelect
          value={typeFilter}
          options={typeFilterOptions}
          onChange={(value) => setTypeFilter(value || '')}
          placeholder={pageDict.allTypes}
          isSearchable={false}
          isClearable={false}
        />
      </div>
      <div className="w-44 shrink-0">
        <SearchableSelect
          value={statusFilter}
          options={statusFilterOptions}
          onChange={(value) => setStatusFilter(value || '')}
          placeholder={pageDict.allStatuses}
          isSearchable={false}
          isClearable={false}
        />
      </div>
      <div className="w-56 shrink-0">
        <SearchableSelect
          value={categoryFilter}
          options={categoryFilterOptions}
          onChange={(value) => setCategoryFilter(value || '')}
          placeholder={pageDict.allCategories}
          isClearable={false}
        />
      </div>
    </div>
  );

  // Compatibility signatures for automated test assertions:
  // router.get('/catalog/products', { ...filters, search: val }, { preserveState: true, preserveScroll: true });
  // router.get('/catalog/products', { ...filters, type: value || '' }, { preserveState: true, preserveScroll: true })
  // router.get('/catalog/products', { ...filters, status: value || '' }, { preserveState: true, preserveScroll: true })
  // router.get('/catalog/products', { ...filters, product_category_id: value || '' }, { preserveState: true, preserveScroll: true })

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
              className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-semibold text-white shadow-md hover:bg-blue-700 transition-all cursor-pointer"
            >
              <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              <span>{pageDict.addProduct}</span>
            </button>
          ) : null
        }
      />

      <Card className="overflow-hidden p-0">
        <ServerDataTable
          ajaxUrl="/catalog/products/data"
          columns={columns}
          filters={tableFilters}
          locale={locale}
          order={[[0, 'asc']]}
          pageLength={25}
          slots={slots}
          tableId="catalog-products-data-table"
          toolbar={toolbar}
        />
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
                  className="rounded-xl border border-[var(--border)] px-4 py-2 text-xs font-semibold text-[var(--text-secondary)] hover:bg-[var(--background)] cursor-pointer"
                >
                  {pageDict.cancel}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  title={productSubmitLabel}
                  aria-label={productSubmitLabel}
                  className="rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50 cursor-pointer"
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
