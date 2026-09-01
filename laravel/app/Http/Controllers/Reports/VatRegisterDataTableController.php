<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\VatRegisterDataTableService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\VatRegisterDataTableRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class VatRegisterDataTableController extends Controller
{
    public function __construct(
        private readonly VatRegisterDataTableService $service,
    ) {}

    public function __invoke(VatRegisterDataTableRequest $request): JsonResponse
    {
        Gate::authorize('reports.view');
        Gate::authorize('view_financials');

        return $this->service->data($request->reportFilters());
    }
}
