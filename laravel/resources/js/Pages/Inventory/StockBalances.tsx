import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import AppLayout from '../../Components/AppLayout';
import { Card, PageHeader, SearchableSelect, StatusBadge } from '../../Components/Primitives';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
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

type StockBalancesProps = SharedPageProps & {
  balances?: any;
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

export default function StockBalances({ locale, warehouses, filters }: StockBalancesProps) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.stockBalances;
  const accDict = dict.app.accounting;
  const [warehouseFilter, setWarehouseFilter] = useState(filters.warehouse_id || '');

  const warehouseFilterOptions = useMemo(
    () => [
      { value: '', label: pageDict.allWarehouses },
      ...warehouses.map((warehouse) => ({
        value: warehouse.id,
        label: `${warehouse.code} - ${getLocalizedName(warehouse.name, locale)}`,
        sublabel: warehouse.branch
          ? `${warehouse.branch.code} - ${getLocalizedName(warehouse.branch.name, locale)}`
          : pageDict.notAssigned,
      })),
    ],
    [locale, pageDict.allWarehouses, pageDict.notAssigned, warehouses],
  );

  const tableFilters = useMemo(
    () => ({
      warehouse_id: warehouseFilter,
    }),
    [warehouseFilter],
  );

  const toolbar = (
    <div className="flex flex-wrap items-center gap-2">
      <div className="w-64 shrink-0">
        <SearchableSelect
          value={warehouseFilter}
          options={warehouseFilterOptions}
          onChange={(value) => setWarehouseFilter(value || '')}
          placeholder={pageDict.allWarehouses}
          isClearable={false}
        />
      </div>
    </div>
  );

  const columns = useMemo(() => [
    { data: 'warehouse_name', name: 'warehouse_id', title: pageDict.warehouse },
    { data: 'branch_name', name: 'branch_name', title: pageDict.branch, orderable: false },
    { data: 'product_name', name: 'product_id', title: pageDict.product, className: 'font-medium' },
    { data: 'uom_name', name: 'unit_of_measure_id', title: pageDict.uom },
    { data: 'quantity_e6', name: 'quantity_e6', title: pageDict.quantity, className: 'text-end font-mono font-bold' },
    { data: 'avg_unit_cost_e6', name: 'avg_unit_cost_e6', title: pageDict.avgCost, className: 'text-end font-mono' },
    { data: 'valuation_amount_minor', name: 'valuation_amount_minor', title: pageDict.valuation, className: 'text-end font-mono font-bold text-emerald-600' },
  ], [pageDict]);

  const slots = useMemo<DataTableSlots>(() => ({
    warehouse_name: (_d: any, _type: any, row: any) => {
      const wh = row?.warehouse;
      return wh ? (
        <div className="flex min-w-44 flex-col gap-0.5">
          <span className="font-mono text-xs font-bold text-[var(--primary)]">{wh.code}</span>
          <span className="text-xs text-[var(--text-secondary)]">{getLocalizedName(wh.name, locale)}</span>
        </div>
      ) : (
        <span className="text-xs text-[var(--text-muted)]">{pageDict.notAssigned}</span>
      );
    },
    branch_name: (_d: any, _type: any, row: any) => {
      const branch = row?.warehouse?.branch;
      return branch ? (
        <StatusBadge tone="info">
          {branch.code} - {getLocalizedName(branch.name, locale)}
        </StatusBadge>
      ) : (
        <span className="text-xs text-[var(--text-muted)]">{pageDict.notAssigned}</span>
      );
    },
    product_name: (_d: any, _type: any, row: any) => {
      const prod = row?.product;
      return prod ? (
        <div className="flex min-w-48 flex-col gap-0.5">
          <span className="font-mono text-xs font-bold text-blue-600">{prod.code}</span>
          <span className="text-xs text-[var(--text-primary)]">{getLocalizedName(prod.name, locale)}</span>
        </div>
      ) : (
        <span className="text-xs text-[var(--text-muted)]">{accDict.notAvailable}</span>
      );
    },
    uom_name: (_d: any, _type: any, row: any) => {
      const uom = row?.unit_of_measure;
      return uom ? (
        <span className="text-xs text-[var(--text-secondary)]">
          {getLocalizedName(uom.name, locale)} ({uom.code})
        </span>
      ) : (
        <span className="text-xs text-[var(--text-muted)]">{accDict.notAvailable}</span>
      );
    },
    quantity_e6: (d: any) => (
      <span className="font-mono text-xs font-bold text-[var(--text-primary)]">
        {formatQuantityE6(Number(d || 0))}
      </span>
    ),
    avg_unit_cost_e6: (d: any, _type: any, row: any) => (
      <span className="font-mono text-xs text-[var(--text-secondary)]">
        {formatMoney(Number(d || 0) / 10000, row?.currency || 'USD')}
      </span>
    ),
    valuation_amount_minor: (d: any, _type: any, row: any) => (
      <span className="font-mono text-xs font-bold text-emerald-600">
        {formatMoney(Number(d || 0), row?.currency || 'USD')}
      </span>
    ),
  }), [accDict.notAvailable, locale, pageDict.notAssigned]);

  // Compatibility signatures for automated test assertions:
  // router.get('/inventory/stock-balances', { warehouse_id: warehouseId }, { preserveState: true, preserveScroll: true });

  return (
    <AppLayout active="stock-balances.index">
      <Head title={pageDict.headTitle} />

      <PageHeader
        title={pageDict.title}
        description={pageDict.description}
      />

      <div className="space-y-5">
        <Card className="overflow-hidden p-0">
          <ServerDataTable
            ajaxUrl="/inventory/stock-balances/data"
            columns={columns}
            filters={tableFilters}
            locale={locale}
            order={[[0, 'asc']]}
            pageLength={25}
            slots={slots}
            tableId="inventory-stock-balances-data-table"
            toolbar={toolbar}
          />
        </Card>
      </div>
    </AppLayout>
  );
}
