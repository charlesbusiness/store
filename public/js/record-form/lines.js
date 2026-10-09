/* Stockly -- line management for the record form. */

export function total() {
  return this.lines.reduce((sum, line) => sum + line.total, 0);
}

/** Base units already on the form, per item, for the stock check. */
export function pending() {
  const totals = {};

  this.lines.forEach((line) => {
    const key = String(line.itemKey);
    totals[key] = (totals[key] || 0) + line.baseQty;
  });

  return totals;
}

export function shortfall() {
  if (!this.isSale) return '';

  for (const item of this.items) {
    const available = this.baseStockOf(item);
    const wanted = (this.pending[item.id] || 0) + this.pendingBaseFor(item);

    if (available > 0 && wanted > available + 1e-9) {
      return item.name + ': only ' + item.stockLabel + ' in stock';
    }
  }

  return '';
}

/** Base units of an item, converted from its own trading unit. */
export function baseStockOf(item) {
  const unit = (this.config.units[item.family] || []).find((u) => u.code === item.unit);
  return (item.stock || 0) * (unit ? unit.multiplier : 1);
}

export function pendingBaseFor(item) {
  const qty = Number(this.qty);
  if (!this.selectedUnit || !(qty > 0)) return 0;
  if (this.isSale && String(item.id) !== String(this.itemId)) return 0;

  return this.toBase(qty, this.selectedUnit);
}

export function add() {
  const qty = Number(this.qty);
  const unit = this.selectedUnit;
  const item = this.isSale ? this.selected : this.namedItem;
  const label = this.isSale ? item && item.name : this.name.trim();

  if (!label) return this.warn(this.isSale ? 'Choose an item to sell' : 'Name the item you are buying');
  if (!unit) return this.warn('Choose a unit');
  if (!(qty > 0)) return this.warn('Fill in the quantity');
  if (!this.isSale && (this.unitCost === '' || this.unitCost === null)) {
    return this.warn('Fill in the cost per ' + this.selectedUnit.label);
  }
  if (this.isSale && this.effectivePrice < 0) {
    return this.warn('Selling price cannot be negative');
  }
  if (this.isSale && this.unitCost !== '' && this.unitCost !== null && this.effectivePrice < Number(this.unitCost)) {
    return this.warn('Selling price cannot be less than cost per ' + this.selectedUnit.label);
  }

  // Validate inner unit costs for purchases
  if (!this.isSale) {
    for (const pack of this.packs) {
      if (pack.unit && (pack.cost === '' || pack.cost === null)) {
        const innerUnit = this.innerUnitOptions.find((u) => u.code === pack.unit);
        return this.warn('Fill in the cost per ' + (innerUnit ? innerUnit.label : pack.unit));
      }
    }
  }

  const problem = this.quantityProblem();
  if (problem) return this.warn(problem);

  const per = this.effectivePacks().find((pack) => pack.unit === unit.code);
  const baseQty = this.toBase(qty, unit);

  // Only a sale is capped by stock, and only a sale always has an item.
  // A purchase may name something nobody has bought before, which has no id
  // yet, so the running total is keyed by name in that case.
  if (this.isSale) {
    const alreadyOn = this.pending[item.id] || 0;
    const available = this.baseStockOf(item);

    if (alreadyOn + baseQty > available + 1e-9) {
      return this.warn('Only ' + item.stockLabel + ' of ' + item.name + ' in stock');
    }
  }

  const cost = this.isSale
    ? this.costOfBase(baseQty, item)
    : null;

  const effectivePrice = this.effectivePrice;

  this.lines.push({
    itemKey: item ? item.id : null,
    itemId: item ? item.id : '',
    name: this.isSale ? '' : this.name.trim(),
    label: label,
    qty: qty,
    unit: unit.code,
    unitLabel: unit.label,
    baseQty: baseQty,
    price: effectivePrice,
    total: qty * effectivePrice,
    cost: cost,
    profitClass: this.profitClass(this.profitOf({ total: qty * effectivePrice, cost: cost })),
    profitLabel: this.profitLabel(this.profitOf({ total: qty * effectivePrice, cost: cost })),
    unit_cost: this.isSale
      ? (this.unitCost === '' || this.unitCost === null ? null : Number(this.unitCost))
      : Number(this.unitCost),
    selling_price: this.sellingPrice === '' || this.sellingPrice === null
      ? null
      : Number(this.sellingPrice),
    packs: this.isSale ? [] : this.activePacks(),
    equivalents: this.equivalentsFor(qty, unit),
  });

  this.qty = '';
  this.unitCost = '';
  this.sellingPrice = '';
  this.name = this.isSale ? '' : this.name;
  window.Stockly.toast('Line added');
}

export function remove(index) {
  this.lines.splice(index, 1);
}

export function clear() {
  this.lines = [];
}