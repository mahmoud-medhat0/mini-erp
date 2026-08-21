/**
 * Prisma-backed user repository for authentication + grant loading.
 */
import { prisma } from '../prisma';
import type { AuthUser, UserRepository } from '../../auth/authService';
import type { Grant } from '../../rbac';

export class PrismaUserRepository implements UserRepository {
  async findByEmail(email: string): Promise<AuthUser | null> {
    const u = await prisma.user.findUnique({ where: { email } });
    if (!u) return null;
    return { id: u.id, email: u.email, name: u.name, passwordHash: u.passwordHash, isActive: u.isActive };
  }
}

/** Load a user's effective grants within a company (roles → permissions + scope). */
export async function loadGrants(userId: string, companyId: string): Promise<Grant[]> {
  const userRoles = await prisma.userRole.findMany({
    where: { userId, role: { companyId } },
    include: { role: { include: { permissions: { include: { permission: true } } } } },
  });
  const grants: Grant[] = [];
  for (const ur of userRoles) {
    const roleScope = (ur.scopeJson as Grant['scope']) ?? { companyId };
    for (const rp of ur.role.permissions) {
      const permission =
        rp.permission.module === '_capability'
          ? rp.permission.action
          : `${rp.permission.module}.${rp.permission.action}`;
      grants.push({ permission: permission as Grant['permission'], scope: (rp.scopeJson as Grant['scope']) ?? roleScope });
    }
  }
  return grants;
}

/** Resolve the user's active company (first membership for now; UI can switch later). */
export async function resolveActiveCompany(userId: string): Promise<string | null> {
  const membership = await prisma.userCompany.findFirst({ where: { userId } });
  return membership?.companyId ?? null;
}
