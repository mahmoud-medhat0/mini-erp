import { useMemo, type ReactElement } from 'react';

import { formatMoney, getLocalizedName } from '../lib/accountingHelpers';
import ServerDataTable from './ServerDataTable';

type VatRegisterFilters = {
  from_date: string;
  to_date: string;
  type: string;
  tax_code_id: string | null;
};

type VatRegisterLabels = {
  documentDate: string;
  documentType: string;
  documentNumber: string;
  entity: string;
  taxCategory: string;
  taxCode: string;
  subtotal: string;
  taxAmount: string;
  grossAmount: string;
  documentTypes: Record<string, string>;
  categories: Record<string, string>;
};

type VatRegisterDataTableProps = {
  currency: string;
  filters: VatRegisterFilters;
  labels: VatRegisterLabels;
  locale: string;
};

type VatRegisterSlots = Record<string, (data: any, row: any) => ReactElement>;

export default function VatRegisterDataTable({
  currency,
  filters,
  labels,
  locale,
}: VatRegisterDataTableProps) {
  const columns = useMemo(() => [
    { data: 'document_date', name: 'document_date', title: labels.documentDate },
    { data: 'document_type', name: 'document_type', title: labels.documentType },
    { data: 'document_number', name: 'document_number', title: labels.documentNumber },
    { data: 'entity_name', name: 'entity_name', title: labels.entity },
    { data: 'tax_category', name: 'tax_category', title: labels.taxCategory },
    { data: 'tax_code', name: 'tax_code', title: labels.taxCode },
    { data: 'subtotal_minor', name: 'subtotal_minor', title: labels.subtotal, searchable: false },
    { data: 'tax_amount_minor', name: 'tax_amount_minor', title: labels.taxAmount, searchable: false },
    { data: 'gross_amount_minor', name: 'gross_amount_minor', title: labels.grossAmount, searchable: false },
  ], [labels]);

  const slots = useMemo<VatRegisterSlots>(() => ({
    document_date: (data) => <span className="font-mono">{String(data)}</span>,
    document_type: (data) => {
      const type = String(data);

      return (
        <span className="font-mono text-xs uppercase tracking-wider">
          {labels.documentTypes[type] || type.replace(/_/g, ' ')}
        </span>
      );
    },
    document_number: (data) => <span className="font-semibold">{String(data)}</span>,
    entity_name: (data) => <span>{getLocalizedName(data, locale)}</span>,
    tax_category: (data) => {
      const category = String(data);
      const tone = category === 'output'
        ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
        : 'bg-sky-500/10 text-sky-700 dark:text-sky-400';

      return (
        <span className={`px-2 py-0.5 rounded text-xs font-semibold ${tone}`}>
          {labels.categories[category] || category.toUpperCase()}
        </span>
      );
    },
    tax_code: (data, row) => (
      <span>
        <span className="font-semibold">{String(data)}</span> ({Number(row.tax_rate_bps) / 100}%)
      </span>
    ),
    subtotal_minor: (data) => (
      <span className="font-mono">{formatMoney(Number(data), currency)}</span>
    ),
    tax_amount_minor: (data) => {
      const amount = Number(data);

      return (
        <span className={`font-mono font-bold ${amount < 0 ? 'text-rose-600' : ''}`}>
          {formatMoney(amount, currency)}
        </span>
      );
    },
    gross_amount_minor: (data) => (
      <span className="font-mono">{formatMoney(Number(data), currency)}</span>
    ),
  }), [currency, labels, locale]);

  return (
    <ServerDataTable
      ajaxUrl="/reports/vat-register/data"
      columns={columns}
      filters={filters}
      locale={locale}
      order={[[0, 'asc']]}
      pageLength={25}
      slots={slots}
      tableId="vat-register-data-table"
    />
  );
}

export type { VatRegisterFilters, VatRegisterLabels };
