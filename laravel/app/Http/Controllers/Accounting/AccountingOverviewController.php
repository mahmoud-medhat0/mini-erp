<?php

namespace App\Http\Controllers\Accounting;

use App\Application\Accounting\AccountingOverviewPageData;
use App\Http\Controllers\Concerns\AuthorizesAccountingRequests;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountingOverviewController extends Controller
{
    use AuthorizesAccountingRequests;

    public function __construct(
        private readonly AccountingOverviewPageData $pageData,
    ) {}

    public function __invoke(Request $request): Response
    {
        $this->authorizePermission($request, 'accounting.view');

        return Inertia::render('Accounting/Index', $this->pageData->indexData());
    }
}
