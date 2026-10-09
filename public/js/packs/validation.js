/* Stockly -- quantity validation for pack helpers. */

export function packCounts() {
  return this.effectivePacks()
    .map((pack) => Number(pack.per))
    .filter((per) => per > 0);
}

/**
 * Does this quantity make sense?
 *
 * A fraction is fine when it lands on a whole number of something declared
 * inside the unit, so 0.5 of a carton of 12 is 6 rolls. Two fifths of it is
 * 4.8 rolls, so it is not. A quantity typed in an inner unit has to be whole,
 * because half a sachet is not a thing.
 */
export function quantityProblem() {
  const qty = Number(this.qty);
  const unit = this.selectedUnit;

  if (!unit || !(qty > 0) || unit.decimal) return '';

  const item = this.currentItem;
  const trading = item ? this.unitOption(item.unit) : null;

  if (trading && unit.code !== trading.code) {
    return Math.abs(qty - Math.round(qty)) < 1e-9
      ? ''
      : 'Enter a whole number of ' + (unit.plural || unit.label).toLowerCase() + '.';
  }

  const counts = this.packCounts();

  if (counts.some((per) => Math.abs(qty * per - Math.round(qty * per)) < 1e-9)) return '';

  if (counts.length === 0) {
    return 'Enter a whole number of ' + (unit.plural || unit.label).toLowerCase() + '.';
  }

  // The nearest quantity that does divide, and which unit says so.
  const best = this.effectivePacks()
    .map((pack) => ({ count: Math.floor(qty * pack.per + 1e-9), pack }))
    .filter((option) => option.count >= 1)
    .sort((a, b) => b.count - a.count)[0];

  if (!best) {
    return 'Enter a whole number of ' + (unit.plural || unit.label).toLowerCase() + '.';
  }

  return (
    this.quantityLabel(qty, unit) +
    ' does not divide into whole ' +
    this.packSummary(this.effectivePacks()) +
    '. Enter ' +
    this.quantityLabel(best.count, this.unitOption(best.pack.unit) || unit) +
    ' instead.'
  );
}