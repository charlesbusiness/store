@extends('layouts.app')

@section('title', 'Reports')

@section('content')
  <form method="GET" action="{{ route('reports.index') }}" class="seg">
    <div class="sg">
      @foreach ($modes as $option)
        <a href="{{ route('reports.index', ['mode' => $option->value, 'anchor' => $summary->anchor->toDateString()]) }}"
           class="{{ $option === $summary->mode ? 'on' : '' }}">{{ $option->label() }}</a>
      @endforeach
    </div>
    <input type="date" name="anchor" value="{{ $summary->anchor->toDateString() }}"
           onchange="this.form.submit()" aria-label="Period end date">
    <noscript><button class="btn sm" type="submit">Go</button></noscript>
    <span class="mut">{{ $summary->periodLabel }}</span>
  </form>

  @include('reports.partials.kpis', ['summary' => $summary])
  @include('reports.partials.trend', ['summary' => $summary])
  @include('reports.partials.items', [
      'summary' => $summary,
      'sold' => $summary->itemsSold,
      'bought' => $summary->itemsBought,
  ])
@endsection
