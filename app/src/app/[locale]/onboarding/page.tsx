import { getTranslations } from 'next-intl/server';
import { redirect } from 'next/navigation';
import { requireIdentity } from '@/core/auth/server';
import { Button } from '@/ui/Button';
import { Input } from '@/ui/Input';
import { Card, PageHeader } from '@/ui/primitives';
import { createCompany } from './actions';

export default async function OnboardingPage({
  params,
  searchParams,
}: {
  params: Promise<{ locale: string }>;
  searchParams: Promise<{ error?: string }>;
}) {
  const { locale } = await params;
  const { error } = await searchParams;
  const identity = await requireIdentity(locale);
  if (identity.companyId) redirect(`/${locale}/dashboard`);

  const t = await getTranslations();
  const action = createCompany.bind(null, locale);

  return (
    <main style={{ minHeight: '100vh', display: 'grid', placeItems: 'center', padding: 'var(--space-6)' }}>
      <div style={{ width: '100%', maxWidth: 640 }}>
        <PageHeader title={t('onboarding.title')} />
        <Card>
          <p style={{ marginBlockStart: 0, color: 'var(--text-secondary)', fontSize: 'var(--text-sm)' }}>
            {t('onboarding.description')}
          </p>
          {error && (
            <p role="alert" style={{ color: 'var(--danger)', fontSize: 'var(--text-sm)' }}>
              {t('onboarding.error')}
            </p>
          )}
          <form action={action} style={{ display: 'grid', gap: 'var(--space-4)' }}>
            <Input label={t('onboarding.companyNameEn')} name="companyNameEn" required />
            <Input label={t('onboarding.companyNameAr')} name="companyNameAr" required />
            <div style={{ display: 'grid', gridTemplateColumns: '120px 1fr 1fr', gap: 'var(--space-3)' }}>
              <Input label={t('onboarding.branchCode')} name="branchCode" defaultValue="HQ" required className="code" />
              <Input label={t('onboarding.branchNameEn')} name="branchNameEn" required />
              <Input label={t('onboarding.branchNameAr')} name="branchNameAr" required />
            </div>
            <div>
              <Button type="submit">{t('onboarding.submit')}</Button>
            </div>
          </form>
        </Card>
      </div>
    </main>
  );
}
