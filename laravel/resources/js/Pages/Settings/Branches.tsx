import { Head } from '@inertiajs/react';

import AppLayout from '../../Components/AppLayout';
import { EmptyState, PageHeader, StatusBadge, tableClasses } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types/page';

type BranchRow = {
  id: string;
  companyName: string;
  code: string;
  name: string;
  isActive: boolean;
};

type BranchesProps = SharedPageProps & {
  branches: BranchRow[];
};

export default function Branches({ branches, locale }: BranchesProps) {
  const dict = getDictionary(locale);

  return (
    <AppLayout active="settings">
      <Head title={dict.app.settings.sections.branches.title} />
      <PageHeader title={dict.app.settings.sections.branches.title} description={dict.app.settings.branches.description} />

      {branches.length === 0 ? (
        <EmptyState title={dict.app.settings.branches.empty} />
      ) : (
        <div className={tableClasses.wrap}>
          <table className={tableClasses.table}>
            <thead>
              <tr>
                <th className={tableClasses.th}>{dict.app.fields.code}</th>
                <th className={tableClasses.th}>{dict.app.fields.branch}</th>
                <th className={tableClasses.th}>{dict.app.fields.company}</th>
                <th className={tableClasses.th}>{dict.app.fields.status}</th>
              </tr>
            </thead>
            <tbody>
              {branches.map((branch) => (
                <tr key={branch.id}>
                  <td className={`${tableClasses.td} code`}>{branch.code}</td>
                  <td className={tableClasses.td}>{branch.name}</td>
                  <td className={tableClasses.td}>{branch.companyName}</td>
                  <td className={tableClasses.td}>
                    <StatusBadge tone={branch.isActive ? 'ok' : 'muted'}>
                      {branch.isActive ? dict.app.status.active : dict.app.status.inactive}
                    </StatusBadge>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </AppLayout>
  );
}
