/* Stockly -- draft/reactive getters for the record form. */

/** The running total of the line being typed, in one place above the form. */
export function preview() {
  const qty = Number(this.qty);
  const price = this.effectivePrice;

  if (!(qty > 0) || price < 0) return '';

  return 'Line total: ' + this.money(qty * price);
}

/**
 * Whether the form has enough filled in to be worth showing a preview row.
 *
 * An item and a unit have to be settled before there is a row to describe: the
 * family those units come from is chosen by them. Everything after that can
 * appear as it is typed, so picking an item alone is enough to see the row and
 * start filling it in.
 */
export function hasDraft() {
  if (this.isSale) {
    // Sales: must select existing item
    return Boolean(this.currentItem && this.selectedUnit);
  }
  // Purchase: allow new items (name + measurement + unit) OR existing items
  return Boolean(
    (this.currentItem || this.name.trim()) &&
    this.measurement &&
    this.selectedUnit
  );
}

/**
 * Is the draft ready to be added?
 *
 * Also drives the Add line button, so the trader is never invited to click
 * something that would only refuse.
 */
export function draftComplete() {
  if (this.isSale) {
    return this.hasDraft && Number(this.qty) > 0 && this.effectivePrice >= 0;
  }
  return this.hasDraft && Number(this.qty) > 0 && this.unitCost !== '' && Number(this.unitCost) >= 0;
}

/**
 * The line being typed, ready to render.
 *
 * Live and unsaved: it appears in the cart above the committed lines so it
 * reads as the thing currently being worked on, and it posts nothing. The Add
 * line button is the only way a line becomes real. Until then these are
 * previews, and the server will recompute all of them.
 */
export function draft() {
  if (!this.hasDraft) return null;

  const item = this.currentItem;
  const unit = this.selectedUnit;
  const qty = Number(this.qty);
  const price = this.effectivePrice;
  const ready = this.draftComplete;

  // Nothing is costed until there is a quantity to cost, so a half-typed
  // draft never shows a margin derived from a quantity of zero.
  const cost = ready && this.isSale ? this.costOfBase(this.toBase(qty, unit), item) : null;
  const total = ready ? qty * price : 0;
  const profit = cost === null ? null : total - cost;

  return {
    label: item.name,
    qty: qty,
    qtyLabel: this.formatQty(ready ? qty : ''),
    unit: unit.code,
    unitLabel: this.unitLabel(unit.code),
    price: ready ? price : '',
    total: total,
    equivalents: ready ? this.equivalentsFor(qty, unit) : '',
    cost: cost,
    profit: profit,
    profitClass: this.profitClass(profit),
    profitLabel: this.profitLabel(profit),
  };
}

/** The total if the draft were added, so nothing is left ambiguous. */
export function totalWithDraft() {
  return this.total + (this.draft ? this.draft.total : 0);
}

/**
 * The margin across the committed lines, or null if any of them is unknown.
 *
 * Null wins over a partial sum. One unknown-cost line makes the whole figure
 * unknown, because a total that quietly counted it as free would look like a
 * real result and be wrong.
 */
export function totalProfit() {
  if (!this.lines.length) return null;

  let sum = 0;

  for (const line of this.lines) {
    const profit = this.profitOf(line);

    if (profit === null) return null;

    sum += profit;
  }

  return sum;
}

export function totalProfitClass() {
  return this.profitClass(this.totalProfit);
}

export function totalProfitLabel() {
  return this.profitLabel(this.totalProfit);
}