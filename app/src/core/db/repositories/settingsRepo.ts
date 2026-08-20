/**
 * Prisma-backed settings repository. Stores CompanySettings as JSON on the company
 * row and keeps baseCurrency mirrored for cheap queries.
 */
import { prisma } from '../prisma';
import type { SettingsRepository } from '../../../modules/company/application/settingsService';
import { CompanySettings, DEFAULT_SETTINGS } from '../../../modules/company/application/companyService';

export class PrismaSettingsRepository implements SettingsRepository {
  async getSettings(companyId: string): Promise<CompanySettings | null> {
    const c = await prisma.company.findUnique({ where: { id: companyId } });
    if (!c) return null;
    const json = (c.settingsJson as Partial<CompanySettings> | null) ?? {};
    return { ...DEFAULT_SETTINGS, ...json, baseCurrency: c.baseCurrency ?? DEFAULT_SETTINGS.baseCurrency };
  }

  async updateSettings(companyId: string, settings: CompanySettings): Promise<CompanySettings> {
    await prisma.company.update({
      where: { id: companyId },
      data: { settingsJson: settings, baseCurrency: settings.baseCurrency },
    });
    return settings;
  }
}
