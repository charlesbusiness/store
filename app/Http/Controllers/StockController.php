<?php

namespace App\Http\Controllers;

use App\Services\Stock\PackReader;
use App\Services\Stock\StockLedger;
use App\Services\Stock\UnitCatalog;
use Illuminate\View\View;

/**
 * The stock tab: every item, what is on hand and what it is worth.
 */
class StockController extends Controller
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly UnitCatalog $units,
        private readonly PackReader $packs,
    ) {}

    public function index(): View
    {
        $positions = $this->ledger->positions();

        return view('stock.index', [
            'positions' => $positions,
            // One query for the whole page: what each item was last received in,
            // and what its stock works out to in those units.
            'breakdowns' => $this->packs->breakdownsFor($positions),
            'traded' => $positions->filter(fn ($position) => $position->hasTraded()),
            'stockValue' => $this->ledger->totalStockValue($positions),
            'totalPurchased' => $this->ledger->totalPurchased($positions),
        ]);
    }
}
