import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import AppLayout from '../../Components/AppLayout';
import { Card, EmptyState, MetricCard, PageHeader, SearchableSelect, StatusBadge, tableClasses } from '../../Components/Primitives';
import { formatMoney, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types';

type TranslatedName = Record<string, string> | string | null;

type Product = {
  id: string;
  code: string;
  name: TranslatedName;
  type: string;
};

type UnitOfMeasure = {
  id: string;
  code: string;
  name: TranslatedName;
  symbol?: string;
};

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

type StockBalance = {
  id: string;
  warehouse_id?: string | null;
  product_id: string;
  unit_of_measure_id: string;
  currency: string;
  quantity_e6: number;
  valuation_amount_minor: number;
  avg_unit_cost_e6: number;
  warehouse?: Warehouse | null;
  product?: Product | null;
  unit_of_measure?: UnitOfMeasure | null;
};

type PaginatedData<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
};

type StockBalancesProps = SharedPageProps & {
  balances: PaginatedData<StockBalance>;
  warehouses: Warehouse[];
  filters: {
    warehouse_id?: string;
  };
};

function formatQuantityE6(quantityE6: number): string {
  const sign = quantityE6 < 0 ? '-' : '';
  const absolute = Math.abs(Math.trunc(quantityE6));
  const whole = Math.floor(absolute / 1000000).toLocaleString();
  const fraction = String(absolute % 1000000).padStart(6, '0').replace(/0+$/, '');

  return `${sign}${whole}${fraction ? `.${fraction}` : ''}`;
}

export default function StockBalances({ locale, balances, warehouses, filters }: StockBalancesProps) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.stockBalances;
  const [warehouseId, setWarehouseId] = useState(filters.warehouse_id || '');

  const warehouseOptions = useMemo(
    () => warehouses.map((warehouse) => ({
      value: warehouse.id,
      label: `${warehouse.code} - ${getLocalizedName(warehouse.name, locale)}`,
      sublabel: warehouse.branch
        ? `${warehouse.branch.code} - ${getLocalizedName(warehouse.branch.name, locale)}`
        : pageDict.notAssigned,
    })),
    [locale, pageDict.notAssigned, warehouses],
  );

  const totals = useMemo(
    () => balances.data.reduce(
      (carry, balance) => ({
        quantity: carry.quantity + Number(balance.quantity_e6 || 0),
        valuation: carry.valuation + Number(balance.valuation_amount_minor || 0),
      }),
      { quantity: 0, valuation: 0 },
    ),
    [balances.data],
  );
  const activeFilterCount = [warehouseId].filter(Boolean).length;

  function applyFilter() {
    router.get('/inventory/stock-balances', { warehouse_id: warehouseId }, { preserveState: true, preserveScroll: true });
  }

  function clearFilter() {
    setWarehouseId('');
    router.get('/inventory/stock-balances', {}, { preserveState: true, preserveScroll: true });
  }

  return (
    <AppLayout active="stock-balances.index">
      <Head title={pageDict.headTitle} />

      <PageHeader
        title={pageDict.title}
        description={pageDict.description}
      />

      <div className="space-y-5">
        <Card className="p-4">
          <div className="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto_auto] md:items-end">
            <SearchableSelect
              label={pageDict.warehouse}
              options={warehouseOptions}
              value={warehouseId}
              onChange={(value) => setWarehouseId(value || '')}
              placeholder={pageDict.allWarehouses}
            />
            <button
              type="button"
              onClick={applyFilter}
              title={pageDict.applyFilter}
              aria-label={pageDict.applyFilter}
              className="inline-flex h-[42px] items-center justify-center gap-2 rounded-xl bg-[var(--primary)] px-4 text-xs font-bold text-white shadow-sm transition-all hover:opacity-90"
            >
              <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.4}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M3 4h18M6 10h12M10 16h4" />
              </svg>
              <span>{pageDict.applyFilter}</span>
            </button>
            <button
              type="button"
              onClick={clearFilter}
              disabled={activeFilterCount === 0}
              title={pageDict.clearFilter}
              aria-label={pageDict.clearFilter}
              className="inline-flex h-[42px] items-center justify-center gap-2 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 text-xs font-bold text-[var(--text-primary)] shadow-sm transition-all hover:bg-[var(--background)]"
            >
              <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.4}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
              <span>{pageDict.clearFilter}</span>
            </button>
          </div>
        </Card>

        <div className="grid gap-4 md:grid-cols-3">
          <MetricCard label={pageDict.totalRows} value={balances.total.toLocaleString()} tone="blue" />
          <MetricCard label={pageDict.totalQuantity} value={formatQuantityE6(totals.quantity)} tone="emerald" />
          <MetricCard label={pageDict.totalValuation} value={formatMoney(totals.valuation, balances.data[0]?.currency || pageDict.noCurrency)} tone="purple" />
        </div>

        {balances.data.length === 0 ? (
          <EmptyState title={pageDict.emptyTitle} description={pageDict.emptyDescription} />
        ) : (
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{pageDict.warehouse}</th>
                  <th className={tableClasses.th}>{pageDict.branch}</th>
                  <th className={tableClasses.th}>{pageDict.product}</th>
                  <th className={tableClasses.th}>{pageDict.uom}</th>
                  <th className={`${tableClasses.th} text-end`}>{pageDict.quantity}</th>
                  <th className={`${tableClasses.th} text-end`}>{pageDict.avgCost}</th>
                  <th className={`${tableClasses.th} text-end`}>{pageDict.valuation}</th>
                </tr>
              </thead>
              <tbody>
                {balances.data.map((balance) => (
                  <tr key={balance.id} className="hover:bg-[var(--background)]">
                    <td className={tableClasses.td}>
                      <div className="flex min-w-48 flex-col gap-1">
                        <span className="font-mono text-xs font-bold text-[var(--text-primary)]">{balance.warehouse?.code || pageDict.notAssigned}</span>
                        <span className="text-xs text-[var(--text-secondary)]">
                          {getLocalizedName(balance.warehouse?.name, locale) || pageDict.notAssigned}
                        </span>
                      </div>
                    </td>
                    <td className={tableClasses.td}>
                      {balance.warehouse?.branch ? (
                        <StatusBadge tone="info">
                          {balance.warehouse.branch.code} - {getLocalizedName(balance.warehouse.branch.name, locale)}
                        </StatusBadge>
                      ) : (
                        <span className="text-xs text-[var(--text-muted)]">{pageDict.notAssigned}</span>
                      )}
                    </td>
                    <td className={tableClasses.td}>
                      <div className="flex min-w-52 flex-col gap-1">
                        <span className="font-mono text-xs font-bold text-[var(--text-primary)]">
                          {balance.product?.code || balance.product_id}
                        </span>
                        <span className="text-xs text-[var(--text-secondary)]">
                          {getLocalizedName(balance.product?.name, locale)}
                        </span>
                      </div>
                    </td>
                    <td className={tableClasses.td}>
                      <span className="font-mono text-xs font-bold">{balance.unit_of_measure?.code || balance.unit_of_measure_id}</span>
                    </td>
                    <td className={`${tableClasses.td} text-end font-mono font-bold`}>
                      {formatQuantityE6(balance.quantity_e6)}
                    </td>
                    <td className={`${tableClasses.td} text-end font-mono font-bold text-[var(--text-secondary)]`}>
                      {formatMoney(balance.avg_unit_cost_e6, balance.currency)}
                    </td>
                    <td className={`${tableClasses.td} text-end font-mono font-extrabold`}>
                      {formatMoney(balance.valuation_amount_minor, balance.currency)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
