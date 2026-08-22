import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import SearchableSelect from '../../Components/SearchableSelect';
import { Button, Card, PageHeader } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import type { SharedPageProps } from '../../Types';
import { getDictionary } from '../../lib/i18n';

type ApGlReconciliationProps = SharedPageProps & {
  report: {
    as_of_date: string;
    currency: string;
    mapping_configured: boolean;
    ap_control_account: { id: string; code: string; name: string } | null;
    subledger_total_minor: number;
    gl_total_minor: number;
    difference_minor: number;
    is_reconciled: boolean;
    supplier_breakdown: Array<{
      supplier_id: string;
      supplier_code: string;
      supplier_name: string;
      subledger_balance_minor: number;
    }>;
  };
  currencies: Array<{ code: string }>;
  filters: { as_of_date: string; currency: string };
};

export default function ApGlReconciliation({ locale, report, currencies, filters }: ApGlReconciliationProps) {
  const dict = getDictionary(locale);

  const [asOfDate, setAsOfDate] = useState(filters.as_of_date);
  const [currency, setCurrency] = useState(filters.currency);

  const handleFilter = () => {
    router.get('/reports/ap-gl-reconciliation', {
      as_of_date: asOfDate,
      currency,
    });
  };

  const handleExport = () => {
    const url = `/reports/ap-gl-reconciliation/export?as_of_date=${asOfDate}&currency=${currency}`;
    window.open(url, '_blank');
  };

  return (
    <AppLayout active="reports.ap-gl-reconciliation">
      <Head title={dict.app.pages.reportsApGlReconciliation.apToGlReconMiniErp} />

      <PageHeader
        title={dict.app.pages.reportsApGlReconciliation.apToGlControlReconciliation}
        description={dict.app.pages.reportsApGlReconciliation.reconcilesTotalActiveSupplierSubledgerBalances}
        actions={
          <Button variant="secondary" onClick={handleExport}>
            {dict.app.pages.reportsApGlReconciliation.exportCsv}
          </Button>
        }
      />

      <div className="space-y-6">
        <Card className="p-4">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {dict.app.pages.reportsApGlReconciliation.asOfDate}
              </label>
              <DatePicker value={asOfDate} onChange={(val) => setAsOfDate(val || '')} />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {dict.app.pages.reportsApGlReconciliation.currency}
              </label>
              <SearchableSelect
                options={currencies.map((c) => ({ value: c.code, label: c.code }))}
                value={currency}
                onChange={(val) => setCurrency(val || 'EGP')}
              />
            </div>
            <div>
              <Button onClick={handleFilter} className="w-full">
                {dict.app.pages.reportsApGlReconciliation.updateReport}
              </Button>
            </div>
          </div>
        </Card>

        {!report.mapping_configured ? (
          <div className="p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-800 text-xs font-medium">
            {dict.app.pages.reportsApGlReconciliation.warningApControlAccountApControl}
          </div>
        ) : null}

        <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
          <div className="bg-[var(--card)] p-4 rounded-xl border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsApGlReconciliation.apSubledgerTotal}</div>
            <div className="text-base font-bold text-[var(--text-primary)]">
              {formatMoney(report.subledger_total_minor, report.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-4 rounded-xl border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">
              {dict.app.pages.reportsApGlReconciliation.apControlAccountGl}
              {report.ap_control_account ? ` (${report.ap_control_account.code})` : ''}
            </div>
            <div className="text-base font-bold text-blue-600">
              {formatMoney(report.gl_total_minor, report.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-4 rounded-xl border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsApGlReconciliation.reconciliationDifference}</div>
            <div className={`text-base font-bold ${report.difference_minor === 0 ? 'text-emerald-600' : 'text-rose-600'}`}>
              {formatMoney(report.difference_minor, report.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-4 rounded-xl border border-[var(--border-color)] flex items-center justify-between">
            <div>
              <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsApGlReconciliation.reconciliationStatus}</div>
              <span className={`px-2.5 py-1 text-xs font-bold rounded-full ${
                report.is_reconciled
                  ? 'bg-emerald-100 text-emerald-800 border border-emerald-300'
                  : 'bg-rose-100 text-rose-800 border border-rose-300'
              }`}>
                {report.is_reconciled ? dict.app.pages.reportsApGlReconciliation.reconciled : dict.app.pages.reportsApGlReconciliation.unreconciled}
              </span>
            </div>
          </div>
        </div>

        <Card className="overflow-hidden p-0">
          <div className="p-3 bg-[var(--background)] font-bold text-xs border-b border-[var(--border-color)]">
            {dict.app.pages.reportsApGlReconciliation.supplierSubledgerBalanceBreakdown}
          </div>
          <table className="w-full text-left text-xs">
            <thead className="bg-[var(--background)]/50 border-b border-[var(--border-color)]">
              <tr>
                <th className="p-3 font-semibold text-[var(--text-secondary)]">{dict.app.pages.reportsApGlReconciliation.supplierCode}</th>
                <th className="p-3 font-semibold text-[var(--text-secondary)]">{dict.app.pages.reportsApGlReconciliation.supplierName}</th>
                <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">{dict.app.pages.reportsApGlReconciliation.subledgerOpenBalance}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[var(--border-color)]">
              {report.supplier_breakdown.map((row) => (
                <tr key={row.supplier_id} className="hover:bg-[var(--background)]/30">
                  <td className="p-3 font-mono font-bold">{row.supplier_code}</td>
                  <td className="p-3 font-medium">{row.supplier_name}</td>
                  <td className="p-3 text-end font-mono font-bold">
                    {formatMoney(row.subledger_balance_minor, report.currency)}
                  </td>
                </tr>
              ))}
              {report.supplier_breakdown.length === 0 ? (
                <tr>
                  <td colSpan={3} className="p-6 text-center text-[var(--text-muted)]">
                    {dict.app.pages.reportsApGlReconciliation.noOpenSupplierSubledgerBalances}
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
