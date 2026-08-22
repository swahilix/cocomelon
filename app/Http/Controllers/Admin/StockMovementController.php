<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class StockMovementController extends Controller
{
    public function reverse(Request $request, int $movement, InventoryService $inventory): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        try {
            $record = DB::table('stock_movements')->find($movement);

            if (! $record) {
                throw new RuntimeException('Stock movement was not found.');
            }

            $manualTypes = ['opening_balance', 'adjustment_increase', 'adjustment_decrease'];

            if (! in_array($record->type, $manualTypes, true)) {
                throw new RuntimeException('Only opening-balance and manual-adjustment movements can be reversed here. Purchase, POS and order movements must be reversed from their source transaction.');
            }

            if ((int) $record->quantity === 0) {
                throw new RuntimeException('A zero-quantity opening baseline cannot be reversed. Post a new physical-count adjustment instead.');
            }

            $inventory->reverse($movement, $validated['notes'], $request->user()?->id);
        } catch (Throwable $exception) {
            return back()->withErrors(['reversal' => $exception->getMessage()]);
        }

        return back()->with('success', 'Stock movement reversed successfully. The reversal was added to the ledger; the original record was preserved.');
    }
}
