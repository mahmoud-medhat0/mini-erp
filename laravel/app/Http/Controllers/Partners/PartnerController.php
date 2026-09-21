<?php

namespace App\Http\Controllers\Partners;

use App\Application\Partners\PartnerService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PartnerController extends Controller
{
    public function __construct(
        private readonly PartnerService $partnerService,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('partners.view');

        return Inertia::render('Partners/Index', [
            'partners' => [],
            'filters' => [
                'status' => (string) $request->query('status', ''),
            ],
            'can' => [
                'create' => $request->user()?->can('partners.create') ?? false,
                'edit' => $request->user()?->can('partners.edit') ?? false,
                'delete' => $request->user()?->can('partners.delete') ?? false,
            ],
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        Gate::authorize('partners.view');

        return $this->partnerService->datatable($request->only(['status']));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('partners.create');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name.en' => ['required', 'string', 'max:255'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'share_bps' => ['required', 'integer', 'min:0', 'max:10000'],
            'status' => ['sometimes', 'in:active,inactive'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->partnerService->create($validated, $request->user()?->id);

        return back()->with('success', __('Partner saved.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        Gate::authorize('partners.edit');

        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:50'],
            'name.en' => ['sometimes', 'string', 'max:255'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'share_bps' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'status' => ['sometimes', 'in:active,inactive'],
            'notes' => ['nullable', 'string'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ]);

        $this->partnerService->update($id, $validated, $request->user()?->id);

        return back()->with('success', __('Partner updated.'));
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        Gate::authorize('partners.delete');

        $this->partnerService->delete($id, $request->user()?->id);

        return back()->with('success', __('Partner deleted.'));
    }
}
