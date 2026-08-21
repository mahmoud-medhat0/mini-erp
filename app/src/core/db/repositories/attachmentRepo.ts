/**
 * Prisma-backed attachment metadata repository. Blob bytes live in an
 * AttachmentStorage adapter; this repository only stores scoped metadata.
 */
import { prisma } from '../prisma';
import type { AttachmentMeta, AttachmentRepository } from '../../attachments/storage';

export class PrismaAttachmentRepository implements AttachmentRepository {
  async saveMeta(meta: Omit<AttachmentMeta, 'id'>): Promise<AttachmentMeta> {
    const row = await prisma.attachment.create({
      data: {
        companyId: meta.companyId,
        entityType: meta.entityType,
        entityId: meta.entityId,
        fileRef: meta.key,
        name: meta.name,
        mime: meta.mime,
        size: meta.size,
        uploadedBy: meta.uploadedBy,
      },
    });
    return {
      id: row.id,
      companyId: row.companyId,
      entityType: row.entityType,
      entityId: row.entityId,
      key: row.fileRef,
      name: row.name,
      mime: row.mime,
      size: row.size,
      uploadedBy: row.uploadedBy,
    };
  }

  async getMeta(companyId: string, id: string): Promise<AttachmentMeta | null> {
    const row = await prisma.attachment.findFirst({ where: { id, companyId } });
    if (!row) return null;
    return {
      id: row.id,
      companyId: row.companyId,
      entityType: row.entityType,
      entityId: row.entityId,
      key: row.fileRef,
      name: row.name,
      mime: row.mime,
      size: row.size,
      uploadedBy: row.uploadedBy,
    };
  }
}
