import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import SearchableSelect from '../../Components/SearchableSelect';
import { Button, Card, PageHeader, StatusBadge } from '../../Components/Primitives';
import { formatDate, formatMoney, getLocalizedName } from '../../lib/accountingHelpers';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';
import { getDictionary } from '../../lib/i18n';

type BankReconciliationReportProps = SharedPageProps & {
  report: {
    filters: { bank_account_id: string | null; status: string | null; date_from: string | null; date_to: string | null };
    reconciliations: Array<{
      id: string;
      bank_account: { id: string; code: string; name: string; currency: string };
      statement_reference: string;
      date_from: string;
      date_to: string;
      statement_opening_balance_minor: number;
      statement_closing_balance_minor: number;
      status: string;
      finalized_at: string | null;
      summary: {
        statement_movement_minor: number;
        system_movement_minor: number;
        matched_movement_minor: number;
        difference_minor: number;
        unmatched_statement_lines_count: number;
        matched_statement_lines_count: number;
        total_statement_lines_count: number;
      };
    }>;
  };
  bankAccounts: Array<{ id: string; code: string; name: string }>;
  filters: { bank_account_id: string | null; status: string | null; date_from: string | null; date_to: string | null };
};

export default function BankReconciliationReport({ locale, report, bankAccounts, filters }: BankReconciliationReportProps) {
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
          <table className="w-full text-start text-xs">
            <thead className="bg-[var(--background)] border-b border-[var(--border-color)]">
              <tr>
                <th className="p-3 font-semibold text-start text-[var(--text-secondary)]">{dict.app.pages.reportsBankReconciliation.bankAccount_2}</th>
                <th className="p-3 font-semibold text-start text-[var(--text-secondary)]">{dict.app.pages.reportsBankReconciliation.statementRef}</th>
                <th className="p-3 font-semibold text-start text-[var(--text-secondary)]">{dict.app.pages.reportsBankReconciliation.period}</th>
                <th className="p-3 font-semibold text-start text-[var(--text-secondary)]">{dict.app.pages.reportsBankReconciliation.status_2}</th>
                <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">{dict.app.pages.reportsBankReconciliation.matchedTotal}</th>
                <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">{dict.app.pages.reportsBankReconciliation.difference}</th>
                <th className="p-3 font-semibold text-center text-[var(--text-secondary)]">{dict.app.pages.reportsBankReconciliation.actions}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[var(--border-color)]">
              {report.reconciliations.map((recon) => (
                <tr key={recon.id} className="hover:bg-[var(--background)]/30">
                  <td className="p-3 font-bold">{recon.bank_account.code} - {getLocalizedName(recon.bank_account.name, locale)}</td>
                  <td className="p-3 font-mono">{recon.statement_reference}</td>
                  <td className="p-3 text-[var(--text-secondary)]">{formatDate(recon.date_from)} → {formatDate(recon.date_to)}</td>
                  <td className="p-3">
                    <StatusBadge tone={recon.status === 'reconciled' ? 'ok' : 'warning'}>
                      {recon.status === 'reconciled'
                        ? dict.app.pages.reportsBankReconciliation.reconciled
                        : dict.app.pages.reportsBankReconciliation.draft}
                    </StatusBadge>
                  </td>
                  <td className="p-3 text-end font-mono">
                    {recon.summary.matched_statement_lines_count} / {recon.summary.total_statement_lines_count}
                  </td>
                  <td className={`p-3 text-end font-mono font-bold ${recon.summary.difference_minor === 0 ? 'text-emerald-600' : 'text-rose-600'}`}>
                    {formatMoney(recon.summary.difference_minor, recon.bank_account.currency)}
                  </td>
                  <td className="p-3 text-center">
                    <Link
                      href={`/reports/bank-reconciliations/${recon.id}`}
                      className="text-xs font-bold text-[var(--primary)] hover:underline"
                    >
                      {dict.app.pages.reportsBankReconciliation.viewDetail}
                    </Link>
                  </td>
                </tr>
              ))}
              {report.reconciliations.length === 0 ? (
                <tr>
                  <td colSpan={7} className="p-8 text-center text-[var(--text-muted)]">
                    {dict.app.pages.reportsBankReconciliation.noBankReconciliationsFound}
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </Card>
      </div>
    </AppLayout>
  );
}
