/* Stockly -- the buy/sell line-item builder, ported from the original Alpine
 * modules so the React SPA previews a line with exactly the arithmetic the
 * server uses.
 *
 * The browser still only previews: the server recomputes every figure and has
 * the last word on stock and cost.
 */

import { toast } from 'sonner';

export interface UnitOption {
    code: string;
    label: string;
    plural: string;
    decimal: boolean;
    fraction: boolean;
    multiplier: number;
}

export interface ItemPack {
    unit: string;
    per: number;
    cost: number | null;
    sellingPrice: number | null;
}

export interface ItemConfig {
    id: number;
    name: string;
    family: string;
    unit: string;
    stock: number | null;
    stockLabel: string | null;
    lastPrice?: number | null;
    lastSellingPrice?: number | null;
    costPerUnit?: number | null;
    costPerUnitByUnit?: Record<string, number | null>;
    sellingPricePerUnitByUnit?: Record<string, number | null>;
    packs: ItemPack[];
}

export interface FamilyOption {
    value: string;
    label: string;
}

export interface RecordConfig {
    type: string;
    isSale: boolean;
    today: string;
    items: ItemConfig[];
    families: FamilyOption[];
    units: Record<string, UnitOption[]>;
    currency: string;
    moneyDecimals: number;
}

export interface PackState {
    unit: string;
    per: string;
    cost: string;
    sellingPrice: string;
}

export interface ActivePack {
    unit: string;
    per: number;
    cost: number | null;
    sellingPrice: number | null;
}

export interface StagedLine {
    itemKey: number | null;
    itemId: number | '';
    name: string;
    label: string;
    qty: number;
    unit: string;
    unitLabel: string;
    baseQty: number;
    price: number;
    total: number;
    cost: number | null;
    profitClass: string;
    profitLabel: string;
    unit_cost: number | null;
    selling_price: number | null;
    packs: ActivePack[];
    equivalents: string;
}

export interface DraftView {
    label: string;
    qty: number;
    qtyLabel: string;
    unit: string;
    unitLabel: string;
    price: number | string;
    total: number;
    equivalents: string;
    cost: number | null;
    profit: number | null;
    profitClass: string;
    profitLabel: string;
}

export interface PurchasePayload {
    transacted_on: string;
    lines: {
        item_id: number | null;
        name: string;
        qty: number;
        unit_code: string;
        unit_price: number;
        unit_cost: number | null;
        selling_price: number | null;
        inner_units: { unit: string; per: number; cost: number | null; sellingPrice: number | null }[];
    }[];
}

function emptyPack(): PackState {
    return { unit: '', per: '', cost: '', sellingPrice: '' };
}

function toNumberOrNull(value: string): number | null {
    return value === '' || value === null || value === undefined ? null : Number(value);
}

/**
 * One line-item builder. Fields are mutable and read by getters that the view
 * calls during render; mutating methods notify subscribers so React re-reads.
 */
export class RecordFormModel {
    private _config: RecordConfig;

    date: string;
    itemId = '';
    name = '';
    qty = '';
    measurement = '';
    unit = '';
    unitCost = '';
    sellingPrice = '';
    packs: PackState[] = [];
    lines: StagedLine[] = [];
    draggingPackIndex = -1;
    dragOverPackIndex = -1;

    private listeners = new Set<() => void>();

    constructor(config: RecordConfig) {
        this._config = config;
        this.date = config.today;
    }

    get config(): RecordConfig {
        return this._config;
    }

    /**
     * Swaps in a freshly loaded config without discarding staged lines, so a
     * background refetch cannot wipe a half-filled form.
     */
    updateConfig(config: RecordConfig): void {
        this._config = config;
        this.notify();
    }

    subscribe(listener: () => void): () => void {
        this.listeners.add(listener);
        return () => {
            this.listeners.delete(listener);
        };
    }

    private notify(): void {
        this.listeners.forEach((listener) => listener());
    }

    // -- formatting -----------------------------------------------------------

    money(value: number | string | null | undefined): string {
        return (
            this.config.currency +
            Number(value).toLocaleString('en', { maximumFractionDigits: this.config.moneyDecimals })
        );
    }

    formatQty(value: number | string | null | undefined): string {
        return Number(value).toLocaleString('en', { maximumFractionDigits: 3 });
    }

    profitClass(value: number | null | undefined): string {
        if (value === null || value === undefined) return '';
        return value < 0 ? 'neg' : 'pos';
    }

