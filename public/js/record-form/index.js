/* Stockly -- record form barrel export. */

import { createInitialState } from './state.js';
import { 
  selected, 
  namedItem, 
  currentItem, 
  onUnitChange, 
  fetchUnitCost, 
  syncItem 
} from './items.js';
import { 
  family, 
  unitOptions, 
  innerUnitOptions, 
  setMeasurement, 
  selectedUnit, 
  unitCostLabel, 
  sellingPriceLabel, 
  innerUnitCostLabel,
  updateInnerUnitCosts 
} from './units.js';
import { 
  effectivePrice, 
  itemSellingPriceForUnit, 
  purchaseSellingPriceDisplay 
} from './pricing.js?v=20261009c';
import { searchableSelect } from './searchable-select.js';
import { 
  total, 
  pending, 
  shortfall, 
  baseStockOf, 
  pendingBaseFor, 
  add, 
  remove, 
  clear,
  addPack,
  removePack,
  dragStartPack,
  dragOverPack,
  dropPack,
  dragEndPack
} from './lines.js';
import { 
  preview, 
  hasDraft, 
  draftComplete, 
  draft, 
  totalWithDraft, 
  totalProfit, 
  totalProfitClass, 
  totalProfitLabel 
} from './draft.js';
import { 
  money, 
  formatQty, 
  profitClass, 
  profitLabel, 
  profitOf, 
  costOfBase 
} from './formatting.js';
import { warn } from './utils.js';
import { packHelpers } from '../packs/index.js?v=20261009b';

/**
 * Creates the record form state object with all methods composed.
 * @param {Object} config - The configuration object from the server
 * @returns {Object} The complete record form state
 */
export function createRecordForm(config) {
  // Create initial state
  const state = createInitialState(config);

  // Mix in all modules
  const recordForm = {
    ...state,

    // From items.js
    get isSale() { return config.isSale; },
    get items() { return config.items; },
    get selected() { return selected.call(this); },
    get namedItem() { return namedItem.call(this); },
    get currentItem() { return currentItem.call(this); },
    onUnitChange,
    fetchUnitCost,
    syncItem,

    // From searchable-select.js
    searchableSelect: searchableSelect(),

    // From units.js
    get family() { return family.call(this); },
    get unitOptions() { return unitOptions.call(this); },
    get innerUnitOptions() { return innerUnitOptions.call(this); },
    setMeasurement,
    get selectedUnit() { return selectedUnit.call(this); },
    get unitCostLabel() { return unitCostLabel.call(this); },
    get sellingPriceLabel() { return sellingPriceLabel.call(this); },
    innerUnitCostLabel,
    updateInnerUnitCosts,

    // From pricing.js
    get effectivePrice() { return effectivePrice.call(this); },
    itemSellingPriceForUnit,
    get purchaseSellingPriceDisplay() { return purchaseSellingPriceDisplay.call(this); },

    // From lines.js
    get total() { return total.call(this); },
    get pending() { return pending.call(this); },
    get shortfall() { return shortfall.call(this); },
    baseStockOf,
    pendingBaseFor,
    add,
    remove,
    clear,
    addPack,
    removePack,
    dragStartPack,
    dragOverPack,
    dropPack,
    dragEndPack,

    // From draft.js
    get preview() { return preview.call(this); },
    get hasDraft() { return hasDraft.call(this); },
    get draftComplete() { return draftComplete.call(this); },
    get draft() { return draft.call(this); },
    get totalWithDraft() { return totalWithDraft.call(this); },
    get totalProfit() { return totalProfit.call(this); },
    get totalProfitClass() { return totalProfitClass.call(this); },
    get totalProfitLabel() { return totalProfitLabel.call(this); },

    // From formatting.js
    money,
    formatQty,
    profitClass,
    profitLabel,
    profitOf,
    costOfBase,

    // From utils.js
    warn,
  };

  // Mix in pack helpers (provides pack-related methods)
  // Note: packHelpers needs the config and will be mixed in
  const packHelpersInstance = packHelpers(config);

  // Merge pack helpers into the record form without overwriting accessors such
  // as innerUnitOptions, which the record form exposes as a reactive getter.
  for (const [key, value] of Object.entries(packHelpersInstance)) {
    if (Object.prototype.hasOwnProperty.call(recordForm, key)) continue;
    recordForm[key] = value;
  }

  return recordForm;
}

// Re-export for testing
export { createInitialState } from './state.js';
export { selected, namedItem, currentItem, onUnitChange, fetchUnitCost, syncItem } from './items.js';
export { family, unitOptions, innerUnitOptions, setMeasurement, selectedUnit, unitCostLabel, sellingPriceLabel, innerUnitCostLabel, updateInnerUnitCosts } from './units.js';
export { effectivePrice, itemSellingPriceForUnit, purchaseSellingPriceDisplay } from './pricing.js';
export { total, pending, shortfall, baseStockOf, pendingBaseFor, add, remove, clear, addPack, removePack, dragStartPack, dragOverPack, dropPack, dragEndPack } from './lines.js';
export { preview, hasDraft, draftComplete, draft, totalWithDraft, totalProfit, totalProfitClass, totalProfitLabel } from './draft.js';
export { money, formatQty, profitClass, profitLabel, profitOf, costOfBase } from './formatting.js';
export { warn } from './utils.js';
export { searchableSelect } from './searchable-select.js';
