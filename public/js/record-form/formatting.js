/* Stockly -- formatting utilities for the record form. */

/**
 * Format money with currency symbol and decimals.
 */
export function money(value) {
  return (
    this.config.currency +
    Number(value).toLocaleString('en', { maximumFractionDigits: this.config.moneyDecimals })
  );
}

/**
 * Must not be called qty(): that name is the quantity field itself, and a
 * method of the same name would be overwritten by x-model on first input.
 */
export function formatQty(value) {
  return Number(value).toLocaleString('en', { maximumFractionDigits: 3 });
}

/** Painted green when a sale made money, red when it did not. */
export function profitClass(value) {
  if (value == null) return '';
  return value < 0 ? 'neg' : 'pos';
}

/** An unknown margin reads as a dash, never as a zero. */
export function profitLabel(value) {
  return value == null ? '—' : this.money(value);
}

/**
 * The margin on a line, or null when its cost is unknown.
 *
 * Null propagates rather than collapsing to zero, because a total that counted
 * an unknown-cost line as zero would understate the whole sale and look merely
 * disappointing rather than incomplete.
 */
export function profitOf(line) {
  if (line.cost == null) return null;
  return line.total - line.cost;
}

/**
 * What a base quantity of an item costs, at the average it was bought for.
 *
 * Null, never zero, when nothing has ever been bought: there is no cost to
 * average yet, and zero would value the line as free and turn the whole sale
 * into apparent profit. The server freezes the same figure onto the line when
 * it saves.
 */
export function costOfBase(baseQty, item) {
  const costPerUnit = item ? item.costPerUnit : null;

  if (costPerUnit == null) return null;

  // costPerUnit arrives as a price per trading unit, so it is converted the
  // same way the quantity is: down through any declared inner unit first.
  const trading = this.unitOption(item.unit);
  const pack = this.declaredPacks().find((entry) => entry.unit === item.unit);

  if (pack && pack.per > 0) return (baseQty / pack.per) * costPerUnit;

  return baseQty * (trading && trading.multiplier ? costPerUnit / trading.multiplier : costPerUnit);
}