/**
 * RBAC — Module → Feature → Action with scopes. Server-side authority only.
 * UI hiding is cosmetic; every Application Service calls `requirePermission`.
 */
import { PermissionDeniedError } from '../errors';

export type Action =
  | 'view'
  | 'create'
  | 'edit'
  | 'delete'
  | 'submit'
  | 'approve'
  | 'reject'
  | 'post'
  | 'cancel'
  | 'reverse'
  | 'export'
  | 'print'
  | 'configure';

/** Sensitive capability flags gated independently of ordinary actions. */
export type SensitiveCapability =
  | 'view_financials'
  | 'view_payroll'
  | 'override_credit_limit'
  | 'override_negative_stock'
  | 'close_period'
  | 'reopen_period';

/** A permission string: `${module}.${action}` e.g. "sales.post", or a sensitive flag. */
export type Permission = `${string}.${Action}` | SensitiveCapability;

export interface Scope {
  companyId?: string;
  branchIds?: string[];
  warehouseIds?: string[];
  projectIds?: string[];
  costCenterIds?: string[];
  docTypes?: string[];
}

export interface Grant {
  permission: Permission;
  scope?: Scope;
}

export interface AccessContext {
  companyId: string;
  branchId?: string;
  warehouseId?: string;
  projectId?: string;
  costCenterId?: string;
  docType?: string;
}

function scopeAllows(scope: Scope | undefined, ctx: AccessContext): boolean {
  if (!scope) return true; // unscoped grant = company-wide (company still enforced below)
  if (scope.companyId && scope.companyId !== ctx.companyId) return false;
  if (scope.branchIds && ctx.branchId && !scope.branchIds.includes(ctx.branchId)) return false;
  if (scope.warehouseIds && ctx.warehouseId && !scope.warehouseIds.includes(ctx.warehouseId)) return false;
  if (scope.projectIds && ctx.projectId && !scope.projectIds.includes(ctx.projectId)) return false;
  if (scope.costCenterIds && ctx.costCenterId && !scope.costCenterIds.includes(ctx.costCenterId)) return false;
  if (scope.docTypes && ctx.docType && !scope.docTypes.includes(ctx.docType)) return false;
  return true;
}

export class PermissionSet {
  private readonly grants: Grant[];
  constructor(grants: Grant[]) {
    this.grants = grants;
  }

  can(permission: Permission, ctx: AccessContext): boolean {
    // company isolation is mandatory and cannot be widened by a grant
    return this.grants.some(
      (g) => g.permission === permission && (g.scope?.companyId ?? ctx.companyId) === ctx.companyId && scopeAllows(g.scope, ctx),
    );
  }

  requirePermission(permission: Permission, ctx: AccessContext): void {
    if (!this.can(permission, ctx)) throw new PermissionDeniedError(permission, { ...ctx });
  }
}

/** Role templates — starting grants; custom roles compose the same catalog. */
export const ROLE_TEMPLATES: Record<string, Permission[]> = {
  Admin: ['*.configure' as Permission], // Admin resolves to all permissions at runtime (expanded from catalog)
  Accountant: [
    'accounting.view',
    'accounting.create',
    'accounting.post',
    'accounting.reverse',
    'reports.view',
    'reports.export',
    'view_financials',
    'close_period',
  ],
  Sales: ['sales.view', 'sales.create', 'sales.edit', 'sales.submit', 'customers.view', 'customers.create'],
  Purchases: ['purchasing.view', 'purchasing.create', 'purchasing.edit', 'purchasing.submit', 'suppliers.view'],
  Warehouse: ['inventory.view', 'inventory.create', 'inventory.edit'],
  Management: ['reports.view', 'reports.export', 'view_financials', 'sales.approve', 'purchasing.approve'],
};
