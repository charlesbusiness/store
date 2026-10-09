<?php

namespace App\Http\Controllers;

use App\Enums\UnitFamily;
use App\Http\Requests\UnitRequest;
use App\Models\Unit;
use App\Services\Stock\UnitCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The unit catalogue: every measurement the app understands, including any local
 * units a trader wants to add.
 */
class UnitController extends Controller
{
    public function __construct(private readonly UnitCatalog $catalog) {}

    public function index(): View
    {
        $units = Unit::active()->ordered()->get();

        // Keyed by the family enum's value, in declaration order, so the page
        // keeps a stable shape even for a family with no units yet.
        return view('units.index', [
            'groups' => collect(UnitFamily::cases())
                ->mapWithKeys(fn (UnitFamily $family) => [
                    $family->value => [
                        'family' => $family,
                        'units' => $units->where('family', $family)->values(),
                    ],
                ]),
        ]);
    }

    public function create(): View
    {
        return view('units.create', [
            'unit' => new Unit(['is_decimal' => true, 'is_active' => true, 'base_multiplier' => 1]),
        ]);
    }

    public function store(UnitRequest $request): RedirectResponse
    {
        Unit::create($request->payload());

        return redirect()
            ->route('units.index')
            ->with('status', 'Unit added.');
    }

    public function show(Unit $unit): RedirectResponse
    {
        return redirect()->route('units.edit', $unit);
    }

    public function edit(Unit $unit): View
    {
        return view('units.edit', ['unit' => $unit, 'inUse' => $unit->isInUse()]);
    }

    public function update(UnitRequest $request, Unit $unit): RedirectResponse
    {
        $unit->update($request->payload());

        return redirect()
            ->route('units.index')
            ->with('status', 'Unit updated.');
    }

    /** Units are disabled rather than deleted, so old lines keep resolving. */
    public function destroy(Unit $unit): RedirectResponse
    {
        $unit->update(['is_active' => false]);

        return redirect()
            ->route('units.index')
            ->with('status', sprintf('%s is no longer offered on the forms.', $unit->label));
    }
}
