<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\TransactionLine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Deleting a line, or a whole record.
 *
 * No stock is recalculated: the ledger is the source of truth, so removing a
 * line immediately and automatically adjusts every figure that was derived from
 * it. A transaction left with no lines is removed too.
 */
class LineController extends Controller
{
    public function destroy(TransactionLine $line): RedirectResponse
    {
        $type = $line->transaction?->type;
        $itemName = $line->item?->name ?? 'the item';

        DB::transaction(function () use ($line) {
            $transaction = $line->transaction;
            $line->delete();

            if ($transaction?->lines()->doesntExist()) {
                $transaction->delete();
            }
        });

        return back()->with('status', sprintf(
            '%s removed from the %s ledger. Stock has been adjusted.',
            $itemName,
            $type?->plural() ?? 'record'
        ));
    }

    public function destroyTransaction(Transaction $transaction): RedirectResponse
    {
        $type = $transaction->type;
        $count = $transaction->lines()->count();

        $transaction->delete();

        return back()->with('status', sprintf(
            'Record of %d %s deleted. Stock has been adjusted.',
            $count,
            $count === 1 ? 'line' : 'lines'
        ));
    }

    public function markPaid(Transaction $transaction): RedirectResponse
    {
        if (! $transaction->isSale()) {
            return back()->with('error', 'Only sales can be marked as paid.');
        }

        $transaction->update(['paid' => true]);

        return back()->with('status', 'Sale marked as paid.');
    }
}
