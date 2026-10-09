/* Stockly -- shared utilities for pack helpers. */

/**
 * The label for a unit code, straight from the catalogue.
 *
 * Deliberately a plain function rather than a method on the mixed-in object.
 * Building the unit list reads labels, and a method would have had to reach back
 * through this.unitsForMeasurement to do it -- which is a cycle, and a cycle that
 * only shows up once an item actually has packs declared. Out here it has no
 * `this` at all, so that mistake cannot be made again.
 */
export function labelFor(config, code) {
  if (!code) return '';

  for (const options of Object.values(config.units || {})) {
    const found = options.find((unit) => unit.code === code);
    if (found) return found.label;
  }

  return code;
}

/**
 * "12 rolls or 24 sachets", matching the server's wording exactly.
 */
export function packSummary(self, packs) {
  const parts = packs.map(
    (pack) => pack.per + ' ' + self.unitLabel(pack.unit).toLowerCase()
  );

  return parts.length <= 1 ? parts[0] || '' : parts.slice(0, -1).join(' or ') + ' or ' + parts[parts.length - 1];
}

/**
 * "0.4 cartons", mirroring Unit::quantityLabel() on the server.
 */
export function quantityLabel(self, qty, unit) {
  const word = Math.abs(qty) === 1 ? unit.label : unit.plural || unit.label;
  return self.formatQty(qty) + ' ' + word;
}

/**
 * The label of the unit one trading unit of this item counts as.
 */
export function tradingUnitLabel(self) {
  const item = self.currentItem;
  const code = item ? item.unit : self.unit;
  return code ? labelFor(self.config, code).toLowerCase() : 'unit';
}

/**
 * "One carton holds 12 rolls and 24 sachets."
 */
export function packHint(self) {
  const trading = self.currentItem ? self.currentItem.unit : null;

  if (self.isSale) {
    return self.declaredPacks().length
      ? 'One ' + self.tradingUnitLabel() + ' holds ' + self.packSummary(self.declaredPacks()) + '.'
      : '';
  }

  // The unit an item is traded in cannot also be its own contents.
  return self.activePacks()
    .filter((pack) => pack.unit !== trading)
    .map(
      (pack) =>
        'One ' +
        self.tradingUnitLabel() +
        ' holds ' +
        pack.per +
        ' ' +
        self.unitLabel(pack.unit).toLowerCase() +
        '.'
    )
    .join(' ');
}