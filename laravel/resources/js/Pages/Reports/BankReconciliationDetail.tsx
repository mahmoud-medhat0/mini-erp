import { Head, Link } from '@inertiajs/react';
import { useMemo, type ReactElement } from 'react';
import AppLayout from '../../Components/AppLayout';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { Button, Card, PageHeader, StatusBadge } from '../../Components/Primitives';
import { formatDate, formatMoney, getLocalizedName } from '../../lib/accountingHelpers';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';
import { getDictionary, interpolate } from '../../lib/i18n';

type StatementLine = {
  id: string;
  statement_date: string;
  reference: string;
  description: string;
  debit_minor: number;
  credit_minor: number;
  statement_net_minor?: number;
  matched_ledger_entry_id: string | null;
  matched_at: string | null;
  journal_number?: string | null;
  matched_entry_date?: string | null;
  matched_net_minor?: number | null;
  matched_ledger_entry: {
    id: string;
    entry_date: string;
    description: string;
    debit_minor: number;
    credit_minor: number;
    journal_number?: string;
  } | null;
};

type BankReconciliationDetailProps = SharedPageProps & {
  detail: {
    reconciliation: {
      id: string;
      bank_account: { id: string; code: string; name: string; currency: string };
      statement_reference: string;
      date_from: string;
      date_to: string;
      statement_opening_balance_minor: number;
      statement_closing_balance_minor: number;
      status: string;
      finalized_at: string | null;
      lines: StatementLine[];
    };
    summary: {
      statement_movement_minor: number;
      system_movement_minor: number;
      matched_movement_minor: number;
      difference_minor: number;
      unmatched_statement_lines_count: number;
      matched_statement_lines_count: number;
      total_statement_lines_count: number;
    };
  };
};

export default function BankReconciliationDetail({ locale, detail }: BankReconciliationDetailProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const can = useCan();
  const canPrint = can('reports.print') && can('view_financials');
  const { reconciliation, summary } = detail;

  const columns = useMemo(() => [
    { data: 'statement_date', name: 'statement_date', title: dict.app.pages.reportsBankReconciliationDetail.statementDate },
    { data: 'reference', name: 'reference', title: dict.app.pages.reportsBankReconciliationDetail.refDescription },
    { data: 'statement_net_minor', name: 'statement_net_minor', title: dict.app.pages.reportsBankReconciliationDetail.statementAmount, searchable: false },
    { data: 'journal_number', name: 'journal_number', title: dict.app.pages.reportsBankReconciliationDetail.matchedGlEntry, orderable: false },
    { data: 'matched_net_minor', name: 'matched_net_minor', title: dict.app.pages.reportsBankReconciliationDetail.glAmount, orderable: false, searchable: false },
  ], [dict]);

  const slots = useMemo<DataTableSlots>(() => ({
    statement_date: (data: string): ReactElement => <span className="whitespace-nowrap font-mono text-xs">{formatDate(data)}</span>,
    reference: (data: string, _type: unknown, line: StatementLine): ReactElement => (
      <div>
        <div className="font-semibold">{data}</div>
        <div className="text-[11px] text-[var(--text-secondary)]">{line.description}</div>
      </div>
    ),
    statement_net_minor: (data: number): ReactElement => (
      <span className="font-mono font-bold">{formatMoney(data, reconciliation.bank_account.currency)}</span>
    ),
    journal_number: (data: string | null, _type: unknown, line: StatementLine): ReactElement => (
      line.matched_ledger_entry_id ? (
        <div>
          <span className="font-mono font-bold text-blue-600">
            {data || dict.app.pages.reportsBankReconciliationDetail.missingGlJournalReference}
          </span>
          {line.matched_entry_date ? (
            <span className="ms-2 text-[var(--text-secondary)]">({formatDate(line.matched_entry_date)})</span>
          ) : null}
        </div>
      ) : <span className="italic text-slate-400">{dict.app.pages.reportsBankReconciliationDetail.unmatched}</span>
    ),
    matched_net_minor: (data: number | null): ReactElement => (
      <span className="font-mono">
        {data === null ? accDict.notAvailable : formatMoney(data, reconciliation.bank_account.currency)}
      </span>
    ),
  }), [accDict.notAvailable, dict, reconciliation.bank_account.currency]);

  return (
    <AppLayout active="reports.bank-reconciliations">
      <Head title={interpolate(dict.app.pages.bankReconciliationDetail.headTitle, { ref: reconciliation.statement_reference })} />

      <PageHeader
        title={interpolate(dict.app.pages.bankReconciliationDetail.reportTitle, { ref: reconciliation.statement_reference })}
        description={`${reconciliation.bank_account.code} - ${getLocalizedName(reconciliation.bank_account.name, locale)} (${formatDate(reconciliation.date_from)} → ${formatDate(reconciliation.date_to)})`}
        actions={
          <div className="flex items-center gap-3">
            {canPrint ? (
              <Button variant="secondary" onClick={() => window.print()}>
                {dict.app.actions.printReport}
              </Button>
            ) : null}
            <Link href="/reports/bank-reconciliations" className="inline-flex items-center text-xs font-bold text-[var(--primary)] hover:underline">
              {dict.app.pages.reportsBankReconciliationDetail.backToList}
            </Link>
          </div>
        }
      />

      <div className="space-y-6">
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsBankReconciliationDetail.statementMovement}</div>
            <div className="text-sm font-bold text-[var(--text-primary)]">
              {formatMoney(summary.statement_movement_minor, reconciliation.bank_account.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsBankReconciliationDetail.matchedSystemMovement}</div>
            <div className="text-sm font-bold text-blue-600">
              {formatMoney(summary.matched_movement_minor, reconciliation.bank_account.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsBankReconciliationDetail.reconciliationDifference}</div>
            <div className={`text-sm font-bold ${summary.difference_minor === 0 ? 'text-emerald-600' : 'text-rose-600'}`}>
              {formatMoney(summary.difference_minor, reconciliation.bank_account.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsBankReconciliationDetail.reconStatus}</div>
            <div className="text-sm font-bold text-[var(--text-primary)]">
              <StatusBadge tone={reconciliation.status === 'reconciled' ? 'ok' : 'warning'}>
                {reconciliation.status === 'reconciled'
                  ? dict.app.pages.reportsBankReconciliation.reconciled
                  : dict.app.pages.reportsBankReconciliation.draft}
              </StatusBadge>
            </div>
          </div>
        </div>

        <Card className="overflow-hidden p-0">
          <div className="p-3 bg-[var(--background)] font-bold text-xs border-b border-[var(--border-color)]">
            {dict.app.pages.reportsBankReconciliationDetail.bankStatementLinesMatchedSystemEntries}
          </div>
          <ServerDataTable
            ajaxUrl={`/reports/bank-reconciliations/${reconciliation.id}/data`}
            columns={columns}
            locale={locale}
            order={[[0, 'asc']]}
            pageLength={25}
            slots={slots}
            tableId="bank-reconciliation-report-lines-table"
          />
        </Card>
      </div>
    </AppLayout>
  );
}
