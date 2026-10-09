/* Stockly -- initial state for the record form. */

/**
 * Creates the initial state object for the record form.
 * @param {Object} config - The configuration object from the server
 * @returns {Object} The initial state object
 */
export function createInitialState(config) {
  return {
    config: config,
    date: config.today,
    itemId: '',
    name: '',
    qty: '',
    measurement: '',
    unit: '',
    unitCost: '',
    sellingPrice: '',
    packs: [
      { unit: '', per: '', cost: '', sellingPrice: '' },
      { unit: '', per: '', cost: '', sellingPrice: '' },
    ],
    lines: [],
  };
}