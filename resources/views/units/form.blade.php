@extends('layouts.app')

@section('title', $unit->exists ? 'Edit unit' : 'Add unit')

@section('content')
  <form method="POST" action="{{ $unit->exists ? route('units.update', $unit) : route('units.store') }}" class="card">
    @csrf
    @if ($unit->exists)
      @method('PUT')
    @endif

    <h2>{{ $unit->exists ? $unit->label : 'Add a unit' }}</h2>

    @if (($inUse ?? false) || ($unit->exists && $unit->isInUse()))
      <div class="e">
        This unit is already in use, so its code, family and multiplier are fixed. Existing stock was
        converted through them. You can still rename it or take it off the forms.
      </div>
    @endif

    <div class="g">
      <label>
        Code
        <input type="text" name="code" value="{{ old('code', $unit->code) }}" required
               maxlength="32" placeholder="e.g. crate" @disabled($unit->exists && $unit->isInUse())>
      </label>

      <label>
        Label
        <input type="text" name="label" value="{{ old('label', $unit->label) }}" required maxlength="64">
      </label>

      <label>
        Family
        <select name="family" @disabled($unit->exists && $unit->isInUse())>
          @foreach (\App\Enums\UnitFamily::cases() as $option)
            <option value="{{ $option->value }}" @selected(old('family', $unit->family?->value) === $option->value)>
              {{ $option->label() }}
            </option>
          @endforeach
        </select>
      </label>

      <label>
        Multiplier
        <input type="number" name="base_multiplier" value="{{ old('base_multiplier', $unit->base_multiplier ?? 1) }}"
               step="any" min="0" required @disabled($unit->exists && $unit->isInUse())>
      </label>

      <label>
        Symbol
        <input type="text" name="symbol" value="{{ old('symbol', $unit->symbol) }}" maxlength="32" placeholder="crate">
      </label>

      <label>
        Plural
        <input type="text" name="plural" value="{{ old('plural', $unit->plural) }}" maxlength="64" placeholder="crates">
      </label>

      <label>
        Sort order
        <input type="number" name="sort" value="{{ old('sort', $unit->sort ?? 0) }}" min="0" max="9999">
      </label>
    </div>

    <div style="margin-top:12px;display:grid;gap:8px">
      <label class="check">
        <input type="checkbox" name="is_decimal" value="1" @checked(old('is_decimal', $unit->is_decimal ?? true))>
        Allow fractional quantities
      </label>
      <label class="check">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $unit->is_active ?? true))>
        Offer this unit on the forms
      </label>
    </div>

    <div class="row">
      <button class="btn" type="submit">{{ $unit->exists ? 'Save unit' : 'Add unit' }}</button>
      <a class="btn alt" href="{{ route('units.index') }}">Cancel</a>
    </div>
  </form>
@endsection
