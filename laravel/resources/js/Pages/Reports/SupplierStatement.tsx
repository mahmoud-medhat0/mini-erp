import { useMemo, useState, type ReactElement } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import SearchableSelect from '../../Components/SearchableSelect';
import ServerDataTable from '../../Components/ServerDataTable';
import { Button, Card, PageHeader } from '../../Components/Primitives';
import { formatMoney, getLocalizedName } from '../../lib/accountingHelpers';
import { useCan } from '../../lib/permissions';
import type { SharedPageProps } from '../../Types';
import { getDictionary } from '../../lib/i18n';

type SupplierStatementProps = SharedPageProps & {
  report: {
    supplier: { id: string; code: string; name: string; tax_number?: string; phone?: string };
    filters: { date_from: string; date_to: string; currency: string };
    opening_balance_minor: number;
    total_debit_minor: number;
    total_credit_minor: number;
    closing_balance_minor: number;
  } | null;
  suppliers: Array<{ id: string; code: string; name: string }>;
  currencies: Array<{ code: string }>;
  filters: { supplier_id: string | null; date_from: string; date_to: string; currency: string };
};

type StatementTableSlots = Record<string, (data: any, row: any) => ReactElement>;

export default function SupplierStatement({ locale, report, suppliers, currencies, filters }: SupplierStatementProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const actionsDict = dict.app.actions;
  const can = useCan();
  const canExport = can('reports.export') && can('view_financials');
  const canPrint = can('reports.print') && can('view_financials');

  const [supplierId, setSupplierId] = useState(filters.supplier_id || '');
  const [dateFrom, setDateFrom] = useState(filters.date_from);
  const [dateTo, setDateTo] = useState(filters.date_to);
  const [currency, setCurrency] = useState(filters.currency);

  const tableColumns = useMemo(() => [
    { data: 'date', name: 'date', title: dict.app.pages.reportsSupplierStatement.date },
    { data: 'type', name: 'type', title: dict.app.pages.reportsSupplierStatement.type },
    { data: 'reference', name: 'reference', title: dict.app.pages.reportsSupplierStatement.reference },
    { data: 'description', name: 'description', title: dict.app.pages.reportsSupplierStatement.description },
    { data: 'debit_minor', name: 'debit_minor', title: dict.app.pages.reportsSupplierStatement.debitPayment, searchable: false },
    { data: 'credit_minor', name: 'credit_minor', title: dict.app.pages.reportsSupplierStatement.creditIncrease, searchable: false },
    { data: 'running_balance_minor', name: 'running_balance_minor', title: dict.app.pages.reportsSupplierStatement.runningBalance, searchable: false },
  ], [dict]);
  const tableSlots = useMemo<StatementTableSlots>(() => ({
    type: (data) => <span className="font-medium">{String(data)}</span>,
    reference: (data) => <span className="font-mono">{String(data)}</span>,
    description: (data) => <span className="text-[var(--text-secondary)]">{String(data)}</span>,
    debit_minor: (data) => (
      <span className="font-mono">
        {Number(data) > 0 ? formatMoney(Number(data), report?.filters.currency) : accDict.zeroAmount}
      </span>
    ),
    credit_minor: (data) => (
      <span className="font-mono">
        {Number(data) > 0 ? formatMoney(Number(data), report?.filters.currency) : accDict.zeroAmount}
      </span>
    ),
    running_balance_minor: (data) => (
      <span className="font-mono font-bold">{formatMoney(Number(data), report?.filters.currency)}</span>
    ),
  }), [accDict.zeroAmount, report?.filters.currency]);
  const tableFilters = useMemo(() => ({
    supplier_id: filters.supplier_id,
    date_from: filters.date_from,
    date_to: filters.date_to,
    currency: filters.currency,
  }), [filters.currency, filters.date_from, filters.date_to, filters.supplier_id]);

  const hasActiveFilters = Boolean(supplierId || dateFrom || dateTo);

  const handleFilter = () => {
    router.get('/reports/supplier-statement', {
      supplier_id: supplierId,
      date_from: dateFrom,
      date_to: dateTo,
      currency,
    }, { preserveScroll: true });
  };

  const handleReset = () => {
    setSupplierId('');
    router.get('/reports/supplier-statement', {
      currency,
    }, { preserveScroll: true });
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
            <div className="flex items-center gap-2">
              {canPrint ? (
                <Button variant="secondary" onClick={() => window.print()}>
                  {actionsDict.printReport}
                </Button>
              ) : null}
              {canExport ? (
                <Button variant="secondary" onClick={handleExport}>
                  {dict.app.pages.reportsSupplierStatement.exportCsv}
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
                {dict.app.pages.reportsSupplierStatement.supplier}
              </label>
              <SearchableSelect
                options={suppliers.map((s) => ({ value: s.id, label: `${s.code} - ${getLocalizedName(s.name, locale)}` }))}
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
            <div className="flex items-center gap-2">
              <Button onClick={handleFilter} className="flex-1">
                {dict.app.pages.reportsSupplierStatement.viewReport}
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
              <div className="border-b border-[var(--border-color)] bg-[var(--background)]/50 px-4 py-3 text-xs font-bold">
                {dict.app.pages.reportsSupplierStatement.openingBalancePriorToRange}: {formatMoney(report.opening_balance_minor, report.filters.currency)}
              </div>
              <ServerDataTable
                key={`${filters.supplier_id}-${filters.date_from}-${filters.date_to}-${filters.currency}`}
                ajaxUrl="/reports/supplier-statement/data"
                columns={tableColumns}
                filters={tableFilters}
                locale={locale}
                order={[]}
                pageLength={25}
                slots={tableSlots}
                tableId="supplier-statement-data-table"
              />
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
