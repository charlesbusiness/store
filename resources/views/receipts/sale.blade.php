<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Sales Receipt</title>
  <style>
    @page { margin: 24px; }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: "DejaVu Sans", sans-serif;
      font-size: 11px;
      line-height: 1.45;
      color: #111827;
    }
    .header { border-bottom: 2px solid #111827; padding-bottom: 12px; margin-bottom: 16px; }
    .shop { font-size: 20px; font-weight: bold; letter-spacing: 0.5px; margin: 0; }
    .title { font-size: 12px; color: #4b5563; margin: 2px 0 0; text-transform: uppercase; letter-spacing: 1px; }
    .meta { width: 100%; margin-top: 12px; border-collapse: collapse; }
    .meta td { padding: 1px 0; vertical-align: top; }
    .meta .label { color: #6b7280; width: 45%; }
    .meta .value { text-align: right; font-weight: bold; }
    table.items { width: 100%; border-collapse: collapse; }
    table.items thead th {
      text-align: left;
      font-size: 9px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #6b7280;
      border-bottom: 1px solid #d1d5db;
      padding: 6px 4px;
    }
    table.items tbody td {
      padding: 7px 4px;
      border-bottom: 1px solid #e5e7eb;
      vertical-align: top;
    }
    table.items .r { text-align: right; }
    table.items .nowrap { white-space: nowrap; }
    .total {
      margin-top: 16px;
      border-top: 2px solid #111827;
      padding-top: 10px;
      text-align: right;
    }
    .total .label { font-size: 11px; color: #4b5563; text-transform: uppercase; letter-spacing: 1px; }
    .total .value { font-size: 18px; font-weight: bold; }
    .footer { margin-top: 22px; text-align: center; color: #6b7280; font-size: 10px; }
  </style>
</head>
<body>
  <div class="header">
    <h1 class="shop">Zaveb Horizon Integrated Solutions</h1>
    <p class="title">Sales Receipt</p>

    <table class="meta">
      @if (filled($customerName ?? null))
        <tr>
          <td class="label">Customer</td>
          <td class="value">{{ $customerName }}</td>
        </tr>
      @endif
      <tr>
        <td class="label">Date</td>
        <td class="value">{{ $sale->transacted_on?->format('M j, Y') }}</td>
      </tr>
    </table>
  </div>

  <table class="items">
    <thead>
      <tr>
        <th>Item</th>
        <th class="r">Qty</th>
        <th class="r">Unit Price ({{ config('stockly.currency') }})</th>
        <th class="r">Total ({{ config('stockly.currency') }})</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($sale->lines as $line)
        <tr>
          <td>{{ $line->item?->name ?? 'Item #'.$line->item_id }}</td>
          <td class="r">{{ \App\Support\Concerns\FormatsValues::plain($line->qty, (int) config('stockly.quantity_decimals', 3)) }}</td>
          <td class="r">{{ \App\Support\Concerns\FormatsValues::plain($line->unit_price, 2) }}</td>
          <td class="r">{{ \App\Support\Concerns\FormatsValues::plain($line->total, 2) }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="4" class="r">No items.</td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <div class="total">
    <div class="label">Total Sales ({{ config('stockly.currency') }})</div>
    <div class="value">{{ \App\Support\Concerns\FormatsValues::plain($sale->total(), 2) }}</div>
  </div>

  <div class="footer">Thank you for your patronage.</div>
</body>
</html>
