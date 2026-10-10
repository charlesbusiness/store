/* Stockly -- pricing logic for the record form. */

/** Effective price for total calculation:
 * - Sales:
 *   1. User manual entry (sellingPrice)
 *   2. Purchase history selling price for this unit (from buy page)
 *   3. Cost price (unitCost)
 * - Purchases: unitCost (cost per unit)
 */
export function effectivePrice() {
  if (this.isSale) {
    // 1. User manual entry
    if (this.sellingPrice !== '' && this.sellingPrice !== null) {
      return Number(this.sellingPrice);
    }
    // 2. Purchase history selling price for this unit
    const unit = this.selectedUnit;
    if (unit) {
      const historyPrice = this.itemSellingPriceForUnit(unit.code);
      // Treat 0 as "no price set" - fall through to cost
      if (historyPrice !== null && historyPrice > 0) {
        return Number(historyPrice);
      }
    }
    // 3. Fallback to cost price
    return Number(this.unitCost || 0);
  }
  // Buy page: use cost price
  return Number(this.unitCost || 0);
}

/** Effective selling price for an inner unit on the sell form.
 * Priority:
 * 1. User manual entry (pack.sellingPrice)
 * 2. Purchase history selling price for that inner unit
 * 3. Cost price (pack.cost)
 * 4. Main unit cost price (fallback)
 */
export function effectiveInnerSellingPrice(pack) {
  if (!this.isSale) return null;
  
  // 1. User manual entry
  if (pack.sellingPrice !== '' && pack.sellingPrice !== null) {
    return Number(pack.sellingPrice);
  }
  // 2. Purchase history selling price for this inner unit
  if (pack.unit) {
    const historyPrice = this.itemSellingPriceForUnit(pack.unit);
    if (historyPrice !== null && historyPrice > 0) {
      return Number(historyPrice);
    }
  }
  // 3. Inner unit cost price
  if (pack.cost !== '' && pack.cost !== null && pack.cost !== undefined) {
    return Number(pack.cost);
  }
  // 4. Main unit cost price
  if (this.unitCost !== '' && this.unitCost !== null) {
    return Number(this.unitCost);
  }
  return null;
}

/** Get the purchase selling price for a unit code, if available. */
export function itemSellingPriceForUnit(code) {
  const item = this.currentItem;
  if (!item || !item.sellingPricePerUnitByUnit) return null;

  const price = item.sellingPricePerUnitByUnit[code];
  return price === undefined || price === null ? null : price;
}

/** Formatted purchase selling price for display, or empty string. */
export function purchaseSellingPriceDisplay() {
  const unit = this.selectedUnit;
  if (!unit) return '';
  const price = this.itemSellingPriceForUnit(unit.code);
  return price === null ? '' : 'Selling price: ' + this.money(price);
}
