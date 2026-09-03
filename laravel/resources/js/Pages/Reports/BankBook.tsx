import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import { BankBookDataTable } from '../../Components/CashBankBookDataTables';
import DatePicker from '../../Components/DatePicker';
import SearchableSelect from '../../Components/SearchableSelect';
import { Button, Card, PageHeader } from '../../Components/Primitives';
import { formatMoney, getLocalizedName } from '../../lib/accountingHelpers';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';
import { getDictionary } from '../../lib/i18n';

type BankBookProps = SharedPageProps & {
  report: {
    bank_account: { id: string; code: string; name: string };
    currency: string;
    date_from: string;
    date_to: string;
    opening_balance_minor: number;
    period_debit_minor: number;
    period_credit_minor: number;
    period_movement_minor: number;
    closing_balance_minor: number;
  } | null;
  bankAccounts: Array<{ id: string; code: string; name: string }>;
  filters: { bank_account_id: string | null; date_from: string; date_to: string };
};

export default function BankBook({ locale, report, bankAccounts, filters }: BankBookProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const actionsDict = dict.app.actions;
  const can = useCan();
  const canExport = can('reports.export') && can('view_financials');
  const canPrint = can('reports.print') && can('view_financials');

  const [bankAccountId, setBankAccountId] = useState(filters.bank_account_id || '');
  const [dateFrom, setDateFrom] = useState(filters.date_from);
  const [dateTo, setDateTo] = useState(filters.date_to);

  const hasActiveFilters = Boolean(bankAccountId || dateFrom || dateTo);

  const handleFilter = () => {
    router.get('/reports/bank-book', {
      bank_account_id: bankAccountId,
      date_from: dateFrom,
      date_to: dateTo,
    }, { preserveScroll: true });
  };

  const handleReset = () => {
    setBankAccountId('');
    router.get('/reports/bank-book', {}, { preserveScroll: true });
  };

  const handleExport = () => {
    if (!bankAccountId) return;
    const url = `/reports/bank-book/export?bank_account_id=${bankAccountId}&date_from=${dateFrom}&date_to=${dateTo}`;
    window.open(url, '_blank');
  };

  return (
    <AppLayout active="reports.bank-book">
      <Head title={dict.app.pages.reportsBankBook.bankBookReportMiniErp} />

      <PageHeader
        title={dict.app.pages.reportsBankBook.bankBookReport}
        description={dict.app.pages.reportsBankBook.ledgerBackedDetailedBankMovementDaily}
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
                  {dict.app.pages.reportsBankBook.exportCsv}
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
                {dict.app.pages.reportsBankBook.bankAccount}
              </label>
              <SearchableSelect
                options={bankAccounts.map((b) => ({ value: b.id, label: `${b.code} - ${getLocalizedName(b.name, locale)}` }))}
                value={bankAccountId}
                onChange={(val) => setBankAccountId(val || '')}
              />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {dict.app.pages.reportsBankBook.fromDate}
              </label>
              <DatePicker value={dateFrom} onChange={(val) => setDateFrom(val || '')} />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {dict.app.pages.reportsBankBook.toDate}
              </label>
              <DatePicker value={dateTo} onChange={(val) => setDateTo(val || '')} />
            </div>
            <div className="flex items-center gap-2">
              <Button onClick={handleFilter} className="flex-1">
                {dict.app.pages.reportsBankBook.viewReport}
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
                <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsBankBook.openingBalance}</div>
                <div className="text-sm font-bold text-[var(--text-primary)]">
                  {formatMoney(report.opening_balance_minor, report.currency)}
                </div>
              </div>
              <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
                <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsBankBook.totalDepositsIn}</div>
                <div className="text-sm font-bold text-emerald-600">
                  {formatMoney(report.period_debit_minor, report.currency)}
                </div>
              </div>
              <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
                <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsBankBook.totalWithdrawalsOut}</div>
                <div className="text-sm font-bold text-rose-600">
                  {formatMoney(report.period_credit_minor, report.currency)}
                </div>
              </div>
              <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
                <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsBankBook.closingBalance}</div>
                <div className="text-sm font-bold text-[var(--text-primary)]">
                  {formatMoney(report.closing_balance_minor, report.currency)}
                </div>
              </div>
            </div>

            <Card className="overflow-hidden p-0">
              <BankBookDataTable
                key={`${filters.bank_account_id}-${filters.date_from}-${filters.date_to}`}
                accountId={report.bank_account.id}
                currency={report.currency}
                dateFrom={report.date_from}
                dateTo={report.date_to}
                labels={{
                  date: dict.app.pages.reportsBankBook.date,
                  journalRef: dict.app.pages.reportsBankBook.journalRef,
                  description: dict.app.pages.reportsBankBook.description,
                  reconciliation: dict.app.pages.reportsBankBook.reconStatus,
                  matched: dict.app.pages.reportsBankBook.matched,
                  unmatched: dict.app.pages.reportsBankBook.unmatched,
                  debit: dict.app.pages.reportsBankBook.depositIn,
                  credit: dict.app.pages.reportsBankBook.withdrawalOut,
                  runningBalance: dict.app.pages.reportsBankBook.runningBalance,
                  zeroAmount: accDict.zeroAmount,
                }}
                locale={locale}
              />
            </Card>
          </div>
        ) : (
          <Card className="p-12 text-center text-[var(--text-muted)]">
            {dict.app.pages.reportsBankBook.pleaseSelectABankAccountTo}
          </Card>
        )}
      </div>
    </AppLayout>
  );
}
