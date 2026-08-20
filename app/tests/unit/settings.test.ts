import { describe, it, expect } from 'vitest';
import { SettingsService, SettingsRepository } from '../../src/modules/company/application/settingsService';
import { CompanySettings, DEFAULT_SETTINGS } from '../../src/modules/company/application/companyService';
import { ValidationError } from '../../src/core/errors';

class MemSettings implements SettingsRepository {
  store = new Map<string, CompanySettings>();
  async getSettings(companyId: string) {
    return this.store.get(companyId) ?? null;
  }
  async updateSettings(companyId: string, settings: CompanySettings) {
    this.store.set(companyId, settings);
    return settings;
  }
}

const ctx = { userId: 'u1', companyId: 'c1' };

describe('SettingsService', () => {
  it('returns defaults when none saved', async () => {
    const svc = new SettingsService(new MemSettings());
    expect(await svc.get(ctx)).toEqual(DEFAULT_SETTINGS);
  });

  it('validates and persists updated settings', async () => {
    const repo = new MemSettings();
    const svc = new SettingsService(repo);
    const next = { ...DEFAULT_SETTINGS, locale: 'ar' as const, baseCurrency: 'USD' };
    await svc.update(ctx, next);
    expect((await svc.get(ctx)).baseCurrency).toBe('USD');
    expect((await svc.get(ctx)).locale).toBe('ar');
  });

  it('rejects invalid settings', async () => {
    const svc = new SettingsService(new MemSettings());
    await expect(svc.update(ctx, { ...DEFAULT_SETTINGS, fiscalYearStartMonth: 0 })).rejects.toBeInstanceOf(ValidationError);
    await expect(svc.update(ctx, { ...DEFAULT_SETTINGS, baseCurrency: 'ZZZ' })).rejects.toBeInstanceOf(ValidationError);
  });
});
