import { Head, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, EmptyState, PageHeader, StatusBadge, tableClasses } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types';

type SupplierRow = {
  id: string;
  code: string;
  name: string;
  status: 'active' | 'inactive';
  email?: string | null;
  phone?: string | null;
  address?: string | null;
  tax_number?: string | null;
  lock_version: number;
  created_at: string;
};

type SuppliersProps = SharedPageProps & {
  suppliers: {
    data: SupplierRow[];
    links: any[];
  };
  filters: {
    search?: string;
    status?: string;
  };
};

export default function SuppliersIndex({ locale, suppliers, filters }: SuppliersProps) {
  const isAr = locale === 'ar';
  const dict = getDictionary(locale);

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
      name: supplier.name,
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
        onSuccess: () => {
          setShowModal(false);
          reset();
        },
      });
    } else {
      post('/suppliers', {
        onSuccess: () => {
          setShowModal(false);
          reset();
        },
      });
    }
  };

  return (
    <AppLayout active="suppliers.index">
      <Head title={isAr ? 'إدارة الموردين - Mini ERP' : 'Suppliers - Mini ERP'} />

      <PageHeader
        title={isAr ? 'إدارة الموردين' : 'Supplier Master Data'}
        description={isAr ? 'إدارة سجلات الموردين والبيانات الأساسية وتفاصيل الاتصال.' : 'Manage supplier records, tax numbers, and contact details.'}
        actions={
          <button
            type="button"
            onClick={openCreateModal}
            className="rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] transition-all cursor-pointer"
          >
            {isAr ? '+ إضافة مورد جديد' : '+ Create Supplier'}
          </button>
        }
      />

      <Card className="p-4 mb-6">
        <div className="flex flex-wrap items-center gap-3">
          <input
            type="text"
            placeholder={isAr ? 'بحث بالكود أو الاسم أو الهاتـف...' : 'Search by code, name, phone...'}
            defaultValue={filters.search || ''}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                const target = e.target as HTMLInputElement;
                window.location.href = `/suppliers?search=${encodeURIComponent(target.value)}`;
              }
            }}
            className="w-72 rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2 text-xs text-[var(--text-primary)] outline-hidden focus:border-[var(--primary)]"
          />
        </div>
      </Card>

      {suppliers.data.length === 0 ? (
        <EmptyState
          title={isAr ? 'لا يوجد موردين' : 'No Suppliers Found'}
          description={isAr ? 'قم بإضافة اول مورد بالضغط على زر الإنشاء اعلاه.' : 'Get started by creating your first supplier.'}
        />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{isAr ? 'الكود' : 'Code'}</th>
                <th className={tableClasses.th}>{isAr ? 'الاسم' : 'Name'}</th>
                <th className={tableClasses.th}>{isAr ? 'الهاتف' : 'Phone'}</th>
                <th className={tableClasses.th}>{isAr ? 'البريد الإلكتروني' : 'Email'}</th>
                <th className={tableClasses.th}>{isAr ? 'الرقم الضريبي' : 'Tax Number'}</th>
                <th className={tableClasses.th}>{isAr ? 'الحالة' : 'Status'}</th>
                <th className={tableClasses.th}>{isAr ? 'إجراءات' : 'Actions'}</th>
              </tr>
            </thead>
            <tbody>
              {suppliers.data.map((supplier) => (
                <tr key={supplier.id} className="hover:bg-[var(--background)]/50 transition-colors">
                  <td className={`${tableClasses.td} font-mono font-bold text-xs`}>{supplier.code}</td>
                  <td className={`${tableClasses.td} font-semibold`}>{supplier.name}</td>
                  <td className={tableClasses.td}>{supplier.phone || '—'}</td>
                  <td className={tableClasses.td}>{supplier.email || '—'}</td>
                  <td className={`${tableClasses.td} font-mono text-xs`}>{supplier.tax_number || '—'}</td>
                  <td className={tableClasses.td}>
                    <StatusBadge tone={supplier.status === 'active' ? 'ok' : 'muted'}>
                      {supplier.status === 'active' ? (isAr ? 'نشط' : 'Active') : (isAr ? 'غير نشط' : 'Inactive')}
                    </StatusBadge>
                  </td>
                  <td className={tableClasses.td}>
                    <button
                      type="button"
                      onClick={() => openEditModal(supplier)}
                      className="text-xs font-bold text-[var(--primary)] hover:underline cursor-pointer"
                    >
                      {isAr ? 'تعديل' : 'Edit'}
                    </button>
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
              {editingSupplier ? (isAr ? 'تعديل بيانات المورد' : 'Edit Supplier') : (isAr ? 'إضافة مورد جديد' : 'Create New Supplier')}
            </h2>

            <form onSubmit={submit} className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {isAr ? 'كود المورد' : 'Code'} *
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
                    {isAr ? 'الحالة' : 'Status'} *
                  </label>
                  <select
                    value={data.status}
                    onChange={(e) => setData('status', e.target.value as any)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-semibold text-[var(--text-primary)]"
                  >
                    <option value="active">{isAr ? 'نشط' : 'Active'}</option>
                    <option value="inactive">{isAr ? 'غير نشط' : 'Inactive'}</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                  {isAr ? 'اسم المورد' : 'Supplier Name'} *
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
                    {isAr ? 'الهاتف' : 'Phone'}
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
                    {isAr ? 'البريد الإلكتروني' : 'Email'}
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
                  {isAr ? 'الرقم الضريبي' : 'Tax Number'}
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
                  {isAr ? 'العنوان' : 'Address'}
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
                  className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] cursor-pointer"
                >
                  {isAr ? 'إلغاء' : 'Cancel'}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  className="rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] cursor-pointer disabled:opacity-50"
                >
                  {processing ? (isAr ? 'جاري الحفظ...' : 'Saving...') : (isAr ? 'حفظ البيانات' : 'Save Supplier')}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
