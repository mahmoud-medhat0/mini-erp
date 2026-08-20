/**
 * Seed: permission catalog + role templates. Idempotent (upserts). Applies the
 * pure seed plan from core/rbac/seed inside a transaction. Run: `tsx prisma/seed.ts`.
 */
import { PrismaClient } from '@prisma/client';
import { buildSeedPlan } from '../src/core/rbac/seed';
import { CURRENCIES } from '../src/core/currency';

const prisma = new PrismaClient();

async function main() {
  const plan = buildSeedPlan();

  // Currencies
  for (const c of Object.values(CURRENCIES)) {
    await prisma.currency.upsert({
      where: { code: c.code },
      update: { nameEn: c.name_en, nameAr: c.name_ar, symbol: c.symbol, exponent: c.exponent },
      create: { code: c.code, nameEn: c.name_en, nameAr: c.name_ar, symbol: c.symbol, exponent: c.exponent },
    });
  }

  // Permissions
  for (const p of plan.permissions) {
    await prisma.permission.upsert({
      where: { module_action: { module: p.module, action: p.action } },
      update: {},
      create: { module: p.module, action: p.action },
    });
  }

  console.warn(
    `Seed complete: ${Object.keys(CURRENCIES).length} currencies, ${plan.permissions.length} permissions, ` +
      `${plan.roles.length} role templates available for company provisioning.`,
  );
  // Role templates are instantiated per-company at onboarding (companyId-scoped),
  // using plan.roles as the template source — see CompanyService.createCompany.
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(() => prisma.$disconnect());
