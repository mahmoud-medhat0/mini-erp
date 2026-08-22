import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import SearchableSelect from '../../Components/SearchableSelect';
import { Button, Card, PageHeader } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import type { SharedPageProps } from '../../Types';
import { getDictionary } from '../../lib/i18n';

type SupplierStatementProps = SharedPageProps & {
  report: {
    supplier: { id: string; code: string; name: string; tax_number?: string; phone?: string };
    filters: { date_from: string; date_to: string; currency: string };
    opening_balance_minor: number;
    lines: Array<{
      date: string;
      type: string;
      reference: string;
      description: string;
      debit_minor: number;
      credit_minor: number;
      running_balance_minor: number;
    }>;
    total_debit_minor: number;
    total_credit_minor: number;
    closing_balance_minor: number;
  } | null;
  suppliers: Array<{ id: string; code: string; name: string }>;
  currencies: Array<{ code: string }>;
  filters: { supplier_id: string | null; date_from: string; date_to: string; currency: string };
};

export default function SupplierStatement({ locale, report, suppliers, currencies, filters }: SupplierStatementProps) {
  const dict = getDictionary(locale);

  const [supplierId, setSupplierId] = useState(filters.supplier_id || '');
  const [dateFrom, setDateFrom] = useState(filters.date_from);
  const [dateTo, setDateTo] = useState(filters.date_to);
  const [currency, setCurrency] = useState(filters.currency);

  const handleFilter = () => {
    router.get('/reports/supplier-statement', {
      supplier_id: supplierId,
      date_from: dateFrom,
      date_to: dateTo,
      currency,
    });
  };

  const handleExport = () => {
    if (!supplierId) return;
    const url = `/reports/supplier-statement/export?supplier_id=${supplierId}&date_from=${dateFrom}&date_to=${dateTo}&currency=${currency}`;
    window.open(url, '_blank');
  };

  return (
    <AppLayout active="reports.supplier-statement">
      <Head title={dict.app.pages.reportsSupplierStatement.supplierStatementMiniErp} />

      <PageHeader
        title={dict.app.pages.reportsSupplierStatement.supplierStatement}
        description={dict.app.pages.reportsSupplierStatement.detailedSubledgerStatementShowingOpeningBalance}
        actions={
          report ? (
            <Button variant="secondary" onClick={handleExport}>
              {dict.app.pages.reportsSupplierStatement.exportCsv}
            </Button>
          ) : undefined
        }
      />

      <div className="space-y-6">
        <Card className="p-4">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {dict.app.pages.reportsSupplierStatement.supplier}
              </label>
              <SearchableSelect
                options={suppliers.map((s) => ({ value: s.id, label: `${s.code} - ${s.name}` }))}
                value={supplierId}
                onChange={(val) => setSupplierId(val || '')}
                placeholder={dict.app.pages.reportsSupplierStatement.selectSupplier}
              />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {dict.app.pages.reportsSupplierStatement.fromDate}
              </label>
              <DatePicker value={dateFrom} onChange={(val) => setDateFrom(val || '')} />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {dict.app.pages.reportsSupplierStatement.toDate}
              </label>
              <DatePicker value={dateTo} onChange={(val) => setDateTo(val || '')} />
            </div>
            <div>
              <Button onClick={handleFilter} className="w-full">
                {dict.app.pages.reportsSupplierStatement.viewReport}
              </Button>
            </div>
          </div>
        </Card>

        {report ? (
          <div className="space-y-4">
            <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
              <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
                <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsSupplierStatement.openingBalance}</div>
                <div className="text-sm font-bold text-[var(--text-primary)]">
                  {formatMoney(report.opening_balance_minor, report.filters.currency)}
                </div>
              </div>
              <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
                <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsSupplierStatement.totalDebitPayments}</div>
                <div className="text-sm font-bold text-blue-600">
                  {formatMoney(report.total_debit_minor, report.filters.currency)}
                </div>
              </div>
              <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
                <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsSupplierStatement.totalCreditIncrease}</div>
                <div className="text-sm font-bold text-emerald-600">
                  {formatMoney(report.total_credit_minor, report.filters.currency)}
                </div>
              </div>
              <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
                <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsSupplierStatement.closingBalance}</div>
                <div className="text-sm font-bold text-[var(--text-primary)]">
                  {formatMoney(report.closing_balance_minor, report.filters.currency)}
                </div>
              </div>
            </div>

            <Card className="overflow-hidden p-0">
              <table className="w-full text-left text-xs">
                <thead className="bg-[var(--background)] border-b border-[var(--border-color)]">
                  <tr>
                    <th className="p-3 font-semibold text-[var(--text-secondary)]">{dict.app.pages.reportsSupplierStatement.date}</th>
                    <th className="p-3 font-semibold text-[var(--text-secondary)]">{dict.app.pages.reportsSupplierStatement.type}</th>
                    <th className="p-3 font-semibold text-[var(--text-secondary)]">{dict.app.pages.reportsSupplierStatement.reference}</th>
                    <th className="p-3 font-semibold text-[var(--text-secondary)]">{dict.app.pages.reportsSupplierStatement.description}</th>
                    <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">{dict.app.pages.reportsSupplierStatement.debitPayment}</th>
                    <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">{dict.app.pages.reportsSupplierStatement.creditIncrease}</th>
                    <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">{dict.app.pages.reportsSupplierStatement.runningBalance}</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[var(--border-color)]">
                  <tr className="bg-[var(--background)]/50 font-bold">
                    <td colSpan={6} className="p-3">{dict.app.pages.reportsSupplierStatement.openingBalancePriorToRange}</td>
                    <td className="p-3 text-end">{formatMoney(report.opening_balance_minor, report.filters.currency)}</td>
                  </tr>
                  {report.lines.map((line, idx) => (
                    <tr key={idx} className="hover:bg-[var(--background)]/30">
                      <td className="p-3">{line.date}</td>
                      <td className="p-3 font-medium">{line.type}</td>
                      <td className="p-3 font-mono">{line.reference}</td>
                      <td className="p-3 text-[var(--text-secondary)]">{line.description}</td>
                      <td className="p-3 text-end font-mono">
                        {line.debit_minor > 0 ? formatMoney(line.debit_minor, report.filters.currency) : '—'}
                      </td>
                      <td className="p-3 text-end font-mono">
                        {line.credit_minor > 0 ? formatMoney(line.credit_minor, report.filters.currency) : '—'}
                      </td>
                      <td className="p-3 text-end font-mono font-bold">
                        {formatMoney(line.running_balance_minor, report.filters.currency)}
                      </td>
                    </tr>
                  ))}
                  {report.lines.length === 0 ? (
                    <tr>
                      <td colSpan={7} className="p-6 text-center text-[var(--text-muted)]">
                        {dict.app.pages.reportsSupplierStatement.noMovementsFoundForTheSelected}
                      </td>
                    </tr>
                  ) : null}
                </tbody>
              </table>
            </Card>
          </div>
        ) : (
          <Card className="p-12 text-center text-[var(--text-muted)]">
            {dict.app.pages.reportsSupplierStatement.pleaseSelectASupplierAndPeriod}
          </Card>
        )}
      </div>
    </AppLayout>
  );
}
