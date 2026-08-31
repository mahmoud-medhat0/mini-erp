import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import SearchableSelect from '../../Components/SearchableSelect';
import { Button, Card, PageHeader } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';
import { getDictionary } from '../../lib/i18n';

type CustomerStatementProps = SharedPageProps & {
  report: {
    customer: { id: string; code: string; name: string; tax_number?: string; phone?: string };
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
  customers: Array<{ id: string; code: string; name: string }>;
  currencies: Array<{ code: string }>;
  filters: { customer_id: string | null; date_from: string; date_to: string; currency: string };
};

export default function CustomerStatement({ locale, report, customers, currencies, filters }: CustomerStatementProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const actionsDict = dict.app.actions;
  const can = useCan();
  const canExport = can('reports.export') && can('view_financials');
  const canPrint = can('reports.print') && can('view_financials');

  const [customerId, setCustomerId] = useState(filters.customer_id || '');
  const [dateFrom, setDateFrom] = useState(filters.date_from);
  const [dateTo, setDateTo] = useState(filters.date_to);
  const [currency, setCurrency] = useState(filters.currency);

  const hasActiveFilters = Boolean(customerId || dateFrom || dateTo);

  const handleFilter = () => {
    router.get('/reports/customer-statement', {
      customer_id: customerId,
      date_from: dateFrom,
      date_to: dateTo,
      currency,
    }, { preserveScroll: true });
  };

  const handleReset = () => {
    setCustomerId('');
    router.get('/reports/customer-statement', {
      currency,
    }, { preserveScroll: true });
  };

  const handleExport = () => {
    if (!customerId) return;
    const url = `/reports/customer-statement/export?customer_id=${customerId}&date_from=${dateFrom}&date_to=${dateTo}&currency=${currency}`;
    window.open(url, '_blank');
  };

  return (
    <AppLayout active="reports.customer-statement">
      <Head title={dict.app.pages.reportsCustomerStatement.customerStatementMiniErp} />

      <PageHeader
        title={dict.app.pages.reportsCustomerStatement.customerStatement}
        description={dict.app.pages.reportsCustomerStatement.detailedSubledgerStatementShowingOpeningBalance}
        actions={
          report ? (
            <div className="flex items-center gap-2">
              {canPrint ? (
                <Button variant="secondary" onClick={() => window.print()}>
                  {actionsDict.printReport}
                </Button>
              ) : null}
              {canExport ? (
                <Button variant="secondary" onClick={handleExport}>
                  {dict.app.pages.reportsCustomerStatement.exportCsv}
                </Button>
              ) : null}
            </div>
          ) : undefined
        }
      />

      <div className="space-y-6">
        <Card className="p-4">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {dict.app.pages.reportsCustomerStatement.customer}
              </label>
              <SearchableSelect
                options={customers.map((c) => ({ value: c.id, label: `${c.code} - ${c.name}` }))}
                value={customerId}
                onChange={(val) => setCustomerId(val || '')}
                placeholder={dict.app.pages.reportsCustomerStatement.selectCustomer}
              />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {dict.app.pages.reportsCustomerStatement.fromDate}
              </label>
              <DatePicker value={dateFrom} onChange={(val) => setDateFrom(val || '')} />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {dict.app.pages.reportsCustomerStatement.toDate}
              </label>
              <DatePicker value={dateTo} onChange={(val) => setDateTo(val || '')} />
            </div>
            <div className="flex items-center gap-2">
              <Button onClick={handleFilter} className="flex-1">
                {dict.app.pages.reportsCustomerStatement.viewReport}
              </Button>
              <Button
                variant="secondary"
                onClick={handleReset}
                disabled={!hasActiveFilters}
                title={actionsDict.reset}
                aria-label={actionsDict.reset}
              >
                {actionsDict.reset}
              </Button>
            </div>
          </div>
        </Card>

        {report ? (
          <div className="space-y-4">
            <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
              <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
                <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsCustomerStatement.openingBalance}</div>
                <div className="text-sm font-bold text-[var(--text-primary)]">
                  {formatMoney(report.opening_balance_minor, report.filters.currency)}
                </div>
              </div>
              <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
                <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsCustomerStatement.totalDebitIncrease}</div>
                <div className="text-sm font-bold text-emerald-600">
                  {formatMoney(report.total_debit_minor, report.filters.currency)}
                </div>
              </div>
              <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
                <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsCustomerStatement.totalCreditPayments}</div>
                <div className="text-sm font-bold text-blue-600">
                  {formatMoney(report.total_credit_minor, report.filters.currency)}
                </div>
              </div>
              <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
                <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsCustomerStatement.closingBalance}</div>
                <div className="text-sm font-bold text-[var(--text-primary)]">
                  {formatMoney(report.closing_balance_minor, report.filters.currency)}
                </div>
              </div>
            </div>

            <Card className="overflow-hidden p-0">
              <table className="w-full text-start text-xs">
                <thead className="bg-[var(--background)] border-b border-[var(--border-color)]">
                  <tr>
                    <th className="p-3 font-semibold text-start text-[var(--text-secondary)]">{dict.app.pages.reportsCustomerStatement.date}</th>
                    <th className="p-3 font-semibold text-start text-[var(--text-secondary)]">{dict.app.pages.reportsCustomerStatement.type}</th>
                    <th className="p-3 font-semibold text-start text-[var(--text-secondary)]">{dict.app.pages.reportsCustomerStatement.reference}</th>
                    <th className="p-3 font-semibold text-start text-[var(--text-secondary)]">{dict.app.pages.reportsCustomerStatement.description}</th>
                    <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">{dict.app.pages.reportsCustomerStatement.debitIncrease}</th>
                    <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">{dict.app.pages.reportsCustomerStatement.creditPayment}</th>
                    <th className="p-3 font-semibold text-end text-[var(--text-secondary)]">{dict.app.pages.reportsCustomerStatement.runningBalance}</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[var(--border-color)]">
                  <tr className="bg-[var(--background)]/50 font-bold">
                    <td colSpan={6} className="p-3">{dict.app.pages.reportsCustomerStatement.openingBalancePriorToRange}</td>
                    <td className="p-3 text-end">{formatMoney(report.opening_balance_minor, report.filters.currency)}</td>
                  </tr>
                  {report.lines.map((line, idx) => (
                    <tr key={idx} className="hover:bg-[var(--background)]/30">
                      <td className="p-3">{line.date}</td>
                      <td className="p-3 font-medium">{line.type}</td>
                      <td className="p-3 font-mono">{line.reference}</td>
                      <td className="p-3 text-[var(--text-secondary)]">{line.description}</td>
                      <td className="p-3 text-end font-mono">
                        {line.debit_minor > 0 ? formatMoney(line.debit_minor, report.filters.currency) : accDict.zeroAmount}
                      </td>
                      <td className="p-3 text-end font-mono">
                        {line.credit_minor > 0 ? formatMoney(line.credit_minor, report.filters.currency) : accDict.zeroAmount}
                      </td>
                      <td className="p-3 text-end font-mono font-bold">
                        {formatMoney(line.running_balance_minor, report.filters.currency)}
                      </td>
                    </tr>
                  ))}
                  {report.lines.length === 0 ? (
                    <tr>
                      <td colSpan={7} className="p-6 text-center text-[var(--text-muted)]">
                        {dict.app.pages.reportsCustomerStatement.noMovementsFoundForTheSelected}
                      </td>
                    </tr>
                  ) : null}
                </tbody>
              </table>
            </Card>
          </div>
        ) : (
          <Card className="p-12 text-center text-[var(--text-muted)]">
            {dict.app.pages.reportsCustomerStatement.pleaseSelectACustomerAndPeriod}
          </Card>
        )}
      </div>
    </AppLayout>
  );
}
