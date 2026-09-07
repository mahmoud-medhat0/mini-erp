import { useMemo, useState, type ReactElement } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import SearchableSelect from '../../Components/SearchableSelect';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { Button, Card, PageHeader, StatusBadge } from '../../Components/Primitives';
import { formatDate, formatMoney, getLocalizedName } from '../../lib/accountingHelpers';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';
import { getDictionary } from '../../lib/i18n';

type ReconciliationRow = {
  id: string;
  bank_account?: { id: string; code: string; name: string; currency: string };
  bank_account_code?: string;
  bank_account_name?: Record<string, string> | string;
  bank_account_currency?: string;
  statement_reference: string;
  date_from: string;
  date_to: string;
  statement_opening_balance_minor: number;
  statement_closing_balance_minor: number;
  status: string;
  finalized_at: string | null;
  matched_statement_lines_count: number;
  total_statement_lines_count: number;
  difference_minor: number;
  summary?: {
    statement_movement_minor: number;
    system_movement_minor: number;
    matched_movement_minor: number;
    difference_minor: number;
    unmatched_statement_lines_count: number;
    matched_statement_lines_count: number;
    total_statement_lines_count: number;
  };
};

type BankReconciliationReportProps = SharedPageProps & {
  report: {
    filters: { bank_account_id: string | null; status: string | null; date_from: string | null; date_to: string | null };
    reconciliations: ReconciliationRow[];
  };
  bankAccounts: Array<{ id: string; code: string; name: string }>;
  filters: { bank_account_id: string | null; status: string | null; date_from: string | null; date_to: string | null };
};

