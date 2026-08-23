import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '../../../Components/AppLayout';
import { Button, Card, EmptyState, Modal, PageHeader, tableClasses } from '../../../Components/Primitives';
import { formatMoney } from '../../../lib/accountingHelpers';
import { getDictionary } from '../../../lib/i18n';
import { useCan } from '../../../lib/permissions';
import type { SharedPageProps } from '../../../Types';

type VatSummaryRow = {
  tax_code_id: string;
  code: string;
  name: Record<string, string> | string;
  rate_bps: number;
  subtotal_minor: number;
  tax_amount_minor: number;
  gross_amount_minor: number;
};

type TaxReturnSnapshot = {
  from_date: string;
  to_date: string;
  output_vat_breakdown: VatSummaryRow[];
  input_vat_breakdown: VatSummaryRow[];
  summary: {
    total_output_subtotal_minor: number;
    total_output_tax_minor: number;
    total_output_gross_minor: number;
    total_input_subtotal_minor: number;
    total_input_tax_minor: number;
    total_input_gross_minor: number;
    net_vat_payable_minor: number;
  };
};

type TaxReturnItem = {
  id: string;
  number: string;
  status: 'draft' | 'filed';
  output_tax_minor: number;
  input_tax_minor: number;
  net_payable_minor: number;
  snapshot: TaxReturnSnapshot | null;
  generated_at: string | null;
  filed_at: string | null;
};

type TaxPeriodShowProps = SharedPageProps & {
  period: {
    id: string;
    period_label: string;
    start_date: string;
    end_date: string;
    status: 'open' | 'draft_return' | 'filed';
    filed_at: string | null;
    file_reference: string | null;
    notes: string | null;
  };
  latestReturn: TaxReturnItem | null;
  filedReturn: TaxReturnItem | null;
};

