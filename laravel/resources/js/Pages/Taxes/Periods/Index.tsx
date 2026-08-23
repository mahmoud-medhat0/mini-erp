import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '../../../Components/AppLayout';
import DatePicker from '../../../Components/DatePicker';
import { Button, Card, EmptyState, Modal, PageHeader, tableClasses } from '../../../Components/Primitives';
import { getDictionary } from '../../../lib/i18n';
import { useCan } from '../../../lib/permissions';
import type { SharedPageProps } from '../../../Types';

type TaxPeriodItem = {
  id: string;
  period_label: string;
  start_date: string;
  end_date: string;
  status: 'open' | 'draft_return' | 'filed';
  filed_at: string | null;
  file_reference: string | null;
  latest_return?: {
    id: string;
    number: string;
    status: string;
    net_payable_minor: number;
  };
};

type TaxPeriodsIndexProps = SharedPageProps & {
  periods: TaxPeriodItem[];
};

export default function TaxPeriodsIndex({ locale, periods }: TaxPeriodsIndexProps) {
  const dict = getDictionary(locale) as any;
  const can = useCan();
  const canEdit = can('taxes.edit');

  const [createModalOpen, setCreateModalOpen] = useState(false);

  const t = dict.taxes?.periods || {};

  const { data, setData, post, processing, errors, reset } = useForm({
    period_label: '',
    start_date: '',
    end_date: '',
    notes: '',
  });

  const handleCreate = (e: React.FormEvent) => {
    e.preventDefault();
    post('/taxes/periods', {
      onSuccess: () => {
        setCreateModalOpen(false);
        reset();
      },
    });
  };

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'filed':
        return <span className="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-700 dark:text-emerald-400">{t.filed || 'FILED'}</span>;
      case 'draft_return':
        return <span className="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-500/20 text-amber-700 dark:text-amber-400">{t.draftReturn || 'DRAFT RETURN'}</span>;
      default:
        return <span className="px-2.5 py-1 rounded-full text-xs font-bold bg-sky-500/20 text-sky-700 dark:text-sky-400">{t.open || 'OPEN'}</span>;
    }
  };

  return (
    <AppLayout active="taxes.periods.index">
      <Head title={`${t.title || 'Tax Periods & Filing'} - Mini ERP`} />

      <PageHeader
        title={t.title || 'Tax Periods & Filing'}
        description={t.subtitle || 'Manage monthly tax periods, generate tax return draft snapshots, and file official returns.'}
        actions={
          canEdit ? (
            <Button onClick={() => setCreateModalOpen(true)}>
              {t.newPeriod || 'New Tax Period'}
            </Button>
          ) : null
        }
      />

      <div className="space-y-6">
        <Card className="p-0 overflow-hidden">
          {periods.length === 0 ? (
            <EmptyState
              title={t.emptyPeriods || 'No tax periods created yet.'}
              description="Create a tax period to calculate and file tax returns."
            />
          ) : (
            <div className="overflow-x-auto">
              <table className={tableClasses.table}>
                <thead className="bg-[var(--surface-color)]">
                  <tr>
                    <th className={tableClasses.th}>{t.periodLabel || 'Period Label'}</th>
                    <th className={tableClasses.th}>{t.startDate || 'Start Date'}</th>
                    <th className={tableClasses.th}>{t.endDate || 'End Date'}</th>
                    <th className={tableClasses.th}>{t.status || 'Status'}</th>
                    <th className={tableClasses.th}>{t.fileReference || 'Filing Reference'}</th>
                    <th className="px-4 py-3 text-right font-medium text-xs text-[var(--text-secondary)] uppercase border-b border-[var(--border-color)]">
                      {t.actions || 'Actions'}
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[var(--border-color)]">
                  {periods.map((p) => (
                    <tr key={p.id} className="hover:bg-[var(--surface-color)] transition-colors">
                      <td className={`${tableClasses.td} font-bold text-[var(--text-primary)]`}>{p.period_label}</td>
                      <td className={tableClasses.td}>{p.start_date}</td>
                      <td className={tableClasses.td}>{p.end_date}</td>
                      <td className={tableClasses.td}>{getStatusBadge(p.status)}</td>
                      <td className={`${tableClasses.td} font-mono text-xs`}>{p.file_reference || p.latest_return?.number || '—'}</td>
                      <td className="px-4 py-3 text-right text-xs border-b border-[var(--border-color)]">
                        <Link
                          href={`/taxes/periods/${p.id}`}
                          className="inline-flex items-center gap-1 font-semibold text-[var(--primary-color)] hover:underline"
                        >
                          {t.viewDetails || 'View & File'} &rarr;
                        </Link>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Card>
      </div>

      <Modal
        isOpen={createModalOpen}
        onClose={() => setCreateModalOpen(false)}
        title={t.createPeriod || 'Create Tax Period'}
      >
        <form onSubmit={handleCreate} className="space-y-4">
          <div>
            <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
              {t.periodLabel || 'Period Label'} (e.g. 2026-01) *
            </label>
            <input
              type="text"
              className="w-full px-3 py-2 text-xs rounded-xl border border-[var(--border-color)] bg-[var(--card)] text-[var(--text-primary)]"
              value={data.period_label}
              onChange={(e) => setData('period_label', e.target.value)}
              placeholder="YYYY-MM"
              required
            />
            {errors.period_label && <p className="text-xs text-rose-500 mt-1">{errors.period_label}</p>}
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {t.startDate || 'Start Date'} *
              </label>
              <DatePicker value={data.start_date} onChange={(val) => setData('start_date', val || '')} />
              {errors.start_date && <p className="text-xs text-rose-500 mt-1">{errors.start_date}</p>}
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {t.endDate || 'End Date'} *
              </label>
              <DatePicker value={data.end_date} onChange={(val) => setData('end_date', val || '')} />
              {errors.end_date && <p className="text-xs text-rose-500 mt-1">{errors.end_date}</p>}
            </div>
          </div>

          <div>
            <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
              {t.notes || 'Notes'}
            </label>
            <textarea
              className="w-full px-3 py-2 text-xs rounded-xl border border-[var(--border-color)] bg-[var(--card)] text-[var(--text-primary)]"
              rows={3}
              value={data.notes}
              onChange={(e) => setData('notes', e.target.value)}
            />
          </div>

          <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="secondary" onClick={() => setCreateModalOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" disabled={processing}>
              {processing ? 'Saving...' : 'Create Period'}
            </Button>
          </div>
        </form>
      </Modal>
    </AppLayout>
  );
}