export default function BankReconciliationReport({ locale, bankAccounts, filters }: BankReconciliationReportProps) {
  const dict = getDictionary(locale);
  const actionsDict = dict.app.actions;
  const can = useCan();
  const canPrint = can('reports.print') && can('view_financials');

  const [bankAccountId, setBankAccountId] = useState(filters.bank_account_id || '');
  const [status, setStatus] = useState(filters.status || '');

  const hasActiveFilters = Boolean(bankAccountId || status);

  const handleFilter = () => {
    router.get('/reports/bank-reconciliations', {
      bank_account_id: bankAccountId || undefined,
      status: status || undefined,
    }, { preserveScroll: true });
  };

  const handleReset = () => {
    setBankAccountId('');
    setStatus('');
    router.get('/reports/bank-reconciliations', {}, { preserveScroll: true });
  };

  const columns = useMemo(() => [
    { data: 'bank_account_name', name: 'bank_account_name', title: dict.app.pages.reportsBankReconciliation.bankAccount_2 },
    { data: 'statement_reference', name: 'statement_reference', title: dict.app.pages.reportsBankReconciliation.statementRef },
    { data: 'date_from', name: 'date_from', title: dict.app.pages.reportsBankReconciliation.period },
    { data: 'status', name: 'status', title: dict.app.pages.reportsBankReconciliation.status_2 },
    { data: 'matched_statement_lines_count', name: 'matched_statement_lines_count', title: dict.app.pages.reportsBankReconciliation.matchedTotal, searchable: false },
    { data: 'difference_minor', name: 'difference_minor', title: dict.app.pages.reportsBankReconciliation.difference, searchable: false },
    { data: 'actions', name: 'actions', title: dict.app.pages.reportsBankReconciliation.actions, orderable: false, searchable: false },
  ], [dict]);

  const slots = useMemo<DataTableSlots>(() => ({
    bank_account_name: (data: ReconciliationRow['bank_account_name'], _type: unknown, row: ReconciliationRow): ReactElement => (
      <span className="font-bold">{row.bank_account_code} - {getLocalizedName(data, locale)}</span>
    ),
    statement_reference: (data: string): ReactElement => <span className="font-mono text-xs">{data}</span>,
    date_from: (data: string, _type: unknown, row: ReconciliationRow): ReactElement => (
      <span className="whitespace-nowrap text-[var(--text-secondary)]">{formatDate(data)} → {formatDate(row.date_to)}</span>
    ),
    status: (data: string): ReactElement => (
      <StatusBadge tone={data === 'reconciled' ? 'ok' : 'warning'}>
        {data === 'reconciled'
          ? dict.app.pages.reportsBankReconciliation.reconciled
          : dict.app.pages.reportsBankReconciliation.draft}
      </StatusBadge>
    ),
    matched_statement_lines_count: (data: number, _type: unknown, row: ReconciliationRow): ReactElement => (
      <span className="font-mono">{data} / {row.total_statement_lines_count}</span>
    ),
    difference_minor: (data: number, _type: unknown, row: ReconciliationRow): ReactElement => (
      <span className={`font-mono font-bold ${data === 0 ? 'text-emerald-600' : 'text-rose-600'}`}>
        {formatMoney(data, row.bank_account_currency || '')}
      </span>
    ),
    actions: (_data: unknown, _type: unknown, row: ReconciliationRow): ReactElement => (
      <Link href={`/reports/bank-reconciliations/${row.id}`} className="text-xs font-bold text-[var(--primary)] hover:underline">
        {dict.app.pages.reportsBankReconciliation.viewDetail}
      </Link>
    ),
  }), [dict, locale]);

  return (
    <AppLayout active="reports.bank-reconciliations">
      <Head title={dict.app.pages.reportsBankReconciliation.bankReconciliationReportMiniErp} />

      <PageHeader
        title={dict.app.pages.reportsBankReconciliation.bankReconciliationReport}
        description={dict.app.pages.reportsBankReconciliation.readOnlyOverviewAndAuditReports}
        actions={
          canPrint ? (
            <Button variant="secondary" onClick={() => window.print()}>
              {actionsDict.printReport}
            </Button>
          ) : undefined
        }
      />

      <div className="space-y-6">
        <Card className="p-4">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {dict.app.pages.reportsBankReconciliation.bankAccount}
              </label>
              <SearchableSelect
                options={[
                  { value: '', label: dict.app.pages.reportsBankReconciliation.allBankAccounts },
                  ...bankAccounts.map((b) => ({ value: b.id, label: `${b.code} - ${getLocalizedName(b.name, locale)}` })),
                ]}
                value={bankAccountId}
                onChange={(val) => setBankAccountId(val || '')}
              />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {dict.app.pages.reportsBankReconciliation.status}
              </label>
              <SearchableSelect
                options={[
                  { value: '', label: dict.app.pages.reportsBankReconciliation.allStatuses },
                  { value: 'draft', label: dict.app.pages.reportsBankReconciliation.draft },
                  { value: 'reconciled', label: dict.app.pages.reportsBankReconciliation.reconciled },
                ]}
                value={status}
                onChange={(val) => setStatus(val || '')}
              />
            </div>
            <div className="flex items-center gap-2">
              <Button onClick={handleFilter} className="flex-1">
                {dict.app.pages.reportsBankReconciliation.viewReconciliations}
              </Button>
              <Button
                variant="secondary"
                onClick={handleReset}
                disabled={!hasActiveFilters}
                title={actionsDict.reset}
                aria-label={actionsDict.reset}
              >
                {actionsDict.reset}
              </Button>
            </div>
          </div>
        </Card>

        <Card className="overflow-hidden p-0">
          <ServerDataTable
            ajaxUrl="/reports/bank-reconciliations/data"
            columns={columns}
            filters={{
              bank_account_id: bankAccountId,
              status,
              date_from: filters.date_from,
              date_to: filters.date_to,
            }}
            locale={locale}
            order={[[2, 'desc']]}
            pageLength={25}
            slots={slots}
            tableId="bank-reconciliation-report-table"
          />
        </Card>
      </div>
    </AppLayout>
  );
}
