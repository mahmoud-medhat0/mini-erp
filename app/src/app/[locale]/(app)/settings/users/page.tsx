import { getTranslations } from 'next-intl/server';
import { requireAuth } from '@/core/auth/server';
import { authorize } from '@/core/auth/session';
import { PrismaUserAdminRepository } from '@/core/db/repositories/userAdminRepo';
import { PermissionDeniedError } from '@/core/errors';
import { tenantFromSession } from '@/core/tenant/context';
import { UserAdminService } from '@/modules/company/application/userAdminService';
import { Button } from '@/ui/Button';
import { Card, EmptyState, PageHeader, PermissionDenied } from '@/ui/primitives';
import { assignUserRole, revokeUserRole } from './actions';

export default async function UsersSettingsPage({
  params,
  searchParams,
}: {
  params: Promise<{ locale: string }>;
  searchParams: Promise<{ saved?: string; error?: string }>;
}) {
  const { locale } = await params;
  const { saved, error } = await searchParams;
  const session = await requireAuth(locale);
  const t = await getTranslations();

  try {
    authorize(session, 'users.view');
  } catch (e) {
    if (e instanceof PermissionDeniedError) return <PermissionDenied message={t('state.permissionDenied')} />;
    throw e;
  }

  let canConfigure = true;
  try {
    authorize(session, 'users.configure');
  } catch (e) {
    if (e instanceof PermissionDeniedError) canConfigure = false;
    else throw e;
  }

  const service = new UserAdminService(new PrismaUserAdminRepository());
  const ctx = tenantFromSession(session);
  const [users, roles] = await Promise.all([service.listUsers(ctx), service.listRoles(ctx)]);
  const assignAction = assignUserRole.bind(null, locale);
  const revokeAction = revokeUserRole.bind(null, locale);

  return (
    <div>
      <PageHeader title={t('users.title')} />
      {saved && <p role="status" style={{ color: 'var(--success)', fontSize: 'var(--text-sm)' }}>✓ {t('settings.saved')}</p>}
      {error && <p role="alert" style={{ color: 'var(--danger)', fontSize: 'var(--text-sm)' }}>{t('state.error')}</p>}

      {canConfigure ? (
        <Card style={{ marginBlockEnd: 'var(--space-4)' }}>
          <form action={assignAction} style={{ display: 'grid', gridTemplateColumns: '1fr 1fr auto', gap: 'var(--space-3)', alignItems: 'end' }}>
            <label style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
              <span style={{ fontSize: 'var(--text-sm)', fontWeight: 600, color: 'var(--text-secondary)' }}>{t('users.user')}</span>
              <select name="userId" required style={selectStyle}>
                {users.map((user) => (
                  <option key={user.id} value={user.id}>{user.email}</option>
                ))}
              </select>
            </label>
            <label style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
              <span style={{ fontSize: 'var(--text-sm)', fontWeight: 600, color: 'var(--text-secondary)' }}>{t('users.role')}</span>
              <select name="roleId" required style={selectStyle}>
                {roles.map((role) => (
                  <option key={role.id} value={role.id}>{role.name}</option>
                ))}
              </select>
            </label>
            <Button type="submit">{t('users.assign')}</Button>
          </form>
        </Card>
      ) : (
        <Card style={{ marginBlockEnd: 'var(--space-4)' }}>
          <PermissionDenied message={t('users.configureDenied')} />
        </Card>
      )}

      <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0, 1.3fr) minmax(280px, 0.7fr)', gap: 'var(--space-4)' }}>
        <Card style={{ padding: 0 }}>
          {users.length === 0 ? (
            <EmptyState title={t('users.noUsers')} />
          ) : (
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 'var(--text-sm)' }}>
              <thead>
                <tr>
                  <th style={thStyle}>{t('users.user')}</th>
                  <th style={thStyle}>{t('users.roles')}</th>
                </tr>
              </thead>
              <tbody>
                {users.map((user) => (
                  <tr key={user.id} style={{ borderBlockStart: '1px solid var(--border)' }}>
                    <td style={{ padding: '10px var(--space-4)' }}>
                      <strong>{user.name}</strong>
                      <div className="code" style={{ color: 'var(--text-muted)', fontSize: 'var(--text-xs)' }}>{user.email}</div>
                    </td>
                    <td style={{ padding: '10px var(--space-4)' }}>
                      <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                        {user.roles.map((role) => (
                          <form key={role.id} action={revokeAction} style={{ display: 'inline-flex', gap: 4, alignItems: 'center' }}>
                            <input type="hidden" name="userId" value={user.id} />
                            <input type="hidden" name="roleId" value={role.id} />
                            <span style={badgeStyle}>{role.name}</span>
                            {canConfigure && <button type="submit" style={linkButtonStyle}>{t('users.revoke')}</button>}
                          </form>
                        ))}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </Card>

        <Card>
          <h2 style={{ marginBlockStart: 0, fontSize: 'var(--text-md)' }}>{t('users.roles')}</h2>
          {roles.length === 0 ? (
            <EmptyState title={t('users.noRoles')} />
          ) : (
            <div style={{ display: 'grid', gap: 'var(--space-3)' }}>
              {roles.map((role) => (
                <section key={role.id} style={{ borderBlockStart: '1px solid var(--border)', paddingBlockStart: 'var(--space-3)' }}>
                  <strong>{role.name}</strong>
                  <div style={{ color: 'var(--text-muted)', fontSize: 'var(--text-xs)' }}>
                    {role.permissions.length} {t('users.permissions')}
                  </div>
                  <div className="code" style={{ marginBlockStart: 6, color: 'var(--text-secondary)', fontSize: 'var(--text-xs)', overflowWrap: 'anywhere' }}>
                    {role.permissions.slice(0, 8).join(' · ')}
                    {role.permissions.length > 8 ? ' ...' : ''}
                  </div>
                </section>
              ))}
            </div>
          )}
        </Card>
      </div>
    </div>
  );
}

const selectStyle = {
  padding: '8px var(--space-3)',
  border: '1px solid var(--border)',
  borderRadius: 'var(--radius-sm)',
  background: 'var(--background)',
  color: 'var(--text-primary)',
};

const thStyle = {
  textAlign: 'start' as const,
  padding: '10px var(--space-4)',
  color: 'var(--text-secondary)',
};

const badgeStyle = {
  border: '1px solid var(--border)',
  borderRadius: 'var(--radius-sm)',
  padding: '2px 6px',
  background: 'var(--surface-muted)',
};

const linkButtonStyle = {
  border: 0,
  background: 'transparent',
  color: 'var(--danger)',
  cursor: 'pointer',
  fontSize: 'var(--text-xs)',
};
