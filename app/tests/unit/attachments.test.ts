import { describe, it, expect } from 'vitest';
import {
  AttachmentService,
  AttachmentStorage,
  AttachmentRepository,
  AttachmentMeta,
  validateUpload,
} from '../../src/core/attachments/storage';
import { ValidationError } from '../../src/core/errors';

const ctx = { userId: 'u1', companyId: 'c1' };

class MemStorage implements AttachmentStorage {
  blobs = new Map<string, Buffer>();
  async put({ bytes, companyId }: { bytes: Buffer | Uint8Array; companyId: string }) {
    const key = `${companyId}/${this.blobs.size + 1}`;
    this.blobs.set(key, Buffer.from(bytes));
    return { key };
  }
  async get(key: string) {
    return this.blobs.get(key)!;
  }
  async delete(key: string) {
    this.blobs.delete(key);
  }
}
class MemAttachRepo implements AttachmentRepository {
  rows: AttachmentMeta[] = [];
  async saveMeta(m: Omit<AttachmentMeta, 'id'>) {
    const row = { ...m, id: `a${this.rows.length + 1}` };
    this.rows.push(row);
    return row;
  }
  async getMeta(companyId: string, id: string) {
    return this.rows.find((r) => r.id === id && r.companyId === companyId) ?? null;
  }
}

describe('Attachments — validation + company-scoped storage', () => {
  it('rejects bad mime / size / filename', () => {
    expect(() => validateUpload({ name: 'a.exe', mime: 'application/x-msdownload', size: 10 })).toThrow(ValidationError);
    expect(() => validateUpload({ name: 'ok.pdf', mime: 'application/pdf', size: 0 })).toThrow(ValidationError);
    expect(() => validateUpload({ name: 'ok.pdf', mime: 'application/pdf', size: 999 })).not.toThrow();
  });

  it('uploads with company ownership and blocks cross-company download', async () => {
    const svc = new AttachmentService(new MemStorage(), new MemAttachRepo());
    const meta = await svc.upload(ctx, {
      entityType: 'SalesInvoice',
      entityId: 'inv1',
      name: 'inv.pdf',
      mime: 'application/pdf',
      bytes: Buffer.from('%PDF-1.4 test'),
    });
    expect(meta.companyId).toBe('c1');
    const got = await svc.download(ctx, meta.id);
    expect(got.bytes.toString()).toContain('PDF');
    await expect(svc.download({ userId: 'x', companyId: 'c2' }, meta.id)).rejects.toBeInstanceOf(ValidationError);
  });
});
