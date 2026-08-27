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
            ->contains(function (mixed $permission) use ($user): bool {
                if (is_string($permission) && $permission !== '') {
                    return $user->can($permission);
                }

                if (is_array($permission)) {
                    $required = collect($permission)
                        ->filter(fn (mixed $nested): bool => is_string($nested) && $nested !== '')
                        ->values();

                    return $required->isNotEmpty()
                        && $required->every(fn (string $nested): bool => $user->can($nested));
                }

                return false;
            });

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
     * @return array{table: string, key: string, permissions: array<string, mixed>}|null
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

        /** @var array<string, mixed> $permissions */
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
