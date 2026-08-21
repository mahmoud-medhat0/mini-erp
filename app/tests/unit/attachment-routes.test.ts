import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Session } from '../../src/core/auth/session';

let session: Session | null = null;
const blobs = new Map<string, Buffer>();
const metas: Array<{
  id: string;
  companyId: string;
  entityType: string;
  entityId: string;
  key: string;
  name: string;
  mime: string;
  size: number;
  uploadedBy: string;
}> = [];

vi.mock('@/core/auth/server', () => ({
  getServerSession: async () => session,
}));

vi.mock('@/core/attachments/local', () => ({
  LocalDiskStorage: class {
    async put(input: { companyId: string; bytes: Buffer | Uint8Array }) {
      const key = `${input.companyId}/${blobs.size + 1}`;
      blobs.set(key, Buffer.from(input.bytes));
      return { key };
    }
    async get(key: string) {
      return blobs.get(key) ?? Buffer.alloc(0);
    }
  },
}));

vi.mock('@/core/db/repositories/attachmentRepo', () => ({
  PrismaAttachmentRepository: class {
    async saveMeta(meta: Omit<(typeof metas)[number], 'id'>) {
      const row = { ...meta, id: `a${metas.length + 1}` };
      metas.push(row);
      return row;
    }
    async getMeta(companyId: string, id: string) {
      return metas.find((meta) => meta.companyId === companyId && meta.id === id) ?? null;
    }
  },
}));

describe('Attachment route handlers', () => {
  beforeEach(() => {
    session = { userId: 'u1', email: 'u1@example.test', companyId: 'c1', grants: [] };
    blobs.clear();
    metas.length = 0;
  });

  it('uploads and downloads within the authenticated company scope', async () => {
    const { POST } = await import('../../src/app/api/attachments/route');
    const { GET } = await import('../../src/app/api/attachments/[id]/route');

    const data = new FormData();
    data.set('entityType', 'SalesInvoice');
    data.set('entityId', 'inv1');
    data.set('file', new File([Buffer.from('%PDF test')], 'invoice.pdf', { type: 'application/pdf' }));

    const upload = await POST(new Request('http://test.local/api/attachments', { method: 'POST', body: data }));
    expect(upload.status).toBe(201);
    const meta = (await upload.json()) as { id: string; companyId: string };
    expect(meta.companyId).toBe('c1');

    const download = await GET(new Request(`http://test.local/api/attachments/${meta.id}`), {
      params: Promise.resolve({ id: meta.id }),
    });
    expect(download.status).toBe(200);
    expect(await download.text()).toContain('PDF');

    session = { userId: 'u2', email: 'u2@example.test', companyId: 'c2', grants: [] };
    const crossCompany = await GET(new Request(`http://test.local/api/attachments/${meta.id}`), {
      params: Promise.resolve({ id: meta.id }),
    });
    expect(crossCompany.status).toBe(404);
  });

  it('rejects unauthenticated upload attempts', async () => {
    const { POST } = await import('../../src/app/api/attachments/route');
    session = null;
    const res = await POST(new Request('http://test.local/api/attachments', { method: 'POST', body: new FormData() }));
    expect(res.status).toBe(401);
  });
});
