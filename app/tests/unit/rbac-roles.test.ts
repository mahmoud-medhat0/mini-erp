import { describe, it, expect } from 'vitest';
import { permissionsForRole } from '../../src/core/rbac/roles';
import { allPermissions } from '../../src/core/rbac/catalog';
import { buildSeedPlan } from '../../src/core/rbac/seed';
import { PermissionSet, Grant } from '../../src/core/rbac';

function setFor(role: Parameters<typeof permissionsForRole>[0], companyId = 'c1'): PermissionSet {
  const grants: Grant[] = permissionsForRole(role).map((permission) => ({ permission, scope: { companyId } }));
  return new PermissionSet(grants);
}

describe('RBAC — role templates (deny by default)', () => {
  it('SUPER_ADMIN has every catalog permission', () => {
    expect(permissionsForRole('SUPER_ADMIN').sort()).toEqual(allPermissions().sort());
  });

  it('VIEWER can only view dashboard/reports', () => {
    const v = setFor('VIEWER');
    expect(v.can('dashboard.view', { companyId: 'c1' })).toBe(true);
    expect(v.can('reports.view', { companyId: 'c1' })).toBe(true);
    expect(v.can('sales.create', { companyId: 'c1' })).toBe(false);
    expect(v.can('accounting.post', { companyId: 'c1' })).toBe(false);
  });

  it('SALES can create sales but NOT post journals or post invoices', () => {
    const s = setFor('SALES');
    expect(s.can('sales.create', { companyId: 'c1' })).toBe(true);
    expect(s.can('sales.post', { companyId: 'c1' })).toBe(false);
    expect(s.can('accounting.post', { companyId: 'c1' })).toBe(false);
  });

  it('ACCOUNTANT can post accounting and close period; cannot manage users', () => {
    const a = setFor('ACCOUNTANT');
    expect(a.can('accounting.post', { companyId: 'c1' })).toBe(true);
    expect(a.can('close_period', { companyId: 'c1' })).toBe(true);
    expect(a.can('users.configure', { companyId: 'c1' })).toBe(false);
  });

  it('AUDITOR is read-only with audit access', () => {
    const a = setFor('AUDITOR');
    expect(a.can('audit.view', { companyId: 'c1' })).toBe(true);
    expect(a.can('sales.create', { companyId: 'c1' })).toBe(false);
    expect(a.can('accounting.post', { companyId: 'c1' })).toBe(false);
  });

  it('company isolation holds for every role grant', () => {
    const a = setFor('ACCOUNTANT', 'c1');
    expect(a.can('accounting.post', { companyId: 'c2' })).toBe(false);
  });
});

describe('RBAC — seed plan', () => {
  it('produces all permissions and 9 role templates', () => {
    const plan = buildSeedPlan();
    expect(plan.permissions.length).toBe(allPermissions().length);
    expect(plan.roles.map((r) => r.name)).toEqual([
      'SUPER_ADMIN',
      'COMPANY_ADMIN',
      'ACCOUNTANT',
      'SALES',
      'PURCHASING',
      'INVENTORY',
      'HR',
      'AUDITOR',
      'VIEWER',
    ]);
    expect(plan.roles.every((r) => r.isTemplate)).toBe(true);
  });
});