export default function TaxPeriodShow({ locale, period, latestReturn, filedReturn }: TaxPeriodShowProps) {
  const dict = getDictionary(locale) as any;
  const can = useCan();
  const canEdit = can('taxes.edit');
  const canFile = can('taxes.file');

  const [fileModalOpen, setFileModalOpen] = useState(false);

  const t = dict.taxes?.periods || {};

  const activeReturn = filedReturn || latestReturn;
  const isFiled = period.status === 'filed';

  const { data, setData, post, processing } = useForm({
    notes: period.notes || '',
  });

  const handleGenerateDraft = () => {
    router.post(`/taxes/periods/${period.id}/draft`);
  };

  const handleFileReturn = (e: React.FormEvent) => {
    e.preventDefault();
    if (!activeReturn) return;
    post(`/taxes/returns/${activeReturn.id}/file`, {
      onSuccess: () => {
        setFileModalOpen(false);
      },
    });
  };

  const thRightClass = "px-4 py-3 text-right font-medium text-xs text-[var(--text-secondary)] uppercase border-b border-[var(--border-color)]";
  const tdRightClass = "px-4 py-3 text-right text-xs border-b border-[var(--border-color)]";

  return (
    <AppLayout active="taxes.periods.show">
      <Head title={`${period.period_label} - ${t.title || 'Tax Period'} - Mini ERP`} />

      <PageHeader
        title={`Tax Period ${period.period_label}`}
        description={`${period.start_date} to ${period.end_date}`}
        actions={
          <div className="flex gap-2">
            {!isFiled && canEdit ? (
              <Button variant="secondary" onClick={handleGenerateDraft}>
                {latestReturn ? (t.reGenerateDraft || 'Re-generate Draft Return') : (t.generateDraft || 'Generate Draft Return')}
              </Button>
            ) : null}

            {!isFiled && activeReturn && canFile ? (
              <Button onClick={() => setFileModalOpen(true)}>
                {t.fileReturn || 'File Return & Lock Period'}
              </Button>
            ) : null}
          </div>
        }
      />

      <div className="space-y-6">
        {/* Status Summary Card */}
        <Card className="p-4">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4 items-center">
            <div>
              <span className="text-xs text-[var(--text-secondary)] block">Period Status</span>
              <span className={`inline-block mt-1 px-3 py-1 rounded-full text-xs font-bold ${isFiled ? 'bg-emerald-500/20 text-emerald-700 dark:text-emerald-400' : period.status === 'draft_return' ? 'bg-amber-500/20 text-amber-700 dark:text-amber-400' : 'bg-sky-500/20 text-sky-700 dark:text-sky-400'}`}>
                {isFiled ? (t.filed || 'FILED') : period.status === 'draft_return' ? (t.draftReturn || 'DRAFT RETURN') : (t.open || 'OPEN')}
              </span>
            </div>

            <div>
              <span className="text-xs text-[var(--text-secondary)] block">Filing Reference</span>
              <span className="text-sm font-bold font-mono text-[var(--text-primary)] mt-1 block">
                {period.file_reference || activeReturn?.number || '—'}
              </span>
            </div>

            <div>
              <span className="text-xs text-[var(--text-secondary)] block">Filed Date</span>
              <span className="text-sm font-semibold text-[var(--text-primary)] mt-1 block">
                {period.filed_at ? new Date(period.filed_at).toLocaleDateString() : 'Not filed'}
              </span>
            </div>

            <div>
              <span className="text-xs text-[var(--text-secondary)] block">Locking Guard</span>
              <span className={`text-xs font-bold mt-1 block ${isFiled ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600'}`}>
                {isFiled ? 'LOCKED (Postings Blocked)' : 'OPEN FOR POSTINGS'}
              </span>
            </div>
          </div>
        </Card>

        {/* Return Details */}
        {!activeReturn ? (
          <Card>
            <EmptyState
              title="No draft return generated yet"
              description="Click 'Generate Draft Return' above to calculate VAT totals from posted documents."
            />
          </Card>
        ) : (
          <div className="space-y-6">
            {/* Totals Cards */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="bg-[var(--card)] p-4 rounded-xl border border-[var(--border-color)]">
                <div className="text-xs text-[var(--text-secondary)] mb-1">{t.outputTax || 'Output VAT'}</div>
                <div className="text-xl font-bold text-emerald-600 dark:text-emerald-400">
                  {formatMoney(activeReturn.output_tax_minor)}
                </div>
              </div>

              <div className="bg-[var(--card)] p-4 rounded-xl border border-[var(--border-color)]">
                <div className="text-xs text-[var(--text-secondary)] mb-1">{t.inputTax || 'Input VAT'}</div>
                <div className="text-xl font-bold text-sky-600 dark:text-sky-400">
                  {formatMoney(activeReturn.input_tax_minor)}
                </div>
              </div>

              <div className="bg-[var(--card)] p-4 rounded-xl border border-[var(--border-color)]">
                <div className="text-xs text-[var(--text-secondary)] mb-1">{t.netPayable || 'Net VAT Payable'}</div>
                <div className={`text-xl font-bold ${activeReturn.net_payable_minor >= 0 ? 'text-[var(--text-primary)]' : 'text-amber-600'}`}>
                  {formatMoney(activeReturn.net_payable_minor)}
                </div>
              </div>
            </div>

            {/* Snapshot Table */}
            {activeReturn.snapshot && (
              <Card className="p-0 overflow-hidden">
                <div className="p-4 border-b border-[var(--border-color)] bg-[var(--surface-color)] flex justify-between items-center">
                  <h3 className="text-sm font-bold text-[var(--text-primary)]">
                    {t.snapshotHeading || 'Immutable Return Snapshot'} ({activeReturn.number})
                  </h3>
                  <span className="text-xs font-mono px-2 py-0.5 rounded bg-[var(--card)] border border-[var(--border-color)]">
                    {activeReturn.status.toUpperCase()}
                  </span>
                </div>

                <div className="p-4 space-y-6">
                  {/* Output VAT Breakdown */}
                  <div>
                    <h4 className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)] mb-2">
                      Output VAT Breakdown
                    </h4>
                    <div className="overflow-x-auto">
                      <table className={tableClasses.table}>
                        <thead className="bg-[var(--surface-color)]">
                          <tr>
                            <th className={tableClasses.th}>Tax Code</th>
                            <th className={tableClasses.th}>Rate</th>
                            <th className={thRightClass}>Taxable Subtotal</th>
                            <th className={thRightClass}>Tax Amount</th>
                            <th className={thRightClass}>Gross Total</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-[var(--border-color)]">
                          {activeReturn.snapshot.output_vat_breakdown.map((row) => (
                            <tr key={row.tax_code_id} className="hover:bg-[var(--surface-color)] transition-colors">
                              <td className={`${tableClasses.td} font-bold`}>{row.code}</td>
                              <td className={tableClasses.td}>{row.rate_bps / 100}%</td>
                              <td className={`${tdRightClass} font-mono`}>{formatMoney(row.subtotal_minor)}</td>
                              <td className={`${tdRightClass} font-mono font-bold text-emerald-600`}>{formatMoney(row.tax_amount_minor)}</td>
                              <td className={`${tdRightClass} font-mono`}>{formatMoney(row.gross_amount_minor)}</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  </div>

                  {/* Input VAT Breakdown */}
                  <div>
                    <h4 className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)] mb-2">
                      Input VAT Breakdown
                    </h4>
                    <div className="overflow-x-auto">
                      <table className={tableClasses.table}>
                        <thead className="bg-[var(--surface-color)]">
                          <tr>
                            <th className={tableClasses.th}>Tax Code</th>
                            <th className={tableClasses.th}>Rate</th>
                            <th className={thRightClass}>Taxable Subtotal</th>
                            <th className={thRightClass}>Tax Amount</th>
                            <th className={thRightClass}>Gross Total</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-[var(--border-color)]">
                          {activeReturn.snapshot.input_vat_breakdown.map((row) => (
                            <tr key={row.tax_code_id} className="hover:bg-[var(--surface-color)] transition-colors">
                              <td className={`${tableClasses.td} font-bold`}>{row.code}</td>
                              <td className={tableClasses.td}>{row.rate_bps / 100}%</td>
                              <td className={`${tdRightClass} font-mono`}>{formatMoney(row.subtotal_minor)}</td>
                              <td className={`${tdRightClass} font-mono font-bold text-sky-600`}>{formatMoney(row.tax_amount_minor)}</td>
                              <td className={`${tdRightClass} font-mono`}>{formatMoney(row.gross_amount_minor)}</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </Card>
            )}
          </div>
        )}
      </div>

      {/* Confirmation Filing Modal */}
      <Modal
        isOpen={fileModalOpen}
        onClose={() => setFileModalOpen(false)}
        title={t.fileConfirmationTitle || 'Confirm Official Return Filing'}
      >
        <form onSubmit={handleFileReturn} className="space-y-4">
          <p className="text-xs text-[var(--text-secondary)]">
            {t.fileConfirmationDesc || 'Filing is permanent and immutable. Once filed, no new tax-affecting documents can be posted with a document date in this period range.'}
          </p>

          {activeReturn && (
            <div className="p-3 rounded-xl bg-[var(--surface-color)] border border-[var(--border-color)] space-y-1 text-xs">
              <div className="flex justify-between">
                <span>Return Number:</span>
                <span className="font-bold font-mono">{activeReturn.number}</span>
              </div>
              <div className="flex justify-between">
                <span>Output VAT:</span>
                <span className="font-bold text-emerald-600">{formatMoney(activeReturn.output_tax_minor)}</span>
              </div>
              <div className="flex justify-between">
                <span>Input VAT:</span>
                <span className="font-bold text-sky-600">{formatMoney(activeReturn.input_tax_minor)}</span>
              </div>
              <div className="flex justify-between border-t border-[var(--border-color)] pt-1 font-bold">
                <span>Net VAT Payable:</span>
                <span>{formatMoney(activeReturn.net_payable_minor)}</span>
              </div>
            </div>
          )}

          <div>
            <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
              Filing Notes (optional)
            </label>
            <textarea
              className="w-full px-3 py-2 text-xs rounded-xl border border-[var(--border-color)] bg-[var(--card)] text-[var(--text-primary)]"
              rows={3}
              value={data.notes}
              onChange={(e) => setData('notes', e.target.value)}
            />
          </div>

          <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="secondary" onClick={() => setFileModalOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" disabled={processing}>
              {processing ? 'Filing...' : 'Confirm & Lock Tax Period'}
            </Button>
          </div>
        </form>
      </Modal>
    </AppLayout>
  );
}
