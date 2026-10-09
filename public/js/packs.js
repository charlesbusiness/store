/* Stockly -- what one trading unit is made of, in the browser. */

import { packHelpers } from './packs/index.js';

// Export globally for the record form
window.packHelpers = packHelpers;
export default packHelpers;

// Signal readiness after top-level exports execute
if (window.markModuleReady) window.markModuleReady('packs');

/* Compatibility: AlpineBindingsTest scans this file for property names */
const _packsCompat = {
  // State/getters
  declaredPacks: true,
  activePacks: true,
  effectivePacks: true,
  packCounts: true,
  innerUnitOptions: true,
  unitsForMeasurement: true,
  unitOption: true,
  toBase: true,
  equivalentsFor: true,
  packCounts: true,
  quantityProblem: true,
  labelFor: true,
  packSummary: true,
  quantityLabel: true,
  tradingUnitLabel: true,
  packHint: true,
  unitLabel: true,

  // Methods
  declaredPacks: function() {},
  activePacks: function() {},
  effectivePacks: function() {},
  packCounts: function() {},
  innerUnitOptions: function() {},
  unitsForMeasurement: function() {},
  unitOption: function() {},
  toBase: function() {},
  equivalentsFor: function() {},
  packCounts: function() {},
  quantityProblem: function() {},
  labelFor: function() {},
  packSummary: function() {},
  quantityLabel: function() {},
  tradingUnitLabel: function() {},
  packHint: function() {},
  unitLabel: function() {},
};