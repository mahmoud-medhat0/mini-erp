import { Head, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Button, Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatMoney, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary, interpolate } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { CurrencyOption, PaginationLink, SharedPageProps } from '../../Types';

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
    links: PaginationLink[];
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
  const pageDict = dict.app.pages.incomingCheques;
  const accDict = dict.app.accounting;
  const can = useCan();
  const canCreateCheques = can('cheques.create');
  const canReceiveIncomingCheques = can('cheques.receive');
  const canDepositIncomingCheques = can('cheques.deposit');
  const canClearIncomingCheques = can('cheques.clear');
  const canBounceIncomingCheques = can('cheques.bounce');
  const canReturnIncomingCheques = can('cheques.return');

  const [showCreateModal, setShowCreateModal] = useState(false);
  const [activeActionCheque, setActiveActionCheque] = useState<IncomingChequeRow | null>(null);
  const [actionType, setActionType] = useState<'receive' | 'deposit' | 'clear' | 'bounce' | 'return' | null>(null);

  // Form for creation
  const createForm = useForm({
    customer_id: '',
    cheque_number: '',
    bank_name: '',
    due_date: new Date().toISOString().split('T')[0],
    currency: '',
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
  const statusOptions = [
    { value: 'draft', label: pageDict.statuses.draft },
    { value: 'received', label: pageDict.statuses.received },
    { value: 'deposited', label: pageDict.statuses.deposited },
    { value: 'cleared', label: pageDict.statuses.cleared },
    { value: 'bounced', label: pageDict.statuses.bounced },
    { value: 'returned', label: pageDict.statuses.returned },
  ];
  const activeFilterCount = [filters.status, filters.customer_id].filter(Boolean).length;

  const applyFilters = (next: Record<string, string>) => {
    const status = next.status ?? filters.status ?? '';
    const customerId = next.customer_id ?? filters.customer_id ?? '';
    const params: Record<string, string> = {};

    if (status) params.status = status;
    if (customerId) params.customer_id = customerId;

    router.get('/incoming-cheques', params, { preserveScroll: true, preserveState: true });
  };

  function clearFilters() {
    router.get('/incoming-cheques', {}, { preserveScroll: true, preserveState: true });
  }

  const statusToneMap: Record<string, 'muted' | 'info' | 'warning' | 'ok' | 'danger'> = {
    draft: 'muted',
    received: 'info',
    deposited: 'warning',
    cleared: 'ok',
    bounced: 'danger',
    returned: 'muted',
  };

  const isIncomingChequeActionable = (cheque: IncomingChequeRow) => ['draft', 'received', 'deposited'].includes(cheque.status);

  const hasAvailableIncomingChequeAction = (cheque: IncomingChequeRow) => (
    cheque.status === 'draft'
      ? canReceiveIncomingCheques
      : cheque.status === 'received'
        ? canDepositIncomingCheques || canReturnIncomingCheques
        : cheque.status === 'deposited'
          ? canClearIncomingCheques || canBounceIncomingCheques
          : false
  );

  const getIncomingChequeActionState = (cheque: IncomingChequeRow) => {
    if (hasAvailableIncomingChequeAction(cheque)) return null;

    return isIncomingChequeActionable(cheque) ? dict.app.actions.restricted : dict.app.actions.noActions;
  };

  return (
    <AppLayout active="incoming-cheques.index">
      <Head title={dict.app.pages.incomingCheques.incomingChequesMiniErp} />

      <PageHeader
        title={dict.app.pages.incomingCheques.incomingChequesRegister}
        description={dict.app.pages.incomingCheques.manageIncomingChequesLifecycleStateMachine}
        actions={
          canCreateCheques ? (
            <button
              type="button"
              onClick={() => setShowCreateModal(true)}
              title={dict.app.pages.incomingCheques.addIncomingCheque}
              aria-label={dict.app.pages.incomingCheques.addIncomingCheque}
              className="rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] transition-all cursor-pointer"
            >
              {dict.app.pages.incomingCheques.addIncomingCheque}
            </button>
          ) : null
        }
      />

      <Card className="p-4 mb-6">
        <div className="flex flex-wrap items-center gap-3">
          <SearchableSelect
            options={[{ value: '', label: pageDict.allStatuses }, ...statusOptions]}
            value={filters.status || ''}
            onChange={(value) => applyFilters({ status: value || '' })}
            className="w-48"
            isSearchable={false}
          />
          <SearchableSelect
            options={[{ value: '', label: pageDict.customer }, ...customerSelectOptions]}
            value={filters.customer_id || ''}
            onChange={(value) => applyFilters({ customer_id: value || '' })}
            className="w-72"
            isSearchable
          />
          <Button variant="secondary" onClick={clearFilters} disabled={activeFilterCount === 0}>{accDict.clearFilters}</Button>
        </div>
      </Card>

      {cheques.data.length === 0 ? (
        <EmptyState
          title={dict.app.pages.incomingCheques.noIncomingChequesFound}
          description={dict.app.pages.incomingCheques.getStartedByCreatingYourFirst}
        />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{dict.app.pages.incomingCheques.chequeNo}</th>
                <th className={tableClasses.th}>{dict.app.pages.incomingCheques.customer}</th>
                <th className={tableClasses.th}>{dict.app.pages.incomingCheques.drawnBank}</th>
                <th className={tableClasses.th}>{dict.app.pages.incomingCheques.dueDate}</th>
                <th className={tableClasses.th}>{dict.app.pages.incomingCheques.amount}</th>
                <th className={tableClasses.th}>{dict.app.pages.incomingCheques.currentStatus}</th>
                <th className={tableClasses.th}>{dict.app.pages.incomingCheques.validLifecycleActions}</th>
              </tr>
            </thead>
            <tbody>
              {cheques.data.map((row) => {
                const actionState = getIncomingChequeActionState(row);

                return (
                  <tr key={row.id} className="hover:bg-[var(--background)]/50 transition-colors">
                    <td className={`${tableClasses.td} font-mono font-bold text-xs`}>{row.cheque_number}</td>
                    <td className={`${tableClasses.td} font-semibold`}>
                      {row.customer ? `${row.customer.code} - ${getLocalizedName(row.customer.name, locale)}` : accDict.notAvailable}
                    </td>
                    <td className={tableClasses.td}>{row.bank_name}</td>
                    <td className={`${tableClasses.td} font-mono text-xs`}>{row.due_date}</td>
                    <td className={`${tableClasses.td} font-mono font-bold text-xs`}>
                      {formatMoney(row.amount_minor, row.currency)}
                    </td>
                    <td className={tableClasses.td}>
                      <StatusBadge tone={statusToneMap[row.status] || 'muted'}>
                        {pageDict.statuses[row.status]}
                      </StatusBadge>
                    </td>
                    <td className={tableClasses.td}>
                      <div className="flex flex-wrap items-center justify-end gap-2">
                        {row.status === 'draft' && canReceiveIncomingCheques ? (
                          <button
                            type="button"
                            onClick={() => openActionModal(row, 'receive')}
                            title={pageDict.receive}
                            aria-label={pageDict.receive}
                            className="inline-flex h-8 items-center rounded-md border border-blue-200 px-2.5 text-xs font-semibold text-blue-700 transition-colors hover:bg-blue-50 dark:border-blue-900/60 dark:text-blue-300 dark:hover:bg-blue-950/40"
                          >
                            {pageDict.receive}
                          </button>
                        ) : null}

                        {row.status === 'received' && canDepositIncomingCheques ? (
                          <button
                            type="button"
                            onClick={() => openActionModal(row, 'deposit')}
                            title={pageDict.deposit}
                            aria-label={pageDict.deposit}
                            className="inline-flex h-8 items-center rounded-md border border-amber-200 px-2.5 text-xs font-semibold text-amber-700 transition-colors hover:bg-amber-50 dark:border-amber-900/60 dark:text-amber-300 dark:hover:bg-amber-950/40"
                          >
                            {pageDict.deposit}
                          </button>
                        ) : null}

                        {row.status === 'received' && canReturnIncomingCheques ? (
                          <button
                            type="button"
                            onClick={() => openActionModal(row, 'return')}
                            title={pageDict.return}
                            aria-label={pageDict.return}
                            className="inline-flex h-8 items-center rounded-md border border-slate-200 px-2.5 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-800 dark:text-slate-300 dark:hover:bg-slate-900/50"
                          >
                            {pageDict.return}
                          </button>
                        ) : null}

                        {row.status === 'deposited' && canClearIncomingCheques ? (
                          <button
                            type="button"
                            onClick={() => openActionModal(row, 'clear')}
                            title={pageDict.clear}
                            aria-label={pageDict.clear}
                            className="inline-flex h-8 items-center rounded-md border border-emerald-200 px-2.5 text-xs font-semibold text-emerald-700 transition-colors hover:bg-emerald-50 dark:border-emerald-900/60 dark:text-emerald-300 dark:hover:bg-emerald-950/40"
                          >
                            {pageDict.clear}
                          </button>
                        ) : null}

                        {row.status === 'deposited' && canBounceIncomingCheques ? (
                          <button
                            type="button"
                            onClick={() => openActionModal(row, 'bounce')}
                            title={pageDict.bounce}
                            aria-label={pageDict.bounce}
                            className="inline-flex h-8 items-center rounded-md border border-red-200 px-2.5 text-xs font-semibold text-red-700 transition-colors hover:bg-red-50 dark:border-red-900/60 dark:text-red-300 dark:hover:bg-red-950/40"
                          >
                            {pageDict.bounce}
                          </button>
                        ) : null}

                        {actionState ? <StatusBadge tone="muted">{actionState}</StatusBadge> : null}
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}

      {/* Creation Modal */}
      {showCreateModal ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
          <div className="w-full max-w-lg rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <h2 className="text-lg font-bold text-[var(--text-primary)] mb-4">
              {dict.app.pages.incomingCheques.newIncomingCheque}
            </h2>

            <form onSubmit={submitCreate} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                  {dict.app.pages.incomingCheques.customer_2} *
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
                    {dict.app.pages.incomingCheques.chequeNumber} *
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
                    {dict.app.pages.incomingCheques.drawnBankName} *
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
                    label={dict.app.pages.incomingCheques.dueDate_2}
                    value={createForm.data.due_date}
                    onChange={(val) => createForm.setData('due_date', val || '')}
                    required
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {dict.app.pages.incomingCheques.currency} *
                  </label>
                  <SearchableSelect
                    options={currencyOptions}
                    value={createForm.data.currency}
                    onChange={(val) => createForm.setData('currency', val || '')}
                    isClearable={false}
                  />
                </div>
              </div>

              <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {dict.app.pages.incomingCheques.amount_2} *
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

              <div className="flex justify-end gap-2 pt-4 border-t border-[var(--border)]">
                <button
                  type="button"
                  onClick={() => setShowCreateModal(false)}
                  title={dict.app.pages.incomingCheques.cancel}
                  aria-label={dict.app.pages.incomingCheques.cancel}
                  className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] cursor-pointer"
                >
                  {dict.app.pages.incomingCheques.cancel}
                </button>
                <button
                  type="submit"
                  disabled={createForm.processing}
                  title={dict.app.pages.incomingCheques.saveCheque}
                  aria-label={dict.app.pages.incomingCheques.saveCheque}
                  className="rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] cursor-pointer disabled:opacity-50"
                >
                  {createForm.processing ? dict.app.pages.incomingCheques.saving : dict.app.pages.incomingCheques.saveCheque}
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
              {interpolate(dict.app.pages.incomingCheques.updateStatusTitle, { number: activeActionCheque.cheque_number })}
            </h2>
            <p className="text-xs text-[var(--text-secondary)] mb-4">
              {interpolate(dict.app.pages.incomingCheques.targetAction, { action: (actionType ?? '').toUpperCase() })}
            </p>

            <form onSubmit={submitAction} className="space-y-4">
              {actionType === 'receive' ? (
                <>
                  <div>
                    <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                      {dict.app.pages.incomingCheques.financialPeriod} *
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
                      label={dict.app.pages.incomingCheques.receivedDate}
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
                      {dict.app.pages.incomingCheques.bankAccount} *
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
                      label={dict.app.pages.incomingCheques.depositedDate}
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
                    label={dict.app.pages.incomingCheques.clearedDate}
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
                      label={dict.app.pages.incomingCheques.bouncedDate}
                      value={actionForm.data.bounced_date}
                      onChange={(val) => actionForm.setData('bounced_date', val || '')}
                      required
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                      {dict.app.pages.incomingCheques.bounceReason}
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
                      label={dict.app.pages.incomingCheques.returnedDate}
                      value={actionForm.data.returned_date}
                      onChange={(val) => actionForm.setData('returned_date', val || '')}
                      required
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                      {dict.app.pages.incomingCheques.returnReason}
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
                  title={dict.app.pages.incomingCheques.cancel_2}
                  aria-label={dict.app.pages.incomingCheques.cancel_2}
                  className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] cursor-pointer"
                >
                  {dict.app.pages.incomingCheques.cancel_2}
                </button>
                <button
                  type="submit"
                  disabled={actionForm.processing}
                  title={dict.app.pages.incomingCheques.confirmAction}
                  aria-label={dict.app.pages.incomingCheques.confirmAction}
                  className="rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-xs cursor-pointer disabled:opacity-50"
                >
                  {actionForm.processing ? dict.app.pages.incomingCheques.processing : dict.app.pages.incomingCheques.confirmAction}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
