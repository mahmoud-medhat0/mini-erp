import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import { Card } from '../../Components/Primitives';
import { formatMoney, getLocalizedName } from '../../lib/accountingHelpers';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types';

type ProductName = { en?: string; ar?: string } | string;

type RevisionLine = {
  id: string;
  line_no: number;
  description?: string | null;
  original_quantity_e6?: number | null;
  returned_quantity_e6?: number | null;
  net_quantity_e6?: number | null;
  unit_price_minor: number;
  original_subtotal_minor: number;
  credited_subtotal_minor: number;
  net_subtotal_minor: number;
  product?: { code: string; name: ProductName } | null;
  unitOfMeasure?: { id: string; code: string; name: string } | null;
};

type RevisionDetail = {
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
    invoice_date?: string | null;
    customer?: { id: string; name: string } | null;
  } | null;
  lines?: RevisionLine[];
};

type RevisionSnapshot = {
  invoice_number?: string | null;
  credit_note_numbers?: string[];
  sales_return_numbers?: string[];
  generated_at?: string | null;
};

type InvoiceRevisionShowProps = SharedPageProps & {
  revision: RevisionDetail;
  snapshot: RevisionSnapshot | null;
};

const formatQuantity = (qtyE6?: number | null) => String(parseFloat((((qtyE6 || 0) as number) / 1000000).toFixed(6)));

