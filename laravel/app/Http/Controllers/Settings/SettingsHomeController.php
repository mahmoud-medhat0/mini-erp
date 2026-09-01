<?php

namespace App\Http\Controllers\Settings;

use App\Application\Settings\SettingsHomePageData;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsHomeController extends Controller
{
    public function __invoke(Request $request, SettingsHomePageData $pageData): Response
    {
        return Inertia::render('Settings/Index', $pageData->indexData($request->user()));
    }
}
