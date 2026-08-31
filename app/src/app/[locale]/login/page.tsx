import { useTranslations } from 'next-intl';
import { loginAction } from './actions';

/**
 * Login screen — token-based styling, EN/AR + RTL (dir handled by the locale
 * layout), light/dark via CSS variables. Server action performs real auth.
 * Status: PARTIAL — renders + wired; end-to-end verified at full install.
 */
export default async function LoginPage({
  params,
  searchParams,
}: {
  params: Promise<{ locale: string }>;
  searchParams: Promise<{ error?: string }>;
}) {
  const { locale } = await params;
  const { error } = await searchParams;
  return <LoginForm locale={locale} hasError={!!error} />;
}

function LoginForm({ locale, hasError }: { locale: string; hasError: boolean }) {
  const t = useTranslations();
  const action = loginAction.bind(null, locale);
  return (
    <main
      style={{
        minHeight: '100vh',
        display: 'grid',
        placeItems: 'center',
        background: 'var(--background)',
        color: 'var(--text-primary)',
      }}
    >
      <form
        action={action}
        style={{
          width: 360,
          maxWidth: '90vw',
          background: 'var(--surface)',
          border: '1px solid var(--border)',
          borderRadius: 'var(--radius-lg)',
          padding: 'var(--space-6)',
          boxShadow: 'var(--shadow-md)',
          display: 'flex',
          flexDirection: 'column',
          gap: 'var(--space-4)',
        }}
      >
        <h1 style={{ fontWeight: 700, fontSize: 'var(--text-xl)' }}>{t('app.name')}</h1>
        {hasError && (
          <p role="alert" style={{ color: 'var(--danger)', fontSize: 'var(--text-sm)', margin: 0 }}>
            {t('auth.invalidCredentials')}
          </p>
        )}
        <label style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
          <span style={{ fontSize: 'var(--text-sm)', color: 'var(--text-secondary)', fontWeight: 600 }}>
            {t('auth.email')}
          </span>
          <input
            name="email"
            type="email"
            required
            autoComplete="username"
            className="code"
            style={{
              padding: '8px var(--space-3)',
              border: '1px solid var(--border)',
              borderRadius: 'var(--radius-sm)',
              background: 'var(--background)',
              color: 'var(--text-primary)',
            }}
          />
        </label>
        <label style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
          <span style={{ fontSize: 'var(--text-sm)', color: 'var(--text-secondary)', fontWeight: 600 }}>
            {t('auth.password')}
          </span>
          <input
            name="password"
            type="password"
            required
            autoComplete="current-password"
            style={{
              padding: '8px var(--space-3)',
              border: '1px solid var(--border)',
              borderRadius: 'var(--radius-sm)',
              background: 'var(--background)',
              color: 'var(--text-primary)',
            }}
          />
        </label>
        <button
          type="submit"
          style={{
            padding: '9px 14px',
            border: 'none',
            borderRadius: 'var(--radius-sm)',
            background: 'var(--primary)',
            color: 'var(--on-primary)',
            fontWeight: 600,
            cursor: 'pointer',
          }}
        >
          {t('auth.signIn')}
        </button>
      </form>
    </main>
  );
}
