<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ReportsHubController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Reports/Index');
    }
}
