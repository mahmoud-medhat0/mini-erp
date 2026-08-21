import { Head, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import { Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import type { AccountOption, CurrencyOption, SharedPageProps } from '../../Types';

type BankAccountRow = {
  id: string;
  code: string;
  name: string;
  account_number: string;
  bank_name: string;
  currency: string;
  gl_account_id: string;
  gl_account?: { id: string; code: string; name: string };
  iban?: string | null;
  swift_code?: string | null;
  branch_name?: string | null;
  is_active: boolean;
  lock_version: number;
};

type BankAccountsProps = SharedPageProps & {
  bankAccounts: {
    data: BankAccountRow[];
    links: any[];
  };
  glAccounts: AccountOption[];
  currencies: CurrencyOption[];
  filters: {
    search?: string;
    status?: string;
  };
};

export default function BankAccountsIndex({ locale, bankAccounts, glAccounts = [], currencies = [], filters }: BankAccountsProps) {
  const isAr = locale === 'ar';
  const dict = getDictionary(locale);

  const [showModal, setShowModal] = useState(false);
  const [editingAccount, setEditingAccount] = useState<BankAccountRow | null>(null);

  const { data, setData, post, patch, processing, errors, reset } = useForm({
    code: '',
    name: '',
    account_number: '',
    bank_name: '',
    currency: 'EGP',
    gl_account_id: '',
    iban: '',
    swift_code: '',
    branch_name: '',
    is_active: true,
    lock_version: 0,
  });

  const openCreateModal = () => {
    setEditingAccount(null);
    reset();
    setShowModal(true);
  };

  const openEditModal = (acc: BankAccountRow) => {
    setEditingAccount(acc);
    setData({
      code: acc.code,
      name: acc.name,
      account_number: acc.account_number,
      bank_name: acc.bank_name,
      currency: acc.currency,
      gl_account_id: acc.gl_account_id,
      iban: acc.iban || '',
      swift_code: acc.swift_code || '',
      branch_name: acc.branch_name || '',
      is_active: acc.is_active,
      lock_version: acc.lock_version,
    });
    setShowModal(true);
  };

  const submit = (e: FormEvent) => {
    e.preventDefault();
    if (editingAccount) {
      patch(`/bank-accounts/${editingAccount.id}`, {
        onSuccess: () => {
          setShowModal(false);
          reset();
        },
      });
    } else {
      post('/bank-accounts', {
        onSuccess: () => {
          setShowModal(false);
          reset();
        },
      });
    }
  };

  const glSelectOptions = glAccounts.map((a) => ({
    value: a.id,
    label: `${a.code} - ${a.name}`,
  }));

  const currencyOptions = currencies.map((c) => ({
    value: c.code,
    label: `${c.code} (${c.name})`,
  }));

  return (
    <AppLayout active="bank-accounts.index">
      <Head title={isAr ? 'حسابات البنوك - Mini ERP' : 'Bank Accounts - Mini ERP'} />

      <PageHeader
        title={isAr ? 'حسابات البنوك' : 'Bank Accounts'}
        description={isAr ? 'إدارة الحسابات البنكية وأرقام الحسابات وتفاصيل IBAN وربطها بالأستاذ العام.' : 'Manage bank accounts, IBAN numbers, and linked GL asset accounts.'}
        actions={
          <button
            type="button"
            onClick={openCreateModal}
            className="rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] transition-all cursor-pointer"
          >
            {isAr ? '+ إضافة حساب بنكي' : '+ Create Bank Account'}
          </button>
        }
      />

      <Card className="p-4 mb-6">
        <div className="flex flex-wrap items-center gap-3">
          <input
            type="text"
            placeholder={isAr ? 'بحث بالكود، اسم البنك، أو رقم الحساب...' : 'Search by code, bank, account number...'}
            defaultValue={filters.search || ''}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                const target = e.target as HTMLInputElement;
                window.location.href = `/bank-accounts?search=${encodeURIComponent(target.value)}`;
              }
            }}
            className="w-72 rounded-xl border border-[var(--border)] bg-[var(--background)] px-3.5 py-2 text-xs text-[var(--text-primary)] outline-hidden focus:border-[var(--primary)]"
          />
        </div>
      </Card>

      {bankAccounts.data.length === 0 ? (
        <EmptyState
          title={isAr ? 'لا يوجد حسابات بنكية' : 'No Bank Accounts Found'}
          description={isAr ? 'قم بإضافة اول حساب بنكي بالضغط على زر الإنشاء اعلاه.' : 'Get started by creating your first bank account.'}
        />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{isAr ? 'الكود' : 'Code'}</th>
                <th className={tableClasses.th}>{isAr ? 'اسم البنك' : 'Bank Name'}</th>
                <th className={tableClasses.th}>{isAr ? 'رقم الحساب' : 'Account Number'}</th>
                <th className={tableClasses.th}>{isAr ? 'العملة' : 'Currency'}</th>
                <th className={tableClasses.th}>{isAr ? 'حساب الأستاذ المربوط' : 'Linked GL Account'}</th>
                <th className={tableClasses.th}>{isAr ? 'الحالة' : 'Status'}</th>
                <th className={tableClasses.th}>{isAr ? 'إجراءات' : 'Actions'}</th>
              </tr>
            </thead>
            <tbody>
              {bankAccounts.data.map((acc) => (
                <tr key={acc.id} className="hover:bg-[var(--background)]/50 transition-colors">
                  <td className={`${tableClasses.td} font-mono font-bold text-xs`}>{acc.code}</td>
                  <td className={`${tableClasses.td} font-semibold`}>{acc.bank_name} - {acc.name}</td>
                  <td className={`${tableClasses.td} font-mono text-xs`}>{acc.account_number}</td>
                  <td className={`${tableClasses.td} font-mono text-xs font-bold`}>{acc.currency}</td>
                  <td className={tableClasses.td}>
                    {acc.gl_account ? `${acc.gl_account.code} - ${acc.gl_account.name}` : '—'}
                  </td>
                  <td className={tableClasses.td}>
                    <StatusBadge tone={acc.is_active ? 'ok' : 'muted'}>
                      {acc.is_active ? (isAr ? 'نشط' : 'Active') : (isAr ? 'غير نشط' : 'Inactive')}
                    </StatusBadge>
                  </td>
                  <td className={tableClasses.td}>
                    <button
                      type="button"
                      onClick={() => openEditModal(acc)}
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
              {editingAccount ? (isAr ? 'تعديل الحساب البنكي' : 'Edit Bank Account') : (isAr ? 'إضافة حساب بنكي جديد' : 'Create Bank Account')}
            </h2>

            <form onSubmit={submit} className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {isAr ? 'كود الحساب' : 'Code'} *
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
                    {isAr ? 'العملة' : 'Currency'} *
                  </label>
                  <SearchableSelect
                    options={currencyOptions}
                    value={data.currency}
                    onChange={(val) => setData('currency', val || 'EGP')}
                    isClearable={false}
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                  {isAr ? 'اسم الحساب البنكي' : 'Account Label'} *
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
                    {isAr ? 'اسم البنك' : 'Bank Name'} *
                  </label>
                  <input
                    type="text"
                    value={data.bank_name}
                    onChange={(e) => setData('bank_name', e.target.value)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)]"
                    required
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {isAr ? 'رقم الحساب البنكي' : 'Account Number'} *
                  </label>
                  <input
                    type="text"
                    value={data.account_number}
                    onChange={(e) => setData('account_number', e.target.value)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono text-[var(--text-primary)]"
                    required
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                  {isAr ? 'حساب الأستاذ العام المربوط' : 'Linked Asset GL Account'} *
                </label>
                <SearchableSelect
                  options={glSelectOptions}
                  value={data.gl_account_id}
                  onChange={(val) => setData('gl_account_id', val || '')}
                  isClearable={false}
                />
                {errors.gl_account_id && <p className="text-xs text-red-500 mt-1">{errors.gl_account_id}</p>}
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    IBAN
                  </label>
                  <input
                    type="text"
                    value={data.iban}
                    onChange={(e) => setData('iban', e.target.value)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono text-[var(--text-primary)]"
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    SWIFT Code
                  </label>
                  <input
                    type="text"
                    value={data.swift_code}
                    onChange={(e) => setData('swift_code', e.target.value)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono text-[var(--text-primary)]"
                  />
                </div>
              </div>

              <div className="flex items-center gap-2 pt-2">
                <input
                  type="checkbox"
                  id="is_active"
                  checked={data.is_active}
                  onChange={(e) => setData('is_active', e.target.checked)}
                  className="rounded-md border-[var(--border)] text-[var(--primary)]"
                />
                <label htmlFor="is_active" className="text-xs font-semibold text-[var(--text-primary)]">
                  {isAr ? 'الحساب البنكي نشط' : 'Bank Account Active'}
                </label>
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
                  {processing ? (isAr ? 'جاري الحفظ...' : 'Saving...') : (isAr ? 'حفظ البيانات' : 'Save Account')}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
