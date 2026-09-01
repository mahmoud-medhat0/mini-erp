<?php

namespace App\Http\Controllers\Settings;

use App\Application\Settings\NumberingSettingsService;
use App\Http\Controllers\Concerns\AuthorizesSettingsManagement;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NumberingSettingsController extends Controller
{
    use AuthorizesSettingsManagement;

    public function __construct(private readonly NumberingSettingsService $numberingSettingsService) {}

    public function index(): Response
    {
        return Inertia::render('Settings/Numbering', [
            'sequences' => $this->numberingSettingsService->sequences(),
            'numberingContext' => [
                'year' => now()->year,
                'month' => now()->month,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request, 'settings.numbering');

        $validated = $this->validateNumbering($request);
        $this->numberingSettingsService->create($validated, $request->boolean('include_year'), (int) $request->user()->id);

        return back()->with('success', __('Numbering saved.'));
    }

    public function update(Request $request, string $sequenceId): RedirectResponse
    {
        $this->authorizeManagement($request, 'settings.numbering');

        $validated = $this->validateNumbering($request);
        $this->numberingSettingsService->update($sequenceId, $validated, $request->boolean('include_year'), (int) $request->user()->id);

        return back()->with('success', __('Numbering saved.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateNumbering(Request $request): array
    {
        return $request->validate([
            'key' => ['required', 'string', 'max:100'],
            'doc_type' => ['required', 'string', 'max:100'],
            'prefix' => ['required', 'string', 'max:20'],
            'include_year' => ['required', 'boolean'],
            'padding' => ['required', 'integer', 'min:1', 'max:12'],
            'reset_policy' => ['required', 'string', Rule::in(['never', 'yearly', 'monthly'])],
            'next_value' => ['required', 'integer', 'min:1'],
        ]);
    }
}
