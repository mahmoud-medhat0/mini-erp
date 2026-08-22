<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\StockMovementReportService;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StockMovementReportController extends Controller
{
    public function index(Request $request, StockMovementReportService $service): Response
    {
        Gate::authorize('reports.view');

        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $movementType = $request->query('movement_type');
        $productId = $request->query('product_id');
        $currency = $request->query('currency');
        $search = $request->query('search');

        $data = $service->generate(
            dateFrom: $dateFrom ? (string) $dateFrom : null,
            dateTo: $dateTo ? (string) $dateTo : null,
            movementType: $movementType ? (string) $movementType : null,
            productId: $productId ? (string) $productId : null,
            currency: $currency ? (string) $currency : null,
            search: $search ? (string) $search : null
        );

        $products = Product::query()->where('status', 'active')->orderBy('code')->get(['id', 'code', 'name']);

        return Inertia::render('Reports/StockMovementsReport', [
            'reportData' => $data,
            'filters' => [
                'date_from' => $dateFrom ?? '',
                'date_to' => $dateTo ?? '',
                'movement_type' => $movementType ?? '',
                'product_id' => $productId ?? '',
                'currency' => $currency ?? '',
                'search' => $search ?? '',
            ],
            'products' => $products,
        ]);
    }
}
