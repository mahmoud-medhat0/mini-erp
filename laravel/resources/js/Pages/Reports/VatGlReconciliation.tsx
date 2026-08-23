import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import SearchableSelect from '../../Components/SearchableSelect';
import { Button, Card, PageHeader, tableClasses } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types';

type VatGlReconciliationProps = SharedPageProps & {
  report: {
    from_date: string;
    to_date: string;
    currency: string;
    output_tax_account: { id: string; code: string; name: string } | null;
    input_tax_account: { id: string; code: string; name: string } | null;
    register_output_tax_minor: number;
    gl_output_tax_minor: number;
    output_tax_difference_minor: number;
    register_input_tax_minor: number;
    gl_input_tax_minor: number;
    input_tax_difference_minor: number;
    register_net_vat_minor: number;
    gl_net_vat_minor: number;
    net_vat_difference_minor: number;
    is_reconciled: boolean;
    warnings: Array<{ code: string; message_key: string }>;
  };
  currencies: Array<{ code: string }>;
  filters: {
    from_date: string;
    to_date: string;
    currency: string;
  };
};

export default function VatGlReconciliation({ locale, report, currencies, filters }: VatGlReconciliationProps) {
  const dict = getDictionary(locale) as any;

  const [fromDate, setFromDate] = useState(filters.from_date || report.from_date);
  const [toDate, setToDate] = useState(filters.to_date || report.to_date);
  const [currency, setCurrency] = useState(filters.currency || report.currency || 'USD');

  const t = dict.taxes?.vatGlReconciliation || {};
  const tw = dict.taxes?.warnings || {};

  const handleFilter = () => {
    router.get('/reports/vat-gl-reconciliation', {
      from_date: fromDate,
      to_date: toDate,
      currency,
    });
  };

  const handleExport = () => {
    window.open(`/reports/vat-gl-reconciliation/export?from_date=${fromDate}&to_date=${toDate}&currency=${currency}`, '_blank');
  };

  const resolveWarningText = (key: string, code: string) => {
    const keyPath = key.replace('taxes.warnings.', '');
    return tw[keyPath] || code;
  };

  const thRightClass = "px-4 py-3 text-right font-medium text-xs text-[var(--text-secondary)] uppercase border-b border-[var(--border-color)]";
  const tdRightClass = "px-4 py-3 text-right text-xs border-b border-[var(--border-color)]";

  return (
    <AppLayout active="reports.vat-gl-reconciliation">
      <Head title={`${t.title || 'VAT to GL Reconciliation'} - Mini ERP`} />

      <PageHeader
        title={t.title || 'VAT to GL Reconciliation'}
        description={t.subtitle || 'Compares posted tax register totals against GL ledger movement for Output and Input VAT accounts.'}
        actions={
          <Button variant="secondary" onClick={handleExport}>
            {t.exportCsv || 'Export CSV'}
          </Button>
        }
      />

      <div className="space-y-6">
        <Card className="p-4">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {t.fromDate || 'From Date'}
              </label>
              <DatePicker value={fromDate} onChange={(val) => setFromDate(val || '')} />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {t.toDate || 'To Date'}
              </label>
              <DatePicker value={toDate} onChange={(val) => setToDate(val || '')} />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {t.currency || 'Currency'}
              </label>
              <SearchableSelect
                options={currencies.map((c) => ({ value: c.code, label: c.code }))}
                value={currency}
                onChange={(val) => setCurrency(val || 'USD')}
              />
            </div>
            <div>
              <Button onClick={handleFilter} className="w-full">
                {t.updateReport || 'Update Report'}
              </Button>
            </div>
          </div>
        </Card>

        {report.warnings.map((w, i) => (
          <div key={i} className="p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-800 dark:text-amber-300 text-xs font-medium">
            <span className="font-mono font-bold mr-2">[{w.code}]</span>
            {resolveWarningText(w.message_key, w.code)}
          </div>
        ))}

        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div className="bg-[var(--card)] p-4 rounded-xl border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{t.outputAccount || 'Output Tax Account'}</div>
            <div className="text-sm font-bold text-[var(--text-primary)]">
              {report.output_tax_account ? `${report.output_tax_account.code} - ${report.output_tax_account.name}` : (t.notMapped || 'Not Configured')}
            </div>
          </div>

          <div className="bg-[var(--card)] p-4 rounded-xl border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{t.inputAccount || 'Input Tax Account'}</div>
            <div className="text-sm font-bold text-[var(--text-primary)]">
              {report.input_tax_account ? `${report.input_tax_account.code} - ${report.input_tax_account.name}` : (t.notMapped || 'Not Configured')}
            </div>
          </div>

          <div className="bg-[var(--card)] p-4 rounded-xl border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{t.status || 'Reconciliation Status'}</div>
            <div>
              <span className={`inline-block px-3 py-1 rounded-full text-xs font-bold ${report.is_reconciled ? 'bg-emerald-500/20 text-emerald-700 dark:text-emerald-400' : 'bg-rose-500/20 text-rose-700 dark:text-rose-400'}`}>
                {report.is_reconciled ? (t.reconciled || 'RECONCILED') : (t.unreconciled || 'UNRECONCILED DIFFERENCE')}
              </span>
            </div>
          </div>
        </div>

        <Card className="p-0 overflow-hidden">
          <div className="overflow-x-auto">
            <table className={tableClasses.table}>
              <thead className="bg-[var(--surface-color)]">
                <tr>
                  <th className={tableClasses.th}>Tax Category</th>
                  <th className={thRightClass}>Register Tax Amount</th>
                  <th className={thRightClass}>GL Ledger Movement</th>
                  <th className={thRightClass}>Signed Difference</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border-color)]">
                <tr className="hover:bg-[var(--surface-color)] transition-colors">
                  <td className={tableClasses.td}>
                    <span className="font-bold text-[var(--text-primary)]">Output VAT</span> (Sales & Revenue)
                  </td>
                  <td className={`${tdRightClass} font-mono font-semibold`}>
                    {formatMoney(report.register_output_tax_minor)}
                  </td>
                  <td className={`${tdRightClass} font-mono font-semibold`}>
                    {formatMoney(report.gl_output_tax_minor)}
                  </td>
                  <td className={`${tdRightClass} font-mono font-bold ${report.output_tax_difference_minor !== 0 ? 'text-rose-600' : 'text-emerald-600'}`}>
                    {formatMoney(report.output_tax_difference_minor)}
                  </td>
                </tr>

                <tr className="hover:bg-[var(--surface-color)] transition-colors">
                  <td className={tableClasses.td}>
                    <span className="font-bold text-[var(--text-primary)]">Input VAT</span> (Purchases & Expenses)
                  </td>
                  <td className={`${tdRightClass} font-mono font-semibold`}>
                    {formatMoney(report.register_input_tax_minor)}
                  </td>
                  <td className={`${tdRightClass} font-mono font-semibold`}>
                    {formatMoney(report.gl_input_tax_minor)}
                  </td>
                  <td className={`${tdRightClass} font-mono font-bold ${report.input_tax_difference_minor !== 0 ? 'text-rose-600' : 'text-emerald-600'}`}>
                    {formatMoney(report.input_tax_difference_minor)}
                  </td>
                </tr>

                <tr className="bg-[var(--surface-color)] font-bold text-sm">
                  <td className={tableClasses.td}>
                    Net VAT Position (Payable / Claimable)
                  </td>
                  <td className={`${tdRightClass} font-mono`}>
                    {formatMoney(report.register_net_vat_minor)}
                  </td>
                  <td className={`${tdRightClass} font-mono`}>
                    {formatMoney(report.gl_net_vat_minor)}
                  </td>
                  <td className={`${tdRightClass} font-mono font-black ${report.net_vat_difference_minor !== 0 ? 'text-rose-600' : 'text-emerald-600'}`}>
                    {formatMoney(report.net_vat_difference_minor)}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </Card>
      </div>
    </AppLayout>
  );
}
