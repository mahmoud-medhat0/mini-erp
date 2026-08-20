/**
 * Role templates — sensible ERP defaults, deny-by-default, not over-permissioned.
 * SUPER_ADMIN is the only role with everything (expanded from the catalog).
 */
import type { Permission } from './index';
import { allPermissions, MODULE_ACTIONS } from './catalog';

export type RoleName =
  | 'SUPER_ADMIN'
  | 'COMPANY_ADMIN'
  | 'ACCOUNTANT'
  | 'SALES'
  | 'PURCHASING'
  | 'INVENTORY'
  | 'HR'
  | 'AUDITOR'
  | 'VIEWER';

function viewAll(): Permission[] {
  return Object.keys(MODULE_ACTIONS).map((m) => `${m}.view` as Permission);
}
function moduleAll(mod: string): Permission[] {
  return (MODULE_ACTIONS[mod] ?? []).map((a) => `${mod}.${a}` as Permission);
}

const COMPANY_ADMIN: Permission[] = [
  ...allPermissions().filter(
    // company admin can do everything EXCEPT super-admin-only cross-company/user provisioning nuances are handled by scope
    (p) => p !== 'reopen_period', // reopening a closed period stays with accounting+explicit grant
  ),
];

const ACCOUNTANT: Permission[] = [
  ...viewAll(),
  ...moduleAll('accounting'),
  ...moduleAll('cash'),
  ...moduleAll('banks'),
  ...moduleAll('cheques'),
  ...moduleAll('taxes'),
  ...moduleAll('partners'),
  ...moduleAll('fixedAssets'),
  'expenses.post',
  'sales.post',
  'purchasing.post',
  'reports.export',
  'reports.print',
  'view_financials',
  'close_period',
];

const SALES: Permission[] = [
  'dashboard.view',
  ...moduleAll('sales').filter((p) => !p.endsWith('.post') && !p.endsWith('.reverse')),
  'customers.view',
  'customers.create',
  'customers.edit',
  'reports.view',
  'reports.export',
];

const PURCHASING: Permission[] = [
  'dashboard.view',
  ...moduleAll('purchasing').filter((p) => !p.endsWith('.post') && !p.endsWith('.reverse')),
  'suppliers.view',
  'suppliers.create',
  'suppliers.edit',
  'inventory.view',
  'reports.view',
  'reports.export',
];

const INVENTORY: Permission[] = [
  'dashboard.view',
  ...moduleAll('inventory'),
  ...moduleAll('equipment'),
  'reports.view',
  'reports.export',
  'override_negative_stock', // granted but still audited when used
];

const HR: Permission[] = [
  'dashboard.view',
  ...moduleAll('payroll'),
  'reports.view',
  'reports.export',
  'view_payroll',
];

const AUDITOR: Permission[] = [...viewAll(), 'audit.view', 'audit.export', 'reports.view', 'reports.export', 'view_financials'];

const VIEWER: Permission[] = ['dashboard.view', 'reports.view'];

export const ROLE_DEFS: Record<RoleName, Permission[]> = {
  SUPER_ADMIN: allPermissions(),
  COMPANY_ADMIN,
  ACCOUNTANT,
  SALES,
  PURCHASING,
  INVENTORY,
  HR,
  AUDITOR,
  VIEWER,
};

export const ROLE_NAMES = Object.keys(ROLE_DEFS) as RoleName[];

/** Deduplicated permissions for a role. */
export function permissionsForRole(role: RoleName): Permission[] {
  return Array.from(new Set(ROLE_DEFS[role]));
}
