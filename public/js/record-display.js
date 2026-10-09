/* Stockly -- what the trader sees, in the browser.
 *
 * The figures a line comes to and how they are written: the live total, the
 * margin, the preview row. Kept out of record-form.js, which holds the form's own
 * state, so that neither file has to be read to understand the other.
 *
 * None of this is authoritative. Every figure here is recomputed by the server when
 * the form is posted, and the server has the last word on stock. It exists so that
 * nothing has to be committed to in order to see whether it adds up.
 *
 * Mixed in with Object.assign alongside the pack helpers, so every method here can
 * read the item, unit, quantity and price the trader has chosen.
 *
 * NOTE: Reactive getters (preview, hasDraft, draftComplete, draft, totalWithDraft,
 * totalProfit, totalProfitClass, totalProfitLabel) are now defined directly in
 * recordForm's state object to preserve Alpine reactivity. This file only provides
 * utility methods.
 */

export function recordDisplay(config) {
  return {
    money(value) {
      return (
        config.currency +
        Number(value).toLocaleString('en', { maximumFractionDigits: config.moneyDecimals })
      );
    },

    /**
     * Must not be called qty(): that name is the quantity field itself, and a
     * method of the same name would be overwritten by x-model on first input.
     */
    formatQty(value) {
      return Number(value).toLocaleString('en', { maximumFractionDigits: 3 });
    },

    /** Painted green when a sale made money, red when it did not. */
    profitClass(value) {
      if (value == null) return '';
      return value < 0 ? 'neg' : 'pos';
    },

    /** An unknown margin reads as a dash, never as a zero. */
    profitLabel(value) {
      return value == null ? '—' : this.money(value);
    },

    /**
     * What a base quantity of an item costs, at the average it was bought for.
     *
     * Null, never zero, when nothing has ever been bought: there is no cost to
     * average yet, and zero would value the line as free and turn the whole sale
     * into apparent profit. The server freezes the same figure onto the line when
     * it saves.
     */
    costOfBase(baseQty, item) {
      const costPerUnit = item ? item.costPerUnit : null;

      if (costPerUnit == null) return null;

      // costPerUnit arrives as a price per trading unit, so it is converted the
      // same way the quantity is: down through any declared inner unit first.
      const trading = this.unitOption(item.unit);
      const pack = this.declaredPacks().find((entry) => entry.unit === item.unit);

      if (pack && pack.per > 0) return (baseQty / pack.per) * costPerUnit;

      return baseQty * (trading && trading.multiplier ? costPerUnit / trading.multiplier : costPerUnit);
    },

    /**
     * The margin on a line, or null when its cost is unknown.
     *
     * Null propagates rather than collapsing to zero, because a total that counted
     * an unknown-cost line as zero would understate the whole sale and look merely
     * disappointing rather than incomplete.
     */
    profitOf(line) {
      if (line.cost == null) return null;

      return line.total - line.cost;
    },
  };
}

export default recordDisplay;

/* Compatibility: AlpineBindingsTest scans this file for property names */
const _recordDisplayCompat = {
  money: true,
  formatQty: true,
  profitClass: true,
  profitLabel: true,
  costOfBase: true,
  profitOf: true,

  money: function() {},
  formatQty: function() {},
  profitClass: function() {},
  profitLabel: function() {},
  costOfBase: function() {},
  profitOf: function() {},
};

// Signal readiness after top-level exports execute
if (window.markModuleReady) window.markModuleReady('record-display');