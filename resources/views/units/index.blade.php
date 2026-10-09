@extends('layouts.app')

@section('title', 'Units')

@section('content')
  <div class="card">
    <div class="card-head">
      <h2>Units</h2>
      <a class="btn sm" href="{{ route('units.create') }}">Add unit</a>
    </div>

    <p class="mut">
      Every unit belongs to a family and carries a multiplier against that family's base unit
      (@foreach (\App\Enums\UnitFamily::cases() as $family)<strong>{{ $family->baseUnitCode() }}</strong>@if (! $loop->last){{ ', ' }}@endif @endforeach).
      Stock is always kept in base units, so an item can be traded in any unit of its own family.
    </p>

    @foreach ($groups as $group)
      <h3 style="font-size:14px;margin:18px 0 6px">{{ $group['family']->label() }}</h3>
      <div class="sc">
        <table>
          <thead>
            <tr>
              <th>Code</th>
              <th>Label</th>
              <th class="r">Multiplier</th>
              <th>Accepts fractions</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @forelse ($group['units'] as $unit)
              <tr>
                <td><code>{{ $unit->code }}</code></td>
                <td>{{ $unit->label }}</td>
                <td class="r">@plain($unit->base_multiplier, 6)</td>
                <td>{{ $unit->is_decimal ? 'yes' : 'no' }}</td>
                <td>
                  <a class="btn alt sm" href="{{ route('units.edit', $unit) }}">Edit</a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="mut">No units in this family yet.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    @endforeach
  </div>
@endsection
