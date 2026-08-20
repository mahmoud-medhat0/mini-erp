import type { ReactNode } from 'react';
import { getTranslations } from 'next-intl/server';
import { requireAuth } from '@/core/auth/server';
import { AppShell } from '@/ui/AppShell';
import { Button } from '@/ui/Button';
import { signOutAction } from './actions';

/**
 * Protected application layout. Enforces authentication server-side (requireAuth
 * redirects to login when there is no session), then renders the app shell.
 */
export default async function AppLayout({
  children,
  params,
}: {
  children: ReactNode;
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const session = await requireAuth(locale);
  const t = await getTranslations();

  const signOut = (
    <form action={signOutAction.bind(null, locale)}>
      <Button variant="ghost" type="submit">
        {t('auth.signOut')}
      </Button>
    </form>
  );

  return (
    <AppShell locale={locale} active="dashboard" userEmail={session.email} signOut={signOut}>
      {children}
    </AppShell>
  );
}
