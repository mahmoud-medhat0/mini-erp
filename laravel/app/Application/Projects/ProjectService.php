<?php

namespace App\Application\Projects;

use App\Domain\Audit\AuditLogger;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function create(array $data, int|string|null $actorId = null): Project
    {
        return DB::transaction(function () use ($data, $actorId): Project {
            $code = strtoupper(trim((string) $data['code']));
            $this->assertUniqueCode($code);

            $startDate = ! empty($data['start_date']) ? Carbon::parse($data['start_date'])->toDateString() : null;
            $endDate = ! empty($data['end_date']) ? Carbon::parse($data['end_date'])->toDateString() : null;
            $this->assertDateRange($startDate, $endDate);

            $status = (string) ($data['status'] ?? 'active');
            $this->assertStatus($status);

            /** @var Project $project */
            $project = Project::query()->create([
                'code' => $code,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => $status,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'is_billable' => (bool) ($data['is_billable'] ?? false),
                'is_active' => (bool) ($data['is_active'] ?? true),
                'lock_version' => 1,
                'created_by' => $this->actorId($actorId),
                'updated_by' => $this->actorId($actorId),
            ]);

            $this->auditLogger->record(
                $this->actorId($actorId),
                'project.create',
                'project',
                (string) $project->id,
                after: $project->fresh()->toArray()
            );

            return $project->fresh();
        });
    }

    public function update(string $id, array $data, int|string|null $actorId = null): Project
    {
        return DB::transaction(function () use ($id, $data, $actorId): Project {
            /** @var Project $project */
            $project = Project::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== $project->lock_version) {
                throw ValidationException::withMessages([
                    'lock_version' => [__('The record has been modified by another user. Please refresh and try again.')],
                ]);
            }

            if (isset($data['code'])) {
                $data['code'] = strtoupper(trim((string) $data['code']));
                if ($data['code'] !== $project->code) {
                    $this->assertUniqueCode((string) $data['code'], $project->id);
                }
            }

            $startDate = array_key_exists('start_date', $data)
                ? (! empty($data['start_date']) ? Carbon::parse($data['start_date'])->toDateString() : null)
                : ($project->start_date ? $project->start_date->toDateString() : null);

            $endDate = array_key_exists('end_date', $data)
                ? (! empty($data['end_date']) ? Carbon::parse($data['end_date'])->toDateString() : null)
                : ($project->end_date ? $project->end_date->toDateString() : null);

            $this->assertDateRange($startDate, $endDate);

            if (array_key_exists('status', $data) && $data['status'] !== null) {
                $this->assertStatus((string) $data['status']);
            }

            $before = $project->toArray();
            $updates = [];

            foreach (['code', 'name', 'description', 'status', 'is_billable', 'is_active'] as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[$field] = $data[$field];
                }
            }

            if (array_key_exists('start_date', $data)) {
                $updates['start_date'] = $startDate;
            }
            if (array_key_exists('end_date', $data)) {
                $updates['end_date'] = $endDate;
            }

            $updates['updated_by'] = $this->actorId($actorId);
            $updates['lock_version'] = $project->lock_version + 1;
            $project->update($updates);

            $this->auditLogger->record(
                $this->actorId($actorId),
                'project.update',
                'project',
                (string) $project->id,
                before: $before,
                after: $project->fresh()->toArray()
            );

            return $project->fresh();
        });
    }

    public function delete(string $id, int|string|null $actorId = null): void
    {
        DB::transaction(function () use ($id, $actorId): void {
            /** @var Project $project */
            $project = Project::query()->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($project->journalLines()->exists() || $project->ledgerEntries()->exists() || $project->expenseLines()->exists()) {
                throw ValidationException::withMessages([
                    'project' => [__('Cannot delete project referenced by expense lines, journal lines, or ledger entries.')],
                ]);
            }

            $before = $project->toArray();
            $project->delete();

            $this->auditLogger->record(
                $this->actorId($actorId),
                'project.delete',
                'project',
                $id,
                before: $before
            );
        });
    }

    private function assertUniqueCode(string $code, ?string $ignoreId = null): void
    {
        $exists = Project::query()
            ->where('code', $code)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => [__('Project code [:code] already exists.', ['code' => $code])],
            ]);
        }
    }

    private function assertDateRange(?string $startDate, ?string $endDate): void
    {
        if ($startDate !== null && $endDate !== null && $endDate < $startDate) {
            throw ValidationException::withMessages([
                'end_date' => [__('Project end date cannot be earlier than start date.')],
            ]);
        }
    }

    private function assertStatus(string $status): void
    {
        if (! in_array($status, ['active', 'on_hold', 'completed', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'status' => [__('Invalid project status [:status].', ['status' => $status])],
            ]);
        }
    }

    private function actorId(int|string|null $actorId): ?int
    {
        return is_numeric($actorId) ? (int) $actorId : null;
    }
}
