{{-- Lines staged on the form, plus the hidden inputs that post them back. The
     index comes from x-for, so removing a line renumbers the rest.

     The line still being typed appears above them as a draft: live figures, but
     not yet part of the sale. Nothing hidden is posted from that row, so a
     half-typed line can never reach the server. On a sale each row also carries
     the margin, from the same average cost the server will freeze onto the line
     when it saves -- so the number previewed here is the number that lands. --}}
<div class="sc">
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
      <template x-if="draft">
        <tr class="draft">
          <td x-text="draft.label"></td>
          <td class="r">
            <span x-text="draft.qtyLabel"></span> <span x-text="draft.unitLabel"></span>
            <div class="mut" style="font-size:12px" x-show="draft.equivalents" x-text="draft.equivalents"></div>
          </td>
          @if ($form['isSale'])
            <td class="r" x-text="money(draft.unit_cost)"></td>
            <td class="r" x-text="money(draft.selling_price ?? draft.price)"></td>
            <td class="r" x-text="money(draft.total)"></td>
            <td class="r" :class="draft.profitClass" x-text="draft.profitLabel"></td>
          @else
            <td class="r" x-text="money(draft.price)"></td>
            <td class="r" x-text="money(draft.total)"></td>
          @endif
          <td>
            <button type="button" class="x" @click="add()" title="Add this line to the sale">Add</button>
          </td>
        </tr>
      </template>

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
            @endif
            <input type="hidden" :name="'lines[' + index + '][unit_cost]'" :value="line.unit_cost">

            {{-- The pack declaration is optional, so only the slots that were
                 filled in post anything. --}}
            <template x-for="(pack, slot) in line.packs" :key="slot">
              <span>
                <input type="hidden" :name="'lines[' + index + '][inner_units][' + slot + '][unit]'" :value="pack.unit">
                <input type="hidden" :name="'lines[' + index + '][inner_units][' + slot + '][per]'" :value="pack.per">
                <input type="hidden" :name="'lines[' + index + '][inner_units][' + slot + '][cost]'" :value="pack.cost">
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
          <td colspan="3">{{ $form['isSale'] ? 'Sale total' : 'Purchase total' }}</td>
          <td class="r" x-text="money(total)"></td>
          <td class="r" :class="totalProfitClass" x-text="totalProfitLabel"></td>
        @else
          <td colspan="2">Purchase total</td>
          <td class="r" x-text="money(total)"></td>
        @endif
        <td></td>
      </tr>
      {{-- Kept apart from the row above so it is never mistaken for the amount
           that will be saved: the draft is not in it yet. --}}
      <template x-if="draft">
        <tr class="draft">
          @if ($form['isSale'])
            <td colspan="3">Including the line above</td>
            <td class="r" x-text="money(totalWithDraft)"></td>
            <td class="r" :class="draft.profitClass" x-text="draft.profitLabel"></td>
          @else
            <td colspan="2">Including the line above</td>
            <td class="r" x-text="money(totalWithDraft)"></td>
          @endif
          <td></td>
        </tr>
      </template>
    </tfoot>
  </table>
</div>