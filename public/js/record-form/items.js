/* Stockly -- item selection and sync logic for the record form. */

export function selected() {
  return this.items.find((item) => String(item.id) === String(this.itemId)) || null;
}

/**
 * For a purchase, the item whose name matches what has been typed.
 *
 * Whitespace is collapsed first, exactly as Item::normaliseName() does on the
 * server, so a pasted name with a line break still finds the item it came from
 * rather than silently becoming a new one.
 */
export function namedItem() {
  const key = this.name.replace(/\s+/g, ' ').trim().toLowerCase();
  if (!key) return null;
  return this.items.find((item) => item.name.toLowerCase() === key) || null;
}

export function currentItem() {
  return this.isSale ? this.selected : this.namedItem;
}

/** Called when the unit changes: fetch cost for sales. */
export function onUnitChange() {
  if (this.isSale && this.currentItem && this.selectedUnit) {
    this.fetchUnitCost(this.currentItem.id, this.selectedUnit.code);
  }
}

/** Fetch unit cost from pre-loaded data. */
export function fetchUnitCost(itemId, unitCode) {
  const item = this.items.find((i) => i.id === itemId);
  if (item && item.costPerUnitByUnit && item.costPerUnitByUnit[unitCode] !== undefined) {
    this.unitCost = item.costPerUnitByUnit[unitCode];
  } else {
    this.unitCost = '';
  }
  // Reset selling price when unit changes
  this.sellingPrice = '';
  if (this.updateInnerUnitCosts) this.updateInnerUnitCosts();
}

/** Called when the item changes: pin the measurement, reset the unit. */
export function syncItem() {
  const item = this.currentItem;
  const previous = this.unit;

  // An item's family is fixed, so the measurement follows it rather than the
  // other way round. Nothing is chosen for a name nobody has bought before.
  this.measurement = item ? item.family : '';
  this.unit = item ? item.unit : '';

  const stillValid = this.unitOptions.some((unit) => unit.code === previous);
  if (stillValid) this.unit = previous;

  if (this.isSale && item) {
    // For sales, fetch cost for the default unit
    if (this.unit) {
      this.fetchUnitCost(item.id, this.unit);
    }
  } else if (!this.isSale && item) {
    // Prefill the declared packs so a repeat receipt does not have to be retyped.
    (item.packs || []).forEach((pack, slot) => {
      if (slot < this.packs.length) {
        this.packs[slot] = {
          unit: pack.unit,
          per: pack.per,
          cost: pack.cost !== undefined && pack.cost !== null ? pack.cost : '',
          sellingPrice: pack.sellingPrice !== undefined && pack.sellingPrice !== null ? pack.sellingPrice : ''
        };
      }
    });
    // Prefill selling price for the main unit from last sale
    if (item.lastSellingPrice !== undefined && item.lastSellingPrice !== null) {
      this.sellingPrice = item.lastSellingPrice;
    }
    if (this.updateInnerUnitCosts) this.updateInnerUnitCosts();
  }
}