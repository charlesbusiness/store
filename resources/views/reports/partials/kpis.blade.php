{{-- The four headline figures for the selected period. Stock value is
     deliberately all-time rather than period-only: it answers what is on the
     shelf now, while the other three answer how the period went. --}}
<div class="kp">
  <div>
    <small>Sales</small>
    <strong>@money($summary->sales)</strong>
  </div>
  <div>
    <small>Purchases</small>
    <strong>@money($summary->purchases)</strong>
  </div>
  <div>
    <small>Sales &minus; Purchases</small>
    <strong class="{{ $summary->isDeficit() ? 'neg' : 'pos' }}">@money($summary->difference())</strong>
  </div>
  <div>
    <small>Stock value</small>
    <strong>@money($summary->stockValue)</strong>
  </div>
</div>
