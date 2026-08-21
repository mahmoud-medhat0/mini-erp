import { expect, type Page, test } from '@playwright/test';

const HAS_DB = !!process.env.DATABASE_URL;
const PASSWORD = 'Correct-Horse-2026!';

let adminEmail = '';
let viewerEmail = '';

test.beforeAll(async () => {
  if (!HAS_DB) return;
  const [{ prisma }, { PrismaCompanyRepository }, { Argon2PasswordHasher }] = await Promise.all([
    import('../../src/core/db/prisma'),
    import('../../src/core/db/repositories/companyRepo'),
    import('../../src/core/auth/password.argon2'),
  ]);

  const hasher = new Argon2PasswordHasher();
  const passwordHash = await hasher.hash(PASSWORD);
  const suffix = `${Date.now()}-${Math.round(Math.random() * 1_000_000)}`;
  adminEmail = `e2e-admin-${suffix}@example.test`;
  viewerEmail = `e2e-viewer-${suffix}@example.test`;

  const [admin, viewer] = await Promise.all([
    prisma.user.create({ data: { email: adminEmail, name: 'E2E Admin', passwordHash } }),
    prisma.user.create({ data: { email: viewerEmail, name: 'E2E Viewer', passwordHash } }),
  ]);

  const company = await new PrismaCompanyRepository().createCompany({
    nameEn: 'E2E Company',
    nameAr: 'شركة الاختبار',
    ownerUserId: admin.id,
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

  const viewerRole = await prisma.role.findUniqueOrThrow({
    where: { companyId_name: { companyId: company.id, name: 'VIEWER' } },
  });
  await prisma.userCompany.create({ data: { userId: viewer.id, companyId: company.id } });
  await prisma.userRole.create({
    data: { userId: viewer.id, roleId: viewerRole.id, scopeJson: { companyId: company.id } },
  });
});

async function signIn(page: Page, email: string) {
  await page.goto('/en/login');
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password').fill(PASSWORD);
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page).toHaveURL(/\/en\/dashboard$/);
}

test('public locale pages render with correct direction', async ({ page }) => {
  await page.goto('/en');
  await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
  await expect(page.getByRole('heading', { name: 'Mini ERP' })).toBeVisible();

  await page.goto('/ar');
  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
  await expect(page.getByRole('heading', { name: 'نظام إدارة الشركات' })).toBeVisible();
});

test('unauthenticated app route redirects to login', async ({ page }) => {
  await page.goto('/en/dashboard');
  await expect(page).toHaveURL(/\/en\/login/);
});

test('invalid login shows generic error', async ({ page }) => {
  test.skip(!HAS_DB, 'DATABASE_URL is required for credentials auth');
  await page.goto('/en/login');
  await page.getByLabel('Email').fill('missing@example.test');
  await page.getByLabel('Password').fill('bad-password');
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page).toHaveURL(/\/en\/login\?error=1$/);
  await expect(page.locator('form').getByRole('alert')).toContainText('Invalid email or password');
});

test('valid admin login reaches dashboard and settings', async ({ page }) => {
  test.skip(!HAS_DB, 'DATABASE_URL is required for credentials auth');
  await signIn(page, adminEmail);

  await page.goto('/en/dashboard');
  await expect(page).toHaveURL(/\/en\/dashboard$/);
  await expect(page.getByText('KPIs will populate from posted accounting data')).toBeVisible();
  await page.goto('/en/settings/users');
  await expect(page.getByRole('heading', { name: 'Users & Roles' })).toBeVisible();
});

test('viewer sees server-side permission denied for user administration', async ({ page }) => {
  test.skip(!HAS_DB, 'DATABASE_URL is required for credentials auth');
  await signIn(page, viewerEmail);

  await page.goto('/en/settings/users');
  await expect(page.getByText("You don't have permission to view this")).toBeVisible();
});
