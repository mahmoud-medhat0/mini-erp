<?php

namespace App\Application\Partners;

use App\Domain\Audit\AuditLogger;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

/**
 * Phase 30 - Partners registry (PHASE_25_GAP_CLOSURE_DECISION_PACK.md §9).
 * Simple CRUD for the partner master; capital and loan balances live in
 * partner_transaction / partner_loan and are never edited here directly.
 */
class PartnerService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');

        $query = Partner::query()
            ->select('partner.*')
            ->when($status === 'active', fn ($q) => $q->where('partner.status', 'active'))
            ->when($status === 'inactive', fn ($q) => $q->where('partner.status', 'inactive'));

        return DataTables::of($query)
            ->addColumn('name_text', fn (Partner $row) => is_array($row->name) ? ($row->name['en'] ?? '') : (string) $row->name)
            ->addColumn('share_percent', fn (Partner $row) => round(((int) $row->share_bps) / 100, 2))
            ->filterColumn('name_text', fn ($q, $kw) => $q->where(function ($q2) use ($kw): void {
                $q2->where('partner.name->en', 'like', "%{$kw}%")
                    ->orWhere('partner.name->ar', 'like', "%{$kw}%");
            }))
            ->make(true);
    }

    public function create(array $data, ?int $actorId = null): Partner
    {
        return DB::transaction(function () use ($data, $actorId): Partner {
            $payload = $this->validatedPayload($data);

            /** @var Partner $partner */
            $partner = Partner::query()->create([
                ...$payload,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => 1,
            ]);

            $this->auditLogger->record($actorId, 'partner.create', 'partner', $partner->id, after: $partner->fresh()->toArray());

            return $partner;
        });
    }

    public function update(string $id, array $data, ?int $actorId = null): Partner
    {
        return DB::transaction(function () use ($id, $data, $actorId): Partner {
            /** @var Partner $partner */
            $partner = Partner::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== (int) $partner->lock_version) {
                throw ValidationException::withMessages(['lock_version' => [__('The partner was modified by another user. Please refresh and try again.')]]);
            }

            $before = $partner->fresh()->toArray();
            $payload = $this->validatedPayload([
                'code' => $data['code'] ?? $partner->code,
                'name' => $data['name'] ?? $partner->getTranslations('name'),
                'share_bps' => array_key_exists('share_bps', $data) ? $data['share_bps'] : $partner->share_bps,
                'status' => $data['status'] ?? $partner->status,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $partner->notes,
            ], $partner->id);

            $partner->update([
                ...$payload,
                'updated_by' => $actorId,
                'lock_version' => ((int) $partner->lock_version) + 1,
            ]);

            $this->auditLogger->record($actorId, 'partner.update', 'partner', $partner->id, before: $before, after: $partner->fresh()->toArray());

            return $partner->fresh();
        });
    }

    public function delete(string $id, ?int $actorId = null): void
    {
        DB::transaction(function () use ($id, $actorId): void {
            /** @var Partner $partner */
            $partner = Partner::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($partner->transactions()->exists() || $partner->loans()->exists()) {
                throw ValidationException::withMessages(['partner' => [__('Partners with recorded transactions or loans cannot be deleted. Set them to inactive instead.')]]);
            }

            $before = $partner->toArray();
            $partner->delete();
            $this->auditLogger->record($actorId, 'partner.delete', 'partner', $id, before: $before);
        });
    }

    private function validatedPayload(array $data, ?string $ignoreId = null): array
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        if ($code === '' || ! preg_match('/^[A-Z0-9._-]+$/', $code)) {
            throw ValidationException::withMessages(['code' => [__('Partner code is required and may contain letters, numbers, dots, underscores, or dashes.')]]);
        }

        $exists = Partner::query()
            ->where('code', $code)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['code' => [__('Partner code already exists.')]]);
        }

        $translations = is_array($data['name'] ?? null) ? $data['name'] : [];
        $en = trim((string) ($translations['en'] ?? ''));
        $ar = trim((string) ($translations['ar'] ?? $en));
        if ($en === '') {
            throw ValidationException::withMessages(['name.en' => [__('English partner name is required.')]]);
        }

        $shareBps = (int) ($data['share_bps'] ?? 0);
        if ($shareBps < 0 || $shareBps > 10000) {
            throw ValidationException::withMessages(['share_bps' => [__('Ownership share must be between 0% and 100%.')]]);
        }

        $status = (string) ($data['status'] ?? 'active');
        if (! in_array($status, ['active', 'inactive'], true)) {
            throw ValidationException::withMessages(['status' => [__('Invalid partner status.')]]);
        }

        return [
            'code' => $code,
            'name' => ['en' => $en, 'ar' => $ar === '' ? $en : $ar],
            'share_bps' => $shareBps,
            'status' => $status,
            'notes' => $data['notes'] ?? null,
        ];
    }
}
