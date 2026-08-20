/**
 * Server-side session + route protection helpers. Tenant context is derived from
 * the authenticated session ONLY — never from the browser/request body.
 */
import { UnauthenticatedError } from '../errors';
import type { Grant, AccessContext } from '../rbac';
import { PermissionSet } from '../rbac';

export interface Session {
  userId: string;
  email: string;
  companyId: string;
  branchId?: string;
  grants: Grant[];
}

export function requireSession(session: Session | null | undefined): Session {
  if (!session) throw new UnauthenticatedError();
  return session;
}

/** Build a PermissionSet from the session's grants. */
export function permissionSetOf(session: Session): PermissionSet {
  return new PermissionSet(session.grants);
}

/** Assert the session holds a permission in its own (server-derived) tenant context. */
export function authorize(session: Session, permission: Parameters<PermissionSet['requirePermission']>[0], extra?: Partial<AccessContext>): void {
  const ctx: AccessContext = { companyId: session.companyId, branchId: session.branchId, ...extra };
  permissionSetOf(session).requirePermission(permission, ctx);
}
