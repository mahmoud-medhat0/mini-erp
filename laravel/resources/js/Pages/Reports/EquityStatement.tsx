import { Head, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Card, PageHeader, StatusBadge } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types';

type EquityStatementReport = {
  from_date: string;
  to_date: string;
  currency: string;
  opening_equity_minor: number;
  contributions_minor: number;
  drawings_minor: number;
  net_income_minor: number;
  distributions_minor: number;
  computed_closing_equity_minor: number;
  actual_closing_equity_minor: number;
  variance_minor: number;
  is_reconciled: boolean;
};

type Props = SharedPageProps & {
  report: EquityStatementReport;
  filters: { from_date: string; to_date: string };
};

export default function EquityStatement({ locale, report, filters }: Props) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.equityStatement;

  const [fromDate, setFromDate] = useState(filters.from_date);
  const [toDate, setToDate] = useState(filters.to_date);

  function submitFilters(event: FormEvent) {
    event.preventDefault();
    router.get('/reports/equity-statement', { from_date: fromDate, to_date: toDate }, { preserveState: true, preserveScroll: true });
  }

  const rows: Array<{ label: string; value: number; emphasized?: boolean }> = [
    { label: pageDict.openingBalance, value: report.opening_equity_minor, emphasized: true },
    { label: pageDict.contributions, value: report.contributions_minor },
    { label: pageDict.drawings, value: -report.drawings_minor },
    { label: pageDict.netIncome, value: report.net_income_minor },
    { label: pageDict.distributions, value: -report.distributions_minor },
    { label: pageDict.closingBalance, value: report.computed_closing_equity_minor, emphasized: true },
  ];

  return (
    <AppLayout active="reports.equity-statement">
      <Head title={pageDict.headTitle} />

      <div className="space-y-6 p-6">
        <PageHeader title={pageDict.title} description={pageDict.description} />

        <Card className="p-4">
          <form onSubmit={submitFilters} className="flex flex-wrap items-end gap-4">
            <DatePicker label={pageDict.fromDate} value={fromDate} onChange={(value) => setFromDate(value || '')} required />
            <DatePicker label={pageDict.toDate} value={toDate} onChange={(value) => setToDate(value || '')} required />
            <button type="submit" title={pageDict.apply} aria-label={pageDict.apply} className="rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-bold text-white">{pageDict.apply}</button>
          </form>
        </Card>

        <Card className="overflow-hidden p-0">
          <div className="border-b border-[var(--border)] p-4">
            <h3 className="m-0 text-sm font-bold text-[var(--text-primary)]">{pageDict.statementTitle}</h3>
          </div>
          <table className="w-full text-sm">
            <tbody>
              {rows.map((row) => (
                <tr key={row.label} className="border-b border-[var(--border)] last:border-b-0">
                  <td className={`px-4 py-2.5 ${row.emphasized ? 'font-bold' : ''}`}>{row.label}</td>
                  <td className={`px-4 py-2.5 text-end ${row.emphasized ? 'font-bold' : ''}`}>{formatMoney(row.value, report.currency)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>

        <Card className="p-4">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p className="m-0 text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.actualClosingBalance}</p>
              <p className="m-0 mt-1 text-lg font-bold text-[var(--text-primary)]">{formatMoney(report.actual_closing_equity_minor, report.currency)}</p>
            </div>
            <StatusBadge tone={report.is_reconciled ? 'ok' : 'warning'}>{report.is_reconciled ? pageDict.reconciled : pageDict.unreconciled}</StatusBadge>
          </div>
          {!report.is_reconciled ? (
            <p className="m-0 mt-3 text-xs text-[var(--text-secondary)]">{pageDict.varianceHint} {formatMoney(report.variance_minor, report.currency)}</p>
          ) : null}
        </Card>
      </div>
    </AppLayout>
  );
}
