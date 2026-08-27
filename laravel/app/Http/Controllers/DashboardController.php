<?php

namespace App\Http\Controllers;

use App\Application\Dashboard\DashboardPageData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardPageData $pageData): Response
    {
        return Inertia::render('Dashboard', $pageData->forUser($request->user()->id));
    }
}
