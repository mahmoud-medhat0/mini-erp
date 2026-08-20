/**
 * Deterministic seed plan for permissions + role templates. Pure (no DB) so it
 * is unit-tested; `prisma/seed.ts` applies it in a transaction.
 */
import { allPermissions } from './catalog';
import { ROLE_NAMES, permissionsForRole, RoleName } from './roles';

export interface SeedPlan {
  permissions: { module: string; action: string }[];
  roles: { name: RoleName; isTemplate: true; permissions: string[] }[];
}

export function buildSeedPlan(): SeedPlan {
  const permissions = allPermissions().map((p) => {
    const idx = p.indexOf('.');
    return idx === -1 ? { module: '_capability', action: p } : { module: p.slice(0, idx), action: p.slice(idx + 1) };
  });
  const roles = ROLE_NAMES.map((name) => ({
    name,
    isTemplate: true as const,
    permissions: permissionsForRole(name),
  }));
  return { permissions, roles };
}
