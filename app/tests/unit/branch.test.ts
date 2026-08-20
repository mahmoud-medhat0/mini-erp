import { describe, it, expect } from 'vitest';
import { BranchService, BranchRepository, Branch } from '../../src/modules/company/application/branchService';
import { ValidationError } from '../../src/core/errors';

class MemBranchRepo implements BranchRepository {
  rows: Branch[] = [];
  async list(companyId: string) {
    return this.rows.filter((b) => b.companyId === companyId);
  }
  async codeExists(companyId: string, code: string) {
    return this.rows.some((b) => b.companyId === companyId && b.code === code);
  }
  async create(companyId: string, input: { code: string; nameEn: string; nameAr: string }) {
    const b: Branch = { id: `b${this.rows.length + 1}`, companyId, isActive: true, ...input };
    this.rows.push(b);
    return b;
  }
}

const ctx = { userId: 'u1', companyId: 'c1' };

describe('BranchService', () => {
  it('creates and lists branches scoped to the company', async () => {
    const repo = new MemBranchRepo();
    const svc = new BranchService(repo);
    await svc.create(ctx, { code: 'CAI', nameEn: 'Cairo', nameAr: 'القاهرة' });
    await svc.create({ userId: 'u2', companyId: 'c2' }, { code: 'CAI', nameEn: 'Other', nameAr: 'أخرى' });
    expect((await svc.list(ctx)).map((b) => b.code)).toEqual(['CAI']);
  });

  it('rejects duplicate code within a company and invalid codes/names', async () => {
    const svc = new BranchService(new MemBranchRepo());
    await svc.create(ctx, { code: 'CAI', nameEn: 'Cairo', nameAr: 'القاهرة' });
    await expect(svc.create(ctx, { code: 'CAI', nameEn: 'Dup', nameAr: 'مكرر' })).rejects.toBeInstanceOf(ValidationError);
    await expect(svc.create(ctx, { code: 'has space', nameEn: 'x', nameAr: 'ص' })).rejects.toBeInstanceOf(ValidationError);
    await expect(svc.create(ctx, { code: 'GIZ', nameEn: 'Giza', nameAr: '' })).rejects.toBeInstanceOf(ValidationError);
  });
});
