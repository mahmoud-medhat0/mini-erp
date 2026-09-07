import { Head, Link } from '@inertiajs/react';
import { useMemo } from 'react';
import AppLayout from '../../Components/AppLayout';
import ServerDataTable, { type DataTableSlots } from '../../Components/ServerDataTable';
import { Card, PageHeader } from '../../Components/Primitives';
import { formatMoney, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types';

type RevisionRow = {
  id: string;
  display_string: string;
  revision_no: number;
  revision_date: string;
  currency: string;
  original_subtotal_minor: number;
  credited_subtotal_minor: number;
  net_subtotal_minor: number;
  original_tax_minor: number;
  credited_tax_minor: number;
  net_tax_minor: number;
  original_total_minor: number;
  credited_total_minor: number;
  net_total_minor: number;
  customerInvoice?: {
    id: string;
    number?: string | null;
    customer?: { id: string; name: string } | null;
  } | null;
  customer_invoice?: {
    id: string;
    number?: string | null;
    customer?: { id: string; name: string } | null;
  } | null;
};

type InvoiceRevisionsProps = SharedPageProps & {
  customerInvoiceRevisions?: RevisionRow[];
  filters: {
    search?: string;
  };
};

export default function InvoiceRevisionsIndex({ locale, filters }: InvoiceRevisionsProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  const pageDict = dict.app.pages.salesInvoiceRevisions;

  const columns = useMemo(() => [
    { data: 'display_string', name: 'customer_invoice_revision.display_string', title: pageDict.revision },
    { data: 'invoice_number', name: 'invoice_number', title: pageDict.originalInvoice },
    { data: 'customer_name', name: 'customer_name', title: pageDict.customer },
    { data: 'revision_date', name: 'customer_invoice_revision.revision_date', title: pageDict.revisionDate },
    { data: 'original_total_minor', name: 'customer_invoice_revision.original_total_minor', title: pageDict.originalTotal, className: 'text-end' },
    { data: 'credited_total_minor', name: 'customer_invoice_revision.credited_total_minor', title: pageDict.creditedTotal, className: 'text-end' },
    { data: 'net_total_minor', name: 'customer_invoice_revision.net_total_minor', title: pageDict.netTotal, className: 'text-end' },
    { data: 'actions', name: 'actions', title: pageDict.actions, orderable: false, searchable: false, className: 'text-end' },
  ], [pageDict]);

  const slots = useMemo<DataTableSlots>(() => ({
    display_string: (value: string, _type: unknown, row: RevisionRow) => (
      <span className="font-mono font-bold text-blue-600">
        {value}
        <span className="ms-1 text-[10px] font-semibold text-[var(--text-muted)]">#{row.revision_no}</span>
      </span>
    ),
    invoice_number: (_value: unknown, _type: unknown, row: RevisionRow) => {
      const invoice = row.customerInvoice || row.customer_invoice;
      return <span className="font-mono">{invoice?.number || accDict.notAvailable}</span>;
    },
    customer_name: (_value: unknown, _type: unknown, row: RevisionRow) => {
      const invoice = row.customerInvoice || row.customer_invoice;
      return <span className="font-medium">{getLocalizedName(invoice?.customer?.name, locale) || accDict.notAvailable}</span>;
    },
    original_total_minor: (value: number, _type: unknown, row: RevisionRow) => (
      <span className="font-mono font-semibold">{formatMoney(value, row.currency)}</span>
    ),
    credited_total_minor: (value: number, _type: unknown, row: RevisionRow) => (
      <span className="font-mono font-semibold text-red-600">{formatMoney(value, row.currency)}</span>
    ),
    net_total_minor: (value: number, _type: unknown, row: RevisionRow) => (
      <span className="font-mono font-bold">{formatMoney(value, row.currency)}</span>
    ),
    actions: (_value: unknown, _type: unknown, row: RevisionRow) => (
      <Link href={`/sales/invoice-revisions/${row.id}`} className="text-xs font-semibold text-blue-600 hover:text-blue-800 no-underline">
        {pageDict.view}
      </Link>
    ),
  }), [accDict.notAvailable, locale, pageDict]);
  
  return (
    <AppLayout active="invoice-revisions.index">
      <Head title={dict.app.pages.salesInvoiceRevisions.invoiceRevisions} />

      <PageHeader
        title={dict.app.pages.salesInvoiceRevisions.invoiceRevisions_2}
        description={dict.app.pages.salesInvoiceRevisions.correctedCustomerInvoiceCopiesGeneratedBy}
      />

      <Card className="overflow-hidden p-0">
        <ServerDataTable
          ajaxUrl="/sales/invoice-revisions/data"
          columns={columns}
          initialSearch={filters.search || ''}
          locale={locale}
          order={[[3, 'desc']]}
          slots={slots}
          tableId="sales-invoice-revisions-data-table"
        />
      </Card>
    </AppLayout>
  );
}
