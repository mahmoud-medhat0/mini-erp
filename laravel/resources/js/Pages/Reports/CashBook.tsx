import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import SearchableSelect from '../../Components/SearchableSelect';
import { Button, Card, PageHeader } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import type { SharedPageProps } from '../../Types';
import { getDictionary } from '../../lib/i18n';

type CashBookProps = SharedPageProps & {
  report: {
    cash_account: { id: string; code: string; name: string };
    currency: string;
    date_from: string;
    date_to: string;
    opening_balance_minor: number;
    entries: Array<{
      ledger_entry_id: string;
      entry_date: string;
      journal_number: string;
      description: string;
      debit_minor: number;
      credit_minor: number;
      signed_movement_minor: number;
      balance_after_minor: number;
    }>;
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

  const [cashAccountId, setCashAccountId] = useState(filters.cash_account_id || '');
  const [dateFrom, setDateFrom] = useState(filters.date_from);
  const [dateTo, setDateTo] = useState(filters.date_to);

  const handleFilter = () => {
    router.get('/reports/cash-book', {
      cash_account_id: cashAccountId,
      date_from: dateFrom,
      date_to: dateTo,
    });
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
            <Button variant="secondary" onClick={handleExport}>
              {dict.app.pages.reportsCashBook.exportCsv}
            </Button>
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
            <div>
              <Button onClick={handleFilter} className="w-full">
                {dict.app.pages.reportsCashBook.viewReport}
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
              <table className="w-full text-left text-xs">
                <thead className="bg-[var(--background)] border-b border-[var(--border-color)]">
                  <tr>
                    <th className="p-3 font-semibold text-[var(--text-secondary)]">{dict.app.pages.reportsCashBook.date}</th>
                    <th className="p-3 font-semibold text-[var(--text-secondary)]">{dict.app.pages.reportsCashBook.journalRef}</th>
                    <th className="p-3 font-semibold text-[var(--text-secondary)]">{dict.app.pages.reportsCashBook.description}</th>
                    <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">{dict.app.pages.reportsCashBook.receiptsIn}</th>
                    <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">{dict.app.pages.reportsCashBook.paymentsOut}</th>
                    <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">{dict.app.pages.reportsCashBook.runningBalance}</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[var(--border-color)]">
                  <tr className="bg-[var(--background)]/50 font-bold">
                    <td colSpan={5} className="p-3">{dict.app.pages.reportsCashBook.openingBalancePriorToRange}</td>
                    <td className="p-3 text-end">{formatMoney(report.opening_balance_minor, report.currency)}</td>
                  </tr>
                  {report.entries.map((item, idx) => (
                    <tr key={idx} className="hover:bg-[var(--background)]/30">
                      <td className="p-3">{item.entry_date}</td>
                      <td className="p-3 font-mono">{item.journal_number}</td>
                      <td className="p-3 text-[var(--text-secondary)]">{item.description}</td>
                      <td className="p-3 text-end font-mono">
                        {item.debit_minor > 0 ? formatMoney(item.debit_minor, report.currency) : accDict.zeroAmount}
                      </td>
                      <td className="p-3 text-end font-mono">
                        {item.credit_minor > 0 ? formatMoney(item.credit_minor, report.currency) : accDict.zeroAmount}
                      </td>
                      <td className="p-3 text-end font-mono font-bold">
                        {formatMoney(item.balance_after_minor, report.currency)}
                      </td>
                    </tr>
                  ))}
                  {report.entries.length === 0 ? (
                    <tr>
                      <td colSpan={6} className="p-6 text-center text-[var(--text-muted)]">
                        {dict.app.pages.reportsCashBook.noCashMovementsFoundForThe}
                      </td>
                    </tr>
                  ) : null}
                </tbody>
              </table>
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
