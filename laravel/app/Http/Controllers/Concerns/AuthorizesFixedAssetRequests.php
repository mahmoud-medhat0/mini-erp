<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait AuthorizesFixedAssetRequests
{
    protected function authorizePermission(Request $request, string $permission): void
    {
        if (! $request->user()?->can($permission)) {
            abort(403);
        }
    }

    protected function authorizeSensitiveCapability(Request $request, string $capability): void
    {
        if (! $request->user()?->can($capability)) {
            abort(403);
        }
    }
}
