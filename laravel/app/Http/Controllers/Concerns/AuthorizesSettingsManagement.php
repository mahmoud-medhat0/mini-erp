<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait AuthorizesSettingsManagement
{
    private function authorizeManagement(Request $request, string $permission): void
    {
        $user = $request->user();

        abort_if(! $user, 403);

        if ($user->can($permission) || $user->can('settings.configure')) {
            return;
        }

        abort(403);
    }
}
