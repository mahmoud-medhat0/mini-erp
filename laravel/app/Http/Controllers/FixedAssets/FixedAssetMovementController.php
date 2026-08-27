<?php

namespace App\Http\Controllers\FixedAssets;

use App\Application\FixedAssets\FixedAssetMovementService;
use App\Http\Controllers\Concerns\AuthorizesFixedAssetRequests;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FixedAssetMovementController extends Controller
{
    use AuthorizesFixedAssetRequests;

    public function __construct(
        private readonly FixedAssetMovementService $movementService,
    ) {}

    public function store(Request $request, string $id): RedirectResponse
    {
        $this->authorizePermission($request, 'fixedAssets.transfer');

        $validated = $request->validate([
            'movement_date' => ['required', 'date'],
            'to_branch_id' => ['nullable', 'uuid', 'exists:branch,id'],
            'to_location_id' => ['nullable', 'uuid', 'exists:fixed_asset_location,id'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $asset = $this->movementService->move($id, $validated, $this->actorId($request));

        return redirect()->route('fixed-assets.show', $asset->id)
            ->with('success', __('Fixed asset movement recorded successfully.'));
    }

    private function actorId(Request $request): ?int
    {
        $actorId = $request->user()?->getAuthIdentifier();

        return is_numeric($actorId) ? (int) $actorId : null;
    }
}
