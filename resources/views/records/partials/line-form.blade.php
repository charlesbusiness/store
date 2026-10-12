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
          <label class="form-field searchable-select-field">
            Item
            <div class="searchable-select-wrapper" x-data="searchableSelect" 
                 :items="items"
                 x-model="itemId"
                 @model="syncItem()">
              <div class="searchable-select" @click.outside="handleClickOutside($event)">
                <button type="button" 
                        class="searchable-select-trigger" 
                        @click="open()"
                        @keydown="handleKeydown($event)"
                        :aria-expanded="isOpen">
                  <span x-text="getSelectedLabel()"></span>
                  <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 12 15 18 9"></polyline>
                  </svg>
                </button>
                <div class="searchable-select-dropdown" x-show="isOpen" x-cloak>
                  <input type="search" 
                         class="searchable-select-search" 
                         placeholder="Search items..."
                         x-ref="searchInput"
                         x-model="searchQuery"
                         @input="handleSearchInput()"
                         @keydown="handleInputKeydown($event)"
                         @click.stop>
                  <ul class="searchable-select-options" role="listbox">
                    <template x-for="(item, index) in filteredItems" :key="item.id">
                      <li class="searchable-select-option"
                          :class="{ highlighted: highlightedIndex === index }"
                          role="option"
                          @click="selectItem(item)"
                          @mouseenter="highlightedIndex = index"
                          x-ref="option">
                        <span x-text="item.name"></span>
                        <span class="stock-label" x-text="item.stockLabel + ' left'"></span>
                      </li>
                    </template>
                    <template x-if="filteredItems.length === 0">
                      <li class="searchable-select-empty">No items found</li>
                    </template>
                  </ul>
                </div>
              </div>
            </div>
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
      @if ($form['isSale'])
        <div class="form-row row-2-sale" x-show="measurement || currentItem">
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

          <label class="form-field">
            <span x-text="unitCostLabel">Cost per unit</span>
            <input type="number" min="0" step="any" inputmode="decimal" x-model="unitCost" @input="updateInnerUnitCosts" readonly placeholder="Auto-filled from purchase">
          </label>

          <label class="form-field">
            <!-- <span x-text="sellingPriceLabel">Selling price per unit</span> -->
            <span class="pv mut" x-show="purchaseSellingPriceDisplay" x-text="purchaseSellingPriceDisplay"></span>
            <input type="number" min="0" step="any" inputmode="decimal" x-model="sellingPrice" placeholder="Optional - defaults to cost">
          </label>
        </div>
      @else
        <div class="form-row row-2-buy" x-show="measurement || currentItem">
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

          <label class="form-field">
            <span x-text="unitCostLabel">Cost per unit</span>
            <input type="number" min="0" step="any" inputmode="decimal" x-model="unitCost" @input="updateInnerUnitCosts" placeholder="0" required>
          </label>

          <label class="form-field">
            <span x-text="sellingPriceLabel">Selling price per unit</span>
            <input type="number" min="0" step="any" inputmode="decimal" x-model="sellingPrice" placeholder="Optional - defaults to cost">
          </label>
        </div>
      @endif

      {{-- Inner units (purchases only) --}}
      @unless ($form['isSale'])
        <div x-show="measurement || currentItem">
          <template x-for="(pack, packIndex) in packs" :key="packIndex">
            <div class="form-row inner-unit-row" draggable="true"
                 @dragstart="dragStartPack(packIndex)"
                 @dragover.prevent="dragOverPack(packIndex)"
                 @drop="dropPack(packIndex)"
                 @dragend="dragEndPack()"
                 :class="{ 'dragging': draggingPackIndex === packIndex, 'drag-over': dragOverPackIndex === packIndex }">
              <label class="form-field" style="display:flex; align-items:center; gap:8px;">
                <span class="drag-handle mut" style="cursor:grip; cursor:grab;" title="Drag to reorder">⋮⋮</span>
                <span class="mut" x-text="'Inner unit ' + (packIndex + 1)"></span>
                <select x-model="pack.unit" @change="updateInnerUnitCosts" :disabled="!measurement && !currentItem">
                  <option value="">None</option>
                  <template x-for="option in innerUnitOptions" :key="option.code">
                    <option :value="option.code" x-text="option.label"></option>
                  </template>
                </select>
              </label>

              <label class="form-field">
                <span x-text="'How many per ' + tradingUnitLabel()">How many per unit</span>
                <input type="number" min="1" step="1" inputmode="numeric"
                       x-model="pack.per" @input="updateInnerUnitCosts" placeholder="e.g. 12">
              </label>

              <label class="form-field">
                <span x-text="innerUnitCostLabel(packIndex)">Cost per unit</span>
                <input type="number" min="0" step="any" inputmode="decimal"
                       x-model="pack.cost"
                       :disabled="!pack.unit"
                       :readonly="pack.unit && pack.per && unitCost"
                       placeholder="0">
                <span class="pv mut" x-show="pack.unit && pack.per && unitCost" style="font-size:11px">Auto-calculated from base cost</span>
              </label>

              <label class="form-field">
                <span x-text="'Selling price per ' + (innerUnitOptions.find(o => o.code === pack.unit)?.label || 'unit')">Selling price per unit</span>
                <input type="number" min="0" step="any" inputmode="decimal"
                       x-model="pack.sellingPrice" :disabled="!pack.unit" placeholder="Optional">
              </label>

              <button type="button" class="x" @click="removePack(packIndex)" title="Remove" style="align-self:end; justify-self:end;">&times;</button>
            </div>
          </template>

          <button type="button" class="btn alt sm" @click="addPack()" :disabled="packs.length >= 10" x-show="packs.length < 10">+ Add inner unit</button>
          <span class="pv mut" x-show="packs.length >= 10" style="font-size:12px">Maximum 10 inner units</span>
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