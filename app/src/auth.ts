/**
 * NextAuth (Auth.js v5) configuration — credentials provider backed by the tested
 * CredentialsAuthService + Argon2 hasher + Prisma user repo. JWT sessions carry
 * the server-derived companyId and RBAC grants. Requires AUTH_SECRET + DATABASE_URL.
 *
 * Status: PARTIAL — real code; verified only at full install + running Postgres.
 */
import NextAuth from 'next-auth';
import Credentials from 'next-auth/providers/credentials';
import { CredentialsAuthService } from './core/auth/authService';
import { Argon2PasswordHasher } from './core/auth/password.argon2';
import { InMemoryRateLimiter } from './core/auth/rateLimit';
import { PrismaUserRepository, loadGrants, resolveActiveCompany } from './core/db/repositories/userRepo';
import type { Grant } from './core/rbac';

const authService = new CredentialsAuthService(new PrismaUserRepository(), new Argon2PasswordHasher());
const loginLimiter = new InMemoryRateLimiter(5, 60_000);

export const { handlers, auth, signIn, signOut } = NextAuth({
  session: { strategy: 'jwt' },
  pages: { signIn: '/login' },
  trustHost: true,
  providers: [
    Credentials({
      credentials: { email: { label: 'Email' }, password: { label: 'Password' } },
      authorize: async (creds) => {
        const email = String(creds?.email ?? '').toLowerCase();
        const password = String(creds?.password ?? '');
        if (!loginLimiter.consume(email).allowed) return null; // rate limit per email
        try {
          const u = await authService.authenticate(email, password);
          const companyId = await resolveActiveCompany(u.id);
          return { id: u.id, email: u.email, name: u.name, companyId } as { id: string; email: string; name: string; companyId: string | null };
        } catch {
          return null; // generic — never reveals why
        }
      },
    }),
  ],
  callbacks: {
    async jwt({ token, user }) {
      if (user) token.companyId = (user as { companyId?: string }).companyId;
      if (token.sub && !token.companyId) {
        token.companyId = await resolveActiveCompany(token.sub);
      }
      if (token.sub && token.companyId) {
        token.grants = (await loadGrants(token.sub, token.companyId as string)) as unknown as Grant[];
      }
      return token;
    },
    async session({ session, token }) {
      (session as unknown as { companyId?: unknown }).companyId = token.companyId;
      (session as unknown as { grants?: unknown }).grants = token.grants ?? [];
      if (session.user) (session.user as { id?: string }).id = token.sub;
      return session;
    },
  },
});
