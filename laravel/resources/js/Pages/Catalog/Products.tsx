import { Head, useForm, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, EmptyState, PageHeader, StatusBadge, tableClasses } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types';

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

type ProductsProps = SharedPageProps & {
  products: {
    data: ProductRow[];
    links: any[];
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

export default function ProductsIndex({ locale, products, uoms, categories, filters }: ProductsProps) {
  const isAr = locale === 'ar';
  const dict = getDictionary(locale);

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
        onSuccess: () => closeModal(),
      });
    } else {
      post('/catalog/products', {
        onSuccess: () => closeModal(),
      });
    }
  };

  const handleDelete = (product: ProductRow) => {
    if (confirm(isAr ? 'هل أنت تأكد من حذف هذا المنتج/الخدمة؟' : 'Are you sure you want to delete this product/service?')) {
      destroy(`/catalog/products/${product.id}`);
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
        return isAr ? 'مخزني' : 'Stock';
      case 'service':
        return isAr ? 'خدمة' : 'Service';
      case 'non_stock':
        return isAr ? 'غير مخزني' : 'Non-Stock';
      default:
        return type;
    }
  };

  return (
    <AppLayout active="products.index">
      <Head title={isAr ? 'المنتجات والخدمات' : 'Products & Services'} />

      <PageHeader
        title={isAr ? 'كتالوج المنتجات والخدمات' : 'Product & Service Catalog'}
        description={isAr ? 'إدارة دليل الصنوف والمنتجات والخدمات المتاحة للشراء والبيع' : 'Manage master catalog for products and services available for sales and purchasing'}
        actions={
          <button
            type="button"
            onClick={openCreateModal}
            className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-semibold text-white shadow-md hover:bg-blue-700 transition-all"
          >
            <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            <span>{isAr ? 'إضافة صنف / منتج' : 'Add Product'}</span>
          </button>
        }
      />

      <Card className="p-6">
        <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative flex-1 max-w-md">
            <input
              type="text"
              placeholder={isAr ? 'بحث بالرمز أو SKU أو الاسم...' : 'Search code, SKU, or name...'}
              defaultValue={filters.search || ''}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  const val = (e.target as HTMLInputElement).value;
                  router.get('/catalog/products', { ...filters, search: val }, { preserveState: true });
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
              value={filters.type || ''}
              onChange={(e) => router.get('/catalog/products', { ...filters, type: e.target.value }, { preserveState: true })}
              className="rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
            >
              <option value="">{isAr ? 'جميع الأنواع' : 'All Types'}</option>
              <option value="stock">{isAr ? 'مخزني' : 'Stock'}</option>
              <option value="service">{isAr ? 'خدمة' : 'Service'}</option>
              <option value="non_stock">{isAr ? 'غير مخزني' : 'Non-Stock'}</option>
            </select>

            <select
              value={filters.status || ''}
              onChange={(e) => router.get('/catalog/products', { ...filters, status: e.target.value }, { preserveState: true })}
              className="rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
            >
              <option value="">{isAr ? 'جميع الحالات' : 'All Statuses'}</option>
              <option value="active">{isAr ? 'نشط' : 'Active'}</option>
              <option value="inactive">{isAr ? 'غير نشط' : 'Inactive'}</option>
            </select>
          </div>
        </div>

        {products.data.length === 0 ? (
          <EmptyState
            title={isAr ? 'لا توجد منتجات أو خدمات' : 'No Products or Services found'}
            description={isAr ? 'ابدأ بإضافة أول صنف لدليل المنتجات' : 'Get started by adding your first catalog product or service'}
          />
        ) : (
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{isAr ? 'الرمز / SKU' : 'Code / SKU'}</th>
                  <th className={tableClasses.th}>{isAr ? 'الاسم' : 'Name'}</th>
                  <th className={tableClasses.th}>{isAr ? 'النوع' : 'Type'}</th>
                  <th className={tableClasses.th}>{isAr ? 'وحدة القياس' : 'UOM'}</th>
                  <th className={tableClasses.th}>{isAr ? 'التصنيف' : 'Category'}</th>
                  <th className={tableClasses.th}>{isAr ? 'الحالة' : 'Status'}</th>
                  <th className={`${tableClasses.th} text-end`}>{isAr ? 'الإجراءات' : 'Actions'}</th>
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
                    <td className={tableClasses.td}>{prod.unit_of_measure?.name || '-'}</td>
                    <td className={`${tableClasses.td} text-[var(--text-muted)]`}>{prod.category?.name || '-'}</td>
                    <td className={tableClasses.td}>
                      <StatusBadge tone={prod.status === 'active' ? 'ok' : 'muted'}>
                        {prod.status === 'active' ? (isAr ? 'نشط' : 'Active') : (isAr ? 'غير نشط' : 'Inactive')}
                      </StatusBadge>
                    </td>
                    <td className={`${tableClasses.td} text-end space-x-2 rtl:space-x-reverse`}>
                      <button
                        type="button"
                        onClick={() => openEditModal(prod)}
                        className="text-xs font-semibold text-blue-600 hover:text-blue-800"
                      >
                        {isAr ? 'تعديل' : 'Edit'}
                      </button>
                      <button
                        type="button"
                        onClick={() => handleDelete(prod)}
                        className="text-xs font-semibold text-red-600 hover:text-red-800"
                      >
                        {isAr ? 'حذف' : 'Delete'}
                      </button>
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
                ? isAr ? 'تعديل صنف / منتج' : 'Edit Product / Service'
                : isAr ? 'إضافة صنف / منتج جديد' : 'Create Product / Service'}
            </h3>

            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                    {isAr ? 'رمز الصنف / SKU' : 'Code / SKU'} *
                  </label>
                  <input
                    type="text"
                    value={data.code}
                    onChange={(e) => setData('code', e.target.value.toUpperCase())}
                    required
                    placeholder="e.g. PRD-0001"
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none uppercase font-mono"
                  />
                  {errors.code ? <p className="mt-1 text-[10px] text-red-500">{errors.code}</p> : null}
                </div>

                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                    {isAr ? 'النوع' : 'Type'} *
                  </label>
                  <select
                    value={data.type}
                    onChange={(e) => setData('type', e.target.value as any)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
                  >
                    <option value="stock">{isAr ? 'مخزني (Stock)' : 'Stock'}</option>
                    <option value="service">{isAr ? 'خدمة (Service)' : 'Service'}</option>
                    <option value="non_stock">{isAr ? 'غير مخزني (Non-Stock)' : 'Non-Stock'}</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                  {isAr ? 'الاسم' : 'Name'} *
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
                    {isAr ? 'وحدة القياس (UOM)' : 'Unit of Measure'} *
                  </label>
                  <select
                    value={data.unit_of_measure_id}
                    onChange={(e) => setData('unit_of_measure_id', e.target.value)}
                    required
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
                  >
                    <option value="">{isAr ? 'اختر وحدة القياس' : 'Select UOM'}</option>
                    {uoms.map((uom) => (
                      <option key={uom.id} value={uom.id}>
                        {uom.name} ({uom.code})
                      </option>
                    ))}
                  </select>
                  {errors.unit_of_measure_id ? <p className="mt-1 text-[10px] text-red-500">{errors.unit_of_measure_id}</p> : null}
                </div>

                <div>
                  <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                    {isAr ? 'التصنيف' : 'Category'}
                  </label>
                  <select
                    value={data.product_category_id}
                    onChange={(e) => setData('product_category_id', e.target.value)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
                  >
                    <option value="">{isAr ? 'بدون تصنيف' : 'None'}</option>
                    {categories.map((cat) => (
                      <option key={cat.id} value={cat.id}>
                        {cat.name} ({cat.code})
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                  {isAr ? 'الوصف' : 'Description'}
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
                    {isAr ? 'متاح للمبيعات' : 'Sales Enabled'}
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
                    {isAr ? 'متاح للمشتريات' : 'Purchase Enabled'}
                  </label>
                </div>
              </div>

              <div className="pt-2">
                <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                  {isAr ? 'الحالة' : 'Status'}
                </label>
                <select
                  value={data.status}
                  onChange={(e) => setData('status', e.target.value as any)}
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none"
                >
                  <option value="active">{isAr ? 'نشط (Active)' : 'Active'}</option>
                  <option value="inactive">{isAr ? 'غير نشط (Inactive)' : 'Inactive'}</option>
                </select>
              </div>

              <div className="flex items-center justify-end gap-3 pt-4 border-t border-[var(--border)]">
                <button
                  type="button"
                  onClick={closeModal}
                  className="rounded-xl border border-[var(--border)] px-4 py-2 text-xs font-semibold text-[var(--text-secondary)] hover:bg-[var(--background)]"
                >
                  {isAr ? 'إلغاء' : 'Cancel'}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  className="rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                >
                  {processing
                    ? isAr ? 'جاري الحفظ...' : 'Saving...'
                    : isAr ? 'حفظ' : 'Save'}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
