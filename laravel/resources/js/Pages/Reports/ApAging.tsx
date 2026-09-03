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

type ApAgingProps = SharedPageProps & {
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
  suppliers: Array<{ id: string; code: string; name: string }>;
  currencies: Array<{ code: string }>;
  filters: { as_of_date: string; supplier_id: string | null; currency: string };
};

type AgingTableSlots = Record<string, (data: any, row: any) => ReactElement>;

export default function ApAging({ locale, report, suppliers, currencies, filters }: ApAgingProps) {
  const dict = getDictionary(locale);
  const actionsDict = dict.app.actions;
  const can = useCan();
  const canExport = can('reports.export') && can('view_financials');
  const canPrint = can('reports.print') && can('view_financials');

  const [asOfDate, setAsOfDate] = useState(filters.as_of_date);
  const [supplierId, setSupplierId] = useState(filters.supplier_id || '');
  const [currency, setCurrency] = useState(filters.currency);

  const tableColumns = useMemo(() => [
    { data: 'supplier_name', name: 'supplier_name', title: dict.app.pages.reportsApAging.supplier_2 },
    { data: 'open_items_count', name: 'open_items_count', title: dict.app.pages.reportsApAging.openItems, searchable: false },
    { data: 'current', name: 'current', title: dict.app.pages.reportsApAging.current_2, searchable: false },
    { data: 'b1_30', name: 'b1_30', title: '1-30', searchable: false },
    { data: 'b31_60', name: 'b31_60', title: '31-60', searchable: false },
    { data: 'b61_90', name: 'b61_90', title: '61-90', searchable: false },
    { data: 'over_90', name: 'over_90', title: '+90', searchable: false },
    { data: 'total', name: 'total', title: dict.app.pages.reportsApAging.openBalance, searchable: false },
  ], [dict]);
  const tableSlots = useMemo<AgingTableSlots>(() => ({
    supplier_name: (data, row) => (
      <span className="font-semibold">
        {row.supplier_code} - {getLocalizedName(data, locale)}
      </span>
    ),
    open_items_count: (data) => <span>{Number(data)} {dict.app.pages.reportsApAging.openItems}</span>,
    current: (data) => <span className="font-mono text-emerald-600">{formatMoney(Number(data), report.currency)}</span>,
    b1_30: (data) => <span className="font-mono text-blue-600">{formatMoney(Number(data), report.currency)}</span>,
    b31_60: (data) => <span className="font-mono text-amber-600">{formatMoney(Number(data), report.currency)}</span>,
    b61_90: (data) => <span className="font-mono text-orange-600">{formatMoney(Number(data), report.currency)}</span>,
    over_90: (data) => <span className="font-mono text-rose-600">{formatMoney(Number(data), report.currency)}</span>,
    total: (data) => <span className="font-mono font-bold">{formatMoney(Number(data), report.currency)}</span>,
  }), [dict, locale, report.currency]);
  const tableFilters = useMemo(() => ({
    as_of_date: filters.as_of_date,
    supplier_id: filters.supplier_id,
    currency: filters.currency,
  }), [filters.as_of_date, filters.currency, filters.supplier_id]);

  const hasActiveFilters = Boolean(supplierId || asOfDate !== filters.as_of_date);

  const handleFilter = () => {
    router.get('/reports/ap-aging', {
      as_of_date: asOfDate,
      supplier_id: supplierId,
      currency,
    }, { preserveScroll: true });
  };

  const handleReset = () => {
    setSupplierId('');
    router.get('/reports/ap-aging', {
      currency,
    }, { preserveScroll: true });
  };

  const handleExport = () => {
    const url = `/reports/ap-aging/export?as_of_date=${asOfDate}&supplier_id=${supplierId}&currency=${currency}`;
    window.open(url, '_blank');
  };

  return (
    <AppLayout active="reports.ap-aging">
      <Head title={dict.app.pages.reportsApAging.apAgingReportMiniErp} />

      <PageHeader
        title={dict.app.pages.reportsApAging.apAgingReport}
        description={dict.app.pages.reportsApAging.analysisOfOutstandingSupplierPayablesGrouped}
        actions={
          <div className="flex items-center gap-2">
            {canPrint ? (
              <Button variant="secondary" onClick={() => window.print()}>
                {actionsDict.printReport}
              </Button>
            ) : null}
            {canExport ? (
              <Button variant="secondary" onClick={handleExport}>
                {dict.app.pages.reportsApAging.exportCsv}
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
                {dict.app.pages.reportsApAging.asOfDate}
              </label>
              <DatePicker value={asOfDate} onChange={(val) => setAsOfDate(val || '')} />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {dict.app.pages.reportsApAging.supplier}
              </label>
              <SearchableSelect
                options={[
                  { value: '', label: dict.app.pages.reportsApAging.allSuppliers },
                  ...suppliers.map((s) => ({ value: s.id, label: `${s.code} - ${getLocalizedName(s.name, locale)}` })),
                ]}
                value={supplierId}
                onChange={(val) => setSupplierId(val || '')}
              />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {dict.app.pages.reportsApAging.currency}
              </label>
              <SearchableSelect
                options={currencies.map((c) => ({ value: c.code, label: c.code }))}
                value={currency}
                onChange={(val) => setCurrency(val || '')}
              />
            </div>
            <div className="flex items-center gap-2">
              <Button onClick={handleFilter} className="flex-1">
                {dict.app.pages.reportsApAging.viewReport}
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
            <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsApAging.current}</div>
            <div className="text-sm font-bold text-emerald-600">
              {formatMoney(report.grand_totals.current, report.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">1 - 30 {dict.app.pages.reportsApAging.days}</div>
            <div className="text-sm font-bold text-blue-600">
              {formatMoney(report.grand_totals.b1_30, report.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">31 - 60 {dict.app.pages.reportsApAging.days_2}</div>
            <div className="text-sm font-bold text-amber-600">
              {formatMoney(report.grand_totals.b31_60, report.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">61 - 90 {dict.app.pages.reportsApAging.days_3}</div>
            <div className="text-sm font-bold text-orange-600">
              {formatMoney(report.grand_totals.b61_90, report.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">+90 {dict.app.pages.reportsApAging.days_4}</div>
            <div className="text-sm font-bold text-rose-600">
              {formatMoney(report.grand_totals.over_90, report.currency)}
            </div>
          </div>
          <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
            <div className="text-xs text-[var(--text-secondary)] mb-1">{dict.app.pages.reportsApAging.totalOpen}</div>
            <div className="text-sm font-bold text-[var(--text-primary)]">
              {formatMoney(report.grand_totals.total, report.currency)}
            </div>
          </div>
        </div>

        <Card className="overflow-hidden p-0">
          <ServerDataTable
            key={`${filters.as_of_date}-${filters.supplier_id || 'all'}-${filters.currency}`}
            ajaxUrl="/reports/ap-aging/data"
            columns={tableColumns}
            filters={tableFilters}
            locale={locale}
            order={[[7, 'desc']]}
            pageLength={25}
            slots={tableSlots}
            tableId="ap-aging-data-table"
          />
        </Card>
      </div>
    </AppLayout>
  );
}
