@extends('layouts.app')

@section('title', 'Add item')

@section('content')
  {{-- The measurement picks the family, the unit then narrows to it. Without JS
       the measurement box is inert but harmless: the unit select keeps its
       optgroups and stays fully usable, and the server reads the family off the
       submitted unit either way. --}}
  <form method="POST" action="{{ route('items.store') }}" class="card" data-unit-picker>
    @csrf

    <h2>Add an item</h2>
    <p class="mut">Items are usually created for you when you record a purchase. Add one here if you want it in the catalogue first.</p>

    <div class="g">
      <label>
        Name
        <input type="text" name="name" value="{{ old('name') }}" required maxlength="160" autofocus>
      </label>

      <label>
        Measurement
        <select data-unit-group>
          <option value="">Choose a measurement</option>
          @foreach ($families as $value => $family)
            {{-- Preselect from the rejected unit, so a validation failure comes
                 back with the same measurement already chosen. --}}
            <option value="{{ $value }}"
                    @selected(old('family', \App\Models\Unit::where('code', old('default_unit_code'))->value('family')) === $value)>
              {{ $family['label'] }}
            </option>
          @endforeach
        </select>
      </label>

      <label>
        Traded in
        <select name="default_unit_code" data-unit-value required>
          <option value="">Choose a unit</option>
          @foreach ($families as $value => $family)
            <optgroup label="{{ $family['label'] }}" data-unit-group-body="{{ $value }}">
              @foreach ($family['units'] as $code => $label)
                <option value="{{ $code }}" @selected(old('default_unit_code') === $code)>{{ $label }}</option>
              @endforeach
            </optgroup>
          @endforeach
        </select>
      </label>

      <label>
        Pack size
        <input type="text" name="pack_size" value="{{ old('pack_size') }}" placeholder="e.g. 50kg sack" maxlength="80">
      </label>
    </div>

    <label style="margin-top:12px">
      Notes
      <textarea name="notes" rows="2" maxlength="1000">{{ old('notes') }}</textarea>
    </label>

    <div class="row">
      <button class="btn" type="submit">Add item</button>
      <a class="btn alt" href="{{ route('items.index') }}">Cancel</a>
    </div>
  </form>
@endsection
