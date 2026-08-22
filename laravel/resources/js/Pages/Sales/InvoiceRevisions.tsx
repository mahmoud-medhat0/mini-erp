import { Head, router, Link } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import { Card, EmptyState, PageHeader, tableClasses } from '../../Components/Primitives';
import { formatMoney } from '../../lib/accountingHelpers';
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
};

type InvoiceRevisionsProps = SharedPageProps & {
  customerInvoiceRevisions: {
    data: RevisionRow[];
    links: any[];
  };
  filters: {
    search?: string;
  };
};

export default function InvoiceRevisionsIndex({ locale, customerInvoiceRevisions, filters }: InvoiceRevisionsProps) {
  const dict = getDictionary(locale);
  
  return (
    <AppLayout active="invoice-revisions.index">
      <Head title={dict.app.pages.salesInvoiceRevisions.invoiceRevisions} />

      <PageHeader
        title={dict.app.pages.salesInvoiceRevisions.invoiceRevisions_2}
        description={dict.app.pages.salesInvoiceRevisions.correctedCustomerInvoiceCopiesGeneratedBy}
      />

      <Card className="p-6">
        <div className="mb-6">
          <div className="relative flex-1 max-w-md">
            <input
              type="text"
              placeholder={dict.app.pages.salesInvoiceRevisions.searchRevisionNumberOrInvoice}
              defaultValue={filters.search || ''}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  const val = (e.target as HTMLInputElement).value;
                  router.get('/sales/invoice-revisions', { search: val }, { preserveState: true });
                }
              }}
              className="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] py-2.5 ps-10 pe-4 text-xs focus:border-blue-500 focus:outline-none"
            />
            <svg className="absolute start-3 top-3 size-4 text-[var(--text-muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
        </div>

        {customerInvoiceRevisions.data.length === 0 ? (
          <EmptyState
            title={dict.app.pages.salesInvoiceRevisions.noInvoiceRevisionsFound}
            description={dict.app.pages.salesInvoiceRevisions.revisionsAreGeneratedWhenPostedCreditNotes}
          />
        ) : (
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{dict.app.pages.salesInvoiceRevisions.revision}</th>
                  <th className={tableClasses.th}>{dict.app.pages.salesInvoiceRevisions.originalInvoice}</th>
                  <th className={tableClasses.th}>{dict.app.pages.salesInvoiceRevisions.customer}</th>
                  <th className={tableClasses.th}>{dict.app.pages.salesInvoiceRevisions.revisionDate}</th>
                  <th className={`${tableClasses.th} text-end`}>{dict.app.pages.salesInvoiceRevisions.originalTotal}</th>
                  <th className={`${tableClasses.th} text-end`}>{dict.app.pages.salesInvoiceRevisions.creditedTotal}</th>
                  <th className={`${tableClasses.th} text-end`}>{dict.app.pages.salesInvoiceRevisions.netTotal}</th>
                  <th className={`${tableClasses.th} text-end`}>{dict.app.pages.salesInvoiceRevisions.actions}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--border)]">
                {customerInvoiceRevisions.data.map((rev) => (
                  <tr key={rev.id}>
                    <td className={`${tableClasses.td} font-mono font-bold text-blue-600`}>
                      {rev.display_string}
                      <span className="ms-1 text-[10px] font-semibold text-[var(--text-muted)]">#{rev.revision_no}</span>
                    </td>
                    <td className={`${tableClasses.td} font-mono`}>{rev.customerInvoice?.number || '-'}</td>
                    <td className={`${tableClasses.td} font-medium`}>{rev.customerInvoice?.customer?.name || '-'}</td>
                    <td className={tableClasses.td}>{rev.revision_date}</td>
                    <td className={`${tableClasses.td} text-end font-mono font-semibold`}>
                      {formatMoney(rev.original_total_minor, rev.currency)}
                    </td>
                    <td className={`${tableClasses.td} text-end font-mono font-semibold text-red-600`}>
                      {formatMoney(rev.credited_total_minor, rev.currency)}
                    </td>
                    <td className={`${tableClasses.td} text-end font-mono font-bold`}>
                      {formatMoney(rev.net_total_minor, rev.currency)}
                    </td>
                    <td className={`${tableClasses.td} text-end`}>
                      <Link
                        href={`/sales/invoice-revisions/${rev.id}`}
                        className="text-xs font-semibold text-blue-600 hover:text-blue-800 no-underline"
                      >
                        {dict.app.pages.salesInvoiceRevisions.view}
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
    </AppLayout>
  );
}
