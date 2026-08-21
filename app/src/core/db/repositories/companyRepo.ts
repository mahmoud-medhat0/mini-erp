/**
 * Prisma-backed company onboarding repository. Company provisioning is one DB
 * transaction: company + optional first branch + permission catalog + 9 role
 * templates + owner membership + COMPANY_ADMIN assignment.
 */
import type { Prisma } from '@prisma/client';
import { prisma } from '../prisma';
import type { CompanyRepository, Company, Branch, CompanySettings, FirstBranchInput } from '../../../modules/company/application/companyService';
import { DEFAULT_SETTINGS } from '../../../modules/company/application/companyService';
import { buildSeedPlan } from '../../rbac/seed';

export class PrismaCompanyRepository implements CompanyRepository {
  async createCompany(input: {
    nameEn: string;
    nameAr: string;
    settings: CompanySettings;
    ownerUserId: string;
    firstBranch?: FirstBranchInput;
  }): Promise<Company> {
    return prisma.$transaction(async (tx) => {
      const company = await tx.company.create({
        data: {
          nameEn: input.nameEn,
          nameAr: input.nameAr,
          baseCurrency: input.settings.baseCurrency,
          settingsJson: input.settings as unknown as Prisma.InputJsonValue,
        },
      });

      if (input.firstBranch) {
        await tx.branch.create({
          data: {
            companyId: company.id,
            code: input.firstBranch.code,
            nameEn: input.firstBranch.nameEn,
            nameAr: input.firstBranch.nameAr,
          },
        });
      }

      const plan = buildSeedPlan();
      const permissionIds = new Map<string, string>();
      for (const permission of plan.permissions) {
        const row = await tx.permission.upsert({
          where: { module_action: { module: permission.module, action: permission.action } },
          update: {},
          create: permission,
        });
        permissionIds.set(`${permission.module}.${permission.action}`, row.id);
      }

      for (const roleDef of plan.roles) {
        const role = await tx.role.upsert({
          where: { companyId_name: { companyId: company.id, name: roleDef.name } },
          update: { isTemplate: true },
          create: { companyId: company.id, name: roleDef.name, isTemplate: true },
        });

        const rolePermissions = roleDef.permissions.map((permission) => {
          const key = permission.includes('.') ? permission : `_capability.${permission}`;
          const permissionId = permissionIds.get(key);
          if (!permissionId) throw new Error(`Missing seeded permission: ${permission}`);
          return { roleId: role.id, permissionId };
        });

        await tx.rolePermission.createMany({
          data: rolePermissions,
          skipDuplicates: true,
        });
      }

      await tx.userCompany.upsert({
        where: { userId_companyId: { userId: input.ownerUserId, companyId: company.id } },
        update: {},
        create: { userId: input.ownerUserId, companyId: company.id },
      });

      const adminRole = await tx.role.findUniqueOrThrow({
        where: { companyId_name: { companyId: company.id, name: 'COMPANY_ADMIN' } },
      });
      await tx.userRole.upsert({
        where: { userId_roleId: { userId: input.ownerUserId, roleId: adminRole.id } },
        update: { scopeJson: { companyId: company.id } },
        create: { userId: input.ownerUserId, roleId: adminRole.id, scopeJson: { companyId: company.id } },
      });

      return toCompany(company);
    });
  }

  async getCompany(companyId: string): Promise<Company | null> {
    const company = await prisma.company.findUnique({ where: { id: companyId } });
    return company ? toCompany(company) : null;
  }

  async updateSettings(companyId: string, settings: CompanySettings): Promise<Company> {
    const company = await prisma.company.update({
      where: { id: companyId },
      data: {
        baseCurrency: settings.baseCurrency,
        settingsJson: settings as unknown as Prisma.InputJsonValue,
      },
    });
    return toCompany(company);
  }

  async createBranch(companyId: string, input: { code: string; nameEn: string; nameAr: string }): Promise<Branch> {
    return prisma.branch.create({ data: { companyId, ...input } });
  }

  async branchCodeExists(companyId: string, code: string): Promise<boolean> {
    return !!(await prisma.branch.findUnique({ where: { companyId_code: { companyId, code } } }));
  }
}

function toCompany(row: {
  id: string;
  nameEn: string;
  nameAr: string;
  baseCurrency: string;
  settingsJson: Prisma.JsonValue | null;
}): Company {
  const json = (row.settingsJson as Partial<CompanySettings> | null) ?? {};
  return {
    id: row.id,
    nameEn: row.nameEn,
    nameAr: row.nameAr,
    settings: { ...DEFAULT_SETTINGS, ...json, baseCurrency: row.baseCurrency },
  };
}
