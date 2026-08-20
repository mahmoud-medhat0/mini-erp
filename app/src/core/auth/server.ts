/**
 * Server-side auth guards for protected routes (React Server Components / route
 * handlers). Reads the NextAuth session and builds our Session shape; redirects
 * to the localized login page when unauthenticated.
 *
 * Status: PARTIAL — real code; exercised at full app runtime.
 */
import { redirect } from 'next/navigation';
import { auth } from '@/auth';
import type { Session } from './session';
import type { Grant } from '../rbac';

export async function getServerSession(): Promise<Session | null> {
  const s = await auth();
  if (!s?.user) return null;
  const companyId = (s as unknown as { companyId?: string }).companyId;
  if (!companyId) return null;
  return {
    userId: (s.user as { id?: string }).id ?? '',
    email: s.user.email ?? '',
    companyId,
    grants: ((s as unknown as { grants?: Grant[] }).grants ?? []) as Grant[],
  };
}

/** Use at the top of a protected server component/layout. */
export async function requireAuth(locale: string): Promise<Session> {
  const session = await getServerSession();
  if (!session) redirect(`/${locale}/login`);
  return session;
}
