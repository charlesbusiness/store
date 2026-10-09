@extends('layouts.app')

@section('title', 'Edit item')

@section('content')
  <form method="POST" action="{{ route('items.update', $item) }}" class="card" data-unit-picker>
    @csrf
    @method('PUT')

    <h2>{{ $item->name }}</h2>

    @if ($item->hasHistory())
      <div class="e">
        This item has trading history, so its measurement
        ({{ strtolower($item->family->label()) }}) and trading unit are fixed.
        New items can set both.
      </div>
    @endif

    <div class="g">
      <label>
        Name
        <input type="text" name="name" value="{{ old('name', $item->name) }}" required maxlength="160">
      </label>

      <label>
        Measurement
        <select data-unit-group @disabled($item->hasHistory())>
          <option value="">Choose a measurement</option>
          @foreach ($families as $value => $family)
            <option value="{{ $value }}"
                    @selected(old('family', $item->family?->value) === $value)>
              {{ $family['label'] }}
            </option>
          @endforeach
        </select>
      </label>

      <label>
        Traded in
        <select name="default_unit_code" data-unit-value @disabled($item->hasHistory()) required>
          <option value="">Choose a unit</option>
          @foreach ($families as $value => $family)
            <optgroup label="{{ $family['label'] }}" data-unit-group-body="{{ $value }}">
              @foreach ($family['units'] as $code => $label)
                <option value="{{ $code }}" @selected(old('default_unit_code', $item->default_unit_code) === $code)>
                  {{ $label }}
                </option>
              @endforeach
            </optgroup>
          @endforeach
        </select>
      </label>

      <label>
        Pack size
        <input type="text" name="pack_size" value="{{ old('pack_size', $item->pack_size) }}"
               placeholder="e.g. 50kg sack" maxlength="80">
      </label>
    </div>

    <label style="margin-top:12px">
      Notes
      <textarea name="notes" rows="2" maxlength="1000">{{ old('notes', $item->notes) }}</textarea>
    </label>

    <label class="check" style="margin-top:12px">
      <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active))>
      Available for new sales
    </label>

    <div class="row">
      <button class="btn" type="submit">Save item</button>
      <a class="btn alt" href="{{ route('items.index') }}">Cancel</a>
    </div>
  </form>

  <div class="card">
    <h2>Stock position</h2>
    <p class="mut">
      {{ $position->stockLabel() }} on hand &middot;
      @money($position->stockValue()) at an average of
      @money($position->costPerDisplayUnit()) per @unit($position->displayUnit) &middot;
      @money($position->cost) paid in total.
    </p>
  </div>
@endsection
