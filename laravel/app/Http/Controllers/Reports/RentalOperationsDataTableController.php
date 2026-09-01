<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\RentalOperationsDataTableService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\RentalOperationsDataTableRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class RentalOperationsDataTableController extends Controller
{
    public function __construct(
        private readonly RentalOperationsDataTableService $service,
    ) {}

    public function __invoke(RentalOperationsDataTableRequest $request): JsonResponse
    {
        Gate::authorize('reports.view');
        Gate::authorize('view_financials');

        return $this->service->data($request->reportFilters());
    }
}
