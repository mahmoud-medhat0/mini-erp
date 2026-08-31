<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\BranchOperationalReportService;
use App\Application\Reports\ReportPageOptions;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BranchOperationalReportController extends Controller
{
    public function __construct(private readonly ReportPageOptions $options) {}

    public function index(Request $request, BranchOperationalReportService $service): Response
    {
        Gate::authorize('view_financials');

        $validated = $request->validate([
            'branch_id' => ['nullable', 'uuid', 'exists:branch,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $branchId = $validated['branch_id'] ?? null;
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;

        return Inertia::render('Reports/BranchOperations', [
            'reportData' => $service->generate(
                branchId: $branchId,
                dateFrom: $dateFrom,
                dateTo: $dateTo,
            ),
            'filters' => [
                'branch_id' => $branchId ?? '',
                'date_from' => $dateFrom ?? '',
                'date_to' => $dateTo ?? '',
            ],
            'branches' => $this->options->branches(['id', 'code', 'name', 'is_active']),
        ]);
    }
}
