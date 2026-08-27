<?php

namespace App\Http\Controllers\Accounting;

use App\Application\Accounting\CurrencyPageData;
use App\Http\Controllers\Concerns\AuthorizesAccountingRequests;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CurrencyController extends Controller
{
    use AuthorizesAccountingRequests;

    public function __construct(private readonly CurrencyPageData $pageData) {}

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'accounting.view');

        return Inertia::render('Accounting/Currencies', $this->pageData->indexData());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.create');

        $validated = $request->validate([
            'code' => ['required', 'string', 'size:3', 'unique:currency,code'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'symbol' => ['required', 'string', 'max:10'],
            'exponent' => ['required', 'integer', 'min:0', 'max:4'],
        ], [
            'code.unique' => __('Currency code :code already exists.', ['code' => strtoupper((string) $request->input('code'))]),
        ]);

        Currency::create([
            'code' => strtoupper($validated['code']),
            'name' => [
                'en' => $validated['name_en'],
                'ar' => $validated['name_ar'],
            ],
            'symbol' => $validated['symbol'],
            'exponent' => (int) $validated['exponent'],
        ]);

        return redirect()->back()->with('success', __('Currency created successfully.'));
    }

    public function update(Request $request, Currency $currency): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.edit');

        $validated = $request->validate([
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'symbol' => ['required', 'string', 'max:10'],
            'exponent' => ['required', 'integer', 'min:0', 'max:4'],
        ]);

        $currency->setTranslation('name', 'en', $validated['name_en']);
        $currency->setTranslation('name', 'ar', $validated['name_ar']);
        $currency->symbol = $validated['symbol'];
        $currency->exponent = (int) $validated['exponent'];
        $currency->save();

        return redirect()->back()->with('success', __('Currency updated successfully.'));
    }

    public function destroy(Request $request, Currency $currency): RedirectResponse
    {
        $this->authorizePermission($request, 'accounting.delete');

        if ($currency->accounts()->count() > 0 || $currency->journalLines()->count() > 0 || $currency->exchangeRates()->count() > 0) {
            return redirect()->back()->with('error', __('Cannot delete currency because it has linked accounts, journals, or exchange rates.'));
        }

        $currency->delete();

        return redirect()->back()->with('success', __('Currency deleted successfully.'));
    }
}
