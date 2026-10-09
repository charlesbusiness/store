/* Stockly -- unit/measurement logic for the record form. */

export function family() {
  const item = this.currentItem;
  return item ? item.family : this.measurement || null;
}

/** Units in the chosen measurement, plus this item's declared inner units. */
export function unitOptions() {
  return this.unitsForMeasurement();
}

/**
 * Units that may go inside one trading unit.
 *
 * Same measurement, minus the item's own trading unit: an item cannot be
 * counted inside itself. On a sale the declared inner units are also in this
 * list, so it matches what the unit dropdown is offering.
 */
export function innerUnitOptions() {
  const trading = this.currentItem ? this.currentItem.unit : null;
  return this.unitOptions.filter((option) => option.code !== trading);
}

/** Change the measurement, dropping anything the new one cannot hold. */
export function setMeasurement(value) {
  this.measurement = value;
  this.unit = '';
  this.unitCost = '';
  this.sellingPrice = '';
  this.packs = this.packs.map((pack) => ({ unit: '', per: '', cost: '', sellingPrice: '' }));
}

export function selectedUnit() {
  return this.unitOptions.find((unit) => unit.code === this.unit) || null;
}

export function unitCostLabel() {
  return this.selectedUnit ? 'Cost per ' + this.selectedUnit.label : 'Cost per unit';
}

export function sellingPriceLabel() {
  return this.selectedUnit ? 'Selling price per ' + this.unitLabel(this.selectedUnit.code) : 'Selling price per unit';
}

export function innerUnitCostLabel(slot) {
  const pack = this.packs[slot];
  if (!pack || !pack.unit) return 'Cost per unit';
  const innerUnit = this.innerUnitOptions.find((u) => u.code === pack.unit);
  return innerUnit ? 'Cost per ' + innerUnit.label : 'Cost per unit';
}