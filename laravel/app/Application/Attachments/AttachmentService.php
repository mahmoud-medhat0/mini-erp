<?php

namespace App\Application\Attachments;

use App\Domain\Audit\AuditLogger;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AttachmentService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AttachmentEntityAuthorizer $authorizer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function upload(
        UploadedFile $file,
        string $entityType,
        string $entityId,
        Authenticatable $user,
        ?string $displayName = null,
    ): array {
        $this->authorizer->authorize($user, $entityType, $entityId, 'attach');

        $ext = strtolower($file->getClientOriginalExtension());
        $allowedExts = config('erp_attachments.allowed_extensions', ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'txt', 'csv', 'xlsx', 'docx']);

        if ($ext !== '' && ! in_array($ext, $allowedExts, true)) {
            abort(422, __('File extension is not allowed.'));
        }

        $mime = $file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream';
        $allowedMimes = config('erp_attachments.allowed_mimes', []);

        if ($allowedMimes !== [] && ! in_array($mime, $allowedMimes, true)) {
            abort(422, __('File type is not allowed.'));
        }

        $maxSizeKb = (int) config('erp_attachments.max_size_kb', 10240);
        if ($file->getSize() > $maxSizeKb * 1024) {
            abort(422, __('File exceeds maximum allowed size.'));
        }

        $id = (string) Str::uuid();
        $safeEntityType = $this->safePathSegment($entityType);
        $safeEntityId = $this->safePathSegment($entityId);
        $safeOriginalName = $this->safeFilename($file->getClientOriginalName());
        $pathFileName = "{$id}-{$safeOriginalName}";
        $path = "attachments/{$safeEntityType}/{$safeEntityId}/{$pathFileName}";
        $userId = $user->getAuthIdentifier();
        $disk = config('erp_attachments.disk', 'local');

        Storage::disk($disk)->put($path, $file->getContent());

        $originalName = $this->safeDisplayName($displayName ?: $file->getClientOriginalName());
        if (mb_strlen($originalName) > 255) {
            $originalName = mb_substr($originalName, 0, 255);
        }

        $record = [
            'id' => $id,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'file_ref' => $path,
            'name' => $originalName,
            'mime' => $mime,
            'size' => $file->getSize() ?: 0,
            'uploaded_by' => is_numeric($userId) ? (int) $userId : null,
            'at' => now(),
        ];

        try {
            DB::transaction(function () use ($record, $userId, $entityType, $entityId, $id): void {
                DB::table('attachment')->insert($record);

                $this->auditLogger->record(
                    actorId: $userId,
                    action: 'attachment.upload',
                    entityType: $entityType,
                    entityId: $entityId,
                    after: [
                        'attachment_id' => $id,
                        'name' => $record['name'],
                        'mime' => $record['mime'],
                        'size' => $record['size'],
                    ],
                );
            });
        } catch (Throwable $throwable) {
            Storage::disk($disk)->delete($path);

            throw $throwable;
        }

        return $record;
    }

    public function download(string $id, Authenticatable $user): StreamedResponse
    {
        $attachment = DB::table('attachment')->where('id', $id)->first();

        abort_if(! $attachment, 404);

        $this->authorizer->authorize($user, $attachment->entity_type, $attachment->entity_id, 'view');

        $disk = config('erp_attachments.disk', 'local');
        abort_if(! Storage::disk($disk)->exists($attachment->file_ref), 404);

        return Storage::disk($disk)->download($attachment->file_ref, $attachment->name, [
            'Content-Type' => $attachment->mime,
        ]);
    }

    public function delete(string $id, Authenticatable $user): bool
    {
        $attachment = DB::table('attachment')->where('id', $id)->first();

        abort_if(! $attachment, 404);

        $this->authorizer->authorize($user, $attachment->entity_type, $attachment->entity_id, 'delete');

        $disk = config('erp_attachments.disk', 'local');
        Storage::disk($disk)->delete($attachment->file_ref);

        DB::table('attachment')->where('id', $id)->delete();

        $this->auditLogger->record(
            actorId: $user->getAuthIdentifier(),
            action: 'attachment.delete',
            entityType: $attachment->entity_type,
            entityId: $attachment->entity_id,
            before: [
                'attachment_id' => $id,
                'name' => $attachment->name,
                'mime' => $attachment->mime,
                'size' => $attachment->size,
            ],
        );

        return true;
    }

    /**
     * @return Collection<int, object>
     */
    public function listForEntity(string $entityType, string $entityId, Authenticatable $user): Collection
    {
        $this->authorizer->authorize($user, $entityType, $entityId, 'view');

        return DB::table('attachment')
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderByDesc('at')
            ->get();
    }

    private function safePathSegment(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '_', $value) ?: 'unknown';
    }

    private function safeFilename(string $name): string
    {
        $filename = preg_replace('/[^A-Za-z0-9_.-]/', '_', basename($name)) ?: 'file';
        $filename = trim($filename, '.');

        return $filename !== '' ? $filename : 'file';
    }

    private function safeDisplayName(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[[:cntrl:]\/\\\\]+/', '_', $name) ?: 'file';
        $name = trim($name);

        return $name !== '' ? $name : 'file';
    }
}
