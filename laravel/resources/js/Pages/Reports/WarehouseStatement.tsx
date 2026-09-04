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

type TranslatedName = Record<string, string> | string | null;

type Branch = { id: string; code: string; name: TranslatedName };
type Warehouse = { id: string; code: string; name: TranslatedName; branch?: Branch | null };

type WarehouseStatementProps = SharedPageProps & {
  report: {
    entity: { id: string; code: string; name: TranslatedName; branch_code?: string | null; branch_name?: TranslatedName };
    filters: { product_id: string | null; warehouse_id: string | null; date_from: string; date_to: string; currency: string };
    single_product: boolean;
    opening_balance_quantity_e6: number | null;
    opening_balance_value_minor: number;
    total_in_quantity_e6: number | null;
    total_out_quantity_e6: number | null;
    total_in_value_minor: number;
    total_out_value_minor: number;
    closing_balance_quantity_e6: number | null;
    closing_balance_value_minor: number;
  } | null;
  products: Array<{ id: string; code: string; name: TranslatedName }>;
  warehouses: Warehouse[];
  currencies: Array<{ code: string }>;
  filters: { product_id: string | null; warehouse_id: string | null; date_from: string; date_to: string; currency: string };
};

type StatementTableSlots = Record<string, (data: any, row: any) => ReactElement>;

function formatQuantityE6(quantityE6: number): string {
  const sign = quantityE6 < 0 ? '-' : '';
  const absolute = Math.abs(Math.trunc(quantityE6));
  const whole = Math.floor(absolute / 1000000).toLocaleString();
  const fraction = String(absolute % 1000000).padStart(6, '0').replace(/0+$/, '');

  return `${sign}${whole}${fraction ? `.${fraction}` : ''}`;
}

