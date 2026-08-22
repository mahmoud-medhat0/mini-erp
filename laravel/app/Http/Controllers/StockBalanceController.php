<?php

namespace App\Http\Controllers;

use App\Models\StockBalance;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockBalanceController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->hasPermissionTo('inventory.view') && ! $user->hasRole('admin')) {
            abort(403);
        }

        $balances = StockBalance::query()
            ->with(['product', 'unitOfMeasure'])
            ->orderBy('product_id')
            ->paginate(30);

        return Inertia::render('Inventory/StockBalances', [
            'balances' => $balances,
        ]);
    }
}
