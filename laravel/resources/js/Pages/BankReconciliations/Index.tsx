import { Head, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Button, Card, EmptyState, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import { useCan } from '../../lib/permissions';
import type { CurrencyOption, PaginationLink, SharedPageProps } from '../../Types';

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
    links: PaginationLink[];
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
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.bankReconciliations;
  const accDict = dict.app.accounting;
  const can = useCan();
  const canReconcileBanks = can('banks.reconcile');

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
  const formatBankAmount = (amountMinor: number, currency?: string | null): string => (currency ? formatMoney(amountMinor, currency) : accDict.notAvailable);
  const statusOptions = [
    { value: 'draft', label: pageDict.draft },
    { value: 'finalized', label: pageDict.finalized },
  ];
  const activeFilterCount = [filters.status, filters.bank_account_id].filter(Boolean).length;

  const applyFilters = (next: Record<string, string>) => {
    const status = next.status ?? filters.status ?? '';
    const bankAccountId = next.bank_account_id ?? filters.bank_account_id ?? '';
    const params: Record<string, string> = {};

    if (status) params.status = status;
    if (bankAccountId) params.bank_account_id = bankAccountId;

    router.get('/bank-reconciliations', params, { preserveScroll: true, preserveState: true });
  };

  function clearFilters() {
    router.get('/bank-reconciliations', {}, { preserveScroll: true, preserveState: true });
  }

  return (
    <AppLayout active="bank-reconciliations.index">
      <Head title={dict.app.pages.bankReconciliations.bankReconciliationsMiniErp} />

      <PageHeader
        title={dict.app.pages.bankReconciliations.bankReconciliations}
        description={dict.app.pages.bankReconciliations.matchBankStatementLinesWithGeneral}
        actions={
          canReconcileBanks ? (
            <button
              type="button"
              onClick={() => {
                reset();
                setShowModal(true);
              }}
              title={dict.app.pages.bankReconciliations.newBankReconciliation}
              aria-label={dict.app.pages.bankReconciliations.newBankReconciliation}
              className="rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] transition-all cursor-pointer"
            >
              {dict.app.pages.bankReconciliations.newBankReconciliation}
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
            options={[{ value: '', label: pageDict.bankAccount }, ...bankSelectOptions]}
            value={filters.bank_account_id || ''}
            onChange={(value) => applyFilters({ bank_account_id: value || '' })}
            className="w-80"
            isSearchable
          />
          <Button variant="secondary" onClick={clearFilters} disabled={activeFilterCount === 0}>{accDict.clearFilters}</Button>
        </div>
      </Card>

      {reconciliations.data.length === 0 ? (
        <EmptyState
          title={dict.app.pages.bankReconciliations.noBankReconciliationsFound}
          description={dict.app.pages.bankReconciliations.getStartedByCreatingYourFirst}
        />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{dict.app.pages.bankReconciliations.bankAccount}</th>
                <th className={tableClasses.th}>{dict.app.pages.bankReconciliations.statementRef}</th>
                <th className={tableClasses.th}>{dict.app.pages.bankReconciliations.periodRange}</th>
                <th className={tableClasses.th}>{dict.app.pages.bankReconciliations.openingBalance}</th>
                <th className={tableClasses.th}>{dict.app.pages.bankReconciliations.closingBalance}</th>
                <th className={tableClasses.th}>{dict.app.pages.bankReconciliations.status}</th>
                <th className={tableClasses.th}>{dict.app.pages.bankReconciliations.actions}</th>
              </tr>
            </thead>
            <tbody>
              {reconciliations.data.map((row) => (
                <tr key={row.id} className="hover:bg-[var(--background)]/50 transition-colors">
                  <td className={`${tableClasses.td} font-semibold`}>
                    {row.bank_account ? `${row.bank_account.code} - ${row.bank_account.name}` : accDict.notAvailable}
                  </td>
                  <td className={`${tableClasses.td} font-mono text-xs`}>{row.statement_reference || accDict.notAvailable}</td>
                  <td className={`${tableClasses.td} font-mono text-xs`}>
                    {row.date_from} → {row.date_to}
                  </td>
                  <td className={`${tableClasses.td} font-mono text-xs`}>
                    {formatBankAmount(row.statement_opening_balance_minor, row.bank_account?.currency)}
                  </td>
                  <td className={`${tableClasses.td} font-mono font-bold text-xs`}>
                    {formatBankAmount(row.statement_closing_balance_minor, row.bank_account?.currency)}
                  </td>
                  <td className={tableClasses.td}>
                    <StatusBadge tone={row.status === 'finalized' ? 'ok' : 'warning'}>
                      {row.status === 'finalized' ? dict.app.pages.bankReconciliations.finalized : dict.app.pages.bankReconciliations.draft}
                    </StatusBadge>
                  </td>
                  <td className={tableClasses.td}>
                    <div className="flex flex-wrap items-center justify-end gap-2">
                      <button
                        type="button"
                        onClick={() => router.get(`/bank-reconciliations/${row.id}`)}
                        title={row.status === 'draft' ? dict.app.pages.bankReconciliations.openWorkspace : dict.app.pages.bankReconciliations.viewStatement}
                        aria-label={row.status === 'draft' ? dict.app.pages.bankReconciliations.openWorkspace : dict.app.pages.bankReconciliations.viewStatement}
                        className="inline-flex h-8 items-center rounded-md border border-slate-200 px-2.5 text-xs font-semibold text-[var(--primary)] transition-colors hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-900/50"
                      >
                        {row.status === 'draft' ? dict.app.pages.bankReconciliations.openWorkspace : dict.app.pages.bankReconciliations.viewStatement}
                      </button>
                    </div>
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
              {dict.app.pages.bankReconciliations.newBankReconciliation_2}
            </h2>

            <form onSubmit={submit} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                  {dict.app.pages.bankReconciliations.bankAccount_2} *
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
                    {dict.app.pages.bankReconciliations.financialPeriod} *
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
                    {dict.app.pages.bankReconciliations.statementReference}
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
                    label={dict.app.pages.bankReconciliations.dateFrom}
                    value={data.date_from}
                    onChange={(val) => setData('date_from', val || '')}
                    required
                  />
                </div>
                <div>
                  <DatePicker
                    label={dict.app.pages.bankReconciliations.dateTo}
                    value={data.date_to}
                    onChange={(val) => setData('date_to', val || '')}
                    required
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-[var(--text-secondary)] uppercase mb-1">
                    {dict.app.pages.bankReconciliations.openingStatementBalance} *
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
                    {dict.app.pages.bankReconciliations.closingStatementBalance} *
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
                  title={dict.app.pages.bankReconciliations.cancel}
                  aria-label={dict.app.pages.bankReconciliations.cancel}
                  className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--background)] cursor-pointer"
                >
                  {dict.app.pages.bankReconciliations.cancel}
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  title={dict.app.pages.bankReconciliations.createOpenWorkspace}
                  aria-label={dict.app.pages.bankReconciliations.createOpenWorkspace}
                  className="rounded-xl bg-[var(--primary)] px-5 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] cursor-pointer disabled:opacity-50"
                >
                  {processing ? dict.app.pages.bankReconciliations.creating : dict.app.pages.bankReconciliations.createOpenWorkspace}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
