import { Head, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { CurrencyOption, SharedPageProps } from '../../Types';

type IncomingChequeRow = {
  id: string;
  cheque_number: string;
  customer_id: string;
  customer?: { id: string; code: string; name: string };
  bank_name: string;
  bank_account_id?: string | null;
  bank_account?: { id: string; name: string };
  due_date: string;
  currency: string;
  amount_minor: number;
  status: 'draft' | 'received' | 'deposited' | 'cleared' | 'bounced' | 'returned';
  notes?: string | null;
  created_at: string;
};

type IncomingChequesProps = SharedPageProps & {
  cheques: {
    data: IncomingChequeRow[];
    links: any[];
  };
  customers: Array<{ id: string; code: string; name: string }>;
  bankAccounts: Array<{ id: string; code: string; name: string }>;
  fiscalYears: Array<{ id: string; year: number; name: string }>;
  periods: Array<{ id: string; name: string; period_number: number }>;
  currencies: CurrencyOption[];
  filters: {
    status?: string;
    customer_id?: string;
  };
};

export default function IncomingChequesIndex({
  locale,
  cheques,
  customers = [],
  bankAccounts = [],
  fiscalYears = [],
  periods = [],
  currencies = [],
  filters,
}: IncomingChequesProps) {
  const isAr = locale === 'ar';
  const dict = getDictionary(locale);

  const [showCreateModal, setShowCreateModal] = useState(false);
  const [activeActionCheque, setActiveActionCheque] = useState<IncomingChequeRow | null>(null);
  const [actionType, setActionType] = useState<'receive' | 'deposit' | 'clear' | 'bounce' | 'return' | null>(null);

  // Form for creation
  const createForm = useForm({
    customer_id: '',
    cheque_number: '',
    bank_name: '',
    due_date: new Date().toISOString().split('T')[0],
    currency: 'EGP',
    amount: '',
    amount_minor: 0,
    notes: '',
  });

  // Form for status actions
  const actionForm = useForm({
    fiscal_year_id: fiscalYears[0]?.id || '',
    financial_period_id: periods[0]?.id || '',
    bank_account_id: bankAccounts[0]?.id || '',
    received_date: new Date().toISOString().split('T')[0],
    deposited_date: new Date().toISOString().split('T')[0],
    cleared_date: new Date().toISOString().split('T')[0],
    bounced_date: new Date().toISOString().split('T')[0],
    returned_date: new Date().toISOString().split('T')[0],
    bounce_reason: '',
    return_reason: '',
  });

  const submitCreate = (e: FormEvent) => {
    e.preventDefault();
    const amountVal = parseFloat(createForm.data.amount || '0');
    const minorVal = Math.round(amountVal * 100);

    createForm.transform((data) => ({
      ...data,
      amount_minor: minorVal,
    }));

    createForm.post('/incoming-cheques', {
      onSuccess: () => {
        setShowCreateModal(false);
        createForm.reset();
      },
    });
  };

  const openActionModal = (cheque: IncomingChequeRow, type: 'receive' | 'deposit' | 'clear' | 'bounce' | 'return') => {
    setActiveActionCheque(cheque);
    setActionType(type);
  };

  const submitAction = (e: FormEvent) => {
    e.preventDefault();
    if (!activeActionCheque || !actionType) return;

    actionForm.post(`/incoming-cheques/${activeActionCheque.id}/${actionType}`, {
      onSuccess: () => {
        setActiveActionCheque(null);
        setActionType(null);
      },
    });
  };

  const customerSelectOptions = customers.map((c) => ({ value: c.id, label: `${c.code} - ${c.name}` }));
  const bankSelectOptions = bankAccounts.map((b) => ({ value: b.id, label: `${b.code} - ${b.name}` }));
  const periodSelectOptions = periods.map((p) => ({ value: p.id, label: p.name }));
  const currencyOptions = currencies.map((c) => ({ value: c.code, label: `${c.code} (${c.name})` }));

  const statusToneMap: Record<string, 'muted' | 'info' | 'warning' | 'ok' | 'danger'> = {
    draft: 'muted',
    received: 'info',
    deposited: 'warning',
    cleared: 'ok',
    bounced: 'danger',
    returned: 'muted',
  };

  return (
    <AppLayout active="incoming-cheques.index">
      <Head title={isAr ? 'حافظة الشيكات الواردة - Mini ERP' : 'Incoming Cheques - Mini ERP'} />

      <PageHeader
        title={isAr ? 'حافظة الشيكات الواردة' : 'Incoming Cheques Register'}
        description={isAr ? 'متابعة وتحديث حالة الشيكات الواردة من العملاء (استلام، إيداع، تحصيل، ارتداد، إرجاع).' : 'Manage incoming cheques lifecycle state machine.'}
        actions={
          <button
            type="button"
            onClick={() => setShowCreateModal(true)}
            className="rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] transition-all cursor-pointer"
          >
            {isAr ? '+ إضافة شيك وارد' : '+ Add Incoming Cheque'}
          </button>
        }
      />

      <Card className="p-4 mb-6">
        <div className="flex flex-wrap items-center gap-3">
          <select
            defaultValue={filters.status || ''}
            onChange={(e) => {
              window.location.href = `/incoming-cheques?status=${e.target.value}`;
            }}
            className="rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-semibold text-[var(--text-primary)]"
          >
            <option value="">{isAr ? 'جميع الحالات' : 'All Statuses'}</option>
            <option value="draft">Draft</option>
            <option value="received">Received</option>
            <option value="deposited">Deposited</option>
            <option value="cleared">Cleared</option>
            <option value="bounced">Bounced</option>
            <option value="returned">Returned</option>
          </select>
        </div>
      </Card>

      {cheques.data.length === 0 ? (
        <EmptyState
          title={isAr ? 'لا يوجد شيكات واردة' : 'No Incoming Cheques Found'}
          description={isAr ? 'قم بإضافة اول شيك بالضغط على الزر اعلاه.' : 'Get started by creating your first incoming cheque.'}
        />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{isAr ? 'رقم الشيك' : 'Cheque No.'}</th>
                <th className={tableClasses.th}>{isAr ? 'العميل' : 'Customer'}</th>
                <th className={tableClasses.th}>{isAr ? 'البنك الساحب' : 'Drawn Bank'}</th>
                <th className={tableClasses.th}>{isAr ? 'تاريخ الاستحقاق' : 'Due Date'}</th>
                <th className={tableClasses.th}>{isAr ? 'المبلغ' : 'Amount'}</th>
                <th className={tableClasses.th}>{isAr ? 'الحالة الحالية' : 'Current Status'}</th>
                <th className={tableClasses.th}>{isAr ? 'الإجراءات المتاحة' : 'Valid Lifecycle Actions'}</th>
              </tr>
            </thead>
            <tbody>
              {cheques.data.map((row) => (
                <tr key={row.id} className="hover:bg-[var(--background)]/50 transition-colors">
                  <td className={`${tableClasses.td} font-mono font-bold text-xs`}>{row.cheque_number}</td>
                  <td className={`${tableClasses.td} font-semibold`}>
                    {row.customer ? `${row.customer.code} - ${row.customer.name}` : '—'}
                  </td>
                  <td className={tableClasses.td}>{row.bank_name}</td>
                  <td className={`${tableClasses.td} font-mono text-xs`}>{row.due_date}</td>
                  <td className={`${tableClasses.td} font-mono font-bold text-xs`}>
                    {formatMoney(row.amount_minor, row.currency)}
                  </td>
                  <td className={tableClasses.td}>
                    <StatusBadge tone={statusToneMap[row.status] || 'muted'}>
                      {row.status.toUpperCase()}
                    </StatusBadge>
                  </td>
                  <td className={tableClasses.td}>
                    <div className="flex flex-wrap gap-1">
                      {row.status === 'draft' ? (
                        <button
                          type="button"
                          onClick={() => openActionModal(row, 'receive')}
                          className="rounded-lg bg-blue-600/10 text-blue-600 dark:text-blue-400 px-2 py-1 text-[11px] font-bold hover:bg-blue-600/20 cursor-pointer"
                        >
                          {isAr ? 'استلام' : 'Receive'}
                        </button>
                      ) : null}

                      {row.status === 'received' ? (
                        <>
                          <button
                            type="button"
                            onClick={() => openActionModal(row, 'deposit')}
                            className="rounded-lg bg-amber-600/10 text-amber-600 dark:text-amber-400 px-2 py-1 text-[11px] font-bold hover:bg-amber-600/20 cursor-pointer"
                          >
                            {isAr ? 'إيداع بالبنك' : 'Deposit'}
                          </button>
                          <button
                            type="button"
                            onClick={() => openActionModal(row, 'return')}
                            className="rounded-lg bg-slate-600/10 text-slate-600 dark:text-slate-400 px-2 py-1 text-[11px] font-bold hover:bg-slate-600/20 cursor-pointer"
                          >
                            {isAr ? 'إرجاع للعميل' : 'Return'}
                          </button>
                        </>
                      ) : null}

                      {row.status === 'deposited' ? (
                        <>
                          <button
                            type="button"
                            onClick={() => openActionModal(row, 'clear')}
                            className="rounded-lg bg-emerald-600/10 text-emerald-600 dark:text-emerald-400 px-2 py-1 text-[11px] font-bold hover:bg-emerald-600/20 cursor-pointer"
                          >
                            {isAr ? 'تحصيل (تحويل رصيد)' : 'Clear'}
                          </button>
                          <button
                            type="button"
                            onClick={() => openActionModal(row, 'bounce')}
                            className="rounded-lg bg-red-600/10 text-red-600 dark:text-red-400 px-2 py-1 text-[11px] font-bold hover:bg-red-600/20 cursor-pointer"
                          >
                            {isAr ? 'ارتداد الشيك' : 'Bounce'}
                          </button>
                        </>
                      ) : null}

                      {row.status === 'cleared' ? (
                        <span className="text-[11px] font-mono text-[var(--text-muted)]">{isAr ? 'مُحصل (مُقفل)' : 'Terminal Cleared'}</span>
                      ) : null}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Creation Modal */}
      {showCreateModal ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
          <div className="w-full max-w-lg rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <h2 className="text-lg font-bold text-[var(--text-primary)] mb-4">
              {isAr ? 'إضافة شيك وارد جديد (مسودة)' : 'New Incoming Cheque'}
            </h2>

            <form onSubmit={submitCreate} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                  {isAr ? 'اختر العميل' : 'Customer'} *
                </label>
                <SearchableSelect
                  options={customerSelectOptions}
                  value={createForm.data.customer_id}
                  onChange={(val) => createForm.setData('customer_id', val || '')}
                  isClearable={false}
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {isAr ? 'رقم الشيك الفعلي' : 'Cheque Number'} *
                  </label>
                  <input
                    type="text"
                    value={createForm.data.cheque_number}
                    onChange={(e) => createForm.setData('cheque_number', e.target.value)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono font-bold text-[var(--text-primary)]"
                    required
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {isAr ? 'البنك الساحب' : 'Drawn Bank Name'} *
                  </label>
                  <input
                    type="text"
                    value={createForm.data.bank_name}
                    onChange={(e) => createForm.setData('bank_name', e.target.value)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)]"
                    required
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <DatePicker
                    label={isAr ? 'تاريخ الاستحقاق' : 'Due Date'}
                    value={createForm.data.due_date}
                    onChange={(val) => createForm.setData('due_date', val || '')}
                    required
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {isAr ? 'المبلغ' : 'Amount'} *
                  </label>
                  <input
                    type="number"
                    step="0.01"
                    value={createForm.data.amount}
                    onChange={(e) => createForm.setData('amount', e.target.value)}
                    className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-mono font-bold text-[var(--text-primary)]"
                    placeholder="0.00"
                    required
                  />
                </div>
              </div>

              <div className="flex justify-end gap-2 pt-4 border-t border-[var(--border)]">
                <button
                  type="button"
                  onClick={() => setShowCreateModal(false)}
                  className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] cursor-pointer"
                >
                  {isAr ? 'إلغاء' : 'Cancel'}
                </button>
                <button
                  type="submit"
                  disabled={createForm.processing}
                  className="rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] cursor-pointer disabled:opacity-50"
                >
                  {createForm.processing ? (isAr ? 'جاري الحفظ...' : 'Saving...') : (isAr ? 'حفظ الشيك' : 'Save Cheque')}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}

      {/* Lifecycle Action Modal */}
      {activeActionCheque && actionType ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
          <div className="w-full max-w-md rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <h2 className="text-base font-bold text-[var(--text-primary)] mb-2">
              {isAr ? `تحديث حالة الشيك رقم [${activeActionCheque.cheque_number}]` : `Update Cheque status [${activeActionCheque.cheque_number}]`}
            </h2>
            <p className="text-xs text-[var(--text-secondary)] mb-4">
              {isAr ? `الإجراء المطلوب: ${actionType.toUpperCase()}` : `Target action: ${actionType.toUpperCase()}`}
            </p>

            <form onSubmit={submitAction} className="space-y-4">
              {actionType === 'receive' ? (
                <>
                  <div>
                    <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                      {isAr ? 'الفترة المالية' : 'Financial Period'} *
                    </label>
                    <SearchableSelect
                      options={periodSelectOptions}
                      value={actionForm.data.financial_period_id}
                      onChange={(val) => actionForm.setData('financial_period_id', val || '')}
                      isClearable={false}
                    />
                  </div>
                  <div>
                    <DatePicker
                      label={isAr ? 'تاريخ الاستلام' : 'Received Date'}
                      value={actionForm.data.received_date}
                      onChange={(val) => actionForm.setData('received_date', val || '')}
                      required
                    />
                  </div>
                </>
              ) : null}

              {actionType === 'deposit' ? (
                <>
                  <div>
                    <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                      {isAr ? 'إيداع بالحساب البنكي' : 'Bank Account'} *
                    </label>
                    <SearchableSelect
                      options={bankSelectOptions}
                      value={actionForm.data.bank_account_id}
                      onChange={(val) => actionForm.setData('bank_account_id', val || '')}
                      isClearable={false}
                    />
                  </div>
                  <div>
                    <DatePicker
                      label={isAr ? 'تاريخ الإيداع' : 'Deposited Date'}
                      value={actionForm.data.deposited_date}
                      onChange={(val) => actionForm.setData('deposited_date', val || '')}
                      required
                    />
                  </div>
                </>
              ) : null}

              {actionType === 'clear' ? (
                <div>
                  <DatePicker
                    label={isAr ? 'تاريخ التحصيل' : 'Cleared Date'}
                    value={actionForm.data.cleared_date}
                    onChange={(val) => actionForm.setData('cleared_date', val || '')}
                    required
                  />
                </div>
              ) : null}

              {actionType === 'bounce' ? (
                <>
                  <div>
                    <DatePicker
                      label={isAr ? 'تاريخ الارتداد' : 'Bounced Date'}
                      value={actionForm.data.bounced_date}
                      onChange={(val) => actionForm.setData('bounced_date', val || '')}
                      required
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                      {isAr ? 'سبب الارتداد' : 'Bounce Reason'}
                    </label>
                    <input
                      type="text"
                      value={actionForm.data.bounce_reason}
                      onChange={(e) => actionForm.setData('bounce_reason', e.target.value)}
                      className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)]"
                    />
                  </div>
                </>
              ) : null}

              {actionType === 'return' ? (
                <>
                  <div>
                    <DatePicker
                      label={isAr ? 'تاريخ الإرجاع' : 'Returned Date'}
                      value={actionForm.data.returned_date}
                      onChange={(val) => actionForm.setData('returned_date', val || '')}
                      required
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                      {isAr ? 'سبب الإرجاع' : 'Return Reason'}
                    </label>
                    <input
                      type="text"
                      value={actionForm.data.return_reason}
                      onChange={(e) => actionForm.setData('return_reason', e.target.value)}
                      className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs text-[var(--text-primary)]"
                    />
                  </div>
                </>
              ) : null}

              <div className="flex justify-end gap-2 pt-4 border-t border-[var(--border)]">
                <button
                  type="button"
                  onClick={() => {
                    setActiveActionCheque(null);
                    setActionType(null);
                  }}
                  className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] cursor-pointer"
                >
                  {isAr ? 'إلغاء' : 'Cancel'}
                </button>
                <button
                  type="submit"
                  disabled={actionForm.processing}
                  className="rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-xs cursor-pointer disabled:opacity-50"
                >
                  {actionForm.processing ? (isAr ? 'جاري التنفيذ...' : 'Processing...') : (isAr ? 'تأكيد الإجراء' : 'Confirm Action')}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
