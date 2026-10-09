/* Stockly -- pricing logic for the record form. */

/** Effective price for total calculation:
 * - Purchases: unitCost (cost per unit)
 * - Sales: sellingPrice if provided, otherwise unitCost (default to cost)
 */
export function effectivePrice() {
  if (this.isSale) {
    return this.sellingPrice !== '' && this.sellingPrice !== null
      ? Number(this.sellingPrice)
      : Number(this.unitCost || 0);
  }
  return Number(this.unitCost || 0);
}

/** Get the purchase selling price for a unit code, if available. */
export function itemSellingPriceForUnit(code) {
  const item = this.currentItem;
  return item && item.sellingPricePerUnitByUnit && item.sellingPricePerUnitByUnit[code];
}

/** Formatted purchase selling price for display, or empty string. */
export function purchaseSellingPriceDisplay() {
  const unit = this.selectedUnit;
  if (!unit) return '';
  const price = this.itemSellingPriceForUnit(unit.code);
  return price ? 'Purchase selling price: ' + this.money(price) : '';
}