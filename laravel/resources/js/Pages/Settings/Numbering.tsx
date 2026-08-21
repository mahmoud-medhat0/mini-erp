import { Head } from '@inertiajs/react';

import AppLayout from '../../Components/AppLayout';
import { EmptyState, PageHeader, StatusBadge, tableClasses } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types/page';

type SequenceRow = {
  id: string;
  companyName: string;
  key: string;
  docType: string;
  prefix: string;
  includeYear: boolean;
  includeBranch: boolean;
  padding: number;
  resetPolicy: string;
  nextValue: number;
  preview: string;
};

type NumberingProps = SharedPageProps & {
  sequences: SequenceRow[];
};

export default function Numbering({ sequences, locale }: NumberingProps) {
  const dict = getDictionary(locale);

  return (
    <AppLayout active="settings">
      <Head title={dict.app.settings.sections.numbering.title} />
      <PageHeader title={dict.app.settings.sections.numbering.title} description={dict.app.settings.numbering.description} />

      {sequences.length === 0 ? (
        <EmptyState title={dict.app.settings.numbering.empty} />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{dict.app.fields.docType}</th>
                <th className={tableClasses.th}>{dict.app.fields.company}</th>
                <th className={tableClasses.th}>{dict.app.fields.resetPolicy}</th>
                <th className={tableClasses.th}>{dict.app.fields.nextValue}</th>
                <th className={tableClasses.th}>{dict.app.fields.preview}</th>
              </tr>
            </thead>
            <tbody>
              {sequences.map((sequence) => (
                <tr key={sequence.id}>
                  <td className={tableClasses.td}>
                    <strong className="code">{sequence.docType}</strong>
                    <div className="code mt-1 text-xs text-[var(--text-muted)]">{sequence.key}</div>
                  </td>
                  <td className={tableClasses.td}>{sequence.companyName}</td>
                  <td className={tableClasses.td}>
                    <StatusBadge tone="muted">{sequence.resetPolicy}</StatusBadge>
                  </td>
                  <td className={tableClasses.td}>{sequence.nextValue}</td>
                  <td className={`${tableClasses.td} code`}>{sequence.preview}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </AppLayout>
  );
}
