import { useState, type FormEvent } from 'react';
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
  currency?: string | null;
};

export default function TaxPeriodShow({ locale, period, latestReturn, filedReturn, currency }: TaxPeriodShowProps) {
  const dict = getDictionary(locale);
  const can = useCan();
  const canEdit = can('taxes.edit');
  const canFile = can('taxes.file');

  const [fileModalOpen, setFileModalOpen] = useState(false);

  const t = dict.app.taxes.periods;
  const appName = dict.app.accounting.appName;
  const accDict = dict.app.accounting;

  const activeReturn = filedReturn || latestReturn;
  const isFiled = period.status === 'filed';
  const formatTaxMoney = (amountMinor: number) => (currency ? formatMoney(amountMinor, currency) : accDict.notAvailable);

  const { data, setData, post, processing, errors } = useForm({
    notes: period.notes || '',
    reason: period.notes || '',
    confirm_action: 'FILE_TAX_RETURN',
  });

  const handleGenerateDraft = () => {
    router.post(`/taxes/periods/${period.id}/draft`);
  };

  const handleFileReturn = (e: FormEvent) => {
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
      <Head title={`${period.period_label} - ${t.title} - ${appName}`} />

      <PageHeader
        title={`${t.taxPeriod} ${period.period_label}`}
        description={`${period.start_date} ${t.dateRangeSeparator} ${period.end_date}`}
        actions={
          <div className="flex gap-2">
            {!isFiled && canEdit ? (
              <Button variant="secondary" onClick={handleGenerateDraft}>
                {latestReturn ? t.reGenerateDraft : t.generateDraft}
              </Button>
            ) : null}

            {!isFiled && activeReturn && canFile ? (
              <Button onClick={() => setFileModalOpen(true)}>
                {t.fileReturn}
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
              <span className="text-xs text-[var(--text-secondary)] block">{t.periodStatus}</span>
              <span className={`inline-block mt-1 px-3 py-1 rounded-full text-xs font-bold ${isFiled ? 'bg-emerald-500/20 text-emerald-700 dark:text-emerald-400' : period.status === 'draft_return' ? 'bg-amber-500/20 text-amber-700 dark:text-amber-400' : 'bg-sky-500/20 text-sky-700 dark:text-sky-400'}`}>
                {isFiled ? t.filed : period.status === 'draft_return' ? t.draftReturn : t.open}
              </span>
            </div>

            <div>
              <span className="text-xs text-[var(--text-secondary)] block">{t.fileReference}</span>
              <span className="text-sm font-bold font-mono text-[var(--text-primary)] mt-1 block">
                {period.file_reference || activeReturn?.number || t.notAvailable}
              </span>
            </div>

            <div>
              <span className="text-xs text-[var(--text-secondary)] block">{t.filedDate}</span>
              <span className="text-sm font-semibold text-[var(--text-primary)] mt-1 block">
                {period.filed_at ? new Date(period.filed_at).toLocaleDateString() : t.notFiled}
              </span>
            </div>

            <div>
              <span className="text-xs text-[var(--text-secondary)] block">{t.lockingGuard}</span>
              <span className={`text-xs font-bold mt-1 block ${isFiled ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600'}`}>
                {isFiled ? t.lockedGuard : t.openPostingGuard}
              </span>
            </div>
          </div>
        </Card>

        {/* Return Details */}
        {!activeReturn ? (
          <Card>
            <EmptyState
              title={t.emptyDraftTitle}
              description={t.emptyDraftDescription}
            />
          </Card>
        ) : (
          <div className="space-y-6">
            {/* Totals Cards */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="bg-[var(--card)] p-4 rounded-xl border border-[var(--border-color)]">
                <div className="text-xs text-[var(--text-secondary)] mb-1">{t.outputTax}</div>
                <div className="text-xl font-bold text-emerald-600 dark:text-emerald-400">
                  {formatTaxMoney(activeReturn.output_tax_minor)}
                </div>
              </div>

              <div className="bg-[var(--card)] p-4 rounded-xl border border-[var(--border-color)]">
                <div className="text-xs text-[var(--text-secondary)] mb-1">{t.inputTax}</div>
                <div className="text-xl font-bold text-sky-600 dark:text-sky-400">
                  {formatTaxMoney(activeReturn.input_tax_minor)}
                </div>
              </div>

              <div className="bg-[var(--card)] p-4 rounded-xl border border-[var(--border-color)]">
                <div className="text-xs text-[var(--text-secondary)] mb-1">{t.netPayable}</div>
                <div className={`text-xl font-bold ${activeReturn.net_payable_minor >= 0 ? 'text-[var(--text-primary)]' : 'text-amber-600'}`}>
                  {formatTaxMoney(activeReturn.net_payable_minor)}
                </div>
              </div>
            </div>

            {/* Snapshot Table */}
            {activeReturn.snapshot && (
              <Card className="p-0 overflow-hidden">
                <div className="p-4 border-b border-[var(--border-color)] bg-[var(--surface-color)] flex justify-between items-center">
                  <h3 className="text-sm font-bold text-[var(--text-primary)]">
                    {t.snapshotHeading} ({activeReturn.number})
                  </h3>
                  <span className="text-xs font-mono px-2 py-0.5 rounded bg-[var(--card)] border border-[var(--border-color)]">
                    {activeReturn.status.toUpperCase()}
                  </span>
                </div>

                <div className="p-4 space-y-6">
                  <div>
                    <h4 className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)] mb-2">
                      {t.outputBreakdown}
                    </h4>
                    <div className="overflow-x-auto">
                      <table className={tableClasses.table}>
                        <thead className="bg-[var(--surface-color)]">
                          <tr>
                            <th className={tableClasses.th}>{t.taxCode}</th>
                            <th className={tableClasses.th}>{t.rate}</th>
                            <th className={thRightClass}>{t.taxableSubtotal}</th>
                            <th className={thRightClass}>{t.taxAmount}</th>
                            <th className={thRightClass}>{t.grossTotal}</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-[var(--border-color)]">
                          {activeReturn.snapshot.output_vat_breakdown.map((row) => (
                            <tr key={row.tax_code_id} className="hover:bg-[var(--surface-color)] transition-colors">
                              <td className={`${tableClasses.td} font-bold`}>{row.code}</td>
                              <td className={tableClasses.td}>{row.rate_bps / 100}%</td>
                              <td className={`${tdRightClass} font-mono`}>{formatTaxMoney(row.subtotal_minor)}</td>
                              <td className={`${tdRightClass} font-mono font-bold text-emerald-600`}>{formatTaxMoney(row.tax_amount_minor)}</td>
                              <td className={`${tdRightClass} font-mono`}>{formatTaxMoney(row.gross_amount_minor)}</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  </div>

                  <div>
                    <h4 className="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)] mb-2">
                      {t.inputBreakdown}
                    </h4>
                    <div className="overflow-x-auto">
                      <table className={tableClasses.table}>
                        <thead className="bg-[var(--surface-color)]">
                          <tr>
                            <th className={tableClasses.th}>{t.taxCode}</th>
                            <th className={tableClasses.th}>{t.rate}</th>
                            <th className={thRightClass}>{t.taxableSubtotal}</th>
                            <th className={thRightClass}>{t.taxAmount}</th>
                            <th className={thRightClass}>{t.grossTotal}</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-[var(--border-color)]">
                          {activeReturn.snapshot.input_vat_breakdown.map((row) => (
                            <tr key={row.tax_code_id} className="hover:bg-[var(--surface-color)] transition-colors">
                              <td className={`${tableClasses.td} font-bold`}>{row.code}</td>
                              <td className={tableClasses.td}>{row.rate_bps / 100}%</td>
                              <td className={`${tdRightClass} font-mono`}>{formatTaxMoney(row.subtotal_minor)}</td>
                              <td className={`${tdRightClass} font-mono font-bold text-sky-600`}>{formatTaxMoney(row.tax_amount_minor)}</td>
                              <td className={`${tdRightClass} font-mono`}>{formatTaxMoney(row.gross_amount_minor)}</td>
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
        title={t.fileConfirmationTitle}
      >
        <form onSubmit={handleFileReturn} className="space-y-4">
          <p className="text-xs text-[var(--text-secondary)]">
            {t.fileConfirmationDesc}
          </p>

          {activeReturn && (
            <div className="p-3 rounded-xl bg-[var(--surface-color)] border border-[var(--border-color)] space-y-1 text-xs">
              <div className="flex justify-between">
                <span>{t.returnNumber}:</span>
                <span className="font-bold font-mono">{activeReturn.number}</span>
              </div>
              <div className="flex justify-between">
                <span>{t.outputTax}:</span>
                <span className="font-bold text-emerald-600">{formatTaxMoney(activeReturn.output_tax_minor)}</span>
              </div>
              <div className="flex justify-between">
                <span>{t.inputTax}:</span>
                <span className="font-bold text-sky-600">{formatTaxMoney(activeReturn.input_tax_minor)}</span>
              </div>
              <div className="flex justify-between border-t border-[var(--border-color)] pt-1 font-bold">
                <span>{t.netPayable}:</span>
                <span>{formatTaxMoney(activeReturn.net_payable_minor)}</span>
              </div>
            </div>
          )}

          <div>
            <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
              {t.filingNotes}
            </label>
            <textarea
              className="w-full px-3 py-2 text-xs rounded-xl border border-[var(--border-color)] bg-[var(--card)] text-[var(--text-primary)]"
              rows={3}
              value={data.notes}
              onChange={(e) => setData({ ...data, notes: e.target.value, reason: e.target.value })}
            />
            {errors.reason ? <p className="mt-1 text-xs text-rose-600">{errors.reason}</p> : null}
          </div>

          <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="secondary" onClick={() => setFileModalOpen(false)}>
              {t.cancel}
            </Button>
            <Button type="submit" disabled={processing}>
              {processing ? t.filing : t.confirmAndLockTaxPeriod}
            </Button>
          </div>
        </form>
      </Modal>
    </AppLayout>
  );
}
