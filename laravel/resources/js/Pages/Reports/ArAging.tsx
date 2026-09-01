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

type ArAgingProps = SharedPageProps & {
  report: {
    as_of_date: string;
    currency: string;
    grand_totals: {
      current: number;
      b1_30: number;
      b31_60: number;
      b61_90: number;
      over_90: number;
      total: number;
    };
  };
  customers: Array<{ id: string; code: string; name: string }>;
  currencies: Array<{ code: string }>;
  filters: { as_of_date: string; customer_id: string | null; currency: string };
};

type AgingTableSlots = Record<string, (data: any, row: any) => ReactElement>;

export default function ArAging({ locale, report, customers, currencies, filters }: ArAgingProps) {
  const dict = getDictionary(locale);
  const actionsDict = dict.app.actions;
  const can = useCan();
  const canExport = can('reports.export') && can('view_financials');
  const canPrint = can('reports.print') && can('view_financials');

  const [asOfDate, setAsOfDate] = useState(filters.as_of_date);
  const [customerId, setCustomerId] = useState(filters.customer_id || '');
  const [currency, setCurrency] = useState(filters.currency);

  const tableColumns = useMemo(() => [
    { data: 'customer_name', name: 'customer_name', title: dict.app.pages.reportsArAging.customer_2 },
    { data: 'open_items_count', name: 'open_items_count', title: dict.app.pages.reportsArAging.openItems, searchable: false },
    { data: 'current', name: 'current', title: dict.app.pages.reportsArAging.current_2, searchable: false },
    { data: 'b1_30', name: 'b1_30', title: '1-30', searchable: false },
    { data: 'b31_60', name: 'b31_60', title: '31-60', searchable: false },
    { data: 'b61_90', name: 'b61_90', title: '61-90', searchable: false },
    { data: 'over_90', name: 'over_90', title: '+90', searchable: false },
    { data: 'total', name: 'total', title: dict.app.pages.reportsArAging.openBalance, searchable: false },
  ], [dict]);
  const tableSlots = useMemo<AgingTableSlots>(() => ({
    customer_name: (data, row) => (
      <span className="font-semibold">
        {row.customer_code} - {getLocalizedName(data, locale)}
      </span>
    ),
    open_items_count: (data) => <span>{Number(data)} {dict.app.pages.reportsArAging.openItems}</span>,
    current: (data) => <span className="font-mono text-emerald-600">{formatMoney(Number(data), report.currency)}</span>,
    b1_30: (data) => <span className="font-mono text-blue-600">{formatMoney(Number(data), report.currency)}</span>,
    b31_60: (data) => <span className="font-mono text-amber-600">{formatMoney(Number(data), report.currency)}</span>,
    b61_90: (data) => <span className="font-mono text-orange-600">{formatMoney(Number(data), report.currency)}</span>,
    over_90: (data) => <span className="font-mono text-rose-600">{formatMoney(Number(data), report.currency)}</span>,
    total: (data) => <span className="font-mono font-bold">{formatMoney(Number(data), report.currency)}</span>,
  }), [dict, locale, report.currency]);
  const tableFilters = useMemo(() => ({
    as_of_date: filters.as_of_date,
    customer_id: filters.customer_id,
    currency: filters.currency,
  }), [filters.as_of_date, filters.currency, filters.customer_id]);

  const hasActiveFilters = Boolean(customerId || asOfDate !== filters.as_of_date);

  const handleFilter = () => {
    router.get('/reports/ar-aging', {
      as_of_date: asOfDate,
      customer_id: customerId,
      currency,
    }, { preserveScroll: true });
  };

  const handleReset = () => {
    setCustomerId('');
    router.get('/reports/ar-aging', {
      currency,
    }, { preserveScroll: true });
  };

  const handleExport = () => {
    const url = `/reports/ar-aging/export?as_of_date=${asOfDate}&customer_id=${customerId}&currency=${currency}`;
    window.open(url, '_blank');
  };

  return (
    <AppLayout active="reports.ar-aging">
      <Head title={dict.app.pages.reportsArAging.arAgingReportMiniErp} />

      <PageHeader
        title={dict.app.pages.reportsArAging.arAgingReport}
        description={dict.app.pages.reportsArAging.analysisOfOutstandingCustomerReceivablesGrouped}
        actions={
          <div className="flex items-center gap-2">
            {canPrint ? (
              <Button variant="secondary" onClick={() => window.print()}>
                {actionsDict.printReport}
              </Button>
            ) : null}
            {canExport ? (
              <Button variant="secondary" onClick={handleExport}>
                {dict.app.pages.reportsArAging.exportCsv}
              </Button>
            ) : null}
          </div>
        }
      />

      <div className="space-y-6">
        <Card className="p-4">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {dict.app.pages.reportsArAging.asOfDate}
              </label>
              <DatePicker value={asOfDate} onChange={(val) => setAsOfDate(val || '')} />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {dict.app.pages.reportsArAging.customer}
              </label>
              <SearchableSelect
                options={[
                  { value: '', label: dict.app.pages.reportsArAging.allCustomers },
                  ...customers.map((c) => ({ value: c.id, label: `${c.code} - ${c.name}` })),
                ]}
                value={customerId}
                onChange={(val) => setCustomerId(val || '')}
              />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {dict.app.pages.reportsArAging.currency}
              </label>
              <SearchableSelect
                options={currencies.map((c) => ({ value: c.code, label: c.code }))}
                value={currency}
                onChange={(val) => setCurrency(val || '')}
              />
            </div>
            <div className="flex items-center gap-2">
              <Button onClick={handleFilter} className="flex-1">
                {dict.app.pages.reportsArAging.viewReport}
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

        <div className="grid grid-cols-2 md:grid-cols-6 gap-3">
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsArAging.current}</div>
            <div className="text-sm font-bold text-emerald-600">
              {formatMoney(report.grand_totals.current, report.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">1 - 30 {dict.app.pages.reportsArAging.days}</div>
            <div className="text-sm font-bold text-blue-600">
              {formatMoney(report.grand_totals.b1_30, report.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">31 - 60 {dict.app.pages.reportsArAging.days_2}</div>
            <div className="text-sm font-bold text-amber-600">
              {formatMoney(report.grand_totals.b31_60, report.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">61 - 90 {dict.app.pages.reportsArAging.days_3}</div>
            <div className="text-sm font-bold text-orange-600">
              {formatMoney(report.grand_totals.b61_90, report.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">+90 {dict.app.pages.reportsArAging.days_4}</div>
            <div className="text-sm font-bold text-rose-600">
              {formatMoney(report.grand_totals.over_90, report.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsArAging.totalOpen}</div>
            <div className="text-sm font-bold text-[var(--text-primary)]">
              {formatMoney(report.grand_totals.total, report.currency)}
            </div>
          </div>
        </div>

        <Card className="overflow-hidden p-0">
          <ServerDataTable
            key={`${filters.as_of_date}-${filters.customer_id || 'all'}-${filters.currency}`}
            ajaxUrl="/reports/ar-aging/data"
            columns={tableColumns}
            filters={tableFilters}
            locale={locale}
            order={[[7, 'desc']]}
            pageLength={25}
            slots={tableSlots}
            tableId="ar-aging-data-table"
          />
        </Card>
      </div>
    </AppLayout>
  );
}
