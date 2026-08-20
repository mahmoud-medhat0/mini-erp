/**
 * Numbering configuration + allocation application service. Wraps the tested,
 * concurrency-safe engine. UI never generates numbers — it calls `allocate`,
 * which runs the atomic repository counter and formats via the engine.
 */
import { ValidationError } from '../errors';
import {
  NumberingService,
  formatDocNumber,
  SequenceConfig,
  NumberContext,
  ResetPolicy,
} from './index';
import type { TenantContext } from '../tenant/context';

export interface NumberSequenceRepository {
  getConfig(companyId: string, docType: string): Promise<SequenceConfig | null>;
  upsertConfig(companyId: string, cfg: SequenceConfig): Promise<void>;
  listConfigs(companyId: string): Promise<SequenceConfig[]>;
  /** Atomic increment for a bucket key (INSERT ... ON CONFLICT DO UPDATE RETURNING). */
  nextValue(companyId: string, key: string): Promise<number>;
}

const RESET_POLICIES: ResetPolicy[] = ['never', 'yearly', 'monthly'];

export function validateConfig(cfg: SequenceConfig): void {
  if (!cfg.docType?.trim()) throw new ValidationError('docType is required');
  if (!cfg.prefix?.trim()) throw new ValidationError('prefix is required');
  if (!Number.isInteger(cfg.padding) || cfg.padding < 1 || cfg.padding > 12)
    throw new ValidationError('padding must be an integer between 1 and 12');
  if (!RESET_POLICIES.includes(cfg.resetPolicy)) throw new ValidationError('invalid resetPolicy');
}

export class NumberingConfigService {
  constructor(private readonly repo: NumberSequenceRepository) {}

  async saveConfig(ctx: TenantContext, cfg: SequenceConfig): Promise<void> {
    validateConfig(cfg);
    await this.repo.upsertConfig(ctx.companyId, cfg);
  }

  async listConfigs(ctx: TenantContext): Promise<SequenceConfig[]> {
    return this.repo.listConfigs(ctx.companyId);
  }

  /** Preview the NEXT number without consuming it (for UI display only). */
  async preview(ctx: TenantContext, docType: string, numCtx: NumberContext): Promise<string> {
    const cfg = await this.requireConfig(ctx.companyId, docType);
    return formatDocNumber(cfg, numCtx, 1);
  }

  /** Allocate the next real number — atomic + concurrency-safe. */
  async allocate(ctx: TenantContext, docType: string, numCtx: NumberContext): Promise<string> {
    const cfg = await this.requireConfig(ctx.companyId, docType);
    const engine = new NumberingService({
      nextValue: (key: string) => this.repo.nextValue(ctx.companyId, key),
    });
    return engine.allocate(cfg, numCtx);
  }

  private async requireConfig(companyId: string, docType: string): Promise<SequenceConfig> {
    const cfg = await this.repo.getConfig(companyId, docType);
    if (!cfg) throw new ValidationError(`No numbering configuration for docType ${docType}`);
    return cfg;
  }
}
