import { useMemo, type ReactElement } from 'react';

import { formatDate, getLocalizedName } from '../lib/accountingHelpers';
import ServerDataTable from './ServerDataTable';
import { AccountingAmount, StatusBadge } from './Primitives';

type RentalOperationsFilters = {
  as_of_date: string;
  branch_id: string | null;
  customer_id: string | null;
  status: string | null;
  currency: string | null;
  date_from: string | null;
  date_to: string | null;
};

type RentalOperationsLabels = {
  contract: string;
  customer: string;
  branch: string;
  status: string;
  dates: string;
  items: string;
  invoices: string;
  totalBilled: string;
  pendingDamage: string;
  latestJournal: string;
  notNumbered: string;
  noBranch: string;
  operationalReference: string;
  notAvailable: string;
  from: string;
  to: string;
  unbilled: string;
  posted: string;
  open: string;
  statuses: Record<string, string>;
  dueStates: Record<string, string>;
  billingCycles: Record<string, string>;
};

type RentalOperationsDataTableProps = {
  filters: RentalOperationsFilters;
  labels: RentalOperationsLabels;
  locale: string;
  statusTone: (value: string) => 'ok' | 'muted' | 'danger' | 'warning' | 'info';
};

type RentalSlots = Record<string, (data: any, row: any) => ReactElement>;

export default function RentalOperationsDataTable({
  filters,
  labels,
  locale,
  statusTone,
}: RentalOperationsDataTableProps) {
  const columns = useMemo(() => [
    { data: 'contract_number', name: 'contract_number', title: labels.contract },
    { data: 'customer_name', name: 'customer_name', title: labels.customer },
    { data: 'branch_name', name: 'branch_name', title: labels.branch },
    { data: 'status', name: 'status', title: labels.status },
    { data: 'expected_end_date', name: 'expected_end_date', title: labels.dates },
    { data: 'open_item_count', name: 'open_item_count', title: labels.items, searchable: false },
    { data: 'open_invoice_count', name: 'open_invoice_count', title: labels.invoices, searchable: false },
    { data: 'total_billed_minor', name: 'total_billed_minor', title: labels.totalBilled, searchable: false },
    { data: 'pending_damage_minor', name: 'pending_damage_minor', title: labels.pendingDamage, searchable: false },
    { data: 'latest_journal_number', name: 'latest_journal_number', title: labels.latestJournal, searchable: false, orderable: false },
  ], [labels]);

  const slots = useMemo<RentalSlots>(() => ({
    contract_number: (data, row) => (
      <div className="flex min-w-44 flex-col gap-1">
        <span className="font-mono text-xs font-bold">{String(data || '') || labels.notNumbered}</span>
        <span className="text-xs text-[var(--text-secondary)]">
          {labels.billingCycles[row.billing_cycle] || row.billing_cycle}
        </span>
      </div>
    ),
    customer_name: (data, row) => (
      <div className="flex min-w-48 flex-col gap-1">
        <span className="font-mono text-xs font-bold">{row.customer_code}</span>
        <span className="text-xs text-[var(--text-secondary)]">{getLocalizedName(data, locale)}</span>
      </div>
    ),
    branch_name: (data, row) => (
      <div className="flex min-w-40 flex-col gap-1">
        <span className="font-mono text-xs font-bold">{row.branch_code || labels.noBranch}</span>
        <span className="text-xs text-[var(--text-secondary)]">
          {row.branch_id ? getLocalizedName(data, locale) : labels.operationalReference}
        </span>
      </div>
    ),
    status: (data, row) => (
      <div className="flex flex-col gap-2">
        <StatusBadge tone={statusTone(String(data))}>
          {labels.statuses[String(data)] || String(data)}
        </StatusBadge>
        <StatusBadge tone={statusTone(String(row.due_state))}>
          {labels.dueStates[String(row.due_state)] || String(row.due_state)}
        </StatusBadge>
      </div>
    ),
    expected_end_date: (data, row) => (
      <div className="flex min-w-40 flex-col gap-1 text-xs">
        <span>{labels.from}: {formatDate(row.start_date) || labels.notAvailable}</span>
        <span>{labels.to}: {formatDate(data) || labels.notAvailable}</span>
      </div>
    ),
    open_item_count: (data, row) => (
      <div className="space-y-1 text-end font-mono text-xs">
        <div>{Number(data).toLocaleString()} / {Number(row.line_count).toLocaleString()}</div>
        <div className="text-[var(--text-muted)]">
          {labels.unbilled}: {Number(row.unbilled_line_count).toLocaleString()}
        </div>
      </div>
    ),
    open_invoice_count: (data, row) => (
      <div className="space-y-1 text-end font-mono text-xs">
        <div>{labels.posted}: {Number(row.posted_invoice_count).toLocaleString()}</div>
        <div className="text-[var(--text-muted)]">{labels.open}: {Number(data).toLocaleString()}</div>
      </div>
    ),
    total_billed_minor: (data, row) => (
      <div className="text-end">
        <AccountingAmount amountMinor={Number(data)} currency={row.currency} tone="success" />
      </div>
    ),
    pending_damage_minor: (data, row) => (
      <div className="text-end">
        <AccountingAmount
          amountMinor={Number(data)}
          currency={row.currency}
          tone={Number(data) > 0 ? 'danger' : 'muted'}
        />
      </div>
    ),
    latest_journal_number: (data) => (
      <span className="font-mono text-xs font-bold text-[var(--text-secondary)]">
        {String(data || '') || labels.notAvailable}
      </span>
    ),
  }), [labels, locale, statusTone]);

  return (
    <ServerDataTable
      ajaxUrl="/reports/rentals/data"
      columns={columns}
      filters={filters}
      locale={locale}
      order={[[4, 'asc']]}
      pageLength={25}
      slots={slots}
      tableId="rental-operations-data-table"
    />
  );
}

export type { RentalOperationsFilters, RentalOperationsLabels };
