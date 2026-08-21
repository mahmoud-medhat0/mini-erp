<?php

namespace App\Http\Controllers;

use App\Application\Company\CreateCompanyForOwner;
use App\Http\Requests\Onboarding\CreateCompanyRequest;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User && $user->companies()->exists()) {
            return redirect()->route('foundation');
        }

        return Inertia::render('Onboarding/Create', [
            'currencies' => Currency::query()
                ->orderBy('code')
                ->get(['code', 'name', 'symbol', 'exponent']),
        ]);
    }

    public function store(CreateCompanyRequest $request, CreateCompanyForOwner $createCompany): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $tenant = $createCompany->handle($user, $request->payload());

        $request->session()->put([
            'active_company_id' => $tenant['company']->id,
            'active_branch_id' => $tenant['branch']->id,
        ]);

        return redirect()
            ->route('foundation')
            ->with('success', __('Company created successfully.'));
    }
}
