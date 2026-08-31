import type { ReactNode } from 'react';
import { useTranslations } from 'next-intl';

const NAV: { key: string; href: string }[] = [
  { key: 'nav.dashboard', href: 'dashboard' },
  { key: 'nav.accounting', href: 'accounting' },
  { key: 'nav.sales', href: 'sales' },
  { key: 'nav.purchasing', href: 'purchasing' },
  { key: 'nav.inventory', href: 'inventory' },
  { key: 'nav.rentals', href: 'rentals' },
  { key: 'nav.customers', href: 'customers' },
  { key: 'nav.suppliers', href: 'suppliers' },
  { key: 'nav.cash', href: 'cash' },
  { key: 'nav.banks', href: 'banks' },
  { key: 'nav.approvals', href: 'approvals' },
  { key: 'nav.settings', href: 'settings' },
];

/** Application shell: sidebar + topbar. RTL-safe (logical borders/insets), tokens. */
export function AppShell({
  locale,
  active,
  userEmail,
  signOut,
  notificationCount = 0,
  children,
}: {
  locale: string;
  active: string;
  userEmail: string;
  signOut: ReactNode;
  notificationCount?: number;
  children: ReactNode;
}) {
  const t = useTranslations();
  return (
    <div
      style={{
        display: 'grid',
        gridTemplateColumns: '248px 1fr',
        gridTemplateRows: '56px 1fr',
        gridTemplateAreas: '"side head" "side main"',
        minHeight: '100vh',
        background: 'var(--background)',
        color: 'var(--text-primary)',
      }}
    >
      <aside
        style={{
          gridArea: 'side',
          background: 'var(--surface)',
          borderInlineEnd: '1px solid var(--border)',
          padding: 'var(--space-3)',
          display: 'flex',
          flexDirection: 'column',
          gap: 2,
        }}
      >
        <div style={{ fontWeight: 700, fontSize: 'var(--text-md)', padding: '8px 12px 16px' }}>◧ {t('app.name')}</div>
        <nav>
          {NAV.map((item) => {
            const isActive = item.href === active;
            return (
              <a
                key={item.href}
                href={`/${locale}/${item.href}`}
                style={{
                  display: 'block',
                  padding: '8px 12px',
                  borderRadius: 'var(--radius-sm)',
                  textDecoration: 'none',
                  fontSize: 'var(--text-sm)',
                  fontWeight: isActive ? 600 : 500,
                  color: isActive ? 'var(--primary)' : 'var(--text-secondary)',
                  background: isActive ? 'var(--primary-subtle)' : 'transparent',
                }}
              >
                {t(item.key)}
              </a>
            );
          })}
        </nav>
      </aside>

      <header
        style={{
          gridArea: 'head',
          background: 'var(--surface)',
          borderBlockEnd: '1px solid var(--border)',
          display: 'flex',
          alignItems: 'center',
          gap: 'var(--space-3)',
          padding: '0 var(--space-4)',
        }}
      >
        <div style={{ flex: 1 }} />
        <a
          href={`/${locale}/notifications`}
          style={{
            textDecoration: 'none',
            color: 'var(--text-secondary)',
            fontSize: 'var(--text-sm)',
            border: '1px solid var(--border)',
            borderRadius: 'var(--radius-sm)',
            padding: '6px 10px',
          }}
        >
          {t('notifications.title')}
          {notificationCount > 0 && (
            <span
              className="num"
              style={{
                marginInlineStart: 6,
                color: 'var(--on-primary)',
                background: 'var(--primary)',
                borderRadius: 'var(--radius-full)',
                padding: '1px 6px',
                fontSize: 'var(--text-xs)',
              }}
            >
              {notificationCount}
            </span>
          )}
        </a>
        <span style={{ fontSize: 'var(--text-sm)', color: 'var(--text-secondary)' }}>{userEmail}</span>
        {signOut}
      </header>

      <main style={{ gridArea: 'main', padding: 'var(--space-6)', overflow: 'auto' }}>{children}</main>
    </div>
  );
}
