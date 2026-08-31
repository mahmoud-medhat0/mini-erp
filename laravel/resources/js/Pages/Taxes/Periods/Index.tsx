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
  const dict = getDictionary(locale);
  const can = useCan();
  const canEdit = can('taxes.edit');

  const [createModalOpen, setCreateModalOpen] = useState(false);

  const t = dict.app.taxes.periods;
  const appName = dict.app.accounting.appName;

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
        return <span className="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-700 dark:text-emerald-400">{t.filed}</span>;
      case 'draft_return':
        return <span className="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-500/20 text-amber-700 dark:text-amber-400">{t.draftReturn}</span>;
      default:
        return <span className="px-2.5 py-1 rounded-full text-xs font-bold bg-sky-500/20 text-sky-700 dark:text-sky-400">{t.open}</span>;
    }
  };

  return (
    <AppLayout active="taxes.periods.index">
      <Head title={`${t.title} - ${appName}`} />

      <PageHeader
        title={t.title}
        description={t.subtitle}
        actions={
          canEdit ? (
            <Button onClick={() => setCreateModalOpen(true)}>
              {t.newPeriod}
            </Button>
          ) : null
        }
      />

      <div className="space-y-6">
        <Card className="p-0 overflow-hidden">
          {periods.length === 0 ? (
            <EmptyState
              title={t.emptyPeriods}
              description={t.emptyPeriodsDescription}
            />
          ) : (
            <div className="overflow-x-auto">
              <table className={tableClasses.table}>
                <thead className="bg-[var(--surface-color)]">
                  <tr>
                    <th className={tableClasses.th}>{t.periodLabel}</th>
                    <th className={tableClasses.th}>{t.startDate}</th>
                    <th className={tableClasses.th}>{t.endDate}</th>
                    <th className={tableClasses.th}>{t.status}</th>
                    <th className={tableClasses.th}>{t.fileReference}</th>
                    <th className="px-4 py-3 text-right font-medium text-xs text-[var(--text-secondary)] uppercase border-b border-[var(--border-color)]">
                      {t.actions}
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
                      <td className={`${tableClasses.td} font-mono text-xs`}>{p.file_reference || p.latest_return?.number || t.notAvailable}</td>
                      <td className="px-4 py-3 text-right text-xs border-b border-[var(--border-color)]">
                        <Link
                          href={`/taxes/periods/${p.id}`}
                          className="inline-flex items-center gap-1 font-semibold text-[var(--primary-color)] hover:underline"
                        >
                          {t.viewDetails} &rarr;
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
        title={t.createPeriod}
      >
        <form onSubmit={handleCreate} className="space-y-4">
          <div>
            <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
              {t.periodLabelHelp} *
            </label>
            <input
              type="text"
              className="w-full px-3 py-2 text-xs rounded-xl border border-[var(--border-color)] bg-[var(--card)] text-[var(--text-primary)]"
              value={data.period_label}
              onChange={(e) => setData('period_label', e.target.value)}
              placeholder={t.periodLabelPlaceholder}
              required
            />
            {errors.period_label && <p className="text-xs text-rose-500 mt-1">{errors.period_label}</p>}
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {t.startDate} *
              </label>
              <DatePicker value={data.start_date} onChange={(val) => setData('start_date', val || '')} />
              {errors.start_date && <p className="text-xs text-rose-500 mt-1">{errors.start_date}</p>}
            </div>
            <div>
              <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
                {t.endDate} *
              </label>
              <DatePicker value={data.end_date} onChange={(val) => setData('end_date', val || '')} />
              {errors.end_date && <p className="text-xs text-rose-500 mt-1">{errors.end_date}</p>}
            </div>
          </div>

          <div>
            <label className="block text-xs font-semibold mb-1 text-[var(--text-secondary)]">
              {t.notes}
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
              {t.cancel}
            </Button>
            <Button type="submit" disabled={processing}>
              {processing ? t.saving : t.createPeriodAction}
            </Button>
          </div>
        </form>
      </Modal>
    </AppLayout>
  );
}
