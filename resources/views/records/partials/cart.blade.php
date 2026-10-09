{{-- Lines staged on the form, plus the hidden inputs that post them back. The
     index comes from x-for, so removing a line renumbers the rest.

     The line still being typed appears above them as a draft: live figures, but
     not yet part of the sale. Nothing hidden is posted from that row, so a
     half-typed line can never reach the server. On a sale each row also carries
     the margin, from the same average cost the server will freeze onto the line
     when it saves -- so the number previewed here is the number that lands. --}}
<template x-if="draft">
  <div class="invoice-preview">
    <div>
      <span>Item</span>
      <strong x-text="draft.label"></strong>
    </div>
    <div>
      <span>Qty</span>
      <strong>
        <span x-text="draft.qtyLabel"></span> <span x-text="draft.unitLabel"></span>
      </strong>
      <small x-show="draft.equivalents" x-text="draft.equivalents"></small>
    </div>
    @if ($form['isSale'])
      <div>
        <span>Cost / unit</span>
        <strong x-text="money(draft.unit_cost)"></strong>
      </div>
      <div>
        <span>Sell Price / unit</span>
        <strong x-text="money(draft.selling_price ?? draft.price)"></strong>
      </div>
      <div>
        <span>Total</span>
        <strong x-text="money(draft.total)"></strong>
      </div>
      <div>
        <span>Profit</span>
        <strong :class="draft.profitClass" x-text="draft.profitLabel"></strong>
      </div>
    @else
      <div>
        <span>Cost / unit</span>
        <strong x-text="money(draft.price)"></strong>
      </div>
      <div>
        <span>Total</span>
        <strong x-text="money(draft.total)"></strong>
      </div>
    @endif
    <button type="button" class="btn sm" @click="add()">Add</button>
  </div>
</template>

<div class="sc" x-show="lines.length > 0" x-cloak>
  <table>
    <thead>
      <tr>
        <th>Item</th>
        <th class="r">Qty</th>
        @if ($form['isSale'])
          <th class="r">Cost / unit</th>
          <th class="r">Sell Price / unit</th>
          <th class="r">Total</th>
          <th class="r">Profit</th>
        @else
          <th class="r">Cost / unit</th>
          <th class="r">Total</th>
        @endif
        <th></th>
      </tr>
    </thead>
    <tbody>
      <template x-for="(line, index) in lines" :key="index">
        <tr>
          {{-- The inputs live in a hidden cell: a bare <input> inside <tr> is
               invalid markup and browsers hoist it out of the table. --}}
          <td hidden>
            <template x-if="line.itemId">
              <input type="hidden" :name="'lines[' + index + '][item_id]'" :value="line.itemId">
            </template>
            <template x-if="line.name">
              <input type="hidden" :name="'lines[' + index + '][name]'" :value="line.name">
            </template>
            <input type="hidden" :name="'lines[' + index + '][qty]'" :value="line.qty">
            <input type="hidden" :name="'lines[' + index + '][unit_code]'" :value="line.unit">
            @if ($form['isSale'])
              <input type="hidden" :name="'lines[' + index + '][unit_price]'" :value="line.selling_price ?? line.price">
            @else
              <input type="hidden" :name="'lines[' + index + '][unit_price]'" :value="line.price">
              <input type="hidden" :name="'lines[' + index + '][selling_price]'" :value="line.selling_price">
            @endif
            <input type="hidden" :name="'lines[' + index + '][unit_cost]'" :value="line.unit_cost">

            {{-- The pack declaration is optional, so only the slots that were
                 filled in post anything. --}}
            <template x-for="(pack, slot) in line.packs" :key="slot">
              <span>
                <input type="hidden" :name="'lines[' + index + '][inner_units][' + slot + '][unit]'" :value="pack.unit">
                <input type="hidden" :name="'lines[' + index + '][inner_units][' + slot + '][per]'" :value="pack.per">
                <input type="hidden" :name="'lines[' + index + '][inner_units][' + slot + '][cost]'" :value="pack.cost">
                <input type="hidden" :name="'lines[' + index + '][inner_units][' + slot + '][sellingPrice]'" :value="pack.sellingPrice">
              </span>
            </template>
          </td>

          <td x-text="line.label"></td>
          <td class="r">
            <span x-text="formatQty(line.qty)"></span> <span x-text="line.unitLabel"></span>
            <div class="mut" style="font-size:12px" x-show="line.equivalents" x-text="line.equivalents"></div>
          </td>
          @if ($form['isSale'])
            <td class="r" x-text="money(line.unit_cost)"></td>
            <td class="r" x-text="money(line.selling_price ?? line.price)"></td>
            <td class="r" x-text="money(line.total)"></td>
            <td class="r" :class="line.profitClass" x-text="line.profitLabel"></td>
          @else
            <td class="r" x-text="money(line.price)"></td>
            <td class="r" x-text="money(line.total)"></td>
          @endif
          <td>
            <button type="button" class="x" @click="remove(index)" title="Remove this line">&times;</button>
          </td>
        </tr>
      </template>
    </tbody>
    <tfoot>
      <tr>
        @if ($form['isSale'])
          <td colspan="4">Sale total</td>
          <td class="r" x-text="money(total)"></td>
          <td class="r" :class="totalProfitClass" x-text="totalProfitLabel"></td>
          <td></td>
        @else
          <td colspan="3">Purchase total</td>
          <td class="r" x-text="money(total)"></td>
          <td></td>
        @endif
      </tr>
    </tfoot>
  </table>
</div>
