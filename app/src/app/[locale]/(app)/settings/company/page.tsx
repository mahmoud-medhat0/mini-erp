import { getTranslations } from 'next-intl/server';
import { requireAuth } from '@/core/auth/server';
import { tenantFromSession } from '@/core/tenant/context';
import { SettingsService } from '@/modules/company/application/settingsService';
import { PrismaSettingsRepository } from '@/core/db/repositories/settingsRepo';
import { CURRENCIES } from '@/core/currency';
import { PageHeader, Card } from '@/ui/primitives';
import { Input } from '@/ui/Input';
import { Button } from '@/ui/Button';
import { saveCompanySettings } from './actions';

export default async function CompanySettingsPage({
  params,
  searchParams,
}: {
  params: Promise<{ locale: string }>;
  searchParams: Promise<{ saved?: string }>;
}) {
  const { locale } = await params;
  const { saved } = await searchParams;
  const session = await requireAuth(locale);
  const t = await getTranslations();
  const settings = await new SettingsService(new PrismaSettingsRepository()).get(tenantFromSession(session));
  const action = saveCompanySettings.bind(null, locale);

  return (
    <div style={{ maxWidth: 560 }}>
      <PageHeader title={t('settings.company')} />
      {saved && (
        <p role="status" style={{ color: 'var(--success)', fontSize: 'var(--text-sm)' }}>
          ✓ {t('settings.saved')}
        </p>
      )}
      <Card>
        <form action={action} style={{ display: 'flex', flexDirection: 'column', gap: 'var(--space-4)' }}>
          <label style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
            <span style={{ fontSize: 'var(--text-sm)', fontWeight: 600, color: 'var(--text-secondary)' }}>
              {t('settings.baseCurrency')}
            </span>
            <select
              name="baseCurrency"
              defaultValue={settings.baseCurrency}
              style={{ padding: '8px var(--space-3)', border: '1px solid var(--border)', borderRadius: 'var(--radius-sm)', background: 'var(--background)', color: 'var(--text-primary)' }}
            >
              {Object.values(CURRENCIES).map((c) => (
                <option key={c.code} value={c.code}>
                  {c.code} — {locale === 'ar' ? c.name_ar : c.name_en}
                </option>
              ))}
            </select>
          </label>

          <label style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
            <span style={{ fontSize: 'var(--text-sm)', fontWeight: 600, color: 'var(--text-secondary)' }}>
              {t('settings.locale')}
            </span>
            <select
              name="locale"
              defaultValue={settings.locale}
              style={{ padding: '8px var(--space-3)', border: '1px solid var(--border)', borderRadius: 'var(--radius-sm)', background: 'var(--background)', color: 'var(--text-primary)' }}
            >
              <option value="en">English</option>
              <option value="ar">العربية</option>
            </select>
          </label>

          <Input label={t('settings.timezone')} name="timezone" defaultValue={settings.timezone} />
          <Input
            label={t('settings.fiscalStart')}
            name="fiscalYearStartMonth"
            type="number"
            min={1}
            max={12}
            defaultValue={settings.fiscalYearStartMonth}
          />

          <div>
            <Button type="submit">{t('action.save')}</Button>
          </div>
        </form>
      </Card>
    </div>
  );
}
