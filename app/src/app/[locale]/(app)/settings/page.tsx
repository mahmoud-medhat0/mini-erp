import { getTranslations } from 'next-intl/server';
import { PageHeader, Card } from '@/ui/primitives';

const SECTIONS = [
  { key: 'settings.company', href: 'settings/company' },
  { key: 'settings.branches', href: 'settings/branches' },
  { key: 'settings.users', href: 'settings/users' },
  { key: 'settings.numbering', href: 'settings/numbering' },
];

export default async function SettingsHub({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  const t = await getTranslations();
  return (
    <div>
      <PageHeader title={t('settings.title')} />
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))', gap: 'var(--space-4)' }}>
        {SECTIONS.map((s) => (
          <a key={s.href} href={`/${locale}/${s.href}`} style={{ textDecoration: 'none', color: 'inherit' }}>
            <Card style={{ padding: 'var(--space-4)' }}>
              <strong style={{ color: 'var(--text-primary)' }}>{t(s.key)}</strong>
            </Card>
          </a>
        ))}
      </div>
    </div>
  );
}
