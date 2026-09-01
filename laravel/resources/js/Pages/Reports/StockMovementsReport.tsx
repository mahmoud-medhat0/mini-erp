import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import AppLayout from '../../Components/AppLayout';
import DatePicker from '../../Components/DatePicker';
import { StockMovementsDataTable } from '../../Components/OperationalReportDataTables';
import ReportFilterPanel from '../../Components/ReportFilterPanel';
import { Button, Card, MetricCard, PageHeader, SearchableSelect } from '../../Components/Primitives';
import { formatMoney, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types';

type TranslatedName = Record<string, string> | string | null;

type Branch = {
  id: string;
  code: string;
  name: TranslatedName;
};

type Warehouse = {
  id: string;
  code: string;
  name: TranslatedName;
  branch?: Branch | null;
};

type StockMovementReportRow = {
  id: string;
  movement_date: string;
  movement_type: string;
  warehouse_id?: string | null;
  warehouse_code?: string | null;
  warehouse_name?: TranslatedName;
  branch_id?: string | null;
  branch_code?: string | null;
  branch_name?: TranslatedName;
  source_type: string;
  source_id: string;
  source_line_id: string | null;
  product_id: string;
  product_name: TranslatedName;
  product_code: string;
  uom_code: string;
  currency: string;
  quantity_delta_e6: number;
  value_delta_minor: number;
  unit_cost_e6: number;
  balance_quantity_e6: number;
  balance_valuation_amount_minor: number;
  journal_entry_id: string | null;
  journal_entry_number: string | null;
};

type StockMovementsReportProps = SharedPageProps & {
  reportData: {
    rows: StockMovementReportRow[];
    summary: {
      total_movements_count: number;
      total_quantity_delta_e6: number;
      total_value_delta_minor: number;
    };
  };
  filters: {
    date_from: string;
    date_to: string;
    movement_type: string;
    product_id: string;
    warehouse_id: string;
    currency: string;
    search: string;
  };
  products: Array<{ id: string; code: string; name: TranslatedName }>;
  warehouses: Warehouse[];
  currencies: Array<{ code: string }>;
};

function formatQuantityE6(quantityE6: number): string {
  const sign = quantityE6 < 0 ? '-' : '';
  const absolute = Math.abs(Math.trunc(quantityE6));
  const whole = Math.floor(absolute / 1000000).toLocaleString();
  const fraction = String(absolute % 1000000).padStart(6, '0').replace(/0+$/, '');

  return `${sign}${whole}${fraction ? `.${fraction}` : ''}`;
}

export default function StockMovementsReport({ locale, reportData, filters, products, warehouses, currencies }: StockMovementsReportProps) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.stockMovementReport;
  const accDict = dict.app.accounting;

  const [dateFrom, setDateFrom] = useState(filters.date_from || '');
  const [dateTo, setDateTo] = useState(filters.date_to || '');
  const [movementType, setMovementType] = useState(filters.movement_type || '');
  const [productId, setProductId] = useState(filters.product_id || '');
  const [warehouseId, setWarehouseId] = useState(filters.warehouse_id || '');
  const [currency, setCurrency] = useState(filters.currency || '');
  const [search, setSearch] = useState(filters.search || '');
  const activeFilterCount = [dateFrom, dateTo, movementType, productId, warehouseId, currency, search].filter(Boolean).length;

  const movementOptions = [
    { value: 'receipt', label: pageDict.receipt },
    { value: 'issue', label: pageDict.issue },
    { value: 'reversal', label: pageDict.reversal },
    { value: 'scrap', label: pageDict.scrap },
    { value: 'transfer_out', label: pageDict.transferOut },
    { value: 'transfer_in', label: pageDict.transferIn },
    { value: 'adjustment', label: pageDict.adjustment },
  ];

  const productOptions = useMemo(
    () => products.map((product) => ({
      value: product.id,
      label: `${product.code} - ${getLocalizedName(product.name, locale)}`,
    })),
    [products, locale],
  );

  const warehouseOptions = useMemo(
    () => warehouses.map((warehouse) => ({
      value: warehouse.id,
      label: `${warehouse.code} - ${getLocalizedName(warehouse.name, locale)}`,
      sublabel: warehouse.branch
        ? `${warehouse.branch.code} - ${getLocalizedName(warehouse.branch.name, locale)}`
        : pageDict.notAssigned,
    })),
    [warehouses, locale, pageDict.notAssigned],
  );

  const currencyOptions = useMemo(
    () => [
      { value: '', label: pageDict.allCurrencies },
      ...currencies.map((item) => ({ value: item.code, label: item.code })),
    ],
    [currencies, pageDict.allCurrencies],
  );

  function handleFilter(event: React.FormEvent) {
    event.preventDefault();
    router.get('/reports/stock-movements', {
      date_from: dateFrom,
      date_to: dateTo,
      movement_type: movementType,
      product_id: productId,
      warehouse_id: warehouseId,
      currency,
      search,
    }, { preserveState: true, preserveScroll: true });
  }

  function handleReset() {
    setDateFrom('');
    setDateTo('');
    setMovementType('');
    setProductId('');
    setWarehouseId('');
    setCurrency('');
    setSearch('');
    router.get('/reports/stock-movements', {}, { preserveState: true, preserveScroll: true });
  }

  return (
    <AppLayout active="reports.stock-movements">
      <Head title={pageDict.headTitle} />

      <PageHeader title={pageDict.title} description={pageDict.description} />

      <div className="space-y-6">
        <form onSubmit={handleFilter}>
          <ReportFilterPanel
            activeFilterCount={activeFilterCount}
            activeFilterLabel={pageDict.activeFilters}
            actions={(
              <>
                <Button type="button" variant="secondary" onClick={handleReset} disabled={activeFilterCount === 0}>{pageDict.clearFilters}</Button>
                <Button type="submit">
                  <svg className="me-2 size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.4}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M3 4h18M6 10h12M10 16h4" />
                  </svg>
                  {pageDict.filter}
                </Button>
              </>
            )}
          >
            <DatePicker label={pageDict.dateFrom} value={dateFrom} onChange={(value) => setDateFrom(value || '')} />
            <DatePicker label={pageDict.dateTo} value={dateTo} onChange={(value) => setDateTo(value || '')} />
            <SearchableSelect
              label={pageDict.movementType}
              options={movementOptions}
              value={movementType}
              onChange={(value) => setMovementType(value || '')}
              placeholder={pageDict.allTypes}
            />
            <SearchableSelect
              label={pageDict.product}
              options={productOptions}
              value={productId}
              onChange={(value) => setProductId(value || '')}
              placeholder={pageDict.allProducts}
            />
            <SearchableSelect
              label={pageDict.warehouse}
              options={warehouseOptions}
              value={warehouseId}
              onChange={(value) => setWarehouseId(value || '')}
              placeholder={pageDict.allWarehouses}
            />
            <SearchableSelect
              label={pageDict.currency}
              options={currencyOptions}
              value={currency}
              onChange={(value) => setCurrency(value || '')}
              placeholder={pageDict.allCurrencies}
            />
            <div>
              <label className="mb-1.5 block text-xs font-bold uppercase text-[var(--text-secondary)]">{pageDict.search}</label>
              <input
                type="search"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder={pageDict.searchPlaceholder}
                className="h-[42px] w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 text-sm text-[var(--text-primary)] focus:border-[var(--primary)] focus:outline-none"
              />
            </div>
          </ReportFilterPanel>
        </form>

        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
          <MetricCard label={pageDict.totalMovementRecords} value={reportData.summary.total_movements_count.toLocaleString()} tone="blue" />
          <MetricCard label={pageDict.netQuantityDelta} value={formatQuantityE6(reportData.summary.total_quantity_delta_e6)} tone="emerald" />
          <MetricCard
            label={pageDict.netValueDelta}
            value={filters.currency ? formatMoney(reportData.summary.total_value_delta_minor, filters.currency) : pageDict.mixedCurrencyAmount}
            tone="purple"
          />
        </div>

        <Card>
          <StockMovementsDataTable
            filters={filters}
            labels={pageDict}
            locale={locale}
            notAvailable={accDict.notAvailable}
          />
        </Card>
      </div>
    </AppLayout>
  );
}