    profitLabel(value: number | null | undefined): string {
        return value === null || value === undefined ? '—' : this.money(value);
    }

    profitOf(line: { total: number; cost: number | null }): number | null {
        if (line.cost === null || line.cost === undefined) return null;
        return line.total - line.cost;
    }

    // -- units and packs ------------------------------------------------------

    get isSale(): boolean {
        return this.config.isSale;
    }

    get items(): ItemConfig[] {
        return this.config.items;
    }

    labelFor(code: string): string {
        if (!code) return '';
        for (const options of Object.values(this.config.units || {})) {
            const found = options.find((unit) => unit.code === code);
            if (found) return found.label;
        }
        return code;
    }

    unitLabel(code: string): string {
        return this.labelFor(code);
    }

    get selected(): ItemConfig | null {
        return this.items.find((item) => String(item.id) === String(this.itemId)) || null;
    }

    get namedItem(): ItemConfig | null {
        const key = this.name.replace(/\s+/g, ' ').trim().toLowerCase();
        if (!key) return null;
        return this.items.find((item) => item.name.toLowerCase() === key) || null;
    }

    get currentItem(): ItemConfig | null {
        return this.isSale ? this.selected : this.namedItem;
    }

    get family(): string | null {
        const item = this.currentItem;
        return item ? item.family : this.measurement || null;
    }

    unitsForMeasurement(): UnitOption[] {
        const family = this.family;
        if (!family) return [];

        const options = (this.config.units[family] || []).slice();
        const trading = this.currentItem ? this.currentItem.unit : null;

        if (this.isSale && this.currentItem) {
            const tradingUnitOption = options.find((option) => option.code === trading);
            const declaredInnerUnits = this.declaredPacks()
                .filter((pack) => pack.unit && pack.unit !== trading)
                .map((pack) => ({
                    code: pack.unit,
                    label: this.unitLabel(pack.unit) + ' (' + pack.per + ' per ' + this.tradingUnitLabel() + ')',
                }));

            const seen = new Set<string>();
            const result: UnitOption[] = [];
            if (tradingUnitOption) {
                result.push(tradingUnitOption);
                seen.add(tradingUnitOption.code);
            }
            for (const unit of declaredInnerUnits) {
                if (!seen.has(unit.code)) {
                    result.push({ code: unit.code, label: unit.label, plural: unit.label, decimal: false, fraction: false, multiplier: 1 });
                    seen.add(unit.code);
                }
            }
            return result;
        }

        const owned = this.declaredPacks()
            .filter((pack) => pack.unit && pack.unit !== trading)
            .map((pack) => ({
                code: pack.unit,
                label: this.unitLabel(pack.unit) + ' (' + pack.per + ' per ' + this.tradingUnitLabel() + ')',
                plural: this.unitLabel(pack.unit),
                decimal: false,
                fraction: false,
                multiplier: 1,
            }));

        const seen = new Set(options.map((option) => option.code));
        return options.concat(owned.filter((option) => !seen.has(option.code)));
    }

    get unitOptions(): UnitOption[] {
        return this.unitsForMeasurement();
    }

    get innerUnitOptions(): UnitOption[] {
        const trading = this.currentItem ? this.currentItem.unit : null;
        return this.unitOptions.filter((option) => option.code !== trading);
    }

    unitOption(code: string): UnitOption | null {
        if (!this.family) return null;
        return (this.config.units[this.family] || []).find((unit) => unit.code === code) || null;
    }

    get selectedUnit(): UnitOption | null {
        return this.unitOptions.find((unit) => unit.code === this.unit) || null;
    }

    get unitCostLabel(): string {
        return this.selectedUnit ? 'Cost per ' + this.selectedUnit.label : 'Cost per unit';
    }

    get sellingPriceLabel(): string {
        return this.selectedUnit
            ? 'Selling price per ' + this.unitLabel(this.selectedUnit.code)
            : 'Selling price per unit';
    }

    innerUnitCostLabel(slot: number): string {
        const pack = this.packs[slot];
        if (!pack || !pack.unit) return 'Cost per unit';
        const innerUnit = this.innerUnitOptions.find((unit) => unit.code === pack.unit);
        return innerUnit ? 'Cost per ' + innerUnit.label : 'Cost per unit';
    }

    declaredPacks(): ItemPack[] {
        const item = this.currentItem;
        return item && item.packs ? item.packs : [];
    }

