import { useMemo, type ReactElement } from 'react';

import { formatMoney, getLocalizedName } from '../lib/accountingHelpers';
import ServerDataTable from './ServerDataTable';
import { StatusBadge } from './Primitives';

type ChequeFilters = {
  direction: string;
  status: string | null;
  customer_id: string | null;
  supplier_id: string | null;
  bank_account_id: string | null;
  date_from: string | null;
  date_to: string | null;
  currency: string;
};

type ChequeLabels = {
  directionParty: string;
  chequeNumber: string;
  dueDate: string;
  bankAccount: string;
  status: string;
  amount: string;
  statuses: Record<string, string>;
};

type ChequeRegisterDataTableProps = {
  filters: ChequeFilters;
  labels: ChequeLabels;
  locale: string;
};

type ChequeSlots = Record<string, (data: any, row: any) => ReactElement>;

const statusTone = (status: string): 'ok' | 'muted' | 'danger' | 'warning' | 'info' => {
  if (status === 'cleared') return 'ok';
  if (['bounced', 'returned', 'cancelled'].includes(status)) return 'danger';
  if (['deposited', 'issued'].includes(status)) return 'warning';
  if (status === 'received') return 'info';

  return 'muted';
};

export default function ChequeRegisterDataTable({ filters, labels, locale }: ChequeRegisterDataTableProps) {
  const columns = useMemo(() => [
    { data: 'party_name', name: 'party_name', title: labels.directionParty },
    { data: 'cheque_number', name: 'cheque_number', title: labels.chequeNumber },
    { data: 'due_date', name: 'due_date', title: labels.dueDate },
    { data: 'bank_account_name', name: 'bank_account_name', title: labels.bankAccount },
    { data: 'status', name: 'status', title: labels.status },
    { data: 'amount_minor', name: 'amount_minor', title: labels.amount, searchable: false },
  ], [labels]);
  const slots = useMemo<ChequeSlots>(() => ({
    party_name: (data, row) => (
      <div className="flex items-center gap-2">
        <span className={`inline-flex rounded px-1.5 py-0.5 text-[10px] font-bold ${row.direction === 'incoming' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-300'}`}>
          {row.direction === 'incoming' ? (locale === 'ar' ? 'وارد' : 'INCOMING') : (locale === 'ar' ? 'صادر' : 'OUTGOING')}
        </span>
        <span className="font-semibold">{row.party_code} - {getLocalizedName(data, locale)}</span>
      </div>
    ),
    cheque_number: (data) => <span className="font-mono font-bold">{String(data)}</span>,
    due_date: (data) => <span className="font-mono">{data ? String(data).split('T')[0] : ''}</span>,
    bank_account_name: (data, row) => (
      <span className="text-[var(--text-secondary)]">
        {row.bank_account_code ? `${row.bank_account_code} - ` : ''}{getLocalizedName(data, locale)}
      </span>
    ),
    status: (data) => {
      const status = String(data).toLowerCase();

      return <StatusBadge tone={statusTone(status)}>{labels.statuses[status] || status.toUpperCase()}</StatusBadge>;
    },
    amount_minor: (data, row) => (
      <span className="font-mono font-bold">{formatMoney(Number(data), row.currency)}</span>
    ),
  }), [labels, locale]);

  return (
    <ServerDataTable
      ajaxUrl="/reports/cheque-register/data"
      columns={columns}
      filters={filters}
      locale={locale}
      order={[[2, 'asc']]}
      pageLength={25}
      slots={slots}
      tableId="cheque-register-data-table"
    />
  );
}

export type { ChequeFilters };
