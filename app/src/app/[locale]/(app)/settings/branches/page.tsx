import { getTranslations } from 'next-intl/server';
import { requireAuth } from '@/core/auth/server';
import { tenantFromSession } from '@/core/tenant/context';
import { BranchService } from '@/modules/company/application/branchService';
import { PrismaBranchRepository } from '@/core/db/repositories/branchRepo';
import { PageHeader, Card, EmptyState } from '@/ui/primitives';
import { Input } from '@/ui/Input';
import { Button } from '@/ui/Button';
import { addBranch } from './actions';

export default async function BranchesPage({
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
  const branches = await new BranchService(new PrismaBranchRepository()).list(tenantFromSession(session));
  const action = addBranch.bind(null, locale);

  return (
    <div style={{ maxWidth: 640 }}>
      <PageHeader title={t('settings.branches')} />
      {saved && <p role="status" style={{ color: 'var(--success)', fontSize: 'var(--text-sm)' }}>✓ {t('settings.saved')}</p>}
      {error && <p role="alert" style={{ color: 'var(--danger)', fontSize: 'var(--text-sm)' }}>{t('state.error')}</p>}

      <Card style={{ marginBlockEnd: 'var(--space-4)' }}>
        <form action={action} style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr auto', gap: 'var(--space-3)', alignItems: 'end' }}>
          <Input label={t('branches.code')} name="code" required className="code" />
          <Input label={t('branches.nameEn')} name="nameEn" required />
          <Input label={t('branches.nameAr')} name="nameAr" required />
          <Button type="submit">{t('branches.add')}</Button>
        </form>
      </Card>

      {branches.length === 0 ? (
        <EmptyState title={t('branches.none')} />
      ) : (
        <Card style={{ padding: 0 }}>
          <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 'var(--text-sm)' }}>
            <thead>
              <tr>
                <th style={{ textAlign: 'start', padding: '10px var(--space-4)', color: 'var(--text-secondary)' }}>{t('branches.code')}</th>
                <th style={{ textAlign: 'start', padding: '10px var(--space-4)', color: 'var(--text-secondary)' }}>{t('branches.nameEn')}</th>
                <th style={{ textAlign: 'start', padding: '10px var(--space-4)', color: 'var(--text-secondary)' }}>{t('branches.nameAr')}</th>
              </tr>
            </thead>
            <tbody>
              {branches.map((b) => (
                <tr key={b.id} style={{ borderBlockStart: '1px solid var(--border)' }}>
                  <td className="code" style={{ padding: '10px var(--space-4)' }}>{b.code}</td>
                  <td style={{ padding: '10px var(--space-4)' }}>{b.nameEn}</td>
                  <td style={{ padding: '10px var(--space-4)' }}>{b.nameAr}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </div>
  );
}
