/**
 * Attachment storage abstraction. Business modules depend on AttachmentStorage
 * (never on fs/S3 directly). Metadata is persisted in Postgres via the repository;
 * uploads are validated (mime/size/name) and scoped to the company.
 */
import { ValidationError } from '../errors';
import type { TenantContext } from '../tenant/context';

export interface PutInput {
  companyId: string;
  bytes: Buffer | Uint8Array;
  mime: string;
  name: string;
}

export interface AttachmentStorage {
  put(input: PutInput): Promise<{ key: string }>;
  get(key: string): Promise<Buffer>;
  delete(key: string): Promise<void>;
}

export interface AttachmentMeta {
  id: string;
  companyId: string;
  entityType: string;
  entityId: string;
  key: string;
  name: string;
  mime: string;
  size: number;
  uploadedBy: string;
}

export interface AttachmentRepository {
  saveMeta(meta: Omit<AttachmentMeta, 'id'>): Promise<AttachmentMeta>;
  getMeta(companyId: string, id: string): Promise<AttachmentMeta | null>;
}

export const ALLOWED_MIME = [
  'application/pdf',
  'image/png',
  'image/jpeg',
  'image/webp',
  'text/csv',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
export const MAX_SIZE_BYTES = 15 * 1024 * 1024; // 15 MB
const SAFE_NAME = /^[\w \-.()]{1,180}$/;

export function validateUpload(input: { name: string; mime: string; size: number }): void {
  if (!SAFE_NAME.test(input.name)) throw new ValidationError('Invalid file name');
  if (!ALLOWED_MIME.includes(input.mime)) throw new ValidationError(`Unsupported file type: ${input.mime}`);
  if (input.size <= 0 || input.size > MAX_SIZE_BYTES) throw new ValidationError('File size out of range');
}

export class AttachmentService {
  constructor(
    private readonly storage: AttachmentStorage,
    private readonly repo: AttachmentRepository,
  ) {}

  async upload(
    ctx: TenantContext,
    input: { entityType: string; entityId: string; name: string; mime: string; bytes: Buffer },
  ): Promise<AttachmentMeta> {
    validateUpload({ name: input.name, mime: input.mime, size: input.bytes.length });
    const { key } = await this.storage.put({
      companyId: ctx.companyId,
      bytes: input.bytes,
      mime: input.mime,
      name: input.name,
    });
    return this.repo.saveMeta({
      companyId: ctx.companyId,
      entityType: input.entityType,
      entityId: input.entityId,
      key,
      name: input.name,
      mime: input.mime,
      size: input.bytes.length,
      uploadedBy: ctx.userId,
    });
  }

  /** Company-scoped read — cannot fetch another company's attachment. */
  async download(ctx: TenantContext, id: string): Promise<{ meta: AttachmentMeta; bytes: Buffer }> {
    const meta = await this.repo.getMeta(ctx.companyId, id);
    if (!meta) throw new ValidationError('Attachment not found');
    const bytes = await this.storage.get(meta.key);
    return { meta, bytes };
  }
}
