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
    /**
     * Extension to MIME compatibility mapping for allowed extensions.
     *
     * @var array<string, list<string>>
     */
    private const EXTENSION_MIME_MAP = [
        'pdf' => ['application/pdf', 'application/x-pdf'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'webp' => ['image/webp'],
        'txt' => ['text/plain'],
        'csv' => ['text/csv', 'text/plain', 'application/csv', 'text/x-csv', 'application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
    ];

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

        $clientOriginalName = $file->getClientOriginalName();
        $ext = strtolower($file->getClientOriginalExtension() ?: pathinfo($clientOriginalName, PATHINFO_EXTENSION));
        $allowedExts = config('erp_attachments.allowed_extensions', ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'txt', 'csv', 'xlsx', 'docx']);

        if ($ext === '' || ! in_array($ext, $allowedExts, true)) {
            abort(422, __('File extension is not allowed.'));
        }

        $mime = $file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream';
        $allowedMimes = config('erp_attachments.allowed_mimes', []);

        if ($allowedMimes !== [] && ! in_array($mime, $allowedMimes, true)) {
            abort(422, __('File type is not allowed.'));
        }

        if (isset(self::EXTENSION_MIME_MAP[$ext]) && ! in_array($mime, self::EXTENSION_MIME_MAP[$ext], true)) {
            abort(422, __('File type is not allowed.'));
        }

        $maxSizeKb = (int) config('erp_attachments.max_size_kb', 10240);
        if ($file->getSize() > $maxSizeKb * 1024) {
            abort(422, __('File exceeds maximum allowed size.'));
        }

        $id = (string) Str::uuid();
        $safeEntityType = $this->safePathSegment($entityType);
        $safeEntityId = $this->safePathSegment($entityId);
        $safeOriginalName = $this->safeFilename($clientOriginalName, $ext);
        $pathFileName = "{$id}-{$safeOriginalName}";
        $path = "attachments/{$safeEntityType}/{$safeEntityId}/{$pathFileName}";
        $userId = $user->getAuthIdentifier();
        $disk = config('erp_attachments.disk', 'local');

        Storage::disk($disk)->put($path, $file->getContent());

        $rawName = $displayName ?: $clientOriginalName;
        $originalName = $this->safeDisplayName($rawName, $ext);

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
        $this->validateSafePath($attachment->file_ref);

        $disk = config('erp_attachments.disk', 'local');
        abort_if(! Storage::disk($disk)->exists($attachment->file_ref), 404);

        $downloadFilename = $this->safeDisplayName($attachment->name);

        return Storage::disk($disk)->download($attachment->file_ref, $downloadFilename, [
            'Content-Type' => $attachment->mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function delete(string $id, Authenticatable $user): bool
    {
        $attachment = DB::table('attachment')->where('id', $id)->first();

        abort_if(! $attachment, 404);

        $this->authorizer->authorize($user, $attachment->entity_type, $attachment->entity_id, 'delete');
        $this->validateSafePath($attachment->file_ref);

        $disk = config('erp_attachments.disk', 'local');

        DB::transaction(function () use ($attachment, $id, $user, $disk): void {
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

            Storage::disk($disk)->delete($attachment->file_ref);
        });

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
        $clean = preg_replace('/[^A-Za-z0-9_-]/', '_', $value) ?: 'unknown';
        $clean = trim($clean, '_');

        return $clean !== '' ? $clean : 'unknown';
    }

    private function safeFilename(string $name, ?string $fallbackExt = null): string
    {
        $name = str_replace(["\0", "\r", "\n"], '', $name);
        $name = preg_replace('/[[:cntrl:]]/', '', $name) ?? '';
        $name = str_replace('\\', '/', $name);
        $base = basename($name);

        $clean = preg_replace('/[^A-Za-z0-9_.-]/', '_', $base) ?? 'file';
        $clean = preg_replace('/\.{2,}/', '.', $clean) ?? 'file';
        $clean = trim($clean, " ._\t");

        if ($clean === '' || $clean === '.') {
            $clean = 'attachment'.($fallbackExt ? ".{$fallbackExt}" : '');
        }

        if (mb_strlen($clean) > 100) {
            $ext = pathinfo($clean, PATHINFO_EXTENSION);
            $filename = pathinfo($clean, PATHINFO_FILENAME);
            $clean = mb_substr($filename, 0, 80).($ext !== '' ? ".{$ext}" : '');
        }

        return $clean;
    }

    private function safeDisplayName(string $name, ?string $fallbackExt = null): string
    {
        $name = str_replace(["\0", "\r", "\n"], '', $name);
        $name = preg_replace('/[[:cntrl:]]/', '', $name) ?? '';
        $name = str_replace('\\', '/', $name);
        $base = basename($name);

        $clean = preg_replace('/[\/\\\\]+/', '_', $base) ?? 'file';
        $clean = preg_replace('/\.{2,}/', '_', $clean) ?? 'file';
        $clean = trim($clean, " ._\t");

        if ($clean === '' || $clean === '.') {
            $clean = 'attachment'.($fallbackExt ? ".{$fallbackExt}" : '');
        }

        if (mb_strlen($clean) > 255) {
            $clean = mb_substr($clean, 0, 255);
        }

        return $clean;
    }

    private function validateSafePath(string $path): void
    {
        if (str_contains($path, "\0") || str_contains($path, '..') || str_contains($path, '\\')) {
            abort(403, __('Invalid attachment path.'));
        }

        if (! str_starts_with($path, 'attachments/')) {
            abort(403, __('Attachment is outside private storage root.'));
        }
    }
}
