/**
 * Permission catalog — the full set of module.action permissions plus sensitive
 * capability flags. Generated from a single source so seeds, UI, and checks stay
 * in sync. Deny-by-default: a role has only what it is explicitly granted.
 */
import type { Action, Permission, SensitiveCapability } from './index';

/** Modules and the actions that are meaningful for each. */
export const MODULE_ACTIONS: Readonly<Record<string, readonly Action[]>> = {
  dashboard: ['view'],
  accounting: ['view', 'create', 'edit', 'submit', 'approve', 'post', 'reverse', 'export', 'print'],
  sales: ['view', 'create', 'edit', 'delete', 'submit', 'approve', 'post', 'cancel', 'reverse', 'export', 'print'],
  purchasing: ['view', 'create', 'edit', 'delete', 'submit', 'approve', 'post', 'cancel', 'reverse', 'export', 'print'],
  inventory: ['view', 'create', 'edit', 'delete', 'approve', 'post', 'export', 'print'],
  equipment: ['view', 'create', 'edit', 'delete', 'export'],
  rentals: ['view', 'create', 'edit', 'submit', 'approve', 'post', 'cancel', 'export', 'print'],
  customers: ['view', 'create', 'edit', 'delete', 'export'],
  suppliers: ['view', 'create', 'edit', 'delete', 'export'],
  cash: ['view', 'create', 'edit', 'post', 'reverse', 'export', 'print'],
  banks: ['view', 'create', 'edit', 'post', 'reverse', 'export', 'print'],
  cheques: ['view', 'create', 'edit', 'post', 'reverse', 'export'],
  expenses: ['view', 'create', 'edit', 'submit', 'approve', 'post', 'export', 'print'],
  fixedAssets: ['view', 'create', 'edit', 'post', 'reverse', 'export'],
  payroll: ['view', 'create', 'edit', 'submit', 'approve', 'post', 'export', 'print'],
  taxes: ['view', 'edit', 'export', 'print'],
  partners: ['view', 'create', 'edit', 'post', 'export'],
  projects: ['view', 'create', 'edit', 'export'],
  costCenters: ['view', 'create', 'edit', 'export'],
  budgeting: ['view', 'create', 'edit', 'approve', 'export'],
  recurring: ['view', 'create', 'edit', 'export'],
  reports: ['view', 'export', 'print'],
  audit: ['view', 'export'],
  settings: ['view', 'configure'],
  users: ['view', 'create', 'edit', 'delete', 'configure'],
} as const;

export const SENSITIVE_CAPABILITIES: readonly SensitiveCapability[] = [
  'view_financials',
  'view_payroll',
  'override_credit_limit',
  'override_negative_stock',
  'close_period',
  'reopen_period',
];

/** All module.action permissions as flat strings. */
export function allModulePermissions(): Permission[] {
  const out: Permission[] = [];
  for (const [mod, actions] of Object.entries(MODULE_ACTIONS)) {
    for (const a of actions) out.push(`${mod}.${a}` as Permission);
  }
  return out;
}

/** Every permission in the catalog (module.action + sensitive capabilities). */
export function allPermissions(): Permission[] {
  return [...allModulePermissions(), ...SENSITIVE_CAPABILITIES];
}

export function isKnownPermission(p: string): boolean {
  return allPermissions().includes(p as Permission);
}
