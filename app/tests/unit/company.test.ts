import { describe, it, expect } from 'vitest';
import {
  CompanyService,
  CompanyRepository,
  Company,
  Branch,
  CompanySettings,
  validateSettings,
  DEFAULT_SETTINGS,
} from '../../src/modules/company/application/companyService';
import { ValidationError } from '../../src/core/errors';

class MemCompanyRepo implements CompanyRepository {
  companies: Company[] = [];
  branches: Branch[] = [];
  memberships: { companyId: string; userId: string }[] = [];
  roles: { companyId: string; userId: string; roleName: string }[] = [];
  private seq = 0;

  async createCompany(input: { nameEn: string; nameAr: string; settings: CompanySettings }) {
    const c: Company = { id: `co${++this.seq}`, nameEn: input.nameEn, nameAr: input.nameAr, settings: input.settings };
    this.companies.push(c);
    return c;
  }
  async getCompany(id: string) {
    return this.companies.find((c) => c.id === id) ?? null;
  }
  async updateSettings(companyId: string, settings: CompanySettings) {
    const c = this.companies.find((x) => x.id === companyId)!;
    c.settings = settings;
    return c;
  }
  async createBranch(companyId: string, input: { code: string; nameEn: string; nameAr: string }) {
    const b: Branch = { id: `b${++this.seq}`, companyId, ...input };
    this.branches.push(b);
    return b;
  }
  async branchCodeExists(companyId: string, code: string) {
    return this.branches.some((b) => b.companyId === companyId && b.code === code);
  }
  async addMembership(companyId: string, userId: string) {
    this.memberships.push({ companyId, userId });
  }
  async assignRole(companyId: string, userId: string, roleName: string) {
    this.roles.push({ companyId, userId, roleName });
  }
}

describe('Company onboarding', () => {
  it('creates a company with validated settings + owner admin membership/role', async () => {
    const repo = new MemCompanyRepo();
    const svc = new CompanyService(repo);
    const c = await svc.createCompany({ nameEn: 'Acme', nameAr: 'أكمي', ownerUserId: 'u1' });
    expect(c.settings.baseCurrency).toBe('EGP');
    expect(repo.memberships).toEqual([{ companyId: c.id, userId: 'u1' }]);
    expect(repo.roles[0]).toMatchObject({ companyId: c.id, userId: 'u1', roleName: 'COMPANY_ADMIN' });
  });

  it('requires EN + AR names', async () => {
    const svc = new CompanyService(new MemCompanyRepo());
    await expect(svc.createCompany({ nameEn: 'Acme', nameAr: '', ownerUserId: 'u1' })).rejects.toBeInstanceOf(ValidationError);
  });

  it('rejects invalid settings (bad currency, month)', () => {
    expect(() => validateSettings({ ...DEFAULT_SETTINGS, baseCurrency: 'ZZZ' })).toThrow(ValidationError);
    expect(() => validateSettings({ ...DEFAULT_SETTINGS, fiscalYearStartMonth: 13 })).toThrow(ValidationError);
    expect(() => validateSettings(DEFAULT_SETTINGS)).not.toThrow();
  });

  it('creates branches with unique codes per company', async () => {
    const repo = new MemCompanyRepo();
    const svc = new CompanyService(repo);
    const c = await svc.createCompany({ nameEn: 'Acme', nameAr: 'أكمي', ownerUserId: 'u1' });
    const ctx = { userId: 'u1', companyId: c.id };
    await svc.createBranch(ctx, { code: 'CAI', nameEn: 'Cairo', nameAr: 'القاهرة' });
    await expect(svc.createBranch(ctx, { code: 'CAI', nameEn: 'Dup', nameAr: 'مكرر' })).rejects.toBeInstanceOf(ValidationError);
    await expect(svc.createBranch(ctx, { code: 'bad code!', nameEn: 'x', nameAr: 'y' })).rejects.toBeInstanceOf(ValidationError);
  });

  it('persists updated settings', async () => {
    const repo = new MemCompanyRepo();
    const svc = new CompanyService(repo);
    const c = await svc.createCompany({ nameEn: 'Acme', nameAr: 'أكمي', ownerUserId: 'u1' });
    const updated = await svc.updateSettings({ userId: 'u1', companyId: c.id }, { ...c.settings, locale: 'ar', baseCurrency: 'USD' });
    expect(updated.settings.locale).toBe('ar');
    expect((await repo.getCompany(c.id))!.settings.baseCurrency).toBe('USD');
  });
});
