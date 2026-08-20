import { useTranslations } from 'next-intl';

/**
 * Phase-1 foundation landing. Intentionally NOT a fake ERP dashboard — it reports
 * real build status. Module screens arrive in their phases wired to real services.
 */
export default function HomePage() {
  const t = useTranslations();
  return (
    <main style={{ padding: '2rem', maxWidth: 720, margin: '0 auto' }}>
      <h1 style={{ fontSize: 'var(--text-xl)', fontWeight: 700 }}>{t('app.name')}</h1>
      <p style={{ color: 'var(--text-secondary)' }}>
        Foundation (Phase 1) is in place: design tokens, i18n (EN/AR + RTL), theming, and the core
        kernel (money, numbering, RBAC, audit, accounting invariants). Module screens are built in
        their phases against real services — no fake data.
      </p>
      <p style={{ color: 'var(--text-muted)', fontSize: 'var(--text-sm)' }}>{t('state.notAvailable')}</p>
    </main>
  );
}
