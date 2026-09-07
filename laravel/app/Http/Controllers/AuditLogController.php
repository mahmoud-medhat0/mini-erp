<?php

namespace App\Http\Controllers;

use App\Application\Audit\AuditLogQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request, AuditLogQueryService $queryService): Response
    {
        $user = $request->user();

        abort_unless(
            $user->can('audit.view') || $user->can('settings.configure'),
            403,
            __('Unauthorized to view audit logs.')
        );

        $validated = $request->validate([
            'actor_id' => ['nullable', 'string', 'max:50'],
            'action' => ['nullable', 'string', 'max:100'],
            'entity_type' => ['nullable', 'string', 'max:100'],
            'entity_id' => ['nullable', 'string', 'max:100'],
            'request_id' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return Inertia::render('AuditLog/Index', $queryService->pageData($validated));
    }

    public function datatable(Request $request, AuditLogQueryService $queryService): JsonResponse
    {
        $this->authorizeViewingAuditLog($request);

        $validated = $request->validate([
            'actor_id' => ['nullable', 'string', 'max:50'],
            'action' => ['nullable', 'string', 'max:100'],
            'entity_type' => ['nullable', 'string', 'max:100'],
            'entity_id' => ['nullable', 'string', 'max:100'],
            'request_id' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'audit_search' => ['nullable', 'string', 'max:100'],
        ]);

        if (isset($validated['audit_search'])) {
            $validated['search'] = $validated['audit_search'];
            unset($validated['audit_search']);
        }

        return $queryService->datatable($validated);
    }

    private function authorizeViewingAuditLog(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user->can('audit.view') || $user->can('settings.configure'),
            403,
            __('Unauthorized to view audit logs.')
        );
    }
}