    activePacks(): ActivePack[] {
        return this.packs
            .map((pack) => ({
                unit: pack.unit,
                per: Number(pack.per),
                cost: toNumberOrNull(pack.cost),
                sellingPrice: toNumberOrNull(pack.sellingPrice),
            }))
            .filter((pack) => pack.unit && pack.per > 0 && pack.cost !== null);
    }

    effectivePacks(): ActivePack[] {
        return this.isSale ? this.declaredPacks() : this.activePacks();
    }

    packCounts(): number[] {
        return this.effectivePacks()
            .map((pack) => Number(pack.per))
            .filter((per) => per > 0);
    }

    packSummary(packs: { unit: string; per: number }[]): string {
        const parts = packs.map((pack) => pack.per + ' ' + this.unitLabel(pack.unit).toLowerCase());
        return parts.length <= 1 ? parts[0] || '' : parts.slice(0, -1).join(' or ') + ' or ' + parts[parts.length - 1];
    }

    quantityLabel(qty: number, unit: UnitOption | null): string {
        if (!unit) return this.formatQty(qty);
        const word = Math.abs(qty) === 1 ? unit.label : unit.plural || unit.label;
        return this.formatQty(qty) + ' ' + word;
    }

    tradingUnitLabel(): string {
        const item = this.currentItem;
        const code = item ? item.unit : this.unit;
        return code ? this.labelFor(code).toLowerCase() : 'unit';
    }

    packHint(): string {
        const trading = this.currentItem ? this.currentItem.unit : null;

        if (this.isSale) {
            return this.declaredPacks().length
                ? 'One ' + this.tradingUnitLabel() + ' holds ' + this.packSummary(this.declaredPacks()) + '.'
                : '';
        }

        return this.activePacks()
            .filter((pack) => pack.unit !== trading)
            .map(
                (pack) =>
                    'One ' +
                    this.tradingUnitLabel() +
                    ' holds ' +
                    pack.per +
                    ' ' +
                    this.unitLabel(pack.unit).toLowerCase() +
                    '.'
            )
            .join(' ');
    }

    // -- conversion -----------------------------------------------------------

    toBase(qty: number, unit: UnitOption): number {
        const item = this.currentItem;
        const trading = item ? this.unitOption(item.unit) : null;
        const per = this.effectivePacks().find((pack) => pack.unit === unit.code);

        if (per && trading && unit.code !== trading.code) {
            return (qty * trading.multiplier) / per.per;
        }

        return qty * unit.multiplier;
    }

    equivalentsFor(qty: number, unit: UnitOption): string {
        const packs = this.effectivePacks();
        const item = this.currentItem;
        const trading = item ? this.unitOption(item.unit) : null;

        if (packs.length === 0 || !trading || !trading.multiplier) return '';

        const base = this.toBase(qty, unit);

        const parts = packs.map(
            (pack) => this.formatQty((base * pack.per) / trading.multiplier) + ' ' + this.unitLabel(pack.unit).toLowerCase()
        );

        return parts.length ? '= ' + parts.join(', ') : '';
    }

    quantityProblem(): string {
        const qty = Number(this.qty);
        const unit = this.selectedUnit;

        if (!unit || !(qty > 0) || unit.decimal) return '';

        const item = this.currentItem;
        const trading = item ? this.unitOption(item.unit) : null;

        if (trading && unit.code !== trading.code) {
            return Math.abs(qty - Math.round(qty)) < 1e-9
                ? ''
                : 'Enter a whole number of ' + (unit.plural || unit.label).toLowerCase() + '.';
        }

        const counts = this.packCounts();

        if (counts.some((per) => Math.abs(qty * per - Math.round(qty * per)) < 1e-9)) return '';

        if (counts.length === 0) {
            return 'Enter a whole number of ' + (unit.plural || unit.label).toLowerCase() + '.';
        }

        const best = this.effectivePacks()
            .map((pack) => ({ count: Math.floor(qty * pack.per + 1e-9), pack }))
            .filter((option) => option.count >= 1)
            .sort((a, b) => b.count - a.count)[0];

        if (!best) {
            return 'Enter a whole number of ' + (unit.plural || unit.label).toLowerCase() + '.';
        }

        return (
            this.quantityLabel(qty, unit) +
            ' does not divide into whole ' +
            this.packSummary(this.effectivePacks()) +
            '. Enter ' +
            this.quantityLabel(best.count, this.unitOption(best.pack.unit) || unit) +
            ' instead.'
        );
    }

