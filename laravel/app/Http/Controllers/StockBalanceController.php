<?php

namespace App\Http\Controllers;

use App\Application\Inventory\StockBalancePageData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockBalanceController extends Controller
{
    public function __construct(
        private readonly StockBalancePageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Inventory/StockBalances', $this->pageData->indexData($request->only(['warehouse_id'])));
    }

    public function datatable(Request $request): JsonResponse
    {
        return $this->pageData->datatable($request->only(['warehouse_id']));
    }
}
