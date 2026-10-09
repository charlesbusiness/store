<?php

namespace App\Http\Controllers;

use App\Http\Requests\ItemRequest;
use App\Models\Item;
use App\Services\Stock\StockLedger;
use App\Services\Stock\UnitCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The item catalogue: the items purchases can introduce, with their current
 * stock alongside.
 */
class ItemController extends Controller
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly UnitCatalog $units,
    ) {}

    public function index(Request $request): View
    {
        $items = Item::query()
            ->with('displayUnit')
            ->search($request->query('q'))
            ->alphabetical()
            ->get();

        return view('items.index', [
            'positions' => $this->ledger->positions($items),
            'search' => (string) $request->query('q', ''),
        ]);
    }

    public function create(): View
    {
        return view('items.create', [
            'item' => new Item(['is_active' => true]),
            'families' => $this->units->groupedCatalogue(),
        ]);
    }

    public function store(ItemRequest $request): RedirectResponse
    {
        Item::create($request->payload());

        return redirect()
            ->route('items.index')
            ->with('status', 'Item added to the catalogue.');
    }

    public function show(Item $item): RedirectResponse
    {
        return redirect()->route('items.edit', $item);
    }

    public function edit(Item $item): View
    {
        return view('items.edit', [
            'item' => $item->load('displayUnit'),
            'position' => $this->ledger->position($item),
            'families' => $this->units->groupedCatalogue(),
        ]);
    }

    public function update(ItemRequest $request, Item $item): RedirectResponse
    {
        $item->update($request->payload());

        return redirect()
            ->route('items.index')
            ->with('status', 'Item updated.');
    }

    /**
     * Items are retired rather than deleted: their history has to stay intact,
     * and an inactive item drops out of the sale list but keeps its stock
     * figures.
     */
    public function destroy(Item $item): RedirectResponse
    {
        $item->update(['is_active' => false]);

        return redirect()
            ->route('items.index')
            ->with('status', sprintf('%s is now retired and will not appear when recording sales.', $item->name));
    }
}
