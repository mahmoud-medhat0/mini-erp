<?php

namespace App\Application\Attachments;

use App\Domain\Audit\AuditLogger;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
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
    ): array {
        $this->authorizer->authorize($user, $entityType, $entityId, 'attach');

        $id = (string) Str::uuid();
        $extension = $file->getClientOriginalExtension();
        $fileName = $extension ? "{$id}.{$extension}" : $id;
        $path = "attachments/{$fileName}";
        $userId = $user->getAuthIdentifier();

        Storage::disk('local')->put($path, $file->getContent());

        $record = [
            'id' => $id,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'file_ref' => $path,
            'name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType() ?: 'application/octet-stream',
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
                    after: ['attachment_id' => $id, 'name' => $record['name'], 'mime' => $record['mime'], 'size' => $record['size']],
                );
            });
        } catch (Throwable $throwable) {
            Storage::disk('local')->delete($path);

            throw $throwable;
        }

        return $record;
    }

    public function download(string $id, Authenticatable $user): StreamedResponse
    {
        $attachment = DB::table('attachment')->where('id', $id)->first();

        abort_if(! $attachment, 404);

        $this->authorizer->authorize($user, $attachment->entity_type, $attachment->entity_id, 'view');

        abort_if(! Storage::disk('local')->exists($attachment->file_ref), 404);

        return Storage::disk('local')->download($attachment->file_ref, $attachment->name, [
            'Content-Type' => $attachment->mime,
        ]);
    }
}
