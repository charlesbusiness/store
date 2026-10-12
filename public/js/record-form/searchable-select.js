/* Stockly -- Searchable dropdown component for item selection. */

export function searchableSelect() {
  return {
    isOpen: false,
    searchQuery: '',
    highlightedIndex: -1,
    _model: null,

    get model() {
      return this._model;
    },

    set model(value) {
      this._model = value;
    },

    get filteredItems() {
      if (!this.items) return [];
      const query = this.searchQuery.toLowerCase().trim();
      if (!query) return this.items;
      return this.items.filter(item =>
        item.name.toLowerCase().includes(query)
      );
    },

    open() {
      this.isOpen = true;
      this.searchQuery = '';
      this.highlightedIndex = -1;
      this.$nextTick(() => {
        this.$refs.searchInput?.focus();
      });
    },

    close() {
      this.isOpen = false;
      this.searchQuery = '';
      this.highlightedIndex = -1;
    },

    selectItem(item) {
      this.model = item.id;
      this.$dispatch('model', item.id);
      this.onChange?.(item);
      this.close();
    },

    handleKeydown(event) {
      const items = this.filteredItems;
      if (!items.length) return;

      switch (event.key) {
        case 'ArrowDown':
          event.preventDefault();
          this.highlightedIndex = Math.min(this.highlightedIndex + 1, items.length - 1);
          this.scrollHighlighted();
          break;
        case 'ArrowUp':
          event.preventDefault();
          this.highlightedIndex = Math.max(this.highlightedIndex - 1, -1);
          this.scrollHighlighted();
          break;
        case 'Enter':
          if (this.highlightedIndex >= 0) {
            event.preventDefault();
            this.selectItem(items[this.highlightedIndex]);
          }
          break;
        case 'Escape':
          this.close();
          break;
      }
    },

    handleInputKeydown(event) {
      if (event.key === 'ArrowDown' && this.filteredItems.length) {
        event.preventDefault();
        this.highlightedIndex = 0;
        this.scrollHighlighted();
      }
    },

    scrollHighlighted() {
      const option = this.$refs.option?.[this.highlightedIndex];
      if (option) {
        option.scrollIntoView({ block: 'nearest' });
      }
    },

    handleClickOutside(event) {
      if (!this.$el.contains(event.target)) {
        this.close();
      }
    },

    handleSearchInput() {
      this.highlightedIndex = -1;
    },

    getSelectedLabel() {
      if (!this.model) return 'Choose an item';
      const item = this.items?.find(i => String(i.id) === String(this.model));
      return item ? `${item.name} — ${item.stockLabel} left` : 'Choose an item';
    }
  };
}