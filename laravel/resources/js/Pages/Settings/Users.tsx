import { Head } from '@inertiajs/react';

import AppLayout from '../../Components/AppLayout';
import { Card, EmptyState, PageHeader, StatusBadge, tableClasses } from '../../Components/Primitives';
import { getDictionary } from '../../lib/i18n';
import type { SharedPageProps } from '../../Types/page';

type UserRow = {
  id: number | string;
  name: string;
  email: string;
  locale: string;
  theme: string;
  isActive: boolean;
  roles: string[];
};

type RoleRow = {
  id: number | string;
  name: string;
  isTemplate: boolean;
  permissions: string[];
};

type UsersProps = SharedPageProps & {
  users: UserRow[];
  roles: RoleRow[];
};

export default function Users({ users, roles, locale }: UsersProps) {
  const dict = getDictionary(locale);

  return (
    <AppLayout active="settings">
      <Head title={dict.app.settings.sections.users.title} />
      <PageHeader title={dict.app.settings.sections.users.title} description={dict.app.settings.users.description} />

      <div className="grid gap-5 xl:grid-cols-[minmax(0,1.4fr)_minmax(320px,0.6fr)]">
        {users.length === 0 ? (
          <EmptyState title={dict.app.settings.users.emptyUsers} />
        ) : (
          <div className={tableClasses.wrap}>
            <table className={tableClasses.table}>
              <thead>
                <tr>
                  <th className={tableClasses.th}>{dict.app.fields.user}</th>
                  <th className={tableClasses.th}>{dict.app.fields.roles}</th>
                  <th className={tableClasses.th}>{dict.app.fields.status}</th>
                </tr>
              </thead>
              <tbody>
                {users.map((user) => (
                  <tr key={user.id}>
                    <td className={tableClasses.td}>
                      <strong>{user.name}</strong>
                      <div className="code mt-1 text-xs text-[var(--text-muted)]">{user.email}</div>
                    </td>
                    <td className={tableClasses.td}>
                      {user.roles.length === 0 ? (
                        <span className="text-[var(--text-muted)]">{dict.app.state.none}</span>
                      ) : (
                        <div className="flex flex-wrap gap-1.5">
                          {user.roles.map((role) => (
                            <StatusBadge key={role} tone="muted">
                              {role}
                            </StatusBadge>
                          ))}
                        </div>
                      )}
                    </td>
                    <td className={tableClasses.td}>
                      <StatusBadge tone={user.isActive ? 'ok' : 'danger'}>
                        {user.isActive ? dict.app.status.active : dict.app.status.inactive}
                      </StatusBadge>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        <Card className="p-4">
          <h2 className="m-0 text-base font-bold">{dict.app.fields.roles}</h2>
          {roles.length === 0 ? (
            <div className="mt-4">
              <EmptyState title={dict.app.settings.users.emptyRoles} />
            </div>
          ) : (
            <div className="mt-4 grid gap-4">
              {roles.map((role) => (
                <section key={role.id} className="border-t border-[var(--border)] pt-3 first:border-t-0 first:pt-0">
                  <div className="flex flex-wrap items-center gap-2">
                    <strong>{role.name}</strong>
                    {role.isTemplate ? <StatusBadge tone="ok">{dict.app.status.template}</StatusBadge> : null}
                  </div>
                  <p className="m-0 mt-1 text-xs text-[var(--text-muted)]">
                    {role.permissions.length} {dict.app.fields.permissions}
                  </p>
                  <p className="code m-0 mt-2 overflow-hidden text-ellipsis text-xs leading-5 text-[var(--text-secondary)]">
                    {role.permissions.slice(0, 10).join(' · ')}
                    {role.permissions.length > 10 ? ' ...' : ''}
                  </p>
                </section>
              ))}
            </div>
          )}
        </Card>
      </div>
    </AppLayout>
  );
}
