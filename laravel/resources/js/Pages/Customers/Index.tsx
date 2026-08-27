import { Head, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, EmptyState, PageHeader, StatusBadge, tableClasses } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';

type CustomerRow = {
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

type CustomerStatus = CustomerRow['status'];

type CustomersProps = SharedPageProps & {
  customers: {
    data: CustomerRow[];
    links: any[];
  };
  filters: {
    search?: string;
    status?: string;
  };
};

function toCustomerStatus(value: string): CustomerStatus {
  return value === 'inactive' ? 'inactive' : 'active';
}

export default function CustomersIndex({ locale, customers, filters }: CustomersProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const can = useCan();

  const [showModal, setShowModal] = useState(false);
  const [editingCustomer, setEditingCustomer] = useState<CustomerRow | null>(null);

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
    setEditingCustomer(null);
    reset();
    setShowModal(true);
  };

  const openEditModal = (customer: CustomerRow) => {
    setEditingCustomer(customer);
    setData({
      code: customer.code,
      name: customer.name,
      status: customer.status,
      email: customer.email || '',
      phone: customer.phone || '',
      address: customer.address || '',
      tax_number: customer.tax_number || '',
      lock_version: customer.lock_version,
    });
    setShowModal(true);
  };

  const submit = (e: FormEvent) => {
    e.preventDefault();
    if (editingCustomer) {
      patch(`/customers/${editingCustomer.id}`, {
        onSuccess: () => {
          setShowModal(false);
          reset();
        },
      });
    } else {
      post('/customers', {
        onSuccess: () => {
          setShowModal(false);
          reset();
        },
      });
    }
  };

  return (
    <AppLayout active="customers.index">
      <Head title={dict.app.pages.customers.customersMiniErp} />

      <PageHeader
        title={dict.app.pages.customers.customerMasterData}
        description={dict.app.pages.customers.manageCustomerRecordsTaxNumbersAnd}
        actions={
          can('customers.create') ? (
            <button
              type="button"
              onClick={openCreateModal}
              className="rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] transition-all cursor-pointer"
            >
              {dict.app.pages.customers.createCustomer}
            </button>
          ) : null
        }
      />

      <Card className="p-4 mb-6">
        <div className="flex flex-wrap items-center gap-3">
          <input
            type="text"
            placeholder={dict.app.pages.customers.searchByCodeNamePhone}
            defaultValue={filters.search || ''}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                const target = e.target as HTMLInputElement;
                window.location.href = `/customers?search=${encodeURIComponent(target.value)}`;
              }
            }}
            className="w-72 rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2 text-xs text-[var(--text-primary)] outline-hidden focus:border-[var(--primary)]"
          />
        </div>
      </Card>

      {customers.data.length === 0 ? (
        <EmptyState
          title={dict.app.pages.customers.noCustomersFound}
          description={dict.app.pages.customers.getStartedByCreatingYourFirst}
        />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{dict.app.pages.customers.code}</th>
                <th className={tableClasses.th}>{dict.app.pages.customers.name}</th>
                <th className={tableClasses.th}>{dict.app.pages.customers.phone}</th>
                <th className={tableClasses.th}>{dict.app.pages.customers.email}</th>
                <th className={tableClasses.th}>{dict.app.pages.customers.taxNumber}</th>
                <th className={tableClasses.th}>{dict.app.pages.customers.status}</th>
                <th className={tableClasses.th}>{dict.app.pages.customers.actions}</th>
              </tr>
            </thead>
            <tbody>
              {customers.data.map((customer) => (
                <tr key={customer.id} className="hover:bg-[var(--background)]/50 transition-colors">
                  <td className={`${tableClasses.td} font-mono font-bold text-xs`}>{customer.code}</td>
                  <td className={`${tableClasses.td} font-semibold`}>{customer.name}</td>
                  <td className={tableClasses.td}>{customer.phone || accDict.notAvailable}</td>
                  <td className={tableClasses.td}>{customer.email || accDict.notAvailable}</td>
                  <td className={`${tableClasses.td} font-mono text-xs`}>{customer.tax_number || accDict.notAvailable}</td>
                  <td className={tableClasses.td}>
                    <StatusBadge tone={customer.status === 'active' ? 'ok' : 'muted'}>
                      {customer.status === 'active' ? dict.app.pages.customers.active_2 : dict.app.pages.customers.inactive_2}
                    </StatusBadge>
                  </td>
                  <td className={tableClasses.td}>
                    {can('customers.edit') ? (
                      <button
                        type="button"
                        onClick={() => openEditModal(customer)}
                        className="text-xs font-bold text-[var(--primary)] hover:underline cursor-pointer"
                      >
                        {dict.app.pages.customers.edit}
                      </button>
                    ) : null}
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
              {editingCustomer ? dict.app.pages.customers.editCustomer : dict.app.pages.customers.createNewCustomer}
            </h2>

            <form onSubmit={submit} className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {dict.app.pages.customers.code_2} *
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
                    {dict.app.pages.customers.status_2} *
                  </label>
                  <select
                    value={data.status}
                    onChange={(e) => setData('status', toCustomerStatus(e.target.value))}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-semibold text-[var(--text-primary)]"
                  >
                    <option value="active">{dict.app.pages.customers.active}</option>
                    <option value="inactive">{dict.app.pages.customers.inactive}</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                  {dict.app.pages.customers.customerName} *
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
                    {dict.app.pages.customers.phone_2}
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
                    {dict.app.pages.customers.email_2}
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
                  {dict.app.pages.customers.taxNumber_2}
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
                  {dict.app.pages.customers.address}
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
                  {dict.app.pages.customers.cancel}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  className="rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] cursor-pointer disabled:opacity-50"
                >
                  {processing ? dict.app.pages.customers.saving : dict.app.pages.customers.saveCustomer}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
