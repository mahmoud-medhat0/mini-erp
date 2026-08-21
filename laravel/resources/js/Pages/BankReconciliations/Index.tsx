import { Head, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { CurrencyOption, SharedPageProps } from '../../Types';

type BankReconciliationRow = {
  id: string;
  bank_account_id: string;
  bank_account?: { id: string; code: string; name: string; currency: string };
  financial_period_id: string;
  statement_reference?: string | null;
  date_from: string;
  date_to: string;
  statement_opening_balance_minor: number;
  statement_closing_balance_minor: number;
  status: 'draft' | 'finalized';
  finalized_at?: string | null;
  created_at: string;
};

type BankReconciliationsProps = SharedPageProps & {
  reconciliations: {
    data: BankReconciliationRow[];
    links: any[];
  };
  bankAccounts: Array<{ id: string; code: string; name: string; currency: string }>;
  periods: Array<{ id: string; name: string; period_number: number }>;
  currencies: CurrencyOption[];
  filters: {
    status?: string;
    bank_account_id?: string;
  };
};

export default function BankReconciliationsIndex({
  locale,
  reconciliations,
  bankAccounts = [],
  periods = [],
  currencies = [],
  filters,
}: BankReconciliationsProps) {
  const isAr = locale === 'ar';
  const dict = getDictionary(locale);

  const [showModal, setShowModal] = useState(false);

  const { data, setData, post, transform, processing, errors, reset } = useForm({
    bank_account_id: bankAccounts[0]?.id || '',
    financial_period_id: periods[0]?.id || '',
    statement_reference: '',
    date_from: new Date().toISOString().split('T')[0],
    date_to: new Date().toISOString().split('T')[0],
    statement_opening_balance: '0',
    statement_closing_balance: '0',
    statement_opening_balance_minor: 0,
    statement_closing_balance_minor: 0,
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    const openVal = parseFloat(data.statement_opening_balance || '0');
    const closeVal = parseFloat(data.statement_closing_balance || '0');

    transform((data) => ({
      ...data,
      statement_opening_balance_minor: Math.round(openVal * 100),
      statement_closing_balance_minor: Math.round(closeVal * 100),
    }));

    post('/bank-reconciliations', {
      onSuccess: () => {
        setShowModal(false);
        reset();
      },
    });
  };

  const bankSelectOptions = bankAccounts.map((b) => ({ value: b.id, label: `${b.code} - ${b.name} (${b.currency})` }));
  const periodSelectOptions = periods.map((p) => ({ value: p.id, label: p.name }));

  return (
    <AppLayout active="bank-reconciliations.index">
      <Head title={isAr ? 'تسوية كشوف البنوك - Mini ERP' : 'Bank Reconciliations - Mini ERP'} />

      <PageHeader
        title={isAr ? 'تسوية كشوف الحسابات البنكية' : 'Bank Reconciliations'}
        description={isAr ? 'إدراج كشوف الحسابات البنكية وتطبيق المطابقة الإلكترونية مع قيود حركة البنك بالأستاذ العام.' : 'Match bank statement lines with general ledger entries.'}
        actions={
          <button
            type="button"
            onClick={() => {
              reset();
              setShowModal(true);
            }}
            className="rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] transition-all cursor-pointer"
          >
            {isAr ? '+ إنشاء كشف تسوية بنك' : '+ New Bank Reconciliation'}
          </button>
        }
      />

      <Card className="p-4 mb-6">
        <div className="flex flex-wrap items-center gap-3">
          <select
            defaultValue={filters.status || ''}
            onChange={(e) => {
              window.location.href = `/bank-reconciliations?status=${e.target.value}`;
            }}
            className="rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-semibold text-[var(--text-primary)]"
          >
            <option value="">{isAr ? 'جميع الحالات' : 'All Statuses'}</option>
            <option value="draft">Draft</option>
            <option value="finalized">Finalized</option>
          </select>
        </div>
      </Card>

      {reconciliations.data.length === 0 ? (
        <EmptyState
          title={isAr ? 'لا يوجد كشوف تسوية بنك' : 'No Bank Reconciliations Found'}
          description={isAr ? 'قم بإنشاء أول كشف تسوية بالضغط على الزر أعلاه.' : 'Get started by creating your first bank reconciliation statement.'}
        />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{isAr ? 'الحساب البنكي' : 'Bank Account'}</th>
                <th className={tableClasses.th}>{isAr ? 'مرجع الكشف' : 'Statement Ref'}</th>
                <th className={tableClasses.th}>{isAr ? 'الفترة' : 'Period Range'}</th>
                <th className={tableClasses.th}>{isAr ? 'رصيد بداية الكشف' : 'Opening Balance'}</th>
                <th className={tableClasses.th}>{isAr ? 'رصيد نهاية الكشف' : 'Closing Balance'}</th>
                <th className={tableClasses.th}>{isAr ? 'الحالة' : 'Status'}</th>
                <th className={tableClasses.th}>{isAr ? 'إجراءات' : 'Actions'}</th>
              </tr>
            </thead>
            <tbody>
              {reconciliations.data.map((row) => (
                <tr key={row.id} className="hover:bg-[var(--background)]/50 transition-colors">
                  <td className={`${tableClasses.td} font-semibold`}>
                    {row.bank_account ? `${row.bank_account.code} - ${row.bank_account.name}` : '—'}
                  </td>
                  <td className={`${tableClasses.td} font-mono text-xs`}>{row.statement_reference || '—'}</td>
                  <td className={`${tableClasses.td} font-mono text-xs`}>
                    {row.date_from} → {row.date_to}
                  </td>
                  <td className={`${tableClasses.td} font-mono text-xs`}>
                    {formatMoney(row.statement_opening_balance_minor, row.bank_account?.currency || 'EGP')}
                  </td>
                  <td className={`${tableClasses.td} font-mono font-bold text-xs`}>
                    {formatMoney(row.statement_closing_balance_minor, row.bank_account?.currency || 'EGP')}
                  </td>
                  <td className={tableClasses.td}>
                    <StatusBadge tone={row.status === 'finalized' ? 'ok' : 'warning'}>
                      {row.status === 'finalized' ? (isAr ? 'مُعتمد ومُقفل' : 'Finalized') : (isAr ? 'مسودة' : 'Draft')}
                    </StatusBadge>
                  </td>
                  <td className={tableClasses.td}>
                    <a
                      href={`/bank-reconciliations/${row.id}`}
                      className="text-xs font-bold text-[var(--primary)] hover:underline"
                    >
                      {row.status === 'draft' ? (isAr ? 'فتح الشاشة والمطابقة' : 'Open Workspace') : (isAr ? 'عرض الكشف' : 'View Statement')}
                    </a>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Creation Modal */}
      {showModal ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
          <div className="w-full max-w-lg rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <h2 className="text-lg font-bold text-[var(--text-primary)] mb-4">
              {isAr ? 'إنشاء كشف تسوية بنك جديد' : 'New Bank Reconciliation'}
            </h2>

            <form onSubmit={submit} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                  {isAr ? 'اختر الحساب البنكي' : 'Bank Account'} *
                </label>
                <SearchableSelect
                  options={bankSelectOptions}
                  value={data.bank_account_id}
                  onChange={(val) => setData('bank_account_id', val || '')}
                  isClearable={false}
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {isAr ? 'الفترة المالية' : 'Financial Period'} *
                  </label>
                  <SearchableSelect
                    options={periodSelectOptions}
                    value={data.financial_period_id}
                    onChange={(val) => setData('financial_period_id', val || '')}
                    isClearable={false}
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {isAr ? 'مرجع الكشف البنكي' : 'Statement Reference'}
                  </label>
                  <input
                    type="text"
                    value={data.statement_reference}
                    onChange={(e) => setData('statement_reference', e.target.value)}
                    placeholder="STMT-2026-08"
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono text-[var(--text-primary)]"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <DatePicker
                    label={isAr ? 'من تاريخ' : 'Date From'}
                    value={data.date_from}
                    onChange={(val) => setData('date_from', val || '')}
                    required
                  />
                </div>
                <div>
                  <DatePicker
                    label={isAr ? 'إلى تاريخ' : 'Date To'}
                    value={data.date_to}
                    onChange={(val) => setData('date_to', val || '')}
                    required
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {isAr ? 'رصيد بداية الكشف البنكي' : 'Opening Statement Balance'} *
                  </label>
                  <input
                    type="number"
                    step="0.01"
                    value={data.statement_opening_balance}
                    onChange={(e) => setData('statement_opening_balance', e.target.value)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono font-bold text-[var(--text-primary)]"
                    placeholder="0.00"
                    required
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {isAr ? 'رصيد نهاية الكشف البنكي' : 'Closing Statement Balance'} *
                  </label>
                  <input
                    type="number"
                    step="0.01"
                    value={data.statement_closing_balance}
                    onChange={(e) => setData('statement_closing_balance', e.target.value)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono font-bold text-[var(--text-primary)]"
                    placeholder="0.00"
                    required
                  />
                </div>
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
                  {processing ? (isAr ? 'جاري الإنشاء...' : 'Creating...') : (isAr ? 'إنشاء وفتح الشاشة' : 'Create & Open Workspace')}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
