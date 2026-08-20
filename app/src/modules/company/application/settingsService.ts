/**
 * Company settings service — validates and persists company configuration
 * (currency, locale, timezone, formats, fiscal start). Reuses the same
 * validation as onboarding. Persists via a repository interface.
 */
import { CompanySettings, DEFAULT_SETTINGS, validateSettings } from './companyService';
import type { TenantContext } from '../../../core/tenant/context';

export interface SettingsRepository {
  getSettings(companyId: string): Promise<CompanySettings | null>;
  updateSettings(companyId: string, settings: CompanySettings): Promise<CompanySettings>;
}

export class SettingsService {
  constructor(private readonly repo: SettingsRepository) {}

  async get(ctx: TenantContext): Promise<CompanySettings> {
    return (await this.repo.getSettings(ctx.companyId)) ?? DEFAULT_SETTINGS;
  }

  async update(ctx: TenantContext, settings: CompanySettings): Promise<CompanySettings> {
    validateSettings(settings);
    return this.repo.updateSettings(ctx.companyId, settings);
  }
}
