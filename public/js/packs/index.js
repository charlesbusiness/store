/* Stockly -- pack helpers barrel export. */

import {
  labelFor as labelForUnit,
  packSummary as summarizePacks,
  quantityLabel as formatQuantityLabel,
  tradingUnitLabel as formatTradingUnitLabel,
  packHint as describePackHint,
} from './utils.js';
import { declaredPacks, activePacks, effectivePacks, packCounts as packCountsFromPacks } from './packs.js';
import { innerUnitOptions, unitsForMeasurement, unitOption } from './units.js';
import { toBase, equivalentsFor } from './conversion.js';
import { packCounts as packCountsFromValidation, quantityProblem } from './validation.js';

/**
 * Creates the packHelpers object to be mixed into the record form state.
 * @param {Object} config - The configuration object from the server
 * @returns {Object} The packHelpers object with all pack-related methods
 */
export function packHelpers(config) {
  // Create the helpers object with methods that need `this` context
  const helpers = {
    // From packs.js
    declaredPacks,
    activePacks,
    effectivePacks,
    packCounts: packCountsFromPacks,

    // From units.js
    innerUnitOptions,
    unitsForMeasurement,
    unitOption,

    // From conversion.js
    toBase,
    equivalentsFor,

    // From validation.js
    packCounts: packCountsFromValidation,
    quantityProblem,
  };

  // Add utils that need the final Alpine component as `this`.
  return {
    ...helpers,
    labelFor(code) {
      return labelForUnit(config, code);
    },
    packSummary(packs) {
      return summarizePacks(this, packs);
    },
    quantityLabel(qty, unit) {
      return formatQuantityLabel(this, qty, unit);
    },
    tradingUnitLabel() {
      return formatTradingUnitLabel(this);
    },
    packHint() {
      return describePackHint(this);
    },
    unitLabel(code) {
      return labelForUnit(config, code);
    },
  };
}

// Re-export individual functions for testing
export { labelFor, packSummary, quantityLabel, tradingUnitLabel, packHint } from './utils.js';
export { declaredPacks, activePacks, effectivePacks, packCounts as packCountsPack } from './packs.js';
export { innerUnitOptions, unitsForMeasurement, unitOption } from './units.js';
export { toBase, equivalentsFor } from './conversion.js';
export { packCounts as packCountsValidation, quantityProblem } from './validation.js';
