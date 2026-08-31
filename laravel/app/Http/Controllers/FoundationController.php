<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class FoundationController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Foundation', [
            'status' => 'M6 page migration',
            'database' => 'not_checked',
        ]);
    }
}
