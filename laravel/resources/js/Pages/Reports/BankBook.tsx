import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import SearchableSelect from '../../Components/SearchableSelect';
import { Button, Card, PageHeader } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import type { SharedPageProps } from '../../Types';
import { getDictionary } from '../../lib/i18n';

type BankBookProps = SharedPageProps & {
  report: {
    bank_account: { id: string; code: string; name: string };
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
      is_reconciled: boolean;
    }>;
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

  const [bankAccountId, setBankAccountId] = useState(filters.bank_account_id || '');
  const [dateFrom, setDateFrom] = useState(filters.date_from);
  const [dateTo, setDateTo] = useState(filters.date_to);

  const handleFilter = () => {
    router.get('/reports/bank-book', {
      bank_account_id: bankAccountId,
      date_from: dateFrom,
      date_to: dateTo,
    });
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
            <Button variant="secondary" onClick={handleExport}>
              {dict.app.pages.reportsBankBook.exportCsv}
            </Button>
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
                options={bankAccounts.map((b) => ({ value: b.id, label: `${b.code} - ${b.name}` }))}
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
            <div>
              <Button onClick={handleFilter} className="w-full">
                {dict.app.pages.reportsBankBook.viewReport}
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
              <table className="w-full text-left text-xs">
                <thead className="bg-[var(--background)] border-b border-[var(--border-color)]">
                  <tr>
                    <th className="p-3 font-semibold text-[var(--text-secondary)]">{dict.app.pages.reportsBankBook.date}</th>
                    <th className="p-3 font-semibold text-[var(--text-secondary)]">{dict.app.pages.reportsBankBook.journalRef}</th>
                    <th className="p-3 font-semibold text-[var(--text-secondary)]">{dict.app.pages.reportsBankBook.description}</th>
                    <th className="p-3 font-semibold text-[var(--text-secondary)]">{dict.app.pages.reportsBankBook.reconStatus}</th>
                    <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">{dict.app.pages.reportsBankBook.depositIn}</th>
                    <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">{dict.app.pages.reportsBankBook.withdrawalOut}</th>
                    <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">{dict.app.pages.reportsBankBook.runningBalance}</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[var(--border-color)]">
                  <tr className="bg-[var(--background)]/50 font-bold">
                    <td colSpan={6} className="p-3">{dict.app.pages.reportsBankBook.openingBalancePriorToRange}</td>
                    <td className="p-3 text-end">{formatMoney(report.opening_balance_minor, report.currency)}</td>
                  </tr>
                  {report.entries.map((item, idx) => (
                    <tr key={idx} className="hover:bg-[var(--background)]/30">
                      <td className="p-3">{item.entry_date}</td>
                      <td className="p-3 font-mono">{item.journal_number}</td>
                      <td className="p-3 text-[var(--text-secondary)]">{item.description}</td>
                      <td className="p-3">
                        {item.is_reconciled ? (
                          <span className="px-2 py-0.5 text-[10px] font-bold rounded-full bg-emerald-100 text-emerald-800 border border-emerald-300">
                            {dict.app.pages.reportsBankBook.matched}
                          </span>
                        ) : (
                          <span className="px-2 py-0.5 text-[10px] font-medium rounded-full bg-slate-100 text-slate-600 border border-slate-300">
                            {dict.app.pages.reportsBankBook.unmatched}
                          </span>
                        )}
                      </td>
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
                      <td colSpan={7} className="p-6 text-center text-[var(--text-muted)]">
                        {dict.app.pages.reportsBankBook.noBankMovementsFoundForThe}
                      </td>
                    </tr>
                  ) : null}
                </tbody>
              </table>
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
