import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import ArApReconciliationDataTable from '../../Components/ArApReconciliationDataTable';
import DatePicker from '../../Components/DatePicker';
import SearchableSelect from '../../Components/SearchableSelect';
import { Button, Card, PageHeader } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import { useCan } from '../../lib/permissions';
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
  };
  currencies: Array<{ code: string }>;
  filters: { as_of_date: string; currency: string };
};

export default function ApGlReconciliation({ locale, report, currencies, filters }: ApGlReconciliationProps) {
  const dict = getDictionary(locale);
  const actionsDict = dict.app.actions;
  const can = useCan();
  const canExport = can('reports.export') && can('view_financials');
  const canPrint = can('reports.print') && can('view_financials');

  const [asOfDate, setAsOfDate] = useState(filters.as_of_date);
  const [currency, setCurrency] = useState(filters.currency);

  const handleFilter = () => {
    router.get('/reports/ap-gl-reconciliation', {
      as_of_date: asOfDate,
      currency,
    }, { preserveScroll: true });
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
          <div className="flex items-center gap-2">
            {canPrint ? (
              <Button variant="secondary" onClick={() => window.print()}>
                {actionsDict.printReport}
              </Button>
            ) : null}
            {canExport ? (
              <Button variant="secondary" onClick={handleExport}>
                {dict.app.pages.reportsApGlReconciliation.exportCsv}
              </Button>
            ) : null}
          </div>
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
                onChange={(val) => setCurrency(val || '')}
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
          <ArApReconciliationDataTable
            currency={report.currency}
            endpoint="/reports/ap-gl-reconciliation/data"
            filters={{ as_of_date: filters.as_of_date, currency: filters.currency }}
            labels={{
              balance: dict.app.pages.reportsApGlReconciliation.subledgerOpenBalance,
              code: dict.app.pages.reportsApGlReconciliation.supplierCode,
              name: dict.app.pages.reportsApGlReconciliation.supplierName,
            }}
            locale={locale}
            tableId="ap-gl-reconciliation-data-table"
          />
        </Card>
      </div>
    </AppLayout>
  );
}
