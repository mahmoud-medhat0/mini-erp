import { Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';

import { changeLocale, getDictionary } from '../lib/i18n';
import type { SharedPageProps } from '../Types/page';

type AppLayoutProps = {
  active: 'dashboard' | 'settings' | 'notifications';
  children: ReactNode;
};

const navItems = [
  { key: 'dashboard', href: '/dashboard' },
  { key: 'settings', href: '/settings' },
  { key: 'notifications', href: '/notifications' },
] as const;

export default function AppLayout({ active, children }: AppLayoutProps) {
  const { props } = usePage<SharedPageProps>();
  const locale = props.locale === 'ar' ? 'ar' : 'en';
  const dict = getDictionary(locale);
  const { post, processing } = useForm({});

  function logout(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    post('/logout');
  }

  return (
    <div className="min-h-screen bg-[var(--background)] text-[var(--text-primary)]">
      <div className="flex min-h-screen flex-col lg:flex-row">
        <aside className="border-b border-[var(--border)] bg-[var(--surface)] lg:w-64 lg:border-b-0 lg:border-e">
          <div className="flex h-16 items-center justify-between px-4 lg:h-auto lg:justify-start lg:px-5 lg:py-5">
            <Link href="/dashboard" className="text-base font-bold text-[var(--text-primary)] no-underline">
              Mini ERP
            </Link>
            <span className="rounded-sm border border-[var(--border)] px-2 py-1 text-xs font-semibold text-[var(--text-muted)] lg:hidden">
              {dict.app.nav[active]}
            </span>
          </div>
          <nav className="flex gap-1 overflow-x-auto px-3 pb-3 lg:flex-col lg:overflow-visible lg:pb-0">
            {navItems.map((item) => {
              const isActive = item.key === active;

              return (
                <Link
                  key={item.href}
                  href={item.href}
                  className={[
                    'whitespace-nowrap rounded-md px-3 py-2 text-sm font-semibold no-underline transition-colors',
                    isActive
                      ? 'bg-[var(--primary)] text-white'
                      : 'text-[var(--text-secondary)] hover:bg-[var(--background)] hover:text-[var(--text-primary)]',
                  ].join(' ')}
                >
                  {dict.app.nav[item.key]}
                  {item.key === 'notifications' && props.notifications.unreadCount > 0 ? (
                    <span className="ms-2 rounded-sm bg-white/20 px-1.5 py-0.5 text-xs">
                      {props.notifications.unreadCount}
                    </span>
                  ) : null}
                </Link>
              );
            })}
          </nav>
        </aside>

        <div className="flex min-w-0 flex-1 flex-col">
          <header className="flex min-h-14 flex-wrap items-center gap-3 border-b border-[var(--border)] bg-[var(--surface)] px-4 py-3">
            <div className="min-w-0 flex-1">
              <p className="m-0 truncate text-sm font-semibold">{props.auth.user?.name}</p>
              <p className="m-0 truncate text-xs text-[var(--text-muted)]">{props.auth.user?.email}</p>
            </div>

            <div className="flex items-center rounded-md border border-[var(--border)] bg-[var(--background)] p-1">
              <button
                type="button"
                onClick={() => changeLocale('en')}
                className={[
                  'rounded-sm px-2 py-1 text-xs font-semibold',
                  locale === 'en' ? 'bg-[var(--primary)] text-white' : 'text-[var(--text-secondary)]',
                ].join(' ')}
              >
                EN
              </button>
              <button
                type="button"
                onClick={() => changeLocale('ar')}
                className={[
                  'rounded-sm px-2 py-1 text-xs font-semibold',
                  locale === 'ar' ? 'bg-[var(--primary)] text-white' : 'text-[var(--text-secondary)]',
                ].join(' ')}
              >
                AR
              </button>
            </div>

            <form onSubmit={logout}>
              <button
                type="submit"
                disabled={processing}
                className="h-9 rounded-md border border-[var(--border)] bg-[var(--surface)] px-3 text-sm font-semibold text-[var(--text-secondary)] disabled:cursor-not-allowed disabled:opacity-60"
              >
                {dict.app.actions.logout}
              </button>
            </form>
          </header>

          <main className="min-w-0 flex-1 px-4 py-5 sm:px-6 lg:px-8">{children}</main>
        </div>
      </div>
    </div>
  );
}
