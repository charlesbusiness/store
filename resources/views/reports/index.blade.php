@extends('layouts.app')

@section('title', 'Reports')

@section('content')
  <form method="GET" action="{{ route('reports.index') }}" class="seg">
    <div class="sg">
      @foreach ($modes as $option)
        <a href="{{ route('reports.index', ['mode' => $option->value] + ($dateRange ? ['start_date' => $dateRange[0]->toDateString(), 'end_date' => $dateRange[1]->toDateString()] : ['anchor' => $summary->anchor->toDateString()])) }}"
           class="{{ $option === $summary->mode ? 'on' : '' }}">{{ $option->label() }}</a>
      @endforeach
    </div>
    <input type="date" name="start_date" value="{{ $dateRange ? $dateRange[0]->toDateString() : '' }}"
           onchange="this.form.submit()" aria-label="Period start date" placeholder="Start">
    <input type="date" name="end_date" value="{{ $dateRange ? $dateRange[1]->toDateString() : '' }}"
           onchange="this.form.submit()" aria-label="Period end date" placeholder="End">
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
