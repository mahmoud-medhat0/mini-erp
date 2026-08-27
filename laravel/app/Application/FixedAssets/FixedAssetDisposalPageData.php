<?php

namespace App\Application\FixedAssets;

use App\Models\FixedAssetDisposal;

class FixedAssetDisposalPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = (string) ($filters['status'] ?? '');
        $disposalType = (string) ($filters['disposal_type'] ?? '');

        $query = FixedAssetDisposal::query()->with(['asset', 'financialPeriod', 'journalEntry']);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhereHas('asset', function ($assetQuery) use ($search): void {
                        $assetQuery->where('asset_number', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($disposalType !== '') {
            $query->where('disposal_type', $disposalType);
        }

        return [
            'disposals' => $query->latest('created_at')->paginate(15)->withQueryString(),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'disposal_type' => $disposalType,
            ],
        ];
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
