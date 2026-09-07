import { Link } from '@inertiajs/react';
import { useMemo, type ReactElement } from 'react';

import ServerDataTable, { type DataTableSlots } from '../../../Components/ServerDataTable';
import { formatAccountingAmount } from '../../../lib/accountingHelpers';
import { getDictionary } from '../../../lib/i18n';

type TranslatedName = Record<string, string> | string | null;

type ScheduleRow = {
  id: string;
  fixed_asset_id: string;
  asset_number: string | null;
  asset_name: TranslatedName;
  category_name: TranslatedName;
  period_number: number;
  depreciation_minor: number;
  accumulated_depreciation_minor: number;
  net_book_value_minor: number;
};

type Props = {
  ajaxUrl: string;
  locale: string;
  tableId: string;
};

export default function ScheduleDataTable({ ajaxUrl, locale, tableId }: Props) {
  const appDict = getDictionary(locale).app.accounting;
  const formatAmount = (amountMinor: number) => formatAccountingAmount(amountMinor, '', { zeroAsDash: false, showCurrency: false });
  const formatName = (name: TranslatedName): string => {
    if (!name) return appDict.notAvailable;
    if (typeof name === 'string') return name;

    return locale === 'ar' ? name.ar || name.en : name.en || name.ar;
  };

  const columns = useMemo(() => [
    { data: 'asset_number', name: 'asset_number', title: appDict.assetNumber },
    { data: 'asset_name', name: 'asset_name', title: appDict.assetName },
    { data: 'category_name', name: 'category_name', title: appDict.assetCategory },
    { data: 'period_number', name: 'period_number', title: appDict.periodNumber, searchable: false },
    { data: 'depreciation_minor', name: 'depreciation_minor', title: appDict.depreciationAmount, searchable: false },
    { data: 'accumulated_depreciation_minor', name: 'accumulated_depreciation_minor', title: appDict.accumulatedDepreciation, searchable: false },
    { data: 'net_book_value_minor', name: 'net_book_value_minor', title: appDict.netBookValue, searchable: false },
  ], [appDict]);

  const slots = useMemo<DataTableSlots>(() => ({
    asset_number: (data: string | null, _type: unknown, row: ScheduleRow): ReactElement => (
      data ? (
        <Link className="font-mono text-xs font-semibold text-indigo-600 dark:text-indigo-400" href={`/fixed-assets/${row.fixed_asset_id}`}>
          {data}
        </Link>
      ) : <span>{appDict.notAvailable}</span>
    ),
    asset_name: (data: TranslatedName): ReactElement => <span>{formatName(data)}</span>,
    category_name: (data: TranslatedName): ReactElement => <span>{formatName(data)}</span>,
    period_number: (data: number): ReactElement => <span className="font-mono text-xs">{data}</span>,
    depreciation_minor: (data: number): ReactElement => <span className="font-mono text-xs font-medium">{formatAmount(data)}</span>,
    accumulated_depreciation_minor: (data: number): ReactElement => <span className="font-mono text-xs">{formatAmount(data)}</span>,
    net_book_value_minor: (data: number): ReactElement => <span className="font-mono text-xs font-bold text-slate-900 dark:text-slate-100">{formatAmount(data)}</span>,
  }), [appDict.notAvailable, locale]);

  return (
    <ServerDataTable
      ajaxUrl={ajaxUrl}
      columns={columns}
      locale={locale}
      order={[[0, 'asc']]}
      pageLength={25}
      slots={slots}
      tableId={tableId}
    />
  );
}
