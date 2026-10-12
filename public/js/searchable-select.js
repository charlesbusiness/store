/* Stockly -- searchable dropdown for item selection. */

import { searchableSelect } from './record-form/searchable-select.js';

// Export globally for Alpine.js (x-data="searchableSelect")
window.searchableSelect = searchableSelect;
export default searchableSelect;

// Signal readiness after top-level exports execute
if (window.markModuleReady) window.markModuleReady('searchable-select');