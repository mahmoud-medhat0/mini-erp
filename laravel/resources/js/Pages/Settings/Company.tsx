import { Head } from '@inertiajs/react';

import AppLayout from '../../Components/AppLayout';
import { EmptyState, PageHeader, tableClasses } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types/page';

type CompanyRow = {
  id: string;
  name: string;
  baseCurrency: string;
  branchCount: number;
  createdAt: string | null;
};

type CompanyProps = SharedPageProps & {
  companies: CompanyRow[];
};

export default function CompanySettings({ companies, locale }: CompanyProps) {
  const dict = getDictionary(locale);

  return (
    <AppLayout active="settings">
      <Head title={dict.app.settings.sections.company.title} />
      <PageHeader title={dict.app.settings.sections.company.title} description={dict.app.settings.company.description} />

      {companies.length === 0 ? (
        <EmptyState title={dict.app.settings.company.empty} />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{dict.app.fields.company}</th>
                <th className={tableClasses.th}>{dict.app.fields.baseCurrency}</th>
                <th className={tableClasses.th}>{dict.app.fields.branches}</th>
                <th className={tableClasses.th}>{dict.app.fields.createdAt}</th>
              </tr>
            </thead>
            <tbody>
              {companies.map((company) => (
                <tr key={company.id}>
                  <td className={tableClasses.td}>
                    <strong>{company.name}</strong>
                    <div className="identifier mt-1 text-xs text-[var(--text-muted)]">{company.id}</div>
                  </td>
                  <td className={`${tableClasses.td} code`}>{company.baseCurrency}</td>
                  <td className={tableClasses.td}>{company.branchCount}</td>
                  <td className={tableClasses.td}>{company.createdAt ? new Date(company.createdAt).toLocaleDateString(locale) : '-'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </AppLayout>
  );
}
