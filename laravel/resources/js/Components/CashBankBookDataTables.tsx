import { useMemo, type ReactElement } from 'react';

import { formatMoney } from '../lib/accountingHelpers';
import ServerDataTable from './ServerDataTable';
import { StatusBadge } from './Primitives';

type BookLabels = {
  date: string;
  journalRef: string;
  description: string;
  debit: string;
  credit: string;
  runningBalance: string;
  reconciliation?: string;
  matched?: string;
  unmatched?: string;
  zeroAmount: string;
};

type BookDataTableProps = {
  accountId: string;
  currency: string;
  dateFrom: string;
  dateTo: string;
  labels: BookLabels;
  locale: string;
  type: 'cash' | 'bank';
};

type BookSlots = Record<string, (data: any, row: any) => ReactElement>;

function BookDataTable({
  accountId,
  currency,
  dateFrom,
  dateTo,
  labels,
  locale,
  type,
}: BookDataTableProps) {
  const isBank = type === 'bank';
  const columns = useMemo(() => {
    const result = [
      { data: 'entry_date', name: 'entry_date', title: labels.date },
      { data: 'journal_number', name: 'journal_number', title: labels.journalRef },
      { data: 'description', name: 'description', title: labels.description },
      { data: 'debit_minor', name: 'debit_minor', title: labels.debit, searchable: false },
      { data: 'credit_minor', name: 'credit_minor', title: labels.credit, searchable: false },
      { data: 'balance_after_minor', name: 'balance_after_minor', title: labels.runningBalance, searchable: false },
    ];

    if (isBank) {
      result.splice(3, 0, {
        data: 'is_reconciled',
        name: 'is_reconciled',
        title: labels.reconciliation || '',
        searchable: false,
      });
    }

    return result;
  }, [isBank, labels]);
  const slots = useMemo<BookSlots>(() => ({
    journal_number: (data) => (
      <span className="font-mono font-semibold">{data ? String(data) : '—'}</span>
    ),
    description: (data) => (
      <span className="text-[var(--text-secondary)]">{data ? String(data) : '—'}</span>
    ),
    debit_minor: (data) => (
      <span className="font-mono text-emerald-600 dark:text-emerald-400">
        {Number(data) > 0 ? formatMoney(Number(data), currency) : labels.zeroAmount}
      </span>
    ),
    credit_minor: (data) => (
      <span className="font-mono text-rose-600 dark:text-rose-400">
        {Number(data) > 0 ? formatMoney(Number(data), currency) : labels.zeroAmount}
      </span>
    ),
    balance_after_minor: (data) => (
      <span className="font-mono font-bold">{formatMoney(Number(data), currency)}</span>
    ),
    is_reconciled: (data) => (
      <StatusBadge tone={data === true || data === 1 || data === '1' ? 'ok' : 'muted'}>
        {data === true || data === 1 || data === '1' ? (labels.matched || '') : (labels.unmatched || '')}
      </StatusBadge>
    ),
  }), [currency, labels]);
  const filters = isBank
    ? { bank_account_id: accountId, date_from: dateFrom, date_to: dateTo }
    : { cash_account_id: accountId, date_from: dateFrom, date_to: dateTo };

  return (
    <ServerDataTable
      ajaxUrl={`/reports/${type}-book/data`}
      columns={columns}
      filters={filters}
      locale={locale}
      order={[[0, 'asc']]}
      pageLength={25}
      slots={slots}
      tableId={`${type}-book-data-table`}
    />
  );
}

type PublicBookProps = Omit<BookDataTableProps, 'type'>;

export function CashBookDataTable(props: PublicBookProps) {
  return <BookDataTable {...props} type="cash" />;
}

export function BankBookDataTable(props: PublicBookProps) {
  return <BookDataTable {...props} type="bank" />;
}
