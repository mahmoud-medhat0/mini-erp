import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import SearchableSelect from '../../Components/SearchableSelect';
import { Button, Card, EmptyState, PageHeader, tableClasses } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types';

type TaxCodeOption = {
  id: string;
  code: string;
  name: Record<string, string> | string;
};

type VatRegisterRow = {
  document_type: string;
  document_id: string;
  document_number: string;
  document_date: string;
  entity_type: string;
  entity_name: string;
  tax_category: 'output' | 'input';
  tax_code_id: string;
  tax_code: string;
  tax_rate_bps: number;
  subtotal_minor: number;
  tax_amount_minor: number;
  gross_amount_minor: number;
};

type VatRegisterProps = SharedPageProps & {
  report: {
    from_date: string;
    to_date: string;
    type: string;
    tax_code_id: string | null;
    rows: VatRegisterRow[];
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
  taxCodes: TaxCodeOption[];
  filters: {
    from_date: string;
    to_date: string;
    type?: string;
    tax_code_id?: string;
  };
};

export default function VatRegister({ locale, report, taxCodes, filters }: VatRegisterProps) {
  const dict = getDictionary(locale) as any;

  const [fromDate, setFromDate] = useState(filters.from_date || report.from_date);
  const [toDate, setToDate] = useState(filters.to_date || report.to_date);
  const [type, setType] = useState(filters.type || 'all');
  const [taxCodeId, setTaxCodeId] = useState(filters.tax_code_id || '');

  const t = dict.taxes?.vatRegister || {};

  const handleFilter = () => {
    router.get('/reports/vat-register', {
      from_date: fromDate,
      to_date: toDate,
      type,
      tax_code_id: taxCodeId || undefined,
    });
  };

  const handleExport = () => {
    let url = `/reports/vat-register/export?from_date=${fromDate}&to_date=${toDate}&type=${type}`;
    if (taxCodeId) {
      url += `&tax_code_id=${taxCodeId}`;
    }
    window.open(url, '_blank');
  };

  const categoryOptions = [
    { value: 'all', label: t.allCategories || 'All Categories' },
    { value: 'output', label: t.outputSales || 'Sales Output VAT' },
    { value: 'input', label: t.inputPurchases || 'Purchasing Input VAT' },
  ];

  const taxCodeOptions = [
    { value: '', label: t.allTaxCodes || 'All Tax Codes' },
    ...taxCodes.map((tc) => {
      const name = typeof tc.name === 'object' ? (tc.name[locale] || tc.name.en || tc.code) : tc.name;
      return { value: tc.id, label: `${tc.code} - ${name}` };
    }),
  ];

  const thRightClass = "px-4 py-3 text-right font-medium text-xs text-[var(--text-secondary)] uppercase border-b border-[var(--border-color)]";
  const tdRightClass = "px-4 py-3 text-right text-xs border-b border-[var(--border-color)]";

  return (
    <AppLayout active="reports.vat-register">
      <Head title={`${t.title || 'VAT Register'} - Mini ERP`} />

      <PageHeader
        title={t.title || 'VAT Register'}
        description={t.subtitle || 'Detailed line-item audit trail of output and input tax for posted documents.'}
        actions={
          <Button variant="secondary" onClick={handleExport}>
            {t.exportCsv || 'Export CSV'}
          </Button>
        }
      />

      <div className="space-y-6">
        <Card className="p-4">
          <div className="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
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
                {t.taxCategory || 'Tax Category'}
              </label>
              <SearchableSelect
                options={categoryOptions}
                value={type}
                onChange={(val) => setType(val || 'all')}
              />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {t.taxCode || 'Tax Code'}
              </label>
              <SearchableSelect
                options={taxCodeOptions}
                value={taxCodeId}
                onChange={(val) => setTaxCodeId(val || '')}
              />
            </div>
            <div>
              <Button onClick={handleFilter} className="w-full">
                {t.updateReport || 'Update Register'}
              </Button>
            </div>
          </div>
        </Card>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div className="bg-[var(--card)] p-4 rounded-xl border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{t.totalOutput || 'Total Output VAT'}</div>
            <div className="text-lg font-bold text-emerald-600 dark:text-emerald-400">
              {formatMoney(report.summary.total_output_tax_minor)}
            </div>
            <div className="text-xs text-[var(--text-secondary)] mt-1">
              Net: {formatMoney(report.summary.total_output_subtotal_minor)}
            </div>
          </div>

          <div className="bg-[var(--card)] p-4 rounded-xl border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{t.totalInput || 'Total Input VAT'}</div>
            <div className="text-lg font-bold text-sky-600 dark:text-sky-400">
              {formatMoney(report.summary.total_input_tax_minor)}
            </div>
            <div className="text-xs text-[var(--text-secondary)] mt-1">
              Net: {formatMoney(report.summary.total_input_subtotal_minor)}
            </div>
          </div>

          <div className="bg-[var(--card)] p-4 rounded-xl border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{t.netVatPayable || 'Net VAT Payable'}</div>
            <div className={`text-lg font-bold ${report.summary.net_vat_payable_minor >= 0 ? 'text-[var(--text-primary)]' : 'text-amber-600'}`}>
              {formatMoney(report.summary.net_vat_payable_minor)}
            </div>
          </div>
        </div>

        <Card>
          {report.rows.length === 0 ? (
            <EmptyState
              title={t.noRecords || 'No posted tax entries found'}
              description="Adjust date range or tax category filters to view registered transactions."
            />
          ) : (
            <div className="overflow-x-auto">
              <table className={tableClasses.table}>
                <thead className="bg-[var(--surface-color)]">
                  <tr>
                    <th className={tableClasses.th}>{t.documentDate || 'Doc Date'}</th>
                    <th className={tableClasses.th}>{t.documentType || 'Doc Type'}</th>
                    <th className={tableClasses.th}>{t.documentNumber || 'Document #'}</th>
                    <th className={tableClasses.th}>{t.entity || 'Party / Name'}</th>
                    <th className={tableClasses.th}>{t.taxCategory || 'Category'}</th>
                    <th className={tableClasses.th}>{t.taxCode || 'Tax Code'}</th>
                    <th className={thRightClass}>{t.subtotal || 'Net Subtotal'}</th>
                    <th className={thRightClass}>{t.taxAmount || 'Tax Amount'}</th>
                    <th className={thRightClass}>{t.grossAmount || 'Gross Total'}</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[var(--border-color)]">
                  {report.rows.map((row, idx) => (
                    <tr key={`${row.document_type}-${row.document_id}-${idx}`} className="hover:bg-[var(--surface-color)] transition-colors">
                      <td className={tableClasses.td}>{row.document_date}</td>
                      <td className={tableClasses.td}>
                        <span className="font-mono text-xs uppercase tracking-wider">{row.document_type.replace('_', ' ')}</span>
                      </td>
                      <td className={`${tableClasses.td} font-semibold`}>{row.document_number}</td>
                      <td className={tableClasses.td}>{row.entity_name}</td>
                      <td className={tableClasses.td}>
                        <span className={`px-2 py-0.5 rounded text-xs font-semibold ${row.tax_category === 'output' ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' : 'bg-sky-500/10 text-sky-700 dark:text-sky-400'}`}>
                          {row.tax_category.toUpperCase()}
                        </span>
                      </td>
                      <td className={tableClasses.td}>
                        <span className="font-semibold">{row.tax_code}</span> ({row.tax_rate_bps / 100}%)
                      </td>
                      <td className={`${tdRightClass} font-mono`}>{formatMoney(row.subtotal_minor)}</td>
                      <td className={`${tdRightClass} font-mono font-bold ${row.tax_amount_minor < 0 ? 'text-rose-600' : ''}`}>
                        {formatMoney(row.tax_amount_minor)}
                      </td>
                      <td className={`${tdRightClass} font-mono`}>{formatMoney(row.gross_amount_minor)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Card>
      </div>
    </AppLayout>
  );
}
