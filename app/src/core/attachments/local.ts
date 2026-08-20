/**
 * Local-disk AttachmentStorage adapter (development). Imported only by the
 * composition root, not by unit tests. Production uses an S3/object-store adapter
 * implementing the same interface. Keys are namespaced by company.
 */
import { promises as fs } from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import type { AttachmentStorage, PutInput } from './storage';

export class LocalDiskStorage implements AttachmentStorage {
  constructor(private readonly root: string) {}

  private pathFor(key: string): string {
    return path.join(this.root, key);
  }

  async put(input: PutInput): Promise<{ key: string }> {
    const id = crypto.randomUUID();
    const key = path.posix.join(input.companyId, id);
    const full = this.pathFor(key);
    await fs.mkdir(path.dirname(full), { recursive: true });
    await fs.writeFile(full, input.bytes);
    return { key };
  }

  async get(key: string): Promise<Buffer> {
    return fs.readFile(this.pathFor(key));
  }

  async delete(key: string): Promise<void> {
    await fs.rm(this.pathFor(key), { force: true });
  }
}
