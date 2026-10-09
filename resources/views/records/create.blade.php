@extends('layouts.app')

@section('title', $form['isSale'] ? 'Sell' : 'Buy')

@section('content')
  <form method="POST" action="{{ $form['isSale'] ? route('sales.store') : route('purchases.store') }}"
        x-data="recordForm(window.RecordForm)" novalidate>
    @csrf

    @include('records.partials.line-form', ['form' => $form])

    <div class="card" x-show="lines.length > 0 || hasDraft" x-cloak>
      <div class="card-head">
        <h2>{{ $form['isSale'] ? 'Current sale' : 'Current purchase' }}</h2>
        <button type="button" class="btn alt sm" @click="clear()">Clear</button>
      </div>

      @include('records.partials.cart', ['form' => $form])

      <div class="row">
        <button class="btn" type="submit" :disabled="lines.length === 0">
          Save {{ $form['isSale'] ? 'sale' : 'purchase' }}
        </button>
        {{-- Says how many lines are actually saved. The draft is excluded on
             purpose: it is not part of the sale until Add line is pressed. --}}
        <span class="mut" x-text="lines.length + ' line(s)'">0 line(s)</span>
      </div>
    </div>
  </form>

  @include('records.partials.recent', ['form' => $form])
@endsection

@push('scripts')
  <script>window.RecordForm = @json($form);</script>
@endpush
