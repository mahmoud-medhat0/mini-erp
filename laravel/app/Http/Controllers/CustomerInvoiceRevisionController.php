<?php

namespace App\Http\Controllers;

use App\Application\Sales\CustomerInvoiceRevisionPageData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CustomerInvoiceRevisionController extends Controller
{
    public function __construct(
        private readonly CustomerInvoiceRevisionPageData $pageData,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Sales/InvoiceRevisions', $this->pageData->indexData($request->only(['search'])));
    }

    public function datatable(Request $request): JsonResponse
    {
        Gate::authorize('sales.invoice_revisions');

        return $this->pageData->datatable();
    }

    public function show(string $id): Response
    {
        return Inertia::render('Sales/InvoiceRevisionShow', $this->pageData->showData($id));
    }
}