    costOfBase(baseQty: number, item: ItemConfig | null): number | null {
        const costPerUnit = item ? item.costPerUnit : null;

        if (costPerUnit === null || costPerUnit === undefined) return null;

        const trading = item ? this.unitOption(item.unit) : null;
        const pack = this.declaredPacks().find((entry) => entry.unit === item!.unit);

        if (pack && pack.per > 0) return (baseQty / pack.per) * costPerUnit;

        return baseQty * (trading && trading.multiplier ? costPerUnit / trading.multiplier : costPerUnit);
    }

    // -- pricing --------------------------------------------------------------

    get effectivePrice(): number {
        if (this.isSale) {
            if (this.sellingPrice !== '' && this.sellingPrice !== null) {
                return Number(this.sellingPrice);
            }
            const unit = this.selectedUnit;
            if (unit) {
                const historyPrice = this.itemSellingPriceForUnit(unit.code);
                if (historyPrice !== null && historyPrice > 0) {
                    return Number(historyPrice);
                }
            }
            return Number(this.unitCost || 0);
        }
        return Number(this.unitCost || 0);
    }

    itemSellingPriceForUnit(code: string): number | null {
        const item = this.currentItem;
        if (!item || !item.sellingPricePerUnitByUnit) return null;

        const price = item.sellingPricePerUnitByUnit[code];
        return price === undefined || price === null ? null : price;
    }

    get purchaseSellingPriceDisplay(): string {
        const unit = this.selectedUnit;
        if (!unit) return '';
        const price = this.itemSellingPriceForUnit(unit.code);
        return price === null ? '' : 'Selling price: ' + this.money(price);
    }

    // -- totals ---------------------------------------------------------------

    get total(): number {
        return this.lines.reduce((sum, line) => sum + line.total, 0);
    }

    get pending(): Record<string, number> {
        const totals: Record<string, number> = {};
        this.lines.forEach((line) => {
            const key = String(line.itemKey);
            totals[key] = (totals[key] || 0) + line.baseQty;
        });
        return totals;
    }

    baseStockOf(item: ItemConfig): number {
        const unit = (this.config.units[item.family] || []).find((u) => u.code === item.unit);
        return (item.stock || 0) * (unit ? unit.multiplier : 1);
    }

    pendingBaseFor(item: ItemConfig): number {
        const qty = Number(this.qty);
        if (!this.selectedUnit || !(qty > 0)) return 0;
        if (this.isSale && String(item.id) !== String(this.itemId)) return 0;
        return this.toBase(qty, this.selectedUnit);
    }

    get shortfall(): string {
        if (!this.isSale) return '';

        for (const item of this.items) {
            const available = this.baseStockOf(item);
            const wanted = (this.pending[item.id] || 0) + this.pendingBaseFor(item);

            if (available > 0 && wanted > available + 1e-9) {
                return item.name + ': only ' + item.stockLabel + ' in stock';
            }
        }

        return '';
    }

    // -- draft ----------------------------------------------------------------

    get hasDraft(): boolean {
        if (this.isSale) {
            return Boolean(this.currentItem && this.selectedUnit);
        }
        return Boolean(
            (this.currentItem || this.name.trim()) && this.measurement && this.selectedUnit
        );
    }

    get draftComplete(): boolean {
        if (this.isSale) {
            return this.hasDraft && Number(this.qty) > 0 && this.effectivePrice >= 0;
        }
        return this.hasDraft && Number(this.qty) > 0 && this.unitCost !== '' && Number(this.unitCost) >= 0;
    }

    get draft(): DraftView | null {
        if (!this.hasDraft) return null;

        const item = this.currentItem;
        const unit = this.selectedUnit!;
        const qty = Number(this.qty);
        const price = this.effectivePrice;
        const ready = this.draftComplete;

        const cost = ready && this.isSale ? this.costOfBase(this.toBase(qty, unit), item) : null;
        const total = ready ? qty * price : 0;
        const profit = cost === null ? null : total - cost;

        return {
            label: item ? item.name : this.name.trim(),
            qty,
            qtyLabel: this.formatQty(ready ? qty : ''),
            unit: unit.code,
            unitLabel: this.unitLabel(unit.code),
            price: ready ? price : '',
            total,
            equivalents: ready ? this.equivalentsFor(qty, unit) : '',
            cost,
            profit,
            profitClass: this.profitClass(profit),
            profitLabel: this.profitLabel(profit),
        };
    }

