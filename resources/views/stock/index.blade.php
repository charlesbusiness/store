@extends('layouts.app')

@section('title', 'Stock')

@section('content')
  <div class="card">
    <div class="card-head">
      <h2>Inventory</h2>
      <a class="btn alt sm" href="{{ route('items.index') }}">Item catalogue</a>
    </div>

    @if ($traded->isNotEmpty())
      <div class="sc">
        <table>
          <thead>
            <tr>
              <th>Item</th>
              <th class="r">In stock</th>
              <th class="r">Cost / unit</th>
              <th class="r">Stock value</th>
              <th class="r">Total purchased</th>
              <th class="r">Sold</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($traded as $position)
              <tr>
                <td>
                  <span class="line-name">
                    {{ $position->item->name }}
                    @if ($position->isOutOfStock())
                      <span class="tag">OUT</span>
                    @endif
                  </span>
                </td>
                <td class="r">
                  @qty($position->displayStock()) @unit($position->displayUnit)@if (($breakdowns[$position->item->id] ?? null))
                    <span class="mut" style="font-size:12px">{{ $breakdowns[$position->item->id] }}</span>
                  @endif
                </td>
                <td class="r">@money($position->costPerDisplayUnit())/@unit($position->displayUnit)</td>
                <td class="r">@money($position->stockValue())</td>
                <td class="r">@money($position->cost)</td>
                <td class="r">@qty($position->displaySold()) @unit($position->displayUnit)</td>
              </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr>
              <td>Total</td>
              <td class="r"></td>
              <td class="r"></td>
              <td class="r">@money($stockValue)</td>
              <td class="r">@money($totalPurchased)</td>
              <td class="r"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    @else
      <div class="e">No items yet. Record a purchase to start.</div>
    @endif
  </div>
@endsection
