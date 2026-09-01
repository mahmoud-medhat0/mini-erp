<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\ChequeRegisterDataTableService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ChequeRegisterDataTableRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ChequeRegisterDataTableController extends Controller
{
    public function __construct(
        private readonly ChequeRegisterDataTableService $service,
    ) {}

    public function __invoke(ChequeRegisterDataTableRequest $request): JsonResponse
    {
        Gate::authorize('reports.view');
        Gate::authorize('view_financials');

        return $this->service->data($request->reportFilters());
    }
}