    get preview(): string {
        const qty = Number(this.qty);
        const price = this.effectivePrice;

        if (!(qty > 0) || price < 0) return '';

        return 'Line total : ' + this.money(qty * price);
    }

    // -- mutations ------------------------------------------------------------

    setName(value: string): void {
        this.name = value;
        this.syncItem();
        this.notify();
    }

    /**
     * A sale picks its item by id from what is in stock, so selecting one has
     * to carry the id over and re-sync the measurement, unit, auto-cost and
     * reference selling price the way picking a name does on the buy page.
     */
    setItemId(value: string | number): void {
        this.itemId = value === '' ? '' : String(value);
        this.syncItem();
        this.notify();
    }

    setDate(value: string): void {
        this.date = value;
        this.notify();
    }

    setQty(value: string): void {
        this.qty = value;
        this.notify();
    }

    setUnitCost(value: string): void {
        this.unitCost = value;
        this.updateInnerUnitCosts();
        this.notify();
    }

    setSellingPrice(value: string): void {
        this.sellingPrice = value;
        this.notify();
    }

    setMeasurement(value: string): void {
        this.measurement = value;
        this.unit = '';
        this.unitCost = '';
        this.sellingPrice = '';
        this.packs = this.packs.map(() => emptyPack());
        this.notify();
    }

    setUnit(value: string): void {
        this.unit = value;
        this.onUnitChange();
        this.notify();
    }

    setPackField(index: number, field: keyof PackState, value: string): void {
        const pack = this.packs[index];
        if (!pack) return;
        pack[field] = value;
        this.updateInnerUnitCosts();
        this.notify();
    }

    onUnitChange(): void {
        if (this.isSale && this.currentItem && this.selectedUnit) {
            this.fetchUnitCost(this.currentItem.id, this.selectedUnit.code);
        }
    }

    fetchUnitCost(itemId: number, unitCode: string): void {
        const item = this.items.find((i) => i.id === itemId);
        if (item && item.costPerUnitByUnit && item.costPerUnitByUnit[unitCode] !== undefined) {
            this.unitCost = String(item.costPerUnitByUnit[unitCode]);
        } else {
            this.unitCost = '';
        }
        this.sellingPrice = '';
        this.updateInnerUnitCosts();
    }

    syncItem(): void {
        const item = this.currentItem;
        const previous = this.unit;

        this.measurement = item ? item.family : '';
        this.unit = item ? item.unit : '';

        const stillValid = this.unitOptions.some((unit) => unit.code === previous);
        if (stillValid) this.unit = previous;

        if (this.isSale && item) {
            if (this.unit) {
                this.fetchUnitCost(item.id, this.unit);
            }
            if (item.sellingPricePerUnitByUnit && item.sellingPricePerUnitByUnit[this.unit] !== undefined) {
                this.sellingPrice = String(item.sellingPricePerUnitByUnit[this.unit]);
            }
        } else if (!this.isSale && item) {
            (item.packs || []).forEach((pack, slot) => {
                if (slot < this.packs.length) {
                    this.packs[slot] = {
                        unit: pack.unit,
                        per: String(pack.per),
                        cost: pack.cost !== undefined && pack.cost !== null ? String(pack.cost) : '',
                        sellingPrice: pack.sellingPrice !== undefined && pack.sellingPrice !== null ? String(pack.sellingPrice) : '',
                    };
                }
            });
            if (item.lastSellingPrice !== undefined && item.lastSellingPrice !== null) {
                this.sellingPrice = String(item.lastSellingPrice);
            }
            this.updateInnerUnitCosts();
        }
    }

    updateInnerUnitCosts(): void {
        if (this.isSale) return;
        const baseCost = Number(this.unitCost);
        if (Number.isNaN(baseCost) || baseCost <= 0) return;
        this.packs = this.packs.map((pack) => {
            if (!pack.unit || !pack.per) return pack;
            const per = Number(pack.per);
            if (per <= 0 || Number.isNaN(per)) return pack;
            const costPerInner = baseCost / per;
            return { ...pack, cost: String(Math.round(costPerInner * 1000000) / 1000000) };
        });
    }

    addPack(): void {
        if (this.packs.length >= 10) return;
        this.packs.push(emptyPack());
        this.notify();
    }

