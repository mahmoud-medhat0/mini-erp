<?php

namespace App\Http\Controllers;

use App\Models\CustomerInvoiceRevision;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerInvoiceRevisionController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->query('search');

        $query = CustomerInvoiceRevision::query()->with([
            'customerInvoice.customer',
            'customerCreditNote',
            'salesReturn',
            'createdBy',
            'lines.product',
            'lines.unitOfMeasure',
        ]);

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('display_string', 'like', "%{$search}%")
                    ->orWhereHas('customerInvoice', function ($iq) use ($search): void {
                        $iq->where('number', 'like', "%{$search}%");
                    });
            });
        }

        $customerInvoiceRevisions = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Sales/InvoiceRevisions', [
            'customerInvoiceRevisions' => $customerInvoiceRevisions,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function show(string $id): Response
    {
        /** @var CustomerInvoiceRevision $revision */
        $revision = CustomerInvoiceRevision::query()
            ->with([
                'customerInvoice.customer',
                'customerCreditNote',
                'salesReturn',
                'createdBy',
                'lines.product',
                'lines.unitOfMeasure',
            ])
            ->where('id', $id)
            ->firstOrFail();

        return Inertia::render('Sales/InvoiceRevisionShow', [
            'revision' => $revision,
            'snapshot' => json_decode((string) $revision->snapshot_json, true),
        ]);
    }
}
