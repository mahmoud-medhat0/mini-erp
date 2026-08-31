<?php

namespace App\Http\Controllers;

use App\Application\Expenses\PrepaidSchedulePageData;
use App\Application\Expenses\PrepaidScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PrepaidScheduleController extends Controller
{
    public function __construct(
        private readonly PrepaidScheduleService $service,
        private readonly PrepaidSchedulePageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Expenses/Prepaids', $this->pageData->indexData($request->only(['search', 'status', 'branch_id'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->service->create($this->validatedSchedule($request), $request->user()?->id);

        return back()->with('success', __('Prepaid schedule saved.'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->service->update($id, $this->validatedSchedule($request, true), $request->user()?->id);

        return back()->with('success', __('Prepaid schedule updated.'));
    }

    public function submit(Request $request, string $id): RedirectResponse
    {
        $this->service->submit($id, $request->user()?->id);

        return back()->with('success', __('Prepaid schedule submitted.'));
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        $this->service->approve($id, $request->user()?->id);

        return back()->with('success', __('Prepaid schedule approved.'));
    }

    public function postRecognition(Request $request, string $id, string $recognitionId): RedirectResponse
    {
        $this->service->postRecognition($id, $recognitionId, $request->user()?->id);

        return back()->with('success', __('Prepaid recognition posted.'));
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $this->service->cancel($id, $request->user()?->id);

        return back()->with('success', __('Prepaid schedule cancelled.'));
    }

    private function validatedSchedule(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'schedule_date' => [$isUpdate ? 'sometimes' : 'required', 'date'],
            'start_date' => [$isUpdate ? 'sometimes' : 'required', 'date'],
            'months' => [$isUpdate ? 'sometimes' : 'required', 'integer', 'min:1', 'max:120'],
            'branch_id' => ['nullable', 'uuid', 'exists:branch,id'],
            'expense_category_id' => ['nullable', 'uuid', 'exists:expense_category,id'],
            'prepaid_asset_account_id' => [$isUpdate ? 'sometimes' : 'required', 'uuid', 'exists:account,id'],
            'expense_account_id' => [$isUpdate ? 'sometimes' : 'required', 'uuid', 'exists:account,id'],
            'currency' => [$isUpdate ? 'sometimes' : 'required', 'string', 'size:3', 'exists:currency,code'],
            'fx_rate_e6' => ['nullable', 'integer', 'min:1'],
            'total_minor' => [$isUpdate ? 'sometimes' : 'required', 'integer', 'min:1'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'lock_version' => [$isUpdate ? 'required' : 'nullable', 'integer', 'min:1'],
        ]);
    }
}
