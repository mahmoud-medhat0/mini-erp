import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import SearchableSelect from '../../Components/SearchableSelect';
import { Button, Card, PageHeader } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import type { SharedPageProps } from '../../Types';

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
  const isAr = locale === 'ar';

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
      <Head title={isAr ? 'دفتر حركة البنك - Mini ERP' : 'Bank Book Report - Mini ERP'} />

      <PageHeader
        title={isAr ? 'دفتر حركة البنك' : 'Bank Book Report'}
        description={isAr ? 'سجل تفصيلي لجميع الإيداعات والمسحوبات البنكية بالأستاذ العام حالة المطابقة البنكية.' : 'Ledger-backed detailed bank movement, daily running balance, and reconciliation matching status.'}
        actions={
          report ? (
            <Button variant="secondary" onClick={handleExport}>
              {isAr ? 'تصدير CSV' : 'Export CSV'}
            </Button>
          ) : undefined
        }
      />

      <div className="space-y-6">
        <Card className="p-4">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {isAr ? 'حساب البنك' : 'Bank Account'}
              </label>
              <SearchableSelect
                options={bankAccounts.map((b) => ({ value: b.id, label: `${b.code} - ${b.name}` }))}
                value={bankAccountId}
                onChange={(val) => setBankAccountId(val || '')}
              />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {isAr ? 'من تاريخ' : 'From Date'}
              </label>
              <DatePicker value={dateFrom} onChange={(val) => setDateFrom(val || '')} />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {isAr ? 'إلى تاريخ' : 'To Date'}
              </label>
              <DatePicker value={dateTo} onChange={(val) => setDateTo(val || '')} />
            </div>
            <div>
              <Button onClick={handleFilter} className="w-full">
                {isAr ? 'عرض التقرير' : 'View Report'}
              </Button>
            </div>
          </div>
        </Card>

        {report ? (
          <div className="space-y-4">
            <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
              <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
                <div className="text-xs text-[var(--text-secondary)] mb-1">{isAr ? 'الرصيد الافتتاحي' : 'Opening Balance'}</div>
                <div className="text-sm font-bold text-[var(--text-primary)]">
                  {formatMoney(report.opening_balance_minor, report.currency)}
                </div>
              </div>
              <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
                <div className="text-xs text-[var(--text-secondary)] mb-1">{isAr ? 'إجمالي الإيداعات (مدين)' : 'Total Deposits (In)'}</div>
                <div className="text-sm font-bold text-emerald-600">
                  {formatMoney(report.period_debit_minor, report.currency)}
                </div>
              </div>
              <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
                <div className="text-xs text-[var(--text-secondary)] mb-1">{isAr ? 'إجمالي المسحوبات (دائن)' : 'Total Withdrawals (Out)'}</div>
                <div className="text-sm font-bold text-rose-600">
                  {formatMoney(report.period_credit_minor, report.currency)}
                </div>
              </div>
              <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
                <div className="text-xs text-[var(--text-secondary)] mb-1">{isAr ? 'الرصيد الختامي' : 'Closing Balance'}</div>
                <div className="text-sm font-bold text-[var(--text-primary)]">
                  {formatMoney(report.closing_balance_minor, report.currency)}
                </div>
              </div>
            </div>

            <Card className="overflow-hidden p-0">
              <table className="w-full text-left text-xs">
                <thead className="bg-[var(--background)] border-b border-[var(--border-color)]">
                  <tr>
                    <th className="p-3 font-semibold text-[var(--text-secondary)]">{isAr ? 'التاريخ' : 'Date'}</th>
                    <th className="p-3 font-semibold text-[var(--text-secondary)]">{isAr ? 'رقم القيد' : 'Journal Ref'}</th>
                    <th className="p-3 font-semibold text-[var(--text-secondary)]">{isAr ? 'البيان' : 'Description'}</th>
                    <th className="p-3 font-semibold text-[var(--text-secondary)]">{isAr ? 'حالة المطابقة' : 'Recon Status'}</th>
                    <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">{isAr ? 'إيداع (مدين)' : 'Deposit (In)'}</th>
                    <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">{isAr ? 'سحب (دائن)' : 'Withdrawal (Out)'}</th>
                    <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">{isAr ? 'الرصيد التراكمي' : 'Running Balance'}</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[var(--border-color)]">
                  <tr className="bg-[var(--background)]/50 font-bold">
                    <td colSpan={6} className="p-3">{isAr ? 'الرصيد الافتتاحي قبل الفترة' : 'Opening Balance Prior to Range'}</td>
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
                            {isAr ? 'مطابق' : 'Matched'}
                          </span>
                        ) : (
                          <span className="px-2 py-0.5 text-[10px] font-medium rounded-full bg-slate-100 text-slate-600 border border-slate-300">
                            {isAr ? 'غير مطابق' : 'Unmatched'}
                          </span>
                        )}
                      </td>
                      <td className="p-3 text-end font-mono">
                        {item.debit_minor > 0 ? formatMoney(item.debit_minor, report.currency) : '—'}
                      </td>
                      <td className="p-3 text-end font-mono">
                        {item.credit_minor > 0 ? formatMoney(item.credit_minor, report.currency) : '—'}
                      </td>
                      <td className="p-3 text-end font-mono font-bold">
                        {formatMoney(item.balance_after_minor, report.currency)}
                      </td>
                    </tr>
                  ))}
                  {report.entries.length === 0 ? (
                    <tr>
                      <td colSpan={7} className="p-6 text-center text-[var(--text-muted)]">
                        {isAr ? 'لا توجد حركات بنكية خاضعة للفترة المحددة.' : 'No bank movements found for the selected period.'}
                      </td>
                    </tr>
                  ) : null}
                </tbody>
              </table>
            </Card>
          </div>
        ) : (
          <Card className="p-12 text-center text-[var(--text-muted)]">
            {isAr ? 'يرجى اختيار حساب البنك لتوليد التقرير.' : 'Please select a bank account to generate the statement.'}
          </Card>
        )}
      </div>
    </AppLayout>
  );
}
