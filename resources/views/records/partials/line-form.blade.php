{{-- The single-line entry form. A purchase types the item name freely, which
     is how new items come into existence; a sale picks from items that have
     stock. --}}
<div class="card">
  <h2>{{ $form['isSale'] ? 'Record a sale' : 'Record a purchase' }}</h2>

  @if ($form['isSale'] && count($form['items']) === 0)
    <div class="e">No stock available. Record a purchase first.</div>
  @else
    <div class="form-rows">

      {{-- Row 1: Item name, Date, Measurement family --}}
      <div class="form-row">
        @if ($form['isSale'])
          <label class="form-field">
            Item
            <select x-model="itemId" @change="syncItem()">
              <option value="">Choose an item</option>
              <template x-for="item in items" :key="item.id">
                <option :value="item.id" x-text="item.name + ' — ' + item.stockLabel + ' left'"></option>
              </template>
            </select>
          </label>
        @else
          <label class="form-field">
            Item
            <textarea rows="2" list="item-names" x-model="name" @input="syncItem()"
                      placeholder="e.g. Rice" autocomplete="off"></textarea>
            <datalist id="item-names">
              <template x-for="item in items" :key="item.id">
                <option :value="item.name"></option>
              </template>
            </datalist>
          </label>
        @endif

        <label class="form-field">
          Date
          <input type="date" name="transacted_on" x-model="date" required>
        </label>

        <label class="form-field">
          Measurement
          {{-- config.families, not a bare families: the component exposes no such
               property, so x-for over it iterated undefined and the dropdown came
               out empty. Reading straight off config means any key the server sends
               is reachable without a matching declaration on the component. --}}
          <select x-model="measurement" @change="setMeasurement(measurement)" :disabled="!!currentItem">
            <option value="">Choose</option>
            <template x-for="family in config.families" :key="family.value">
              <option :value="family.value" x-text="family.label"></option>
            </template>
          </select>
        </label>
      </div>

      {{-- Row 2: Main Unit, Quantity, Cost per unit (+ Selling price for sales) --}}
      <div class="form-row" x-show="measurement || currentItem">
        <label class="form-field">
          Unit
          <select x-model="unit" @change="onUnitChange()" :disabled="!measurement && !currentItem">
            <option value="">Choose</option>
            <template x-for="option in unitOptions" :key="option.code">
              <option :value="option.code" x-text="option.label"></option>
            </template>
          </select>
        </label>

        <label class="form-field">
          Quantity
          <input type="number" min="0" step="any" inputmode="decimal" x-model="qty" placeholder="0" required>
        </label>

        @if ($form['isSale'])
          {{-- Sell page: Cost per unit (readonly) + Selling price per unit --}}
          <label class="form-field">
            <span x-text="unitCostLabel">Cost per unit</span>
            <input type="number" min="0" step="any" inputmode="decimal" x-model="unitCost" readonly placeholder="Auto-filled from purchase">
          </label>

          <label class="form-field">
            <span x-text="sellingPriceLabel">Selling price per unit</span>
            <input type="number" min="0" step="any" inputmode="decimal" x-model="sellingPrice" placeholder="Optional - defaults to cost">
            <span class="pv mut" x-show="purchaseSellingPriceDisplay" x-text="purchaseSellingPriceDisplay"></span>
          </label>
        @else
          {{-- Buy page: Cost per unit + Selling price per unit --}}
          <label class="form-field">
            <span x-text="unitCostLabel">Cost per unit</span>
            <input type="number" min="0" step="any" inputmode="decimal" x-model="unitCost" placeholder="0" required>
          </label>

          <label class="form-field">
            <span x-text="sellingPriceLabel">Selling price per unit</span>
            <input type="number" min="0" step="any" inputmode="decimal" x-model="sellingPrice" placeholder="Optional - defaults to cost">
          </label>
        @endif
      </div>

      {{-- Row 3 & 4: Inner units (purchases only) --}}
      @unless ($form['isSale'])
        <div class="form-row inner-unit-row" x-show="measurement || currentItem">
          <label class="form-field">
            Inner unit 1
            <select x-model="packs[0].unit" :disabled="!measurement && !currentItem">
              <option value="">None</option>
              <template x-for="option in innerUnitOptions" :key="option.code">
                <option :value="option.code" x-text="option.label"></option>
              </template>
            </select>
          </label>

          <label class="form-field">
            <span x-text="'How many per ' + tradingUnitLabel()">How many per unit</span>
            <input type="number" min="1" step="1" inputmode="numeric"
                   x-model="packs[0].per" placeholder="e.g. 12">
          </label>

          <label class="form-field">
            <span x-text="innerUnitCostLabel(0)">Cost per unit</span>
            <input type="number" min="0" step="any" inputmode="decimal"
                   x-model="packs[0].cost" :disabled="!packs[0].unit" :required="packs[0].unit !== ''" placeholder="0">
          </label>

          <label class="form-field">
            <span x-text="'Selling price per ' + (innerUnitOptions.find(o => o.code === packs[0].unit)?.label || 'unit')">Selling price per unit</span>
            <input type="number" min="0" step="any" inputmode="decimal"
                   x-model="packs[0].sellingPrice" :disabled="!packs[0].unit" placeholder="Optional">
          </label>
        </div>

        <div class="form-row inner-unit-row" x-show="measurement || currentItem">
          <label class="form-field">
            Inner unit 2
            <select x-model="packs[1].unit" :disabled="!measurement && !currentItem">
              <option value="">None</option>
              <template x-for="option in innerUnitOptions" :key="option.code">
                <option :value="option.code" x-text="option.label"></option>
              </template>
            </select>
          </label>

          <label class="form-field">
            <span x-text="'How many per ' + tradingUnitLabel()">How many per unit</span>
            <input type="number" min="1" step="1" inputmode="numeric"
                   x-model="packs[1].per" placeholder="e.g. 12">
          </label>

          <label class="form-field">
            <span x-text="innerUnitCostLabel(1)">Cost per unit</span>
            <input type="number" min="0" step="any" inputmode="decimal"
                   x-model="packs[1].cost" :disabled="!packs[1].unit" :required="packs[1].unit !== ''" placeholder="0">
          </label>

          <label class="form-field">
            <span x-text="'Selling price per ' + (innerUnitOptions.find(o => o.code === packs[1].unit)?.label || 'unit')">Selling price per unit</span>
            <input type="number" min="0" step="any" inputmode="decimal"
                   x-model="packs[1].sellingPrice" :disabled="!packs[1].unit" placeholder="Optional">
          </label>
        </div>

        <div class="pv" x-text="packHint()"></div>
      @endunless

      <div class="pv" x-text="preview"></div>
      <div class="pv bad" x-show="shortfall" x-text="shortfall" x-cloak></div>

      <div class="row">
        {{-- Disabled until there is something addable, because the click would only
             be refused with a toast. Same rule as the cart's draft row, so the two
             never disagree about whether a line is ready. --}}
        <button type="button" class="btn" @click="add()" :disabled="!draftComplete">Add line</button>
      </div>
    </div>
  @endif
</div>