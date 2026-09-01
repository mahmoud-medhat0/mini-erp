import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import SearchableSelect from '../../Components/SearchableSelect';
import { Button, Card, PageHeader } from '../../Components/Primitives';
import VatRegisterDataTable from '../../Components/VatRegisterDataTable';
import { formatMoney } from '../../lib/accountingHelpers';
import { useCan } from '../../lib/permissions';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types';

type TaxCodeOption = {
  id: string;
  code: string;
  name: Record<string, string> | string;
};

type VatRegisterProps = SharedPageProps & {
  report: {
    from_date: string;
    to_date: string;
    type: string;
    tax_code_id: string | null;
    currency?: string | null;
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
  const dict = getDictionary(locale);
  const actionsDict = dict.app.actions;
  const can = useCan();
  const canExport = (can('reports.export') || can('taxes.view')) && can('view_financials');
  const canPrint = can('reports.print') && can('view_financials');

  const [fromDate, setFromDate] = useState(filters.from_date || report.from_date);
  const [toDate, setToDate] = useState(filters.to_date || report.to_date);
  const [type, setType] = useState(filters.type || 'all');
  const [taxCodeId, setTaxCodeId] = useState(filters.tax_code_id || '');

  const t = dict.app.taxes.vatRegister;
  const appName = dict.app.accounting.appName;
  const accDict = dict.app.accounting;
  const formatVatMoney = (amountMinor: number) => (report.currency ? formatMoney(amountMinor, report.currency) : accDict.notAvailable);

  const handleFilter = () => {
    router.get('/reports/vat-register', {
      from_date: fromDate,
      to_date: toDate,
      type,
      tax_code_id: taxCodeId || undefined,
    }, { preserveScroll: true });
  };

  const handleExport = () => {
    let url = `/reports/vat-register/export?from_date=${fromDate}&to_date=${toDate}&type=${type}`;
    if (taxCodeId) {
      url += `&tax_code_id=${taxCodeId}`;
    }
    window.open(url, '_blank');
  };

  const categoryOptions = [
    { value: 'all', label: t.allCategories },
    { value: 'output', label: t.outputSales },
    { value: 'input', label: t.inputPurchases },
  ];

  const taxCodeOptions = [
    { value: '', label: t.allTaxCodes },
    ...taxCodes.map((tc) => {
      const name = typeof tc.name === 'object' ? (tc.name[locale] || tc.name.en || tc.code) : tc.name;
      return { value: tc.id, label: `${tc.code} - ${name}` };
    }),
  ];

  return (
    <AppLayout active="reports.vat-register">
      <Head title={`${t.title} - ${appName}`} />

      <PageHeader
        title={t.title}
        description={t.subtitle}
        actions={
          <div className="flex items-center gap-2">
            {canPrint ? (
              <Button variant="secondary" onClick={() => window.print()}>
                {actionsDict.printReport}
              </Button>
            ) : null}
            {canExport ? (
              <Button variant="secondary" onClick={handleExport}>
                {t.exportCsv}
              </Button>
            ) : null}
          </div>
        }
      />

      <div className="space-y-6">
        <Card className="p-4">
          <div className="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {t.fromDate}
              </label>
              <DatePicker value={fromDate} onChange={(val) => setFromDate(val || '')} />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {t.toDate}
              </label>
              <DatePicker value={toDate} onChange={(val) => setToDate(val || '')} />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {t.taxCategory}
              </label>
              <SearchableSelect
                options={categoryOptions}
                value={type}
                onChange={(val) => setType(val || 'all')}
              />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {t.taxCode}
              </label>
              <SearchableSelect
                options={taxCodeOptions}
                value={taxCodeId}
                onChange={(val) => setTaxCodeId(val || '')}
              />
            </div>
            <div>
              <Button onClick={handleFilter} className="w-full">
                {t.updateReport}
              </Button>
            </div>
          </div>
        </Card>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div className="bg-[var(--card)] p-4 rounded-xl border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{t.totalOutput}</div>
            <div className="text-lg font-bold text-emerald-600 dark:text-emerald-400">
              {formatVatMoney(report.summary.total_output_tax_minor)}
            </div>
            <div className="text-xs text-[var(--text-secondary)] mt-1">
              {t.netSubtotal}: {formatVatMoney(report.summary.total_output_subtotal_minor)}
            </div>
          </div>

          <div className="bg-[var(--card)] p-4 rounded-xl border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{t.totalInput}</div>
            <div className="text-lg font-bold text-sky-600 dark:text-sky-400">
              {formatVatMoney(report.summary.total_input_tax_minor)}
            </div>
            <div className="text-xs text-[var(--text-secondary)] mt-1">
              {t.netSubtotal}: {formatVatMoney(report.summary.total_input_subtotal_minor)}
            </div>
          </div>

          <div className="bg-[var(--card)] p-4 rounded-xl border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{t.netVatPayable}</div>
            <div className={`text-lg font-bold ${report.summary.net_vat_payable_minor >= 0 ? 'text-[var(--text-primary)]' : 'text-amber-600'}`}>
              {formatVatMoney(report.summary.net_vat_payable_minor)}
            </div>
          </div>
        </div>

        <Card className="overflow-hidden p-0">
          <VatRegisterDataTable
            key={`${filters.from_date}-${filters.to_date}-${type}-${taxCodeId || 'all'}`}
            currency={report.currency || ''}
            filters={{
              from_date: filters.from_date,
              to_date: filters.to_date,
              type: filters.type || 'all',
              tax_code_id: filters.tax_code_id || null,
            }}
            labels={{
              documentDate: t.documentDate,
              documentType: t.documentType,
              documentNumber: t.documentNumber,
              entity: t.entity,
              taxCategory: t.taxCategory,
              taxCode: t.taxCode,
              subtotal: t.subtotal,
              taxAmount: t.taxAmount,
              grossAmount: t.grossAmount,
              documentTypes: {
                customer_invoice: accDict.entity_customer_invoice,
                customer_credit_note: accDict.entity_customer_credit_note,
                sales_return: accDict.entity_sales_return,
                rental_invoice: accDict.entity_rental_invoice,
                supplier_bill: accDict.entity_supplier_bill,
                supplier_adjustment_note: accDict.entity_supplier_adjustment_note,
                purchase_return: accDict.entity_purchase_return,
              },
              categories: {
                output: t.outputCategory,
                input: t.inputCategory,
              },
            }}
            locale={locale}
          />
        </Card>
      </div>
    </AppLayout>
  );
}
