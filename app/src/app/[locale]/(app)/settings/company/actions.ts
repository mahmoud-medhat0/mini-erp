'use server';
/**
 * Persist company settings. Tenant context is derived from the server session
 * (never the browser). Validates via SettingsService; redirects back with a saved
 * flag on success.
 */
import { redirect } from 'next/navigation';
import { requireAuth } from '@/core/auth/server';
import { tenantFromSession } from '@/core/tenant/context';
import { SettingsService } from '@/modules/company/application/settingsService';
import { PrismaSettingsRepository } from '@/core/db/repositories/settingsRepo';
import { DEFAULT_SETTINGS } from '@/modules/company/application/companyService';

export async function saveCompanySettings(locale: string, formData: FormData): Promise<void> {
  const session = await requireAuth(locale);
  const ctx = tenantFromSession(session);
  const svc = new SettingsService(new PrismaSettingsRepository());
  const current = await svc.get(ctx);
  await svc.update(ctx, {
    ...DEFAULT_SETTINGS,
    ...current,
    baseCurrency: String(formData.get('baseCurrency') ?? current.baseCurrency),
    locale: (String(formData.get('locale') ?? current.locale) === 'ar' ? 'ar' : 'en') as 'en' | 'ar',
    timezone: String(formData.get('timezone') ?? current.timezone),
    fiscalYearStartMonth: Number(formData.get('fiscalYearStartMonth') ?? current.fiscalYearStartMonth),
  });
  redirect(`/${locale}/settings/company?saved=1`);
}
