/* Stockly -- base conversion and equivalents for pack helpers. */

/**
 * The line's quantity in base units.
 *
 * An inner unit divides down to base; anything else uses the catalogue.
 * One carton of 12 rolls bought as 6 rolls is 0.5 of the item, which is the
 * same base figure as entering 0.5 carton directly.
 */
export function toBase(qty, unit) {
  const item = this.currentItem;
  const trading = item ? this.unitOption(item.unit) : null;
  const per = this.effectivePacks().find((pack) => pack.unit === unit.code);

  if (per && trading && unit.code !== trading.code) {
    return (qty * trading.multiplier) / per.per;
  }

  return qty * unit.multiplier;
}

/**
 * What this line's quantity works out to in the declared inner units, for the
 * cart to show beside it. Half a carton reads "= 6 rolls".
 */
export function equivalentsFor(qty, unit) {
  const packs = this.effectivePacks();
  const item = this.currentItem;
  const trading = item ? this.unitOption(item.unit) : null;

  if (packs.length === 0 || !trading || !trading.multiplier) return '';

  const base = this.toBase(qty, unit);

  // One trading unit holds pack.per inner ones, so each inner one is a
  // multiplier/pack.per slice of base.
  const parts = packs.map((pack) =>
    this.formatQty((base * pack.per) / trading.multiplier) + ' ' + this.unitLabel(pack.unit).toLowerCase()
  );

  return parts.length ? '= ' + parts.join(', ') : '';
}