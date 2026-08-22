<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\CustomerInvoiceReportService;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CustomerInvoiceReportController extends Controller
{
    public function index(Request $request, CustomerInvoiceReportService $service): Response
    {
        Gate::authorize('reports.view');

        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $status = $request->query('status');
        $customerId = $request->query('customer_id');
        $productId = $request->query('product_id');
        $currency = $request->query('currency');
        $search = $request->query('search');

        $data = $service->generate(
            dateFrom: $dateFrom ? (string) $dateFrom : null,
            dateTo: $dateTo ? (string) $dateTo : null,
            status: $status ? (string) $status : null,
            customerId: $customerId ? (string) $customerId : null,
            productId: $productId ? (string) $productId : null,
            currency: $currency ? (string) $currency : null,
            search: $search ? (string) $search : null
        );

        $customers = Customer::query()->where('status', 'active')->orderBy('name')->get(['id', 'code', 'name']);
        $products = Product::query()->where('status', 'active')->orderBy('code')->get(['id', 'code', 'name']);

        return Inertia::render('Reports/CustomerInvoicesReport', [
            'reportData' => $data,
            'filters' => [
                'date_from' => $dateFrom ?? '',
                'date_to' => $dateTo ?? '',
                'status' => $status ?? '',
                'customer_id' => $customerId ?? '',
                'product_id' => $productId ?? '',
                'currency' => $currency ?? '',
                'search' => $search ?? '',
            ],
            'customers' => $customers,
            'products' => $products,
        ]);
    }
}
