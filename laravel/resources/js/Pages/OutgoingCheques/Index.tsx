import { Head, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import { getDictionary, interpolate } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { CurrencyOption, SharedPageProps } from '../../Types';

type OutgoingChequeRow = {
  id: string;
  cheque_number: string;
  supplier_id: string;
  supplier?: { id: string; code: string; name: string };
  bank_account_id: string;
  bank_account?: { id: string; name: string };
  due_date: string;
  currency: string;
  amount_minor: number;
  status: 'draft' | 'issued' | 'cleared' | 'returned' | 'cancelled';
  notes?: string | null;
  created_at: string;
};

type OutgoingChequesProps = SharedPageProps & {
  cheques: {
    data: OutgoingChequeRow[];
    links: any[];
  };
  suppliers: Array<{ id: string; code: string; name: string }>;
  bankAccounts: Array<{ id: string; code: string; name: string }>;
  fiscalYears: Array<{ id: string; year: number; name: string }>;
  periods: Array<{ id: string; name: string; period_number: number }>;
  currencies: CurrencyOption[];
  filters: {
    status?: string;
    supplier_id?: string;
  };
};

export default function OutgoingChequesIndex({
  locale,
  cheques,
  suppliers = [],
  bankAccounts = [],
  fiscalYears = [],
  periods = [],
  currencies = [],
  filters,
}: OutgoingChequesProps) {
  const isAr = locale === 'ar';
  const dict = getDictionary(locale);
  const can = useCan();

  const [showCreateModal, setShowCreateModal] = useState(false);
  const [activeActionCheque, setActiveActionCheque] = useState<OutgoingChequeRow | null>(null);
  const [actionType, setActionType] = useState<'issue' | 'clear' | 'return' | 'cancel' | null>(null);

  // Form for creation
  const createForm = useForm({
    supplier_id: '',
    bank_account_id: bankAccounts[0]?.id || '',
    cheque_number: '',
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
    issued_date: new Date().toISOString().split('T')[0],
    cleared_date: new Date().toISOString().split('T')[0],
    returned_date: new Date().toISOString().split('T')[0],
    cancelled_date: new Date().toISOString().split('T')[0],
    return_reason: '',
    cancel_reason: '',
  });

  const submitCreate = (e: FormEvent) => {
    e.preventDefault();
    const amountVal = parseFloat(createForm.data.amount || '0');
    const minorVal = Math.round(amountVal * 100);

    createForm.transform((data) => ({
      ...data,
      amount_minor: minorVal,
    }));

    createForm.post('/outgoing-cheques', {
      onSuccess: () => {
        setShowCreateModal(false);
        createForm.reset();
      },
    });
  };

  const openActionModal = (cheque: OutgoingChequeRow, type: 'issue' | 'clear' | 'return' | 'cancel') => {
    setActiveActionCheque(cheque);
    setActionType(type);
  };

  const submitAction = (e: FormEvent) => {
    e.preventDefault();
    if (!activeActionCheque || !actionType) return;

    actionForm.post(`/outgoing-cheques/${activeActionCheque.id}/${actionType}`, {
      onSuccess: () => {
        setActiveActionCheque(null);
        setActionType(null);
      },
    });
  };

  const supplierSelectOptions = suppliers.map((s) => ({ value: s.id, label: `${s.code} - ${s.name}` }));
  const bankSelectOptions = bankAccounts.map((b) => ({ value: b.id, label: `${b.code} - ${b.name}` }));
  const periodSelectOptions = periods.map((p) => ({ value: p.id, label: p.name }));
  const currencyOptions = currencies.map((c) => ({ value: c.code, label: `${c.code} (${c.name})` }));

  const statusToneMap: Record<string, 'muted' | 'info' | 'warning' | 'ok' | 'danger'> = {
    draft: 'muted',
    issued: 'info',
    cleared: 'ok',
    returned: 'warning',
    cancelled: 'danger',
  };

  return (
    <AppLayout active="outgoing-cheques.index">
      <Head title={dict.app.pages.outgoingCheques.outgoingChequesMiniErp} />

      <PageHeader
        title={dict.app.pages.outgoingCheques.outgoingChequesRegister}
        description={dict.app.pages.outgoingCheques.manageOutgoingChequesLifecycleStateMachine}
        actions={
          can('cheques.create') ? (
            <button
              type="button"
              onClick={() => setShowCreateModal(true)}
              className="rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] transition-all cursor-pointer"
            >
              {dict.app.pages.outgoingCheques.addOutgoingCheque}
            </button>
          ) : null
        }
      />

      <Card className="p-4 mb-6">
        <div className="flex flex-wrap items-center gap-3">
          <select
            defaultValue={filters.status || ''}
            onChange={(e) => {
              window.location.href = `/outgoing-cheques?status=${e.target.value}`;
            }}
            className="rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-xs font-semibold text-[var(--text-primary)]"
          >
            <option value="">{dict.app.pages.outgoingCheques.allStatuses}</option>
            <option value="draft">Draft</option>
            <option value="issued">Issued</option>
            <option value="cleared">Cleared</option>
            <option value="returned">Returned</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
      </Card>

      {cheques.data.length === 0 ? (
        <EmptyState
          title={dict.app.pages.outgoingCheques.noOutgoingChequesFound}
          description={dict.app.pages.outgoingCheques.getStartedByCreatingYourFirst}
        />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{dict.app.pages.outgoingCheques.chequeNo}</th>
                <th className={tableClasses.th}>{dict.app.pages.outgoingCheques.supplier}</th>
                <th className={tableClasses.th}>{dict.app.pages.outgoingCheques.bankAccount}</th>
                <th className={tableClasses.th}>{dict.app.pages.outgoingCheques.dueDate}</th>
                <th className={tableClasses.th}>{dict.app.pages.outgoingCheques.amount}</th>
                <th className={tableClasses.th}>{dict.app.pages.outgoingCheques.currentStatus}</th>
                <th className={tableClasses.th}>{dict.app.pages.outgoingCheques.validLifecycleActions}</th>
              </tr>
            </thead>
            <tbody>
              {cheques.data.map((row) => (
                <tr key={row.id} className="hover:bg-[var(--background)]/50 transition-colors">
                  <td className={`${tableClasses.td} font-mono font-bold text-xs`}>{row.cheque_number}</td>
                  <td className={`${tableClasses.td} font-semibold`}>
                    {row.supplier ? `${row.supplier.code} - ${row.supplier.name}` : '—'}
                  </td>
                  <td className={tableClasses.td}>{row.bank_account?.name || '—'}</td>
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
                      {row.status === 'draft' && can('cheques.issue') ? (
                        <button
                          type="button"
                          onClick={() => openActionModal(row, 'issue')}
                          className="rounded-lg bg-blue-600/10 text-blue-600 dark:text-blue-400 px-2 py-1 text-[11px] font-bold hover:bg-blue-600/20 cursor-pointer"
                        >
                          {dict.app.pages.outgoingCheques.issue}
                        </button>
                      ) : null}

                      {row.status === 'issued' ? (
                        <>
                          {can('cheques.clear') ? (
                            <button
                              type="button"
                              onClick={() => openActionModal(row, 'clear')}
                              className="rounded-lg bg-emerald-600/10 text-emerald-600 dark:text-emerald-400 px-2 py-1 text-[11px] font-bold hover:bg-emerald-600/20 cursor-pointer"
                            >
                              {dict.app.pages.outgoingCheques.clear}
                            </button>
                          ) : null}
                          {can('cheques.return') ? (
                            <button
                              type="button"
                              onClick={() => openActionModal(row, 'return')}
                              className="rounded-lg bg-amber-600/10 text-amber-600 dark:text-amber-400 px-2 py-1 text-[11px] font-bold hover:bg-amber-600/20 cursor-pointer"
                            >
                              {dict.app.pages.outgoingCheques.return}
                            </button>
                          ) : null}
                          {can('cheques.cancel') ? (
                            <button
                              type="button"
                              onClick={() => openActionModal(row, 'cancel')}
                              className="rounded-lg bg-red-600/10 text-red-600 dark:text-red-400 px-2 py-1 text-[11px] font-bold hover:bg-red-600/20 cursor-pointer"
                            >
                              {dict.app.pages.outgoingCheques.cancel}
                            </button>
                          ) : null}
                        </>
                      ) : null}

                      {row.status === 'cleared' ? (
                        <span className="text-[11px] font-mono text-[var(--text-muted)]">{dict.app.pages.outgoingCheques.terminalCleared}</span>
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
              {dict.app.pages.outgoingCheques.newOutgoingCheque}
            </h2>

            <form onSubmit={submitCreate} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                  {dict.app.pages.outgoingCheques.supplier_2} *
                </label>
                <SearchableSelect
                  options={supplierSelectOptions}
                  value={createForm.data.supplier_id}
                  onChange={(val) => createForm.setData('supplier_id', val || '')}
                  isClearable={false}
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                  {dict.app.pages.outgoingCheques.bankAccount_2} *
                </label>
                <SearchableSelect
                  options={bankSelectOptions}
                  value={createForm.data.bank_account_id}
                  onChange={(val) => createForm.setData('bank_account_id', val || '')}
                  isClearable={false}
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {dict.app.pages.outgoingCheques.chequeNumber} *
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
                  <DatePicker
                    label={dict.app.pages.outgoingCheques.dueDate_2}
                    value={createForm.data.due_date}
                    onChange={(val) => createForm.setData('due_date', val || '')}
                    required
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {dict.app.pages.outgoingCheques.currency} *
                  </label>
                  <SearchableSelect
                    options={currencyOptions}
                    value={createForm.data.currency}
                    onChange={(val) => createForm.setData('currency', val || 'EGP')}
                    isClearable={false}
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {dict.app.pages.outgoingCheques.amount_2} *
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
                  {dict.app.pages.outgoingCheques.cancel_2}
                </button>
                <button
                  type="submit"
                  disabled={createForm.processing}
                  className="rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] cursor-pointer disabled:opacity-50"
                >
                  {createForm.processing ? dict.app.pages.outgoingCheques.saving : dict.app.pages.outgoingCheques.saveCheque}
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
              {interpolate(dict.app.pages.outgoingCheques.updateStatusTitle, { number: activeActionCheque.cheque_number })}
            </h2>
            <p className="text-xs text-[var(--text-secondary)] mb-4">
              {interpolate(dict.app.pages.outgoingCheques.targetAction, { action: (actionType ?? '').toUpperCase() })}
            </p>

            <form onSubmit={submitAction} className="space-y-4">
              {actionType === 'issue' ? (
                <>
                  <div>
                    <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                      {dict.app.pages.outgoingCheques.financialPeriod} *
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
                      label={dict.app.pages.outgoingCheques.issuedDate}
                      value={actionForm.data.issued_date}
                      onChange={(val) => actionForm.setData('issued_date', val || '')}
                      required
                    />
                  </div>
                </>
              ) : null}

              {actionType === 'clear' ? (
                <div>
                  <DatePicker
                    label={dict.app.pages.outgoingCheques.clearedDate}
                    value={actionForm.data.cleared_date}
                    onChange={(val) => actionForm.setData('cleared_date', val || '')}
                    required
                  />
                </div>
              ) : null}

              {actionType === 'return' ? (
                <>
                  <div>
                    <DatePicker
                      label={dict.app.pages.outgoingCheques.returnedDate}
                      value={actionForm.data.returned_date}
                      onChange={(val) => actionForm.setData('returned_date', val || '')}
                      required
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                      {dict.app.pages.outgoingCheques.returnReason}
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

              {actionType === 'cancel' ? (
                <>
                  <div>
                    <DatePicker
                      label={dict.app.pages.outgoingCheques.cancelledDate}
                      value={actionForm.data.cancelled_date}
                      onChange={(val) => actionForm.setData('cancelled_date', val || '')}
                      required
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                      {dict.app.pages.outgoingCheques.cancelReason}
                    </label>
                    <input
                      type="text"
                      value={actionForm.data.cancel_reason}
                      onChange={(e) => actionForm.setData('cancel_reason', e.target.value)}
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
                  {dict.app.pages.outgoingCheques.cancel_3}
                </button>
                <button
                  type="submit"
                  disabled={actionForm.processing}
                  className="rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-xs cursor-pointer disabled:opacity-50"
                >
                  {actionForm.processing ? dict.app.pages.outgoingCheques.processing : dict.app.pages.outgoingCheques.confirmAction}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
