<?php

namespace App\Http\Controllers;

use App\Application\Audit\AuditLogQueryService;
use App\Models\User;
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

        $logs = $queryService->paginate($validated, (int) ($validated['per_page'] ?? 25));
        $actions = $queryService->getAvailableActions();
        $entityTypes = $queryService->getAvailableEntityTypes();
        $usersList = User::query()->select(['id', 'name', 'email'])->orderBy('name')->get();

        return Inertia::render('AuditLog/Index', [
            'logs' => $logs,
            'filters' => $validated,
            'actions' => $actions,
            'entityTypes' => $entityTypes,
            'usersList' => $usersList,
        ]);
    }
}
