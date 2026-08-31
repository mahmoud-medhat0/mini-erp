<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait AuthorizesAccountingRequests
{
    private function authorizePermission(Request $request, string $permission): void
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        if (in_array($permission, ['close_period', 'reopen_period'], true)) {
            if (! $user->can($permission)) {
                abort(403);
            }

            return;
        }

        if ($user->can('settings.configure') || $user->can($permission)) {
            return;
        }

        abort(403, __('You do not have permission to perform this accounting action.'));
    }
}
