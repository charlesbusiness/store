{{-- Server-rendered bar chart, as in the original: one column per bucket with
     a sales bar and a purchase bar scaled against the tallest value. --}}
<div class="card">
  <h2>Trend</h2>

  @if ($summary->hasActivity())
    <div class="ch">
      @foreach ($summary->trendBars() as $bucket)
        <div title="Sales @money($bucket['sales']) &middot; Purchases @money($bucket['purchases'])">
          <div class="bars">
            <i style="height:{{ $bucket['sales_height'] }}"></i>
            <i class="p" style="height:{{ $bucket['purchases_height'] }}"></i>
          </div>
          {{ $bucket['label'] }}
        </div>
      @endforeach
    </div>
  @else
    <div class="e">No activity in this period.</div>
  @endif

  <div class="lg">
    <span></span>Sales<span class="p"></span>Purchases
  </div>
</div>
