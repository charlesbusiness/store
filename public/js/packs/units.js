/* Stockly -- unit/measurement logic for pack helpers. */

export function innerUnitOptions() {
  const trading = this.currentItem ? this.currentItem.unit : null;
  return this.unitOptions.filter((option) => option.code !== trading);
}

/**
 * The units offered in the chosen measurement, as a flat list.
 *
 * A measurement is picked first and the unit second, so there is one list
 * rather than one list per family. Everything on a line has to belong to that
 * measurement -- the trading unit, and both inner units -- because the server
 * refuses a line mixing families.
 *
 * The item's own inner units are added on top, so a sale can be typed in
 * whichever unit is to hand, labelled with what it is worth.
 *
 * On the sell page with an item selected, only the trading unit and its
 * declared inner units are offered.
 */
export function unitsForMeasurement() {
  const family = this.family;

  if (!family) return [];

  // NB: labelFor() and not this.unitLabel(), even though the latter would now
  // be safe. Keeping the lookup out of the build entirely is what guarantees it.
  const options = (this.config.units[family] || []).slice();
  const trading = this.currentItem ? this.currentItem.unit : null;

  // On the sell page with an item selected, restrict to trading unit + declared inner units
  if (this.isSale && this.currentItem) {
    const tradingUnitOption = options.find((opt) => opt.code === trading);
    const declaredInnerUnits = this.declaredPacks()
      .filter((pack) => pack.unit && pack.unit !== trading)
      .map((pack) => ({
        code: pack.unit,
        label: this.unitLabel(pack.unit) + ' (' + pack.per + ' per ' + this.tradingUnitLabel() + ')',
      }));

    const seen = new Set();
    const result = [];
    if (tradingUnitOption) {
      result.push(tradingUnitOption);
      seen.add(tradingUnitOption.code);
    }
    for (const unit of declaredInnerUnits) {
      if (!seen.has(unit.code)) {
        result.push(unit);
        seen.add(unit.code);
      }
    }
    return result;
  }

  // The unit an item is traded in cannot also be counted inside itself.
  const owned = this.declaredPacks()
    .filter((pack) => pack.unit && pack.unit !== trading)
    .map((pack) => ({
      code: pack.unit,
      label: this.unitLabel(pack.unit) + ' (' + pack.per + ' per ' + this.tradingUnitLabel() + ')',
    }));

  const seen = new Set(options.map((option) => option.code));

  return options.concat(owned.filter((option) => !seen.has(option.code)));
}

/** The catalogue entry for a code within the current measurement. */
export function unitOption(code) {
  if (!this.family) return null;
  return (this.config.units[this.family] || []).find((unit) => unit.code === code) || null;
}