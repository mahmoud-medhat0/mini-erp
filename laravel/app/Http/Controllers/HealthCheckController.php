<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('select 1 as ok');

            return response()->json([
                'status' => 'ok',
                'database' => 'ok',
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => 'degraded',
                'database' => 'unavailable',
            ], 503);
        }
    }
}
