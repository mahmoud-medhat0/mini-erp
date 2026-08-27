<?php

namespace App\Http\Controllers\Settings;

use App\Application\Settings\CompanySettingsService;
use App\Http\Controllers\Concerns\AuthorizesSettingsManagement;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanySettingsController extends Controller
{
    use AuthorizesSettingsManagement;

    public function __construct(
        private readonly CompanySettingsService $companySettingsService,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Settings/Company', $this->companySettingsService->indexData($request->user()));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request, 'settings.company');

        $validated = $this->validateCompany($request);
        $this->companySettingsService->create($validated, (int) $request->user()->id);

        return back()->with('success', __('Company saved.'));
    }

    public function update(Request $request, ?string $companyId = null): RedirectResponse
    {
        $this->authorizeManagement($request, 'settings.company');

        $validated = $this->validateCompany($request, includeLockVersion: true);
        $result = $this->companySettingsService->update($companyId, $validated, (int) $request->user()->id);

        return back()->with('success', __($result['created'] ? 'Company created.' : 'Company saved.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCompany(Request $request, bool $includeLockVersion = false): array
    {
        $rules = [
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'base_currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'fiscal_year_start_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
        ];

        if ($includeLockVersion) {
            $rules['lock_version'] = ['nullable', 'integer', 'min:0'];
        }

        return $request->validate($rules);
    }
}
