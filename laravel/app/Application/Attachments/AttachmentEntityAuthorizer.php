<?php

namespace App\Application\Attachments;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

class AttachmentEntityAuthorizer
{
    public function authorize(Authenticatable $user, string $entityType, string $entityId, string $ability): void
    {
        $definition = $this->definition($entityType);

        abort_if($definition === null, 403);

        $permissions = $definition['permissions'][$ability] ?? null;

        abort_if($permissions === null, 403);

        $permissions = is_array($permissions) ? $permissions : [$permissions];
        $hasAccess = collect($permissions)
            ->filter(fn (mixed $permission): bool => is_string($permission) && $permission !== '')
            ->contains(fn (string $permission): bool => $user->can($permission));

        abort_unless($hasAccess, 403);
        abort_unless($this->entityExists($definition, $entityId), 404);
    }

    /**
     * @return list<string>
     */
    public function allowedEntityTypes(): array
    {
        return array_keys(config('erp_attachments.entities', []));
    }

    /**
     * @return array{table: string, key: string, permissions: array<string, string|list<string>>}|null
     */
    private function definition(string $entityType): ?array
    {
        $definition = config("erp_attachments.entities.{$entityType}");

        if (! is_array($definition)) {
            return null;
        }

        $table = $definition['table'] ?? null;
        $key = $definition['key'] ?? null;
        $permissions = $definition['permissions'] ?? null;

        if (! is_string($table) || ! is_string($key) || ! is_array($permissions)) {
            return null;
        }

        if (! preg_match('/^[A-Za-z0-9_]+$/', $table) || ! preg_match('/^[A-Za-z0-9_]+$/', $key)) {
            return null;
        }

        /** @var array<string, string|list<string>> $permissions */
        return [
            'table' => $table,
            'key' => $key,
            'permissions' => $permissions,
        ];
    }

    /**
     * @param  array{table: string, key: string, permissions: array<string, string>}  $definition
     */
    private function entityExists(array $definition, string $entityId): bool
    {
        return DB::table($definition['table'])
            ->where($definition['key'], $entityId)
            ->exists();
    }
}
