import path from 'node:path';
import { NextResponse } from 'next/server';
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

export async function POST(request: Request) {
  const session = await getServerSession();
  if (!session) return NextResponse.json({ error: 'unauthorized' }, { status: 401 });

  try {
    const formData = await request.formData();
    const file = formData.get('file');
    if (!(file instanceof File)) return NextResponse.json({ error: 'file_required' }, { status: 400 });

    const bytes = Buffer.from(await file.arrayBuffer());
    const meta = await attachmentService().upload(tenantFromSession(session), {
      entityType: String(formData.get('entityType') ?? ''),
      entityId: String(formData.get('entityId') ?? ''),
      name: file.name,
      mime: file.type || 'application/octet-stream',
      bytes,
    });
    return NextResponse.json(meta, { status: 201 });
  } catch (e) {
    if (e instanceof DomainError) return NextResponse.json({ error: e.message }, { status: 400 });
    throw e;
  }
}
