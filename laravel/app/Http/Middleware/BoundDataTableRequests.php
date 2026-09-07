<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class BoundDataTableRequests
{
    /**
     * Apply a shared safety ceiling to every named DataTable feed, including
     * ordinary operational grids that do not need a dedicated FormRequest.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isDataTableRequest($request)) {
            return $next($request);
        }

        Validator::make($request->all(), [
            'draw' => ['required', 'integer', 'min:0'],
            'start' => ['required', 'integer', 'min:0'],
            'length' => ['required', 'integer', 'min:1', 'max:100'],
            'search.value' => ['nullable', 'string', 'max:150'],
            'search.regex' => ['nullable', 'in:false,0'],
            'columns' => ['required', 'array', 'max:30'],
            'order' => ['nullable', 'array', 'max:10'],
        ])->validate();

        return $next($request);
    }

    private function isDataTableRequest(Request $request): bool
    {
        if (! $request->isMethod('GET') || ! $request->has('draw')) {
            return false;
        }

        $routeName = (string) ($request->route()?->getName() ?? '');

        return str_ends_with($routeName, '.data') || str_ends_with($routeName, '.datatable');
    }
}
