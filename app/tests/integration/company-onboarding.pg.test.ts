/**
 * DB-backed onboarding provisioning test. Skipped without DATABASE_URL.
 * Verifies company creation seeds per-company role templates + permission links
 * and gives the owner COMPANY_ADMIN membership in one repository call.
 */
import { describe, it, expect, beforeAll, afterAll } from 'vitest';

const HAS_DB = !!process.env.DATABASE_URL;
const d = HAS_DB ? describe : describe.skip;

d('Company onboarding repository (Postgres)', () => {
  let prisma: typeof import('../../src/core/db/prisma').prisma;
  let repo: import('../../src/core/db/repositories/companyRepo').PrismaCompanyRepository;

  beforeAll(async () => {
    prisma = (await import('../../src/core/db/prisma')).prisma;
    const mod = await import('../../src/core/db/repositories/companyRepo');
    repo = new mod.PrismaCompanyRepository();
  });

  afterAll(async () => {
    if (HAS_DB) await prisma.$disconnect();
  });

  it('seeds roles, role permissions, owner membership, and first branch', async () => {
    const user = await prisma.user.create({
      data: {
        email: `owner-${Date.now()}@example.test`,
        name: 'Owner',
        passwordHash: 'not-used-in-this-test',
      },
    });

    const company = await repo.createCompany({
      nameEn: 'Acme',
      nameAr: 'أكمي',
      ownerUserId: user.id,
      settings: {
        baseCurrency: 'EGP',
        locale: 'en',
        timezone: 'Africa/Cairo',
        dateFormat: 'yyyy-MM-dd',
        numberFormat: '1,234.56',
        fiscalYearStartMonth: 1,
      },
      firstBranch: { code: 'HQ', nameEn: 'Head Office', nameAr: 'المركز الرئيسي' },
    });

    const [roles, permissionLinks, membership, adminAssignment, branch] = await Promise.all([
      prisma.role.findMany({ where: { companyId: company.id } }),
      prisma.rolePermission.count({ where: { role: { companyId: company.id } } }),
      prisma.userCompany.findUnique({ where: { userId_companyId: { userId: user.id, companyId: company.id } } }),
      prisma.userRole.findFirst({ where: { userId: user.id, role: { companyId: company.id, name: 'COMPANY_ADMIN' } } }),
      prisma.branch.findUnique({ where: { companyId_code: { companyId: company.id, code: 'HQ' } } }),
    ]);

    expect(roles).toHaveLength(9);
    expect(permissionLinks).toBeGreaterThan(0);
    expect(membership).not.toBeNull();
    expect(adminAssignment).not.toBeNull();
    expect(branch?.nameEn).toBe('Head Office');
  });
});
