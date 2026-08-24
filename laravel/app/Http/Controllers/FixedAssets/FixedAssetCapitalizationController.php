<?php

namespace App\Http\Controllers\FixedAssets;

use App\Application\FixedAssets\FixedAssetCapitalizationService;
use App\Domain\Accounting\PeriodClosedException;
use App\Http\Controllers\Concerns\AuthorizesFixedAssetRequests;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class FixedAssetCapitalizationController extends Controller
{
    use AuthorizesFixedAssetRequests;

    public function __construct(
        private readonly FixedAssetCapitalizationService $capitalizationService,
    ) {}

    public function store(Request $request, string $id): RedirectResponse
    {
        $this->authorizePermission($request, 'fixedAssets.post');
        $this->authorizeSensitiveCapability($request, 'view_financials');

        $validated = $request->validate([
            'capitalization_mode' => ['required', 'string', 'in:opening_already_capitalized,manual_capitalization'],
            'capitalization_date' => ['nullable', 'date'],
        ]);

        try {
            $asset = $this->capitalizationService->capitalize(
                $id,
                $validated['capitalization_mode'],
                $validated['capitalization_date'] ?? null,
                $this->userActorId($request)
            );
        } catch (PeriodClosedException $e) {
            throw ValidationException::withMessages([
                'capitalization_date' => [$e->getMessage()],
            ]);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'capitalization_mode' => [$e->getMessage()],
            ]);
        }

        return redirect()->route('fixed-assets.show', $asset->id)
            ->with('success', __('Fixed asset capitalized successfully.'));
    }

    public function reverse(Request $request, string $id): RedirectResponse
    {
        $this->authorizePermission($request, 'fixedAssets.reverse');
        $this->authorizeSensitiveCapability($request, 'view_financials');

        try {
            $asset = $this->capitalizationService->reverseCapitalization($id, $this->userActorId($request));
        } catch (PeriodClosedException|InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'asset' => [$e->getMessage()],
            ]);
        }

        return redirect()->route('fixed-assets.show', $asset->id)
            ->with('success', __('Fixed asset capitalization reversed successfully.'));
    }

    private function userActorId(Request $request): ?int
    {
        $actorId = $request->user()?->getAuthIdentifier();

        return is_numeric($actorId) ? (int) $actorId : null;
    }
}
