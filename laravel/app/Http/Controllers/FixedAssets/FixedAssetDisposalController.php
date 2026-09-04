<?php

namespace App\Http\Controllers\FixedAssets;

use App\Application\FixedAssets\FixedAssetDisposalPageData;
use App\Application\FixedAssets\FixedAssetDisposalPostingService;
use App\Domain\Accounting\PeriodClosedException;
use App\Http\Controllers\Concerns\AuthorizesFixedAssetRequests;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FixedAssetDisposalController extends Controller
{
    use AuthorizesFixedAssetRequests;

    public function __construct(
        private readonly FixedAssetDisposalPostingService $disposalPostingService,
        private readonly FixedAssetDisposalPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'fixedAssets.view');
        $this->authorizeSensitiveCapability($request, 'view_financials');

        return Inertia::render('FixedAssets/Disposals/Index', $this->pageData->indexData($request->only(['search', 'status', 'disposal_type'])));
    }

    public function datatable(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'fixedAssets.view');
        $this->authorizeSensitiveCapability($request, 'view_financials');

        return $this->pageData->datatable($request->only(['status', 'disposal_type']));
    }

    public function show(Request $request, string $id): Response
    {
        $this->authorizePermission($request, 'fixedAssets.view');
        $this->authorizeSensitiveCapability($request, 'view_financials');

        return Inertia::render('FixedAssets/Disposals/Show', $this->pageData->showData($id));
    }

    public function preview(Request $request, string $assetId): JsonResponse
    {
        $this->authorizePermission($request, 'fixedAssets.view');
        $this->authorizeSensitiveCapability($request, 'view_financials');

        $validated = $request->validate([
            'disposal_date' => ['required', 'date'],
            'disposal_type' => ['required', 'string', 'in:sale,scrap,retirement'],
            'proceeds_minor' => ['nullable', 'integer', 'min:0'],
        ]);

        $preview = $this->disposalPostingService->previewDisposal(
            fixedAssetId: $assetId,
            disposalDate: $validated['disposal_date'],
            disposalType: $validated['disposal_type'],
            proceedsMinor: (int) ($validated['proceeds_minor'] ?? 0)
        );

        return response()->json($preview);
    }

    public function store(Request $request, string $assetId): RedirectResponse
    {
        $this->authorizePermission($request, 'fixedAssets.post');
        $this->authorizeSensitiveCapability($request, 'view_financials');

        $validated = $request->validate([
            'disposal_date' => ['required', 'date'],
            'disposal_type' => ['required', 'string', 'in:sale,scrap,retirement'],
            'proceeds_minor' => ['nullable', 'integer', 'min:0'],
        ]);

        $user = $request->user();

        try {
            $disposal = $this->disposalPostingService->postDisposal(
                fixedAssetId: $assetId,
                disposalDate: $validated['disposal_date'],
                disposalType: $validated['disposal_type'],
                proceedsMinor: (int) ($validated['proceeds_minor'] ?? 0),
                userId: $user ? (int) $user->id : null
            );
        } catch (PeriodClosedException $e) {
            throw ValidationException::withMessages([
                'disposal_date' => [$e->getMessage()],
            ]);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'disposal_date' => [$e->getMessage()],
            ]);
        }

        return redirect()->route('fixed-assets-disposals.show', $disposal->id)
            ->with('success', __('Fixed asset disposal [::number] posted successfully.', ['number' => $disposal->number]));
    }

    public function reverse(Request $request, string $id): RedirectResponse
    {
        $this->authorizePermission($request, 'fixedAssets.reverse');
        $this->authorizeSensitiveCapability($request, 'view_financials');

        $user = $request->user();

        $disposal = $this->disposalPostingService->reverseDisposal(
            disposalId: $id,
            userId: $user ? (int) $user->id : null
        );

        return redirect()->back()
            ->with('success', __('Fixed asset disposal [::number] reversed successfully.', ['number' => $disposal->number]));
    }
}