export default function InvoiceRevisionShow({ locale, revision, snapshot }: InvoiceRevisionShowProps) {
  const dict = getDictionary(locale);
  const accDict = dict.app.accounting;
  
  const getProductName = (prod?: { code: string; name: ProductName } | null): string => {
    if (!prod) return '';
    if (typeof prod.name === 'string') return prod.name;
    return locale === 'ar' ? prod.name?.ar || prod.name?.en || '' : prod.name?.en || prod.name?.ar || '';
  };

  const currency = revision.currency;

  const totalsRows = [
    { label: dict.app.pages.salesInvoiceRevisions.subtotal, original: revision.original_subtotal_minor, credited: revision.credited_subtotal_minor, net: revision.net_subtotal_minor },
    { label: dict.app.pages.salesInvoiceRevisions.tax, original: revision.original_tax_minor, credited: revision.credited_tax_minor, net: revision.net_tax_minor },
    { label: dict.app.pages.salesInvoiceRevisions.total, original: revision.original_total_minor, credited: revision.credited_total_minor, net: revision.net_total_minor },
  ];

  const cellStyle = 'border border-slate-300 px-2 py-1.5';
  const thStyle = `${cellStyle} bg-slate-100 text-start font-bold`;

  return (
    <AppLayout active="invoice-revisions.show">
      <Head title={revision.display_string} />

      <div className="mb-4 flex items-center justify-between print:hidden">
        <Link
          href="/sales/invoice-revisions"
          className="text-xs font-semibold text-blue-600 hover:text-blue-800 no-underline"
        >
          ← {dict.app.pages.salesInvoiceRevisions.backToList}
        </Link>
        <button
          type="button"
          onClick={() => window.print()}
          className="inline-flex items-center gap-2 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs font-semibold text-[var(--text-secondary)] shadow-xs hover:bg-[var(--background)]"
          title={dict.app.pages.salesInvoiceRevisions.print}
          aria-label={dict.app.pages.salesInvoiceRevisions.print}
        >
          <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
          </svg>
          <span>{dict.app.pages.salesInvoiceRevisions.print}</span>
        </button>
      </div>

      <Card className="p-8 bg-white text-slate-900">
        <div id="revision-print" className="mx-auto max-w-3xl">
          <div className="flex flex-col sm:flex-row justify-between gap-4 border-b border-slate-200 pb-6">
            <div>
              <p className="m-0 text-[11px] font-bold uppercase tracking-widest text-slate-500">{dict.app.pages.salesInvoiceRevisions.correctedInvoiceCopy}</p>
              <h1 className="mt-1 mb-0 text-3xl font-extrabold tracking-tight">{revision.display_string}</h1>
              <p className="mt-1 mb-0 text-xs text-slate-500">
                {dict.app.pages.salesInvoiceRevisions.revisionNo}: {revision.revision_no}
              </p>
            </div>
            <div className="text-start sm:text-end text-xs space-y-1">
              <p className="m-0">
                <span className="font-bold">{dict.app.pages.salesInvoiceRevisions.originalInvoice}:</span>{' '}
                <span className="font-mono">{snapshot?.invoice_number || revision.customerInvoice?.number || accDict.notAvailable}</span>
              </p>
              {revision.customerInvoice?.invoice_date ? (
                <p className="m-0">
                  <span className="font-bold">{dict.app.pages.salesInvoiceRevisions.invoiceDate}:</span> {revision.customerInvoice.invoice_date}
                </p>
              ) : null}
              <p className="m-0">
                <span className="font-bold">{dict.app.pages.salesInvoiceRevisions.customer}:</span> {getLocalizedName(revision.customerInvoice?.customer?.name, locale) || accDict.notAvailable}
              </p>
              <p className="m-0">
                <span className="font-bold">{dict.app.pages.salesInvoiceRevisions.revisionDate_2}:</span> {revision.revision_date}
              </p>
              <p className="m-0">
                <span className="font-bold">{dict.app.pages.salesInvoiceRevisions.currency}:</span> {currency}
              </p>
            </div>
          </div>

          <table className="mt-6 w-full border-collapse text-xs">
            <thead>
              <tr>
                <th className={`${thStyle} w-40`}></th>
                <th className={thStyle}>{dict.app.pages.salesInvoiceRevisions.original}</th>
                <th className={thStyle}>{dict.app.pages.salesInvoiceRevisions.credited}</th>
                <th className={thStyle}>{dict.app.pages.salesInvoiceRevisions.net}</th>
              </tr>
            </thead>
            <tbody>
              {totalsRows.map((row, idx) => (
                <tr key={row.label} className={idx === totalsRows.length - 1 ? 'bg-slate-50 font-extrabold' : ''}>
                  <td className={`${cellStyle} font-bold`}>{row.label}</td>
                  <td className={`${cellStyle} text-end font-mono`}>{formatMoney(row.original, currency)}</td>
                  <td className={`${cellStyle} text-end font-mono`}>-{formatMoney(row.credited, currency)}</td>
                  <td className={`${cellStyle} text-end font-mono`}>{formatMoney(row.net, currency)}</td>
                </tr>
              ))}
            </tbody>
          </table>

          <h2 className="mt-8 mb-2 text-sm font-extrabold uppercase tracking-wider">{dict.app.pages.salesInvoiceRevisions.linesTableTitle}</h2>
          <div className="overflow-x-auto">
            <table className="w-full border-collapse text-[11px]">
              <thead>
                <tr>
                  <th className={thStyle}>{dict.app.pages.salesInvoiceRevisions.product}</th>
                  <th className={thStyle}>{dict.app.pages.salesInvoiceRevisions.description}</th>
                  <th className={thStyle}>{dict.app.pages.salesInvoiceRevisions.uom}</th>
                  <th className={`${thStyle} text-end`}>{dict.app.pages.salesInvoiceRevisions.originalQty}</th>
                  <th className={`${thStyle} text-end`}>{dict.app.pages.salesInvoiceRevisions.returnedQty}</th>
                  <th className={`${thStyle} text-end`}>{dict.app.pages.salesInvoiceRevisions.netQty}</th>
                  <th className={`${thStyle} text-end`}>{dict.app.pages.salesInvoiceRevisions.unitPrice}</th>
                  <th className={`${thStyle} text-end`}>{dict.app.pages.salesInvoiceRevisions.originalAmt}</th>
                  <th className={`${thStyle} text-end`}>{dict.app.pages.salesInvoiceRevisions.creditedAmt}</th>
                  <th className={`${thStyle} text-end`}>{dict.app.pages.salesInvoiceRevisions.netAmt}</th>
                </tr>
              </thead>
              <tbody>
                {(revision.lines || []).map((line) => (
                  <tr key={line.id}>
                    <td className={`${cellStyle} font-medium`}>{getProductName(line.product) || accDict.notAvailable}</td>
                    <td className={cellStyle}>{line.description || accDict.notAvailable}</td>
                    <td className={cellStyle}>{getLocalizedName(line.unitOfMeasure?.name, locale) || accDict.notAvailable}</td>
                    <td className={`${cellStyle} text-end font-mono`}>{formatQuantity(line.original_quantity_e6)}</td>
                    <td className={`${cellStyle} text-end font-mono`}>{formatQuantity(line.returned_quantity_e6)}</td>
                    <td className={`${cellStyle} text-end font-mono`}>{formatQuantity(line.net_quantity_e6)}</td>
                    <td className={`${cellStyle} text-end font-mono`}>{formatMoney(line.unit_price_minor, currency)}</td>
                    <td className={`${cellStyle} text-end font-mono`}>{formatMoney(line.original_subtotal_minor, currency)}</td>
                    <td className={`${cellStyle} text-end font-mono`}>-{formatMoney(line.credited_subtotal_minor, currency)}</td>
                    <td className={`${cellStyle} text-end font-mono font-semibold`}>{formatMoney(line.net_subtotal_minor, currency)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="mt-8 border-t border-slate-200 pt-4 text-[11px] text-slate-600">
            <p className="m-0">
              <span className="font-bold">{dict.app.pages.salesInvoiceRevisions.relatedCreditNotes}:</span>{' '}
              {(snapshot?.credit_note_numbers && snapshot.credit_note_numbers.length > 0
                ? snapshot.credit_note_numbers.join(', ')
                : accDict.notAvailable)}
            </p>
            <p className="mt-1 mb-0">
              <span className="font-bold">{dict.app.pages.salesInvoiceRevisions.relatedSalesReturns}:</span>{' '}
              {(snapshot?.sales_return_numbers && snapshot.sales_return_numbers.length > 0
                ? snapshot.sales_return_numbers.join(', ')
                : accDict.notAvailable)}
            </p>
          </div>
        </div>
      </Card>

      <style>{`
        @media print {
          body * {
            visibility: hidden;
          }
          #revision-print,
          #revision-print * {
            visibility: visible;
          }
          #revision-print {
            position: absolute;
            inset-inline-start: 0;
            top: 0;
            width: 100%;
            background: #fff;
            color: #000;
          }
        }
      `}</style>
    </AppLayout>
  );
}
