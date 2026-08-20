import { describe, it, expect } from 'vitest';
import { PermissionSet } from '../../src/core/rbac';
import { PermissionDeniedError } from '../../src/core/errors';

describe('RBAC — server-side authorization + scope + tenant isolation', () => {
  const sales = new PermissionSet([
    { permission: 'sales.create', scope: { companyId: 'c1', branchIds: ['b1'] } },
    { permission: 'sales.view', scope: { companyId: 'c1' } },
  ]);

  it('grants an allowed action in-scope', () => {
    expect(sales.can('sales.create', { companyId: 'c1', branchId: 'b1' })).toBe(true);
  });

  it('denies action outside branch scope', () => {
    expect(sales.can('sales.create', { companyId: 'c1', branchId: 'b2' })).toBe(false);
  });

  it('enforces company isolation (never trusts another company)', () => {
    expect(sales.can('sales.view', { companyId: 'c2' })).toBe(false);
  });

  it('denies an action the role does not have', () => {
    expect(sales.can('sales.post', { companyId: 'c1', branchId: 'b1' })).toBe(false);
  });

  it('requirePermission throws PermissionDeniedError', () => {
    expect(() => sales.requirePermission('sales.post', { companyId: 'c1' })).toThrow(PermissionDeniedError);
  });
});
