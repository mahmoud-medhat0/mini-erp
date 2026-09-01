import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import { CashBookDataTable } from '../../Components/CashBankBookDataTables';
import DatePicker from '../../Components/DatePicker';
import SearchableSelect from '../../Components/SearchableSelect';
import { Button, Card, PageHeader } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';
import { getDictionary } from '../../lib/i18n';

type CashBookProps = SharedPageProps & {
  report: {
    cash_account: { id: string; code: string; name: string };
    currency: string;
    date_from: string;
    date_to: string;
    opening_balance_minor: number;
    period_debit_minor: number;
    period_credit_minor: number;
    period_movement_minor: number;
    closing_balance_minor: number;
  } | null;
  cashAccounts: Array<{ id: string; code: string; name: string }>;
  filters: { cash_account_id: string | null; date_from: string; date_to: string };
};

export default function CashBook({ locale, report, cashAccounts, filters }: CashBookProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const actionsDict = dict.app.actions;
  const can = useCan();
  const canExport = can('reports.export') && can('view_financials');
  const canPrint = can('reports.print') && can('view_financials');

  const [cashAccountId, setCashAccountId] = useState(filters.cash_account_id || '');
  const [dateFrom, setDateFrom] = useState(filters.date_from);
  const [dateTo, setDateTo] = useState(filters.date_to);

  const hasActiveFilters = Boolean(cashAccountId || dateFrom || dateTo);

  const handleFilter = () => {
    router.get('/reports/cash-book', {
      cash_account_id: cashAccountId,
      date_from: dateFrom,
      date_to: dateTo,
    }, { preserveScroll: true });
  };

  const handleReset = () => {
    setCashAccountId('');
    router.get('/reports/cash-book', {}, { preserveScroll: true });
  };

  const handleExport = () => {
    if (!cashAccountId) return;
    const url = `/reports/cash-book/export?cash_account_id=${cashAccountId}&date_from=${dateFrom}&date_to=${dateTo}`;
    window.open(url, '_blank');
  };

  return (
    <AppLayout active="reports.cash-book">
      <Head title={dict.app.pages.reportsCashBook.cashBookReportMiniErp} />

      <PageHeader
        title={dict.app.pages.reportsCashBook.cashBookReport}
        description={dict.app.pages.reportsCashBook.ledgerBackedDetailedCashMovementAnd}
        actions={
          report ? (
            <div className="flex items-center gap-2">
              {canPrint ? (
                <Button variant="secondary" onClick={() => window.print()}>
                  {actionsDict.printReport}
                </Button>
              ) : null}
              {canExport ? (
                <Button variant="secondary" onClick={handleExport}>
                  {dict.app.pages.reportsCashBook.exportCsv}
                </Button>
              ) : null}
            </div>
          ) : undefined
        }
      />

      <div className="space-y-6">
        <Card className="p-4">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {dict.app.pages.reportsCashBook.cashAccount}
              </label>
              <SearchableSelect
                options={cashAccounts.map((c) => ({ value: c.id, label: `${c.code} - ${c.name}` }))}
                value={cashAccountId}
                onChange={(val) => setCashAccountId(val || '')}
              />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {dict.app.pages.reportsCashBook.fromDate}
              </label>
              <DatePicker value={dateFrom} onChange={(val) => setDateFrom(val || '')} />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {dict.app.pages.reportsCashBook.toDate}
              </label>
              <DatePicker value={dateTo} onChange={(val) => setDateTo(val || '')} />
            </div>
            <div className="flex items-center gap-2">
              <Button onClick={handleFilter} className="flex-1">
                {dict.app.pages.reportsCashBook.viewReport}
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

        {report ? (
          <div className="space-y-4">
            <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
              <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
                <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsCashBook.openingBalance}</div>
                <div className="text-sm font-bold text-[var(--text-primary)]">
                  {formatMoney(report.opening_balance_minor, report.currency)}
                </div>
              </div>
              <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
                <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsCashBook.totalReceiptsIn}</div>
                <div className="text-sm font-bold text-emerald-600">
                  {formatMoney(report.period_debit_minor, report.currency)}
                </div>
              </div>
              <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
                <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsCashBook.totalPaymentsOut}</div>
                <div className="text-sm font-bold text-rose-600">
                  {formatMoney(report.period_credit_minor, report.currency)}
                </div>
              </div>
              <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
                <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsCashBook.closingBalance}</div>
                <div className="text-sm font-bold text-[var(--text-primary)]">
                  {formatMoney(report.closing_balance_minor, report.currency)}
                </div>
              </div>
            </div>

            <Card className="overflow-hidden p-0">
              <CashBookDataTable
                key={`${filters.cash_account_id}-${filters.date_from}-${filters.date_to}`}
                accountId={report.cash_account.id}
                currency={report.currency}
                dateFrom={report.date_from}
                dateTo={report.date_to}
                labels={{
                  date: dict.app.pages.reportsCashBook.date,
                  journalRef: dict.app.pages.reportsCashBook.journalRef,
                  description: dict.app.pages.reportsCashBook.description,
                  debit: dict.app.pages.reportsCashBook.receiptsIn,
                  credit: dict.app.pages.reportsCashBook.paymentsOut,
                  runningBalance: dict.app.pages.reportsCashBook.runningBalance,
                  zeroAmount: accDict.zeroAmount,
                }}
                locale={locale}
              />
            </Card>
          </div>
        ) : (
          <Card className="p-12 text-center text-[var(--text-muted)]">
            {dict.app.pages.reportsCashBook.pleaseSelectACashAccountTo}
          </Card>
        )}
      </div>
    </AppLayout>
  );
}
