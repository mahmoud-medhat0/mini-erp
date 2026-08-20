/**
 * Tenant context. Derived from the authenticated session on the server — the
 * browser can never assert company/branch. Every repository query must be scoped
 * with this context, and cross-company access is rejected.
 */
import { CrossTenantError } from '../errors';
import type { Session } from '../auth/session';

export interface TenantContext {
  userId: string;
  companyId: string;
  branchId?: string;
}

export function tenantFromSession(session: Session): TenantContext {
  return { userId: session.userId, companyId: session.companyId, branchId: session.branchId };
}

/** Throws if a loaded entity does not belong to the current company. */
export function assertSameCompany(ctx: TenantContext, entityCompanyId: string): void {
  if (entityCompanyId !== ctx.companyId) throw new CrossTenantError();
}

/** Merge the tenant scope into a Prisma-style where clause (server-side only). */
export function scopeWhere<T extends Record<string, unknown>>(ctx: TenantContext, where: T): T & { companyId: string } {
  return { ...where, companyId: ctx.companyId };
}
