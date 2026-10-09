@extends('layouts.app')

@section('title', 'Items')

@section('content')
  <div class="card">
    <div class="card-head">
      <h2>Item catalogue</h2>
      <a class="btn sm" href="{{ route('items.create') }}">Add item</a>
    </div>

    <form method="GET" action="{{ route('items.index') }}" class="seg">
      <input type="search" name="q" value="{{ $search }}" placeholder="Search items" style="max-width:260px">
      <button class="btn alt sm" type="submit">Search</button>
      @if ($search !== '')
        <a class="mut" href="{{ route('items.index') }}">Clear</a>
      @endif
    </form>

    @if ($positions->isNotEmpty())
      <div class="sc">
        <table>
          <thead>
            <tr>
              <th>Item</th>
              <th>Family</th>
              <th>Traded in</th>
              <th class="r">In stock</th>
              <th class="r">Stock value</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @foreach ($positions as $position)
              <tr>
                <td>
                  <span class="line-name">
                    {{ $position->item->name }}
                    @unless ($position->item->is_active)
                      <span class="tag mute">RETIRED</span>
                    @endunless
                  </span>
                  @if ($position->item->pack_size)
                    <div class="mut" style="font-size:12px">{{ $position->item->pack_size }}</div>
                  @endif
                </td>
                <td class="mut">{{ $position->item->family->label() }}</td>
                <td>@unit($position->displayUnit)</td>
                <td class="r">
                  @if ($position->hasTraded())
                    @qty($position->displayStock()) @unit($position->displayUnit)
                    @if ($position->isOutOfStock())
                      <span class="tag">OUT</span>
                    @endif
                  @else
                    <span class="mut">&mdash;</span>
                  @endif
                </td>
                <td class="r">@money($position->stockValue())</td>
                <td>
                  <a class="btn alt sm" href="{{ route('items.edit', $position->item) }}">Edit</a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <div class="e">
        {{ $search !== '' ? 'No items match that search.' : 'No items yet. They are created automatically when you record a purchase.' }}
      </div>
    @endif
  </div>
@endsection