export default function WarehouseStatement({ locale, report, products, warehouses, currencies, filters }: WarehouseStatementProps) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.reportsWarehouseStatement;
  const actionsDict = dict.app.actions;
  const can = useCan();
  const canExport = can('reports.export') && can('view_financials');
  const canPrint = can('reports.print') && can('view_financials');

  const [warehouseId, setWarehouseId] = useState(filters.warehouse_id || '');
  const [productId, setProductId] = useState(filters.product_id || '');
  const [dateFrom, setDateFrom] = useState(filters.date_from);
  const [dateTo, setDateTo] = useState(filters.date_to);
  const [currency, setCurrency] = useState(filters.currency);

  const singleProduct = report?.single_product ?? false;

  const tableColumns = useMemo(() => [
    { data: 'date', name: 'date', title: pageDict.date },
    { data: 'type', name: 'type', title: pageDict.type },
    { data: 'reference', name: 'reference', title: pageDict.reference },
    { data: 'description', name: 'description', title: pageDict.description },
    { data: 'product_name', name: 'product_name', title: pageDict.productColumn, orderable: false },
    { data: 'quantity_delta_e6', name: 'quantity_delta_e6', title: pageDict.qtyDelta, searchable: false },
    { data: 'value_delta_minor', name: 'value_delta_minor', title: pageDict.valueDelta, searchable: false },
    { data: 'balance_quantity_e6', name: 'balance_quantity_e6', title: pageDict.balanceQuantity, searchable: false },
    { data: 'balance_valuation_amount_minor', name: 'balance_valuation_amount_minor', title: pageDict.balanceValue, searchable: false },
  ], [pageDict]);

  const tableSlots = useMemo<StatementTableSlots>(() => ({
    type: (data) => <span className="font-medium">{String(data)}</span>,
    reference: (data) => <span className="font-mono">{String(data)}</span>,
    description: (data) => <span className="text-[var(--text-secondary)]">{String(data)}</span>,
    // product_name/product_code arrive already locale-resolved from the backend
    // (a window-function query, not an Eloquent model), so no getLocalizedName() here.
    product_name: (data, row) => (
      <span>
        {row.product_code ? <strong>{row.product_code}</strong> : null}
        {data ? <span className="ms-2 text-xs text-[var(--text-secondary)]">{String(data)}</span> : null}
      </span>
    ),
    quantity_delta_e6: (data) => {
      const value = Number(data);
      return (
        <span className={`font-mono font-bold ${value >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'}`}>
          {value >= 0 ? '+' : ''}{formatQuantityE6(value)}
        </span>
      );
    },
    value_delta_minor: (data) => {
      const value = Number(data);
      return (
        <span className={`font-mono font-bold ${value >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'}`}>
          {value >= 0 ? '+' : ''}{formatMoney(value, report?.filters.currency)}
        </span>
      );
    },
    balance_quantity_e6: (data) => (
      <span className="font-mono font-bold">{data === null ? pageDict.mixedUnitsQuantity : formatQuantityE6(Number(data))}</span>
    ),
    balance_valuation_amount_minor: (data) => <span className="font-mono font-bold">{formatMoney(Number(data), report?.filters.currency)}</span>,
  }), [pageDict.mixedUnitsQuantity, report?.filters.currency]);

  const tableFilters = useMemo(() => ({
    product_id: filters.product_id,
    warehouse_id: filters.warehouse_id,
    date_from: filters.date_from,
    date_to: filters.date_to,
    currency: filters.currency,
  }), [filters.currency, filters.date_from, filters.date_to, filters.product_id, filters.warehouse_id]);

  const hasActiveFilters = Boolean(warehouseId || productId || dateFrom || dateTo);

  const handleFilter = () => {
    router.get('/reports/warehouse-statement', {
      warehouse_id: warehouseId,
      product_id: productId,
      date_from: dateFrom,
      date_to: dateTo,
      currency,
    }, { preserveScroll: true });
  };

  const handleReset = () => {
    setWarehouseId('');
    setProductId('');
    router.get('/reports/warehouse-statement', { currency }, { preserveScroll: true });
  };

  const handleExport = () => {
    if (!warehouseId) return;
    const url = `/reports/warehouse-statement/export?warehouse_id=${warehouseId}&product_id=${productId}&date_from=${dateFrom}&date_to=${dateTo}&currency=${currency}`;
    window.open(url, '_blank');
  };

  return (
    <AppLayout active="reports.warehouse-statement">
      <Head title={pageDict.warehouseStatementMiniErp} />

      <PageHeader
        title={pageDict.warehouseStatement}
        description={pageDict.detailedStockLedgerStatementShowingOpeningBalance}
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
                  {pageDict.exportCsv}
                </Button>
              ) : null}
            </div>
          ) : undefined
        }
      />

      <div className="space-y-6">
        <Card className="p-4">
          <div className="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {pageDict.warehouse}
              </label>
              <SearchableSelect
                options={warehouses.map((w) => ({ value: w.id, label: `${w.code} - ${getLocalizedName(w.name, locale)}` }))}
                value={warehouseId}
                onChange={(val) => setWarehouseId(val || '')}
                placeholder={pageDict.selectWarehouse}
              />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {pageDict.product}
              </label>
              <SearchableSelect
                options={products.map((p) => ({ value: p.id, label: `${p.code} - ${getLocalizedName(p.name, locale)}` }))}
                value={productId}
                onChange={(val) => setProductId(val || '')}
                placeholder={pageDict.allProducts}
              />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {pageDict.fromDate}
              </label>
              <DatePicker value={dateFrom} onChange={(val) => setDateFrom(val || '')} />
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {pageDict.toDate}
              </label>
              <DatePicker value={dateTo} onChange={(val) => setDateTo(val || '')} />
            </div>
            <div className="flex items-center gap-2">
              <Button onClick={handleFilter} className="flex-1">
                {pageDict.viewReport}
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
            {!singleProduct ? (
              <div className="rounded-lg border border-amber-300/50 bg-amber-50 px-4 py-2 text-xs font-medium text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-300">
                {pageDict.selectAProductToSeeQuantityBalance}
              </div>
            ) : null}

            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
              <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
                <div className="text-xs text-[var(--text-secondary)] mb-1">{pageDict.openingBalanceQuantity}</div>
                <div className="text-sm font-bold text-[var(--text-primary)]">
                  {singleProduct ? formatQuantityE6(report.opening_balance_quantity_e6 ?? 0) : pageDict.mixedUnitsQuantity}
                </div>
              </div>
              <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
                <div className="text-xs text-[var(--text-secondary)] mb-1">{pageDict.openingBalanceValue}</div>
                <div className="text-sm font-bold text-[var(--text-primary)]">{formatMoney(report.opening_balance_value_minor, report.filters.currency)}</div>
              </div>
              <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
                <div className="text-xs text-[var(--text-secondary)] mb-1">{pageDict.totalIn}</div>
                <div className="text-sm font-bold text-emerald-600">
                  {singleProduct ? `+${formatQuantityE6(report.total_in_quantity_e6 ?? 0)}` : pageDict.mixedUnitsQuantity}
                </div>
              </div>
              <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
                <div className="text-xs text-[var(--text-secondary)] mb-1">{pageDict.totalOut}</div>
                <div className="text-sm font-bold text-amber-600">
                  {singleProduct ? `-${formatQuantityE6(report.total_out_quantity_e6 ?? 0)}` : pageDict.mixedUnitsQuantity}
                </div>
              </div>
              <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
                <div className="text-xs text-[var(--text-secondary)] mb-1">{pageDict.closingBalanceQuantity}</div>
                <div className="text-sm font-bold text-[var(--text-primary)]">
                  {singleProduct ? formatQuantityE6(report.closing_balance_quantity_e6 ?? 0) : pageDict.mixedUnitsQuantity}
                </div>
              </div>
              <div className="bg-[var(--card)] p-3 rounded-lg border border-[var(--border-color)]">
                <div className="text-xs text-[var(--text-secondary)] mb-1">{pageDict.closingBalanceValue}</div>
                <div className="text-sm font-bold text-[var(--text-primary)]">{formatMoney(report.closing_balance_value_minor, report.filters.currency)}</div>
              </div>
            </div>

            <Card className="overflow-hidden p-0">
              <div className="border-b border-[var(--border-color)] bg-[var(--background)]/50 px-4 py-3 text-xs font-bold">
                {pageDict.openingBalancePriorToRange}: {singleProduct ? formatQuantityE6(report.opening_balance_quantity_e6 ?? 0) : pageDict.mixedUnitsQuantity} / {formatMoney(report.opening_balance_value_minor, report.filters.currency)}
              </div>
              <ServerDataTable
                key={`${filters.warehouse_id}-${filters.product_id}-${filters.date_from}-${filters.date_to}-${filters.currency}`}
                ajaxUrl="/reports/warehouse-statement/data"
                columns={tableColumns}
                filters={tableFilters}
                locale={locale}
                order={[]}
                pageLength={25}
                slots={tableSlots}
                tableId="warehouse-statement-data-table"
              />
            </Card>
          </div>
        ) : (
          <Card className="p-12 text-center text-[var(--text-muted)]">
            {pageDict.pleaseSelectAWarehouseAndPeriod}
          </Card>
        )}
      </div>
    </AppLayout>
  );
}