    removePack(index: number): void {
        this.packs.splice(index, 1);
        this.notify();
    }

    dragStartPack(index: number): void {
        this.draggingPackIndex = index;
        this.notify();
    }

    dragOverPack(index: number): void {
        if (index === this.draggingPackIndex) return;
        this.dragOverPackIndex = index;
        this.notify();
    }

    dropPack(index: number): void {
        if (index === this.draggingPackIndex) return;
        const pack = this.packs.splice(this.draggingPackIndex, 1)[0];
        this.packs.splice(index, 0, pack);
        this.draggingPackIndex = -1;
        this.dragOverPackIndex = -1;
        this.notify();
    }

    dragEndPack(): void {
        this.draggingPackIndex = -1;
        this.dragOverPackIndex = -1;
        this.notify();
    }

    add(): void {
        const qty = Number(this.qty);
        const unit = this.selectedUnit;
        const item = this.isSale ? this.selected : this.namedItem;
        const label = this.isSale ? item && item.name : this.name.trim();

        if (!label) return this.warn(this.isSale ? 'Choose an item to sell' : 'Name the item you are buying');
        if (!unit) return this.warn('Choose a unit');
        if (!(qty > 0)) return this.warn('Fill in the quantity');
        if (!this.isSale && (this.unitCost === '' || this.unitCost === null)) {
            return this.warn('Fill in the cost per ' + unit.label);
        }
        if (this.isSale && this.effectivePrice < 0) {
            return this.warn('Selling price cannot be negative');
        }
        if (this.isSale && this.unitCost !== '' && this.unitCost !== null && this.effectivePrice < Number(this.unitCost)) {
            return this.warn('Selling price cannot be less than cost per ' + unit.label);
        }

        const problem = this.quantityProblem();
        if (problem) return this.warn(problem);

        const baseQty = this.toBase(qty, unit);

        if (this.isSale && item) {
            const alreadyOn = this.pending[item.id] || 0;
            const available = this.baseStockOf(item);

            if (alreadyOn + baseQty > available + 1e-9) {
                return this.warn('Only ' + item.stockLabel + ' of ' + item.name + ' in stock');
            }
        }

        const cost = this.isSale ? this.costOfBase(baseQty, item) : null;
        const price = this.effectivePrice;
        const lineTotal = qty * price;

        this.lines.push({
            itemKey: item ? item.id : null,
            itemId: item ? item.id : '',
            name: this.isSale ? '' : this.name.trim(),
            label: label as string,
            qty,
            unit: unit.code,
            unitLabel: unit.label,
            baseQty,
            price,
            total: lineTotal,
            cost,
            profitClass: this.profitClass(this.profitOf({ total: lineTotal, cost })),
            profitLabel: this.profitLabel(this.profitOf({ total: lineTotal, cost })),
            unit_cost: this.isSale ? toNumberOrNull(this.unitCost) : Number(this.unitCost),
            selling_price: toNumberOrNull(this.sellingPrice),
            packs: this.isSale ? [] : this.activePacks(),
            equivalents: this.equivalentsFor(qty, unit),
        });

        this.qty = '';
        this.unitCost = '';
        this.sellingPrice = '';
        this.name = this.isSale ? '' : this.name;
        this.notify();
        toast('Line added');
    }

    remove(index: number): void {
        this.lines.splice(index, 1);
        this.notify();
    }

    clear(): void {
        this.lines = [];
        this.notify();
    }

    warn(message: string): void {
        toast(message);
    }

    reset(): void {
        this.date = this.config.today;
        this.itemId = '';
        this.name = '';
        this.qty = '';
        this.measurement = '';
        this.unit = '';
        this.unitCost = '';
        this.sellingPrice = '';
        this.packs = [];
        this.lines = [];
        this.draggingPackIndex = -1;
        this.dragOverPackIndex = -1;
        this.notify();
    }

    payload(): PurchasePayload {
        return {
            transacted_on: this.date,
            lines: this.lines.map((line) => ({
                item_id: line.itemId || null,
                name: line.name,
                qty: line.qty,
                unit_code: line.unit,
                unit_price: line.price,
                unit_cost: line.unit_cost,
                selling_price: line.selling_price,
                inner_units: line.packs.map((pack) => ({
                    unit: pack.unit,
                    per: pack.per,
                    cost: pack.cost,
                    sellingPrice: pack.sellingPrice,
                })),
            })),
        };
    }
}
