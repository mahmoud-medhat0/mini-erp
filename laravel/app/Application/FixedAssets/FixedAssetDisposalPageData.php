<?php

namespace App\Application\FixedAssets;

use App\Models\FixedAssetDisposal;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class FixedAssetDisposalPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        return [
            'disposals' => [],
            'filters' => $filters,
        ];
    }

    /**
     * Server-side DataTables feed for fixed asset disposals grid.
     *
     * @param  array<string, mixed>  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');
        $disposalType = (string) ($filters['disposal_type'] ?? '');

        $query = FixedAssetDisposal::query()
            ->with(['asset', 'financialPeriod', 'journalEntry'])
            ->leftJoin('fixed_asset', 'fixed_asset.id', '=', 'fixed_asset_disposal.fixed_asset_id')
            ->select('fixed_asset_disposal.*')
            ->when($status !== '', fn ($q) => $q->where('fixed_asset_disposal.status', $status))
            ->when($disposalType !== '', fn ($q) => $q->where('fixed_asset_disposal.disposal_type', $disposalType));

        return DataTables::of($query)
            ->editColumn('disposal_date', fn (FixedAssetDisposal $row) => $row->disposal_date ? substr((string) $row->disposal_date, 0, 10) : '')
            ->addColumn('asset_number', fn (FixedAssetDisposal $row) => $row->asset?->asset_number ?? '')
            ->addColumn('asset_name', fn (FixedAssetDisposal $row) => is_array($row->asset?->name) ? ($row->asset->name['en'] ?? '') : (string) ($row->asset?->name ?? ''))
            ->addColumn('asset_currency', fn (FixedAssetDisposal $row) => $row->asset?->currency ?? '')
            ->filterColumn('number', fn ($q, $kw) => $q->where('fixed_asset_disposal.number', 'like', "%{$kw}%"))
            ->filterColumn('asset_number', fn ($q, $kw) => $q->where('fixed_asset.asset_number', 'like', "%{$kw}%"))
            ->filterColumn('asset_name', fn ($q, $kw) => $q->where(function ($q2) use ($kw) {
                $q2->where('fixed_asset.name->en', 'like', "%{$kw}%")
                   ->orWhere('fixed_asset.name->ar', 'like', "%{$kw}%");
            }))
            ->make(true);
    }

    /**
     * @return array<string, mixed>
     */
    public function showData(string $id): array
    {
        return [
            'disposal' => FixedAssetDisposal::query()
                ->with(['asset.category', 'financialPeriod', 'journalEntry.lines.account', 'reversalJournalEntry', 'poster'])
                ->findOrFail($id),
        ];
    }
}
