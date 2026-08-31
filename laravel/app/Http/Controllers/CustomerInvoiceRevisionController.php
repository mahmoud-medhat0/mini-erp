<?php

namespace App\Http\Controllers;

use App\Application\Sales\CustomerInvoiceRevisionPageData;
use Illuminate\Http\Request;
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

    public function show(string $id): Response
    {
        return Inertia::render('Sales/InvoiceRevisionShow', $this->pageData->showData($id));
    }
}
