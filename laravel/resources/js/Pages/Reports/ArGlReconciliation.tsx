import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import SearchableSelect from '../../Components/SearchableSelect';
import { Button, Card, PageHeader } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import type { SharedPageProps } from '../../Types';
import { getDictionary } from '../../lib/i18n';

type ArGlReconciliationProps = SharedPageProps & {
  report: {
    as_of_date: string;
    currency: string;
    mapping_configured: boolean;
    ar_control_account: { id: string; code: string; name: string } | null;
    subledger_total_minor: number;
    gl_total_minor: number;
    difference_minor: number;
    is_reconciled: boolean;
    customer_breakdown: Array<{
      customer_id: string;
      customer_code: string;
      customer_name: string;
      subledger_balance_minor: number;
    }>;
  };
  currencies: Array<{ code: string }>;
  filters: { as_of_date: string; currency: string };
};

export default function ArGlReconciliation({ locale, report, currencies, filters }: ArGlReconciliationProps) {
  const dict = getDictionary(locale);

  const [asOfDate, setAsOfDate] = useState(filters.as_of_date);
  const [currency, setCurrency] = useState(filters.currency);

  const handleFilter = () => {
    router.get('/reports/ar-gl-reconciliation', {
      as_of_date: asOfDate,
      currency,
    });
  };

  const handleExport = () => {
    const url = `/reports/ar-gl-reconciliation/export?as_of_date=${asOfDate}&currency=${currency}`;
    window.open(url, '_blank');
  };

  return (
    <AppLayout active="reports.ar-gl-reconciliation">
      <Head title={dict.app.pages.reportsArGlReconciliation.arToGlReconMiniErp} />

      <PageHeader
        title={dict.app.pages.reportsArGlReconciliation.arToGlControlReconciliation}
        description={dict.app.pages.reportsArGlReconciliation.reconcilesTotalActiveCustomerSubledgerBalances}
        actions={
          <Button variant="secondary" onClick={handleExport}>
            {dict.app.pages.reportsArGlReconciliation.exportCsv}
          </Button>
        }
      />

      <div className="space-y-6">
        <Card className="p-4">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {dict.app.pages.reportsArGlReconciliation.asOfDate}
              </label>
              <DatePicker value={asOfDate} onChange={(val) => setAsOfDate(val || '')} />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {dict.app.pages.reportsArGlReconciliation.currency}
              </label>
              <SearchableSelect
                options={currencies.map((c) => ({ value: c.code, label: c.code }))}
                value={currency}
                onChange={(val) => setCurrency(val || '')}
              />
            </div>
            <div>
              <Button onClick={handleFilter} className="w-full">
                {dict.app.pages.reportsArGlReconciliation.updateReport}
              </Button>
            </div>
          </div>
        </Card>

        {!report.mapping_configured ? (
          <div className="p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-800 text-xs font-medium">
            {dict.app.pages.reportsArGlReconciliation.warningArControlAccountArControl}
          </div>
        ) : null}

        <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
          <div className="bg-[var(--card)] p-4 rounded-xl border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsArGlReconciliation.arSubledgerTotal}</div>
            <div className="text-base font-bold text-[var(--text-primary)]">
              {formatMoney(report.subledger_total_minor, report.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-4 rounded-xl border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">
              {dict.app.pages.reportsArGlReconciliation.arControlAccountGl}
              {report.ar_control_account ? ` (${report.ar_control_account.code})` : ''}
            </div>
            <div className="text-base font-bold text-blue-600">
              {formatMoney(report.gl_total_minor, report.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-4 rounded-xl border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsArGlReconciliation.reconciliationDifference}</div>
            <div className={`text-base font-bold ${report.difference_minor === 0 ? 'text-emerald-600' : 'text-rose-600'}`}>
              {formatMoney(report.difference_minor, report.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-4 rounded-xl border border-[var(--border-color)] flex items-center justify-between">
            <div>
              <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsArGlReconciliation.reconciliationStatus}</div>
              <span className={`px-2.5 py-1 text-xs font-bold rounded-full ${
                report.is_reconciled
                  ? 'bg-emerald-100 text-emerald-800 border border-emerald-300'
                  : 'bg-rose-100 text-rose-800 border border-rose-300'
              }`}>
                {report.is_reconciled ? dict.app.pages.reportsArGlReconciliation.reconciled : dict.app.pages.reportsArGlReconciliation.unreconciled}
              </span>
            </div>
          </div>
        </div>

        <Card className="overflow-hidden p-0">
          <div className="p-3 bg-[var(--background)] font-bold text-xs border-b border-[var(--border-color)]">
            {dict.app.pages.reportsArGlReconciliation.customerSubledgerBalanceBreakdown}
          </div>
          <table className="w-full text-left text-xs">
            <thead className="bg-[var(--background)]/50 border-b border-[var(--border-color)]">
              <tr>
                <th className="p-3 font-semibold text-[var(--text-secondary)]">{dict.app.pages.reportsArGlReconciliation.customerCode}</th>
                <th className="p-3 font-semibold text-[var(--text-secondary)]">{dict.app.pages.reportsArGlReconciliation.customerName}</th>
                <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">{dict.app.pages.reportsArGlReconciliation.subledgerOpenBalance}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[var(--border-color)]">
              {report.customer_breakdown.map((row) => (
                <tr key={row.customer_id} className="hover:bg-[var(--background)]/30">
                  <td className="p-3 font-mono font-bold">{row.customer_code}</td>
                  <td className="p-3 font-medium">{row.customer_name}</td>
                  <td className="p-3 text-end font-mono font-bold">
                    {formatMoney(row.subledger_balance_minor, report.currency)}
                  </td>
                </tr>
              ))}
              {report.customer_breakdown.length === 0 ? (
                <tr>
                  <td colSpan={3} className="p-6 text-center text-[var(--text-muted)]">
                    {dict.app.pages.reportsArGlReconciliation.noOpenCustomerSubledgerBalances}
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
