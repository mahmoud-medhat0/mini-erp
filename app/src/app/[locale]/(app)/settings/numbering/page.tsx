import { getTranslations } from 'next-intl/server';
import { requireAuth } from '@/core/auth/server';
import { tenantFromSession } from '@/core/tenant/context';
import { NumberingConfigService } from '@/core/numbering/service';
import { PrismaNumberSequenceRepository } from '@/core/db/repositories/numberSequenceRepo';
import { formatDocNumber } from '@/core/numbering';
import { PageHeader, Card, EmptyState } from '@/ui/primitives';
import { Input } from '@/ui/Input';
import { Button } from '@/ui/Button';
import { saveNumbering } from './actions';

export default async function NumberingPage({
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
  const configs = await new NumberingConfigService(new PrismaNumberSequenceRepository()).listConfigs(
    tenantFromSession(session),
  );
  const action = saveNumbering.bind(null, locale);
  const year = 2026;

  return (
    <div style={{ maxWidth: 720 }}>
      <PageHeader title={t('settings.numbering')} />
      {saved && <p role="status" style={{ color: 'var(--success)', fontSize: 'var(--text-sm)' }}>✓ {t('settings.saved')}</p>}
      {error && <p role="alert" style={{ color: 'var(--danger)', fontSize: 'var(--text-sm)' }}>{t('state.error')}</p>}

      <Card style={{ marginBlockEnd: 'var(--space-4)' }}>
        <form action={action} style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 90px 1fr auto', gap: 'var(--space-3)', alignItems: 'end' }}>
          <input type="hidden" name="includeYear" value="on" />
          <Input label={t('numbering.docType')} name="docType" required className="code" />
          <Input label={t('numbering.prefix')} name="prefix" required className="code" />
          <Input label={t('numbering.padding')} name="padding" type="number" min={1} max={12} defaultValue={5} />
          <label style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
            <span style={{ fontSize: 'var(--text-sm)', fontWeight: 600, color: 'var(--text-secondary)' }}>{t('numbering.reset')}</span>
            <select name="resetPolicy" defaultValue="yearly" style={{ padding: '8px var(--space-3)', border: '1px solid var(--border)', borderRadius: 'var(--radius-sm)', background: 'var(--background)', color: 'var(--text-primary)' }}>
              <option value="never">never</option>
              <option value="yearly">yearly</option>
              <option value="monthly">monthly</option>
            </select>
          </label>
          <Button type="submit">{t('numbering.add')}</Button>
        </form>
      </Card>

      {configs.length === 0 ? (
        <EmptyState title={t('numbering.none')} />
      ) : (
        <Card style={{ padding: 0 }}>
          <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 'var(--text-sm)' }}>
            <thead>
              <tr>
                <th style={{ textAlign: 'start', padding: '10px var(--space-4)', color: 'var(--text-secondary)' }}>{t('numbering.docType')}</th>
                <th style={{ textAlign: 'start', padding: '10px var(--space-4)', color: 'var(--text-secondary)' }}>{t('numbering.reset')}</th>
                <th style={{ textAlign: 'start', padding: '10px var(--space-4)', color: 'var(--text-secondary)' }}>Preview</th>
              </tr>
            </thead>
            <tbody>
              {configs.map((c) => (
                <tr key={c.docType} style={{ borderBlockStart: '1px solid var(--border)' }}>
                  <td className="code" style={{ padding: '10px var(--space-4)' }}>{c.docType}</td>
                  <td style={{ padding: '10px var(--space-4)' }}>{c.resetPolicy}</td>
                  <td className="code" style={{ padding: '10px var(--space-4)' }}>{formatDocNumber(c, { year }, 1)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </div>
  );
}
