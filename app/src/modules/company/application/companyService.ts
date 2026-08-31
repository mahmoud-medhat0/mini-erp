/**
 * Company & branch onboarding + settings (application service). Tenant context is
 * server-derived; company_id is never taken from the browser. Persists via a
 * repository interface (the only DB-touching layer). Settings are validated.
 */
import { ValidationError } from '../../../core/errors';
import { CURRENCIES } from '../../../core/currency';
import type { TenantContext } from '../../../core/tenant/context';

export interface CompanySettings {
  baseCurrency: string;
  locale: 'en' | 'ar';
  timezone: string;
  dateFormat: string; // e.g. "yyyy-MM-dd"
  numberFormat: string; // e.g. "1,234.56"
  fiscalYearStartMonth: number; // 1-12
}

export interface Company {
  id: string;
  nameEn: string;
  nameAr: string;
  settings: CompanySettings;
}
export interface Branch {
  id: string;
  companyId: string;
  code: string;
  nameEn: string;
  nameAr: string;
}

export interface FirstBranchInput {
  code: string;
  nameEn: string;
  nameAr: string;
}

export interface CompanyRepository {
  createCompany(input: {
    nameEn: string;
    nameAr: string;
    settings: CompanySettings;
    ownerUserId: string;
    firstBranch?: FirstBranchInput;
  }): Promise<Company>;
  getCompany(companyId: string): Promise<Company | null>;
  updateSettings(companyId: string, settings: CompanySettings): Promise<Company>;
  createBranch(companyId: string, input: { code: string; nameEn: string; nameAr: string }): Promise<Branch>;
  branchCodeExists(companyId: string, code: string): Promise<boolean>;
}

export const DEFAULT_SETTINGS: CompanySettings = {
  baseCurrency: 'EGP',
  locale: 'en',
  timezone: 'Africa/Cairo',
  dateFormat: 'yyyy-MM-dd',
  numberFormat: '1,234.56',
  fiscalYearStartMonth: 1,
};

export function validateSettings(s: CompanySettings): void {
  if (!CURRENCIES[s.baseCurrency]) throw new ValidationError(`Unknown base currency: ${s.baseCurrency}`);
  if (s.locale !== 'en' && s.locale !== 'ar') throw new ValidationError('locale must be en or ar');
  if (!s.timezone?.trim()) throw new ValidationError('timezone is required');
  if (!Number.isInteger(s.fiscalYearStartMonth) || s.fiscalYearStartMonth < 1 || s.fiscalYearStartMonth > 12)
    throw new ValidationError('fiscalYearStartMonth must be 1-12');
}

export function validateBranchInput(input: FirstBranchInput): void {
  if (!/^[A-Za-z0-9_-]{1,16}$/.test(input.code)) throw new ValidationError('Invalid branch code');
  if (!input.nameEn?.trim() || !input.nameAr?.trim()) throw new ValidationError('Branch name (EN and AR) is required');
}

export class CompanyService {
  constructor(private readonly repo: CompanyRepository) {}

  /** Create a company and atomically provision owner membership, roles, and optional first branch. */
  async createCompany(input: {
    nameEn: string;
    nameAr: string;
    ownerUserId: string;
    firstBranch?: FirstBranchInput;
    settings?: Partial<CompanySettings>;
  }): Promise<Company> {
    if (!input.nameEn?.trim() || !input.nameAr?.trim())
      throw new ValidationError('Company name (EN and AR) is required');
    if (!input.ownerUserId?.trim()) throw new ValidationError('Owner user is required');
    if (input.firstBranch) validateBranchInput(input.firstBranch);
    const settings = { ...DEFAULT_SETTINGS, ...input.settings };
    validateSettings(settings);
    return this.repo.createCompany({
      nameEn: input.nameEn,
      nameAr: input.nameAr,
      settings,
      ownerUserId: input.ownerUserId,
      firstBranch: input.firstBranch,
    });
  }

  async updateSettings(ctx: TenantContext, settings: CompanySettings): Promise<Company> {
    validateSettings(settings);
    return this.repo.updateSettings(ctx.companyId, settings);
  }

  async createBranch(
    ctx: TenantContext,
    input: { code: string; nameEn: string; nameAr: string },
  ): Promise<Branch> {
    validateBranchInput(input);
    if (await this.repo.branchCodeExists(ctx.companyId, input.code))
      throw new ValidationError(`Branch code already exists: ${input.code}`);
    return this.repo.createBranch(ctx.companyId, input);
  }
}
