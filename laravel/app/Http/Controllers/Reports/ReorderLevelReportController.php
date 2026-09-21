<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\ReorderLevelReportService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReorderLevelReportController extends Controller
{
    public function __construct(
        private readonly ReorderLevelReportService $reorderLevelReportService,
    ) {}

    public function index(): Response
    {
        Gate::authorize('reports.view');
        Gate::authorize('view_financials');

        return Inertia::render('Reports/ReorderLevel', [
            'report' => $this->reorderLevelReportService->generate(),
        ]);
    }
}
