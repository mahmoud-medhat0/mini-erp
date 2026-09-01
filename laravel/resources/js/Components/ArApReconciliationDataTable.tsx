import { useMemo, type ReactElement } from 'react';

import { formatMoney, getLocalizedName } from '../lib/accountingHelpers';
import ServerDataTable from './ServerDataTable';

type ReconciliationTableProps = {
  currency: string;
  endpoint: string;
  filters: {
    as_of_date: string;
    currency: string;
  };
  labels: {
    balance: string;
    code: string;
    name: string;
  };
  locale: string;
  tableId: string;
};

type ReconciliationSlots = Record<string, (data: any) => ReactElement>;

export default function ArApReconciliationDataTable({
  currency,
  endpoint,
  filters,
  labels,
  locale,
  tableId,
}: ReconciliationTableProps) {
  const columns = useMemo(() => [
    { data: 'partner_code', name: 'partner_code', title: labels.code },
    { data: 'partner_name', name: 'partner_name', title: labels.name },
    {
      data: 'subledger_balance_minor',
      name: 'subledger_balance_minor',
      title: labels.balance,
      searchable: false,
    },
  ], [labels]);
  const slots = useMemo<ReconciliationSlots>(() => ({
    partner_code: (data) => <span className="font-mono font-bold">{String(data)}</span>,
    partner_name: (data) => <span className="font-medium">{getLocalizedName(data, locale)}</span>,
    subledger_balance_minor: (data) => (
      <span className="font-mono font-bold">{formatMoney(Number(data), currency)}</span>
    ),
  }), [currency, locale]);

  return (
    <ServerDataTable
      ajaxUrl={endpoint}
      columns={columns}
      filters={filters}
      locale={locale}
      order={[[0, 'asc']]}
      pageLength={25}
      slots={slots}
      tableId={tableId}
    />
  );
}
