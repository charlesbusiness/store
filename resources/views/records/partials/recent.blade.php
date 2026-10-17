{{-- The last few transactions of this type, newest first, each deletable line by
     line. Deleting a line adjusts stock automatically, because stock is derived from
     the ledger rather than stored.

     Grouped by date, then by transaction. Lines are listed with a "Sale Total"
     column that spans all lines of the transaction. The limit counts transactions,
     not lines, so a subtotal is never shown for a sale whose lines have been cut
     off by it.

     A sale also shows what each line cost and what it made. Both come off the
     line's own snapshot, frozen when the sale was written, so they stay true after
     the item is bought again at a different price. Cost and profit are null for
     lines recorded before that snapshot existed, and null is shown as a dash
     rather than zero -- zero would read as "sold at no cost", which is a different
     claim from "we do not know". --}}
<div class="card">
  <div class="card-head">
    <h2>Recent {{ $form['isSale'] ? 'sales' : 'purchases' }}</h2>
    @if ($form['recentTotal'] > count($form['recent']))
      <span class="mut">showing {{ count($form['recent']) }} of {{ $form['recentTotal'] }}</span>
    @endif
  </div>

  {{-- Search filters --}}
  @if ($form['isSale'])
    <form method="GET" class="filter-form" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px; align-items:end;">
      <label class="form-field" style="min-width:180px;">
        <span>Item name</span>
        <input type="search" name="search" value="{{ $form['recentSearch'] ?? '' }}" placeholder="Search items...">
      </label>
      <label class="form-field" style="min-width:140px;">
        <span>From</span>
        <input type="date" name="date_from" value="{{ $form['recentDateFrom'] ?? '' }}">
      </label>
      <label class="form-field" style="min-width:140px;">
        <span>To</span>
        <input type="date" name="date_to" value="{{ $form['recentDateTo'] ?? '' }}">
      </label>
      <label class="form-field" style="min-width:140px;">
        <span>Status</span>
        <select name="status" style="height:38px; padding:6px 8px; border:1px solid #d1d5db; border-radius:6px;">
          <option value="all" {{ ($form['recentStatus'] ?? 'all') === 'all' ? 'selected' : '' }}>All</option>
          <option value="pending" {{ ($form['recentStatus'] ?? 'all') === 'pending' ? 'selected' : '' }}>Pending</option>
          <option value="paid" {{ ($form['recentStatus'] ?? 'all') === 'paid' ? 'selected' : '' }}>Paid</option>
        </select>
      </label>
      <button type="submit" class="btn sm" style="height:38px;">Filter</button>
      <a href="{{ route('sales.create') }}" class="btn alt sm" style="height:38px;">Clear</a>
    </form>
  @else
    <form method="GET" class="filter-form" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px; align-items:end;">
      <label class="form-field" style="min-width:180px;">
        <span>Item name</span>
        <input type="search" name="search" value="{{ $form['recentSearch'] ?? '' }}" placeholder="Search items...">
      </label>
      <label class="form-field" style="min-width:140px;">
        <span>From</span>
        <input type="date" name="date_from" value="{{ $form['recentDateFrom'] ?? '' }}">
      </label>
      <label class="form-field" style="min-width:140px;">
        <span>To</span>
        <input type="date" name="date_to" value="{{ $form['recentDateTo'] ?? '' }}">
      </label>
      <button type="submit" class="btn sm" style="height:38px;">Filter</button>
      <a href="{{ route('purchases.create') }}" class="btn alt sm" style="height:38px;">Clear</a>
    </form>
  @endif

  @if (count($form['recent']))
    <div class="sc">
      <table>
        <thead>
          <tr>
            <th>Date</th>
            @if ($form['isSale'])
              <th>Sale Total</th>
            @else
              <th>Purchase Total</th>
            @endif
            <th>Item</th>
            <th class="r">Qty</th>
            @if ($form['isSale'])
              <th class="r">Cost Price</th>
              <th class="r">Sales Price</th>
              <th class="r">Profit/Line</th>
            @else
              <th class="r">Cost Price</th>
            @endif
            <th class="r">Line Total</th>
            <th></th>
          </tr>
        </thead>

        <tbody>
          @php
            $grouped = collect($form['recent'])->groupBy('date');
          @endphp

          @foreach ($grouped as $date => $sales)
            @php
              // Calculate date rowspan: total lines across all transactions for this date
              $dateRowspan = 0;
              foreach ($sales as $sale) {
                $dateRowspan += count($sale['lines']);
              }
              $firstDateRow = true;
            @endphp

            @foreach ($sales as $sale)
              @php
                $transactionLineCount = count($sale['lines']);
                $firstTransactionRow = true;
              @endphp

              @foreach ($sale['lines'] as $line)
                <tr>
                  @if ($firstDateRow)
                    <td rowspan="{{ $dateRowspan }}">{{ $sale['dateLabel'] }}</td>
                    @php $firstDateRow = false; @endphp
                  @endif

                  @if ($firstTransactionRow)
                    <td rowspan="{{ $transactionLineCount }}">
                      @if ($form['isSale'])
                        @php
                          $cost = $sale['cost'] !== null
                            ? config('stockly.currency') . number_format($sale['cost'], 2)
                            : '&mdash;';
                          $total = config('stockly.currency') . number_format($sale['total'], 2);
                          $profit = $sale['profit'] !== null
                            ? config('stockly.currency') . number_format($sale['profit'], 2)
                            : '&mdash;';
                          $profitClass = $sale['profit'] !== null && $sale['profit'] < 0 ? 'neg' : 'pos';
                        @endphp
                        <div class="totals-cell">
                          <span class="cost">Cost: {{ $cost }}</span>
                          <span class="total">Total: {{ $total }}</span>
                          <span class="profit {{ $profitClass }}">Profit: {{ $profit }}</span>
                        </div>

                        <div class="paid-status">
                          @if ($sale['paid'])
                            <span class="badge ok">Paid</span>
                          @else
                            <span class="badge warn">Unpaid</span>
                            <form method="POST" action="{{ route('records.paid', $sale['id']) }}"
                                  data-confirm-text="Mark this sale as paid?"
                                  data-confirm-accept-text="Mark Paid"
                                  onsubmit="return Stockly.confirmDanger(event)">
                              @csrf
                              @method('PATCH')
                              <button class="btn sm" type="submit">Mark Paid</button>
                            </form>
                          @endif
                        </div>
                      @else
                        @php
                          $total = config('stockly.currency') . number_format($sale['total'], 2);
                        @endphp
                        <div class="totals-cell">
                          <span class="total">Total: {{ $total }}</span>
                        </div>
                      @endif
                    </td>
                    @php $firstTransactionRow = false; @endphp
                  @endif

                  <td>{{ $line['name'] }}</td>
                  <td class="r">{{ $line['quantity'] }}</td>
                  @if ($form['isSale'])
                    @php
                      $costPrice = $line['cost'] !== null && $line['qty'] > 0
                        ? round($line['cost'] / $line['qty'], 2)
                        : null;
                      $salesPrice = $line['qty'] > 0
                        ? round($line['total'] / $line['qty'], 2)
                        : null;
                    @endphp
                    <td class="r">
                      @if ($costPrice !== null) {{ config('stockly.currency') . number_format($costPrice, 2) }} @else &mdash; @endif
                    </td>
                    <td class="r">
                      @if ($salesPrice !== null) {{ config('stockly.currency') . number_format($salesPrice, 2) }} @else &mdash; @endif
                    </td>
                    <td class="r {{ $line['profit'] === null ? '' : ($line['profit'] < 0 ? 'neg' : 'pos') }}">
                      @if ($line['profit'] === null) &mdash; @else {{ config('stockly.currency') . number_format($line['profit'], 2) }} @endif
                    </td>
                  @else
                    @php
                      $costPrice = $line['qty'] > 0
                        ? round($line['total'] / $line['qty'], 2)
                        : null;
                    @endphp
                    <td class="r">
                      @if ($costPrice !== null) {{ config('stockly.currency') . number_format($costPrice, 2) }} @else &mdash; @endif
                    </td>
                  @endif
                  <td class="r">{{ config('stockly.currency') . number_format($line['total'], 2) }}</td>
                  <td>
                    <form method="POST" action="{{ route('records.lines.destroy', $line['id']) }}"
                          data-confirm-text="Delete {{ $line['name'] }} from the {{ $form['isSale'] ? 'sale' : 'purchase' }} ledger? Stock will be adjusted."
                          onsubmit="return Stockly.confirmDanger(event)">
                      @csrf
                      @method('DELETE')
                      <button class="btn alt sm" type="submit"
                              aria-label="Delete {{ $line['name'] }} from the {{ $form['isSale'] ? 'sale' : 'purchase' }} ledger">Delete</button>
                    </form>
                  </td>
                </tr>
              @endforeach
            @endforeach
          @endforeach
        </tbody>
      </table>
    </div>

    {{-- Pagination --}}
    @if ($form['recentTotalPages'] > 1)
      <div class="pagination" style="display:flex; justify-content:center; gap:8px; margin-top:16px; flex-wrap:wrap;">
        @php
          $baseParams = ['page' => 1];
          if ($form['isSale']) {
            $baseParams = array_merge($baseParams, array_filter([
              'search' => $form['recentSearch'] ?? '',
              'date_from' => $form['recentDateFrom'] ?? '',
              'date_to' => $form['recentDateTo'] ?? '',
              'status' => isset($form['recentStatus']) && $form['recentStatus'] !== 'all' ? $form['recentStatus'] : null,
            ]));
          } else {
            $baseParams = array_merge($baseParams, array_filter([
              'search' => $form['recentSearch'] ?? '',
              'date_from' => $form['recentDateFrom'] ?? '',
              'date_to' => $form['recentDateTo'] ?? '',
            ]));
          }
        @endphp
        @if ($form['recentPage'] > 1)
          <a href="{{ route($form['isSale'] ? 'sales.create' : 'purchases.create', array_merge($baseParams, ['page' => $form['recentPage'] - 1])) }}" class="btn alt sm">Prev</a>
        @endif

        @for ($i = 1; $i <= $form['recentTotalPages']; $i++)
          <a href="{{ route($form['isSale'] ? 'sales.create' : 'purchases.create', array_merge($baseParams, ['page' => $i])) }}"
             class="{{ $i === $form['recentPage'] ? 'btn' : 'btn alt' }} sm"
             style="min-width:40px; text-align:center;">{{ $i }}</a>
        @endfor

        @if ($form['recentPage'] < $form['recentTotalPages'])
          <a href="{{ route($form['isSale'] ? 'sales.create' : 'purchases.create', array_merge($baseParams, ['page' => $form['recentPage'] + 1])) }}" class="btn alt sm">Next</a>
        @endif
      </div>
    @endif
  @else
    <div class="e">Nothing yet.</div>
  @endif
</div>