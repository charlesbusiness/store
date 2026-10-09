{{-- What moved in the selected period, biggest value first. --}}
@php
    $tables = [
        ['title' => 'Items sold', 'rows' => $sold],
        ['title' => 'Items purchased', 'rows' => $bought],
    ];
@endphp

@foreach ($tables as $table)
  <div class="card">
    <h2>{{ $table['title'] }}</h2>

    @if (count($table['rows']))
      <div class="sc">
        <table>
          <thead>
            <tr>
              <th>Item</th>
              <th class="r">Quantity</th>
              <th class="r">Amount</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($table['rows'] as $row)
              <tr>
                <td>{{ $row['item']->name }}</td>
                <td class="r">@qty($row['display_qty']) @unit($row['unit'])</td>
                <td class="r">@money($row['total'])</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <div class="e">Nothing recorded for this period.</div>
    @endif
  </div>
@endforeach
