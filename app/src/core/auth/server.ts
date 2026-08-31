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
import { loadGrants } from '../db/repositories/userRepo';

export interface AuthIdentity {
  userId: string;
  email: string;
  companyId?: string | null;
  grants: Grant[];
}

/** Authenticated user identity, allowing the first-run state where no company exists yet. */
export async function getServerIdentity(): Promise<AuthIdentity | null> {
  const s = await auth();
  if (!s?.user) return null;
  const userId = (s.user as { id?: string }).id ?? '';
  const companyId = (s as unknown as { companyId?: string | null }).companyId ?? null;
  return {
    userId,
    email: s.user.email ?? '',
    companyId,
    grants: userId && companyId ? await loadGrants(userId, companyId) : [],
  };
}

export async function getServerSession(): Promise<Session | null> {
  const identity = await getServerIdentity();
  if (!identity?.companyId) return null;
  return {
    userId: identity.userId,
    email: identity.email,
    companyId: identity.companyId,
    grants: identity.grants,
  };
}

/** Use on onboarding: signed in is required, company is optional. */
export async function requireIdentity(locale: string): Promise<AuthIdentity> {
  const identity = await getServerIdentity();
  if (!identity) redirect(`/${locale}/login`);
  return identity;
}

/** Use at the top of a protected server component/layout. */
export async function requireAuth(locale: string): Promise<Session> {
  const identity = await getServerIdentity();
  if (!identity) redirect(`/${locale}/login`);
  if (!identity.companyId) redirect(`/${locale}/onboarding`);
  const session = await getServerSession();
  if (!session) redirect(`/${locale}/onboarding`);
  return session;
}
