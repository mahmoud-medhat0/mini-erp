import { Head, useForm, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, EmptyState, PageHeader, StatusBadge, tableClasses } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types';

type UnitOfMeasureRow = {
  id: string;
  code: string;
  name: string;
  symbol: string;
  is_active: boolean;
  lock_version: number;
  created_at: string;
};

type UomsProps = SharedPageProps & {
  uoms: {
    data: UnitOfMeasureRow[];
    links: any[];
  };
  filters: {
    search?: string;
  };
};

export default function UnitsOfMeasureIndex({ locale, uoms, filters }: UomsProps) {
  const isAr = locale === 'ar';
  const dict = getDictionary(locale);

  const [showModal, setShowModal] = useState(false);
  const [editingUom, setEditingUom] = useState<UnitOfMeasureRow | null>(null);

  const { data, setData, post, put, delete: destroy, processing, errors, reset } = useForm({
    code: '',
    name: '',
    symbol: '',
    is_active: true,
    lock_version: 1,
  });

  const openCreateModal = () => {
    reset();
    setEditingUom(null);
    setShowModal(true);
  };

  const openEditModal = (uom: UnitOfMeasureRow) => {
    setEditingUom(uom);
    setData({
      code: uom.code,
      name: uom.name,
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
        onSuccess: () => closeModal(),
      });
    } else {
      post('/catalog/uoms', {
        onSuccess: () => closeModal(),
      });
    }
  };

  const handleDelete = (uom: UnitOfMeasureRow) => {
    if (confirm(isAr ? 'هل أنت تأكد من حذف وحدة القياس هذه؟' : 'Are you sure you want to delete this Unit of Measure?')) {
      destroy(`/catalog/uoms/${uom.id}`);
    }
  };

  return (
    <AppLayout active="uoms.index">
      <Head title={isAr ? 'وحدات القياس' : 'Units of Measure'} />

      <PageHeader
        title={isAr ? 'وحدات القياس' : 'Units of Measure'}
        description={isAr ? 'إدارة وحدات القياس المستخدمة للمنتجات والخدمات' : 'Manage Units of Measure for products and services'}
        actions={
          <button
            type="button"
            onClick={openCreateModal}
            className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-semibold text-white shadow-md hover:bg-blue-700 transition-all"
          >
            <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            <span>{isAr ? 'إضافة وحدة قياس' : 'Add Unit of Measure'}</span>
          </button>
        }
      />

      <Card className="p-6">
        <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative flex-1 max-w-md">
            <input
              type="text"
              placeholder={isAr ? 'بحث بالرمز أو الاسم...' : 'Search code or name...'}
              defaultValue={filters.search || ''}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  const val = (e.target as HTMLInputElement).value;
                  router.get('/catalog/uoms', { search: val }, { preserveState: true });
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
            title={isAr ? 'لا توجد وحدات قياس' : 'No Units of Measure found'}
            description={isAr ? 'ابدأ بإضافة أول وحدة قياس للنظام' : 'Get started by creating your first Unit of Measure'}
          />
        ) : (
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{isAr ? 'الرمز' : 'Code'}</th>
                  <th className={tableClasses.th}>{isAr ? 'الاسم' : 'Name'}</th>
                  <th className={tableClasses.th}>{isAr ? 'الرمز المختصر' : 'Symbol'}</th>
                  <th className={tableClasses.th}>{isAr ? 'الحالة' : 'Status'}</th>
                  <th className={`${tableClasses.th} text-end`}>{isAr ? 'الإجراءات' : 'Actions'}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border)]">
                {uoms.data.map((uom) => (
                  <tr key={uom.id}>
                    <td className={`${tableClasses.td} font-mono font-bold text-blue-600`}>{uom.code}</td>
                    <td className={`${tableClasses.td} font-medium`}>{uom.name}</td>
                    <td className={tableClasses.td}>{uom.symbol}</td>
                    <td className={tableClasses.td}>
                      <StatusBadge tone={uom.is_active ? 'ok' : 'muted'}>
                        {uom.is_active ? (isAr ? 'نشط' : 'Active') : (isAr ? 'غير نشط' : 'Inactive')}
                      </StatusBadge>
                    </td>
                    <td className={`${tableClasses.td} text-end space-x-2 rtl:space-x-reverse`}>
                      <button
                        type="button"
                        onClick={() => openEditModal(uom)}
                        className="text-xs font-semibold text-blue-600 hover:text-blue-800"
                      >
                        {isAr ? 'تعديل' : 'Edit'}
                      </button>
                      <button
                        type="button"
                        onClick={() => handleDelete(uom)}
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
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-xs">
          <div className="w-full max-w-md rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-2xl">
            <h3 className="text-base font-bold text-[var(--text-primary)] mb-4">
              {editingUom
                ? isAr ? 'تعديل وحدة قياس' : 'Edit Unit of Measure'
                : isAr ? 'إضافة وحدة قياس جديدة' : 'Create Unit of Measure'}
            </h3>

            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                  {isAr ? 'الرمز (CODE)' : 'Code'} *
                </label>
                <input
                  type="text"
                  value={data.code}
                  onChange={(e) => setData('code', e.target.value.toUpperCase())}
                  required
                  placeholder="e.g. PCS, KG, M"
                  className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs focus:border-blue-500 focus:outline-none uppercase font-mono"
                />
                {errors.code ? <p className="mt-1 text-[10px] text-red-500">{errors.code}</p> : null}
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

              <div>
                <label className="block text-xs font-semibold text-[var(--text-secondary)] mb-1">
                  {isAr ? 'الرمز المختصر (Symbol)' : 'Symbol'}
                </label>
                <input
                  type="text"
                  value={data.symbol}
                  onChange={(e) => setData('symbol', e.target.value)}
                  placeholder="e.g. pc, kg, m"
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
                  {isAr ? 'نشط' : 'Active'}
                </label>
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
