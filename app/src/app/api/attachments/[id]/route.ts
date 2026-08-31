import path from 'node:path';
import { getServerSession } from '@/core/auth/server';
import { AttachmentService } from '@/core/attachments/storage';
import { LocalDiskStorage } from '@/core/attachments/local';
import { PrismaAttachmentRepository } from '@/core/db/repositories/attachmentRepo';
import { DomainError } from '@/core/errors';
import { tenantFromSession } from '@/core/tenant/context';

export const runtime = 'nodejs';

function attachmentService(): AttachmentService {
  const root = process.env.ATTACHMENT_ROOT ?? path.join(process.cwd(), '.data', 'attachments');
  return new AttachmentService(new LocalDiskStorage(root), new PrismaAttachmentRepository());
}

export async function GET(_request: Request, { params }: { params: Promise<{ id: string }> }) {
  const session = await getServerSession();
  if (!session) return new Response('Unauthorized', { status: 401 });

  try {
    const { id } = await params;
    const { meta, bytes } = await attachmentService().download(tenantFromSession(session), id);
    return new Response(new Uint8Array(bytes), {
      headers: {
        'content-type': meta.mime,
        'content-length': String(meta.size),
        'content-disposition': `attachment; filename="${encodeURIComponent(meta.name)}"`,
      },
    });
  } catch (e) {
    if (e instanceof DomainError) return new Response('Not found', { status: 404 });
    throw e;
  }
}
