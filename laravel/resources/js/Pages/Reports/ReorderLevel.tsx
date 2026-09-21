import { Head } from '@inertiajs/react';

import AppLayout from '../../Components/AppLayout';
import { Card, PageHeader, StatusBadge } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps, TranslatedName } from '../../Types';

type ReorderItem = {
  id: string;
  code: string;
  barcode: string | null;
  name: TranslatedName;
  unit_of_measure: TranslatedName;
  on_hand_quantity: number;
  reorder_level: number;
  shortfall: number;
};

type Props = SharedPageProps & {
  report: { items: ReorderItem[]; generated_at: string };
};

function namePart(name: TranslatedName, locale: 'en' | 'ar'): string {
  if (name && typeof name === 'object') return name[locale] || name.en || name.ar || '';
  return name || '';
}

export default function ReorderLevel({ locale, report }: Props) {
  const dict = getDictionary(locale);
  const pageDict = dict.app.pages.reorderLevel;
  const activeLocale = locale === 'ar' ? 'ar' : 'en';

  return (
    <AppLayout active="reports.reorder-level">
      <Head title={pageDict.headTitle} />

      <div className="space-y-6 p-6">
        <PageHeader title={pageDict.title} description={pageDict.description} />

        <Card className="overflow-hidden p-0">
          {report.items.length === 0 ? (
            <div className="p-8 text-center">
              <p className="m-0 text-sm font-semibold text-[var(--text-primary)]">{pageDict.emptyTitle}</p>
              <p className="m-0 mt-1 text-xs text-[var(--text-secondary)]">{pageDict.emptyDescription}</p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-[var(--border)] text-xs font-bold uppercase text-[var(--text-secondary)]">
                    <th className="px-4 py-2 text-start">{pageDict.code}</th>
                    <th className="px-4 py-2 text-start">{pageDict.barcode}</th>
                    <th className="px-4 py-2 text-start">{pageDict.name}</th>
                    <th className="px-4 py-2 text-start">{pageDict.unitOfMeasure}</th>
                    <th className="px-4 py-2 text-end">{pageDict.onHand}</th>
                    <th className="px-4 py-2 text-end">{pageDict.reorderLevel}</th>
                    <th className="px-4 py-2 text-end">{pageDict.shortfall}</th>
                  </tr>
                </thead>
                <tbody>
                  {report.items.map((item) => (
                    <tr key={item.id} className="border-b border-[var(--border)] last:border-b-0">
                      <td className="px-4 py-2 font-mono text-xs font-bold">{item.code}</td>
                      <td className="px-4 py-2 font-mono text-xs">{item.barcode || ''}</td>
                      <td className="px-4 py-2">{namePart(item.name, activeLocale)}</td>
                      <td className="px-4 py-2">{namePart(item.unit_of_measure, activeLocale)}</td>
                      <td className="px-4 py-2 text-end">{item.on_hand_quantity}</td>
                      <td className="px-4 py-2 text-end">{item.reorder_level}</td>
                      <td className="px-4 py-2 text-end"><StatusBadge tone="warning">{item.shortfall}</StatusBadge></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Card>
      </div>
    </AppLayout>
  );
}
