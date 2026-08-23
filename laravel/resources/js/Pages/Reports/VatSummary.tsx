import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { Button, Card, EmptyState, PageHeader, tableClasses } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types';

type VatSummaryRow = {
  tax_code_id: string;
  code: string;
  name: Record<string, string> | string;
  tax_type: string;
  calculation_mode: string;
  rate_bps: number;
  subtotal_minor: number;
  tax_amount_minor: number;
  gross_amount_minor: number;
};

type VatSummaryProps = SharedPageProps & {
  report: {
    from_date: string;
    to_date: string;
    output_vat_breakdown: VatSummaryRow[];
    input_vat_breakdown: VatSummaryRow[];
    summary: {
      total_output_subtotal_minor: number;
      total_output_tax_minor: number;
      total_output_gross_minor: number;
      total_input_subtotal_minor: number;
      total_input_tax_minor: number;
      total_input_gross_minor: number;
      net_vat_payable_minor: number;
    };
  };
  filters: {
    from_date: string;
    to_date: string;
  };
};

export default function VatSummary({ locale, report, filters }: VatSummaryProps) {
  const dict = getDictionary(locale) as any;

  const [fromDate, setFromDate] = useState(filters.from_date || report.from_date);
  const [toDate, setToDate] = useState(filters.to_date || report.to_date);

  const t = dict.taxes?.vatSummary || {};

  const handleFilter = () => {
    router.get('/reports/vat-summary', {
      from_date: fromDate,
      to_date: toDate,
    });
  };

  const handleExport = () => {
    window.open(`/reports/vat-summary/export?from_date=${fromDate}&to_date=${toDate}`, '_blank');
  };

  const formatCodeName = (name: Record<string, string> | string, code: string) => {
    if (typeof name === 'object') {
      return name[locale] || name.en || code;
    }
    return name || code;
  };

  const thRightClass = "px-4 py-3 text-right font-medium text-xs text-[var(--text-secondary)] uppercase border-b border-[var(--border-color)]";
  const tdRightClass = "px-4 py-3 text-right text-xs border-b border-[var(--border-color)]";

  return (
    <AppLayout active="reports.vat-summary">
      <Head title={`${t.title || 'VAT Summary Report'} - Mini ERP`} />

      <PageHeader
        title={t.title || 'VAT Summary Report'}
        description={t.subtitle || 'Summary of output and input VAT grouped by tax code for tax return filing.'}
        actions={
          <Button variant="secondary" onClick={handleExport}>
            {t.exportCsv || 'Export CSV'}
          </Button>
        }
      />

      <div className="space-y-6">
        <Card className="p-4">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
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
              <Button onClick={handleFilter} className="w-full">
                {t.updateReport || 'Update Summary'}
              </Button>
            </div>
          </div>
        </Card>

        {/* Output VAT Section */}
        <Card className="p-0 overflow-hidden">
          <div className="p-4 border-b border-[var(--border-color)] bg-[var(--surface-color)] flex justify-between items-center">
            <h3 className="text-sm font-bold text-[var(--text-primary)]">
              {t.outputVatHeader || 'Output VAT (Sales & Revenue)'}
            </h3>
            <span className="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
              Total Tax: {formatMoney(report.summary.total_output_tax_minor)}
            </span>
          </div>

          {report.output_vat_breakdown.length === 0 ? (
            <EmptyState title={t.noOutputRecords || 'No output tax entries recorded'} description="" />
          ) : (
            <div className="overflow-x-auto">
              <table className={tableClasses.table}>
                <thead className="bg-[var(--surface-color)]">
                  <tr>
                    <th className={tableClasses.th}>{t.taxCode || 'Tax Code'}</th>
                    <th className={tableClasses.th}>{t.rate || 'Rate'}</th>
                    <th className={thRightClass}>{t.taxableAmount || 'Taxable Subtotal'}</th>
                    <th className={thRightClass}>{t.taxAmount || 'Tax Amount'}</th>
                    <th className={thRightClass}>{t.grossAmount || 'Gross Total'}</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[var(--border-color)]">
                  {report.output_vat_breakdown.map((row) => (
                    <tr key={row.tax_code_id} className="hover:bg-[var(--surface-color)] transition-colors">
                      <td className={tableClasses.td}>
                        <span className="font-semibold text-[var(--text-primary)]">{row.code}</span>
                        <span className="text-xs text-[var(--text-secondary)] block">{formatCodeName(row.name, row.code)}</span>
                      </td>
                      <td className={tableClasses.td}>{row.rate_bps / 100}%</td>
                      <td className={`${tdRightClass} font-mono`}>{formatMoney(row.subtotal_minor)}</td>
                      <td className={`${tdRightClass} font-mono font-bold text-emerald-600 dark:text-emerald-400`}>
                        {formatMoney(row.tax_amount_minor)}
                      </td>
                      <td className={`${tdRightClass} font-mono`}>{formatMoney(row.gross_amount_minor)}</td>
                    </tr>
                  ))}
                  <tr className="bg-[var(--surface-color)] font-bold">
                    <td className={tableClasses.td} colSpan={2}>
                      Total Output VAT
                    </td>
                    <td className={`${tdRightClass} font-mono`}>{formatMoney(report.summary.total_output_subtotal_minor)}</td>
                    <td className={`${tdRightClass} font-mono text-emerald-600 dark:text-emerald-400`}>
                      {formatMoney(report.summary.total_output_tax_minor)}
                    </td>
                    <td className={`${tdRightClass} font-mono`}>{formatMoney(report.summary.total_output_gross_minor)}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          )}
        </Card>

        {/* Input VAT Section */}
        <Card className="p-0 overflow-hidden">
          <div className="p-4 border-b border-[var(--border-color)] bg-[var(--surface-color)] flex justify-between items-center">
            <h3 className="text-sm font-bold text-[var(--text-primary)]">
              {t.inputVatHeader || 'Input VAT (Purchases & Expenses)'}
            </h3>
            <span className="text-xs font-semibold text-sky-600 dark:text-sky-400">
              Total Tax: {formatMoney(report.summary.total_input_tax_minor)}
            </span>
          </div>

          {report.input_vat_breakdown.length === 0 ? (
            <EmptyState title={t.noInputRecords || 'No input tax entries recorded'} description="" />
          ) : (
            <div className="overflow-x-auto">
              <table className={tableClasses.table}>
                <thead className="bg-[var(--surface-color)]">
                  <tr>
                    <th className={tableClasses.th}>{t.taxCode || 'Tax Code'}</th>
                    <th className={tableClasses.th}>{t.rate || 'Rate'}</th>
                    <th className={thRightClass}>{t.taxableAmount || 'Taxable Subtotal'}</th>
                    <th className={thRightClass}>{t.taxAmount || 'Tax Amount'}</th>
                    <th className={thRightClass}>{t.grossAmount || 'Gross Total'}</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[var(--border-color)]">
                  {report.input_vat_breakdown.map((row) => (
                    <tr key={row.tax_code_id} className="hover:bg-[var(--surface-color)] transition-colors">
                      <td className={tableClasses.td}>
                        <span className="font-semibold text-[var(--text-primary)]">{row.code}</span>
                        <span className="text-xs text-[var(--text-secondary)] block">{formatCodeName(row.name, row.code)}</span>
                      </td>
                      <td className={tableClasses.td}>{row.rate_bps / 100}%</td>
                      <td className={`${tdRightClass} font-mono`}>{formatMoney(row.subtotal_minor)}</td>
                      <td className={`${tdRightClass} font-mono font-bold text-sky-600 dark:text-sky-400`}>
                        {formatMoney(row.tax_amount_minor)}
                      </td>
                      <td className={`${tdRightClass} font-mono`}>{formatMoney(row.gross_amount_minor)}</td>
                    </tr>
                  ))}
                  <tr className="bg-[var(--surface-color)] font-bold">
                    <td className={tableClasses.td} colSpan={2}>
                      Total Input VAT
                    </td>
                    <td className={`${tdRightClass} font-mono`}>{formatMoney(report.summary.total_input_subtotal_minor)}</td>
                    <td className={`${tdRightClass} font-mono text-sky-600 dark:text-sky-400`}>
                      {formatMoney(report.summary.total_input_tax_minor)}
                    </td>
                    <td className={`${tdRightClass} font-mono`}>{formatMoney(report.summary.total_input_gross_minor)}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          )}
        </Card>

        {/* Net Tax Summary Card */}
        <Card className="p-6 bg-gradient-to-br from-[var(--card)] to-[var(--surface-color)]">
          <div className="flex flex-col md:flex-row justify-between items-center gap-4">
            <div>
              <div className="text-xs font-semibold text-[var(--text-secondary)] uppercase tracking-wider mb-1">
                {t.netVatPayable || 'Net VAT Payable / (Claimable)'}
              </div>
              <div className="text-2xl font-black text-[var(--text-primary)]">
                {formatMoney(report.summary.net_vat_payable_minor)}
              </div>
            </div>
            <div className="flex gap-6 text-sm text-right">
              <div>
                <span className="text-xs text-[var(--text-secondary)] block">Output VAT</span>
                <span className="font-bold text-emerald-600">{formatMoney(report.summary.total_output_tax_minor)}</span>
              </div>
              <div className="border-l border-[var(--border-color)] pl-6">
                <span className="text-xs text-[var(--text-secondary)] block">Input VAT</span>
                <span className="font-bold text-sky-600">{formatMoney(report.summary.total_input_tax_minor)}</span>
              </div>
            </div>
          </div>
        </Card>
      </div>
    </AppLayout>
  );
}
