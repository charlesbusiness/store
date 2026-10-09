/* Stockly -- what one trading unit is made of, in the browser. */

export function declaredPacks() {
  const item = this.currentItem;
  return item && item.packs ? item.packs : [];
}

/** What has been typed into the two optional pack boxes. */
export function activePacks() {
  return this.packs
    .map((pack) => ({
      unit: pack.unit,
      per: Number(pack.per),
      cost: (pack.cost === '' || pack.cost === null || pack.cost === undefined) ? null : Number(pack.cost),
      sellingPrice: (pack.sellingPrice === '' || pack.sellingPrice === null || pack.sellingPrice === undefined) ? null : Number(pack.sellingPrice),
    }))
    .filter((pack) => pack.unit && pack.per > 0 && pack.cost !== null);
}

/** The packs in force for the line being typed: a sale reads, a purchase writes. */
export function effectivePacks() {
  return this.isSale ? this.declaredPacks() : this.activePacks();
}

export function packCounts() {
  return this.effectivePacks()
    .map((pack) => Number(pack.per))
    .filter((per) => per > 0);
}