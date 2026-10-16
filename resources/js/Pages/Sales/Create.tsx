import { FormEvent, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { useRecordFormModel } from '@/hooks';
import type { PurchasePayload, RecordConfig } from '@/lib/recordForm';
import { SearchableSelect } from '@/components/Records';

export default function SaleCreate() {
    const queryClient = useQueryClient();

    const { data, isLoading } = useQuery<{ data: RecordConfig }>({
        queryKey: ['record-form', 'sale'],
        queryFn: () => api.get('/sales/form'),
    });

    const config = data?.data ?? null;
    const model = useRecordFormModel(config);

    const [status, setStatus] = useState<string | null>(null);
    const [errors, setErrors] = useState<string[]>([]);

    const createMutation = useMutation({
        mutationFn: (payload: PurchasePayload) => api.post('/sales', payload),
        onSuccess: (response: any) => {
            setStatus(response?.message ?? 'Sale recorded.');
            setErrors([]);
            model?.reset();
            // Stock is drawn down, so both the ledger and the sellable list move.
            queryClient.invalidateQueries({ queryKey: ['sales'] });
            queryClient.invalidateQueries({ queryKey: ['record-form', 'sale'] });
        },
        onError: (error: any) => {
            const payload = error?.response?.data;
            setStatus(null);
            if (payload?.errors) {
                setErrors(Object.values(payload.errors).flat() as string[]);
            } else {
                setErrors([payload?.message ?? 'Failed to record sale.']);
            }
        },
    });

    const handleSubmit = (event: FormEvent) => {
        event.preventDefault();
        if (!model || model.lines.length === 0) return;
        createMutation.mutate(model.payload());
    };

    if (isLoading || !model) {
        return (
            <div className="card">
                <h2>Record a sale</h2>
            </div>
        );
    }

    const draft = model.draft;
    const costsKnown = model.lines.every((line) => line.cost !== null && line.cost !== undefined);
    const totalProfit = costsKnown
        ? model.total - model.lines.reduce((sum, line) => sum + (line.cost ?? 0), 0)
        : null;

    return (
        <>
            {status && <div className="ok-msg">{status}</div>}
            {errors.length > 0 && (
                <div className="err">
                    <ul>
                        {errors.map((error, index) => (
                            <li key={index}>{error}</li>
                        ))}
                    </ul>
                </div>
            )}

            <form onSubmit={handleSubmit} noValidate>
                <div className="card">
                    <h2>Record a sale</h2>

                    {model.items.length === 0 ? (
                        <div className="e">No stock available. Record a purchase first.</div>
                    ) : (
                        <div className="form-rows">
                            <div className="form-row">
                                <label className="form-field searchable-select-field">
                                    Item
                                    <SearchableSelect
                                        options={model.items.map((item) => ({
                                            value: String(item.id),
                                            label: item.name,
                                            hint: item.stockLabel ? `${item.stockLabel} left` : '',
                                        }))}
                                        value={String(model.itemId ?? '')}
                                        onChange={(value) => model.setItemId(value)}
                                        placeholder="Search items..."
                                    />
                                </label>

                                <label className="form-field">
                                    Date
                                    <input
                                        type="date"
                                        name="transacted_on"
                                        value={model.date}
                                        onChange={(event) => model.setDate(event.target.value)}
                                        required
                                    />
                                </label>

                                <label className="form-field">
                                    Measurement
                                    <select
                                        value={model.measurement}
                                        onChange={(event) => model.setMeasurement(event.target.value)}
                                        disabled={!!model.currentItem}
                                    >
                                        <option value="">Choose</option>
                                        {model.config.families.map((family) => (
                                            <option key={family.value} value={family.value}>
                                                {family.label}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            </div>

                            {(model.measurement || model.currentItem) && (
                                <div className="form-row row-2-sale">
                                    <label className="form-field">
                                        Unit
                                        <select
                                            value={model.unit}
                                            onChange={(event) => model.setUnit(event.target.value)}
                                            disabled={!model.measurement && !model.currentItem}
                                        >
                                            <option value="">Choose</option>
                                            {model.unitOptions.map((option) => (
                                                <option key={option.code} value={option.code}>
                                                    {option.label}
                                                </option>
                                            ))}
                                        </select>
                                    </label>

                                    <label className="form-field">
                                        Quantity
                                        <input
                                            type="number"
                                            min={0}
                                            step="any"
                                            inputMode="decimal"
                                            value={model.qty}
                                            onChange={(event) => model.setQty(event.target.value)}
                                            placeholder="0"
                                            required
                                        />
                                    </label>

                                    <label className="form-field">
                                        <span>{model.unitCostLabel}</span>
                                        <input
                                            type="number"
                                            min={0}
                                            step="any"
                                            inputMode="decimal"
                                            value={model.unitCost}
                                            readOnly
                                            placeholder="Auto-filled from purchase"
                                        />
                                    </label>

                                    <label className="form-field">
                                        {model.purchaseSellingPriceDisplay && (
                                            <span className="pv mut">{model.purchaseSellingPriceDisplay}</span>
                                        )}
                                        <input
                                            type="number"
                                            min={0}
                                            step="any"
                                            inputMode="decimal"
                                            value={model.sellingPrice}
                                            onChange={(event) => model.setSellingPrice(event.target.value)}
                                            placeholder="Optional - defaults to cost"
                                        />
                                    </label>
                                </div>
                            )}

                            <div className="pv">{model.preview}</div>
                            {model.shortfall && <div className="pv bad">{model.shortfall}</div>}

                            <div className="row">
                                <button
                                    type="button"
                                    className="btn"
                                    onClick={() => model.add()}
                                    disabled={!model.draftComplete}
                                >
                                    Add line
                                </button>
                            </div>
                        </div>
                    )}
                </div>

                {(model.lines.length > 0 || model.hasDraft) && (
                    <div className="card">
                        <div className="card-head">
                            <h2>Current sale</h2>
                            <button type="button" className="btn alt sm" onClick={() => model.clear()}>
                                Clear
                            </button>
                        </div>

                        {draft && (
                            <div className="invoice-preview">
                                <div>
                                    <span>Item</span>
                                    <strong>{draft.label}</strong>
                                </div>
                                <div>
                                    <span>Qty</span>
                                    <strong>
                                        {draft.qtyLabel} {draft.unitLabel}
                                    </strong>
                                    {draft.equivalents && <small>{draft.equivalents}</small>}
                                </div>
                                <div>
                                    <span>Cost / unit</span>
                                    <strong>{model.unitCost === '' ? '—' : model.money(Number(model.unitCost))}</strong>
                                </div>
                                <div>
                                    <span>Sell Price / unit</span>
                                    <strong>{model.money(draft.price)}</strong>
                                </div>
                                <div>
                                    <span>Total</span>
                                    <strong>{model.money(draft.total)}</strong>
                                </div>
                                <div>
                                    <span>Profit</span>
                                    <strong className={draft.profitClass}>{draft.profitLabel}</strong>
                                </div>
                                <button type="button" className="btn sm" onClick={() => model.add()}>
                                    Add
                                </button>
                            </div>
                        )}

                        {model.lines.length > 0 && (
                            <div className="sc">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th className="r">Qty</th>
                                            <th className="r">Cost / unit</th>
                                            <th className="r">Sell Price / unit</th>
                                            <th className="r">Total</th>
                                            <th className="r">Profit</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {model.lines.map((line, index) => (
                                            <tr key={index}>
                                                <td>{line.label}</td>
                                                <td className="r">
                                                    {model.formatQty(line.qty)} {line.unitLabel}
                                                </td>
                                                <td className="r">
                                                    {line.unit_cost === null || line.unit_cost === undefined
                                                        ? '—'
                                                        : model.money(line.unit_cost)}
                                                </td>
                                                <td className="r">{model.money(line.selling_price ?? line.price)}</td>
                                                <td className="r">{model.money(line.total)}</td>
                                                <td className={'r ' + line.profitClass}>{line.profitLabel}</td>
                                                <td>
                                                    <button
                                                        type="button"
                                                        className="x"
                                                        onClick={() => model.remove(index)}
                                                        title="Remove this line"
                                                    >
                                                        &times;
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colSpan={4}>Sale total</td>
                                            <td className="r">{model.money(model.total)}</td>
                                            <td className={'r ' + model.profitClass(totalProfit)}>
                                                {model.profitLabel(totalProfit)}
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        )}

                        <div className="row">
                            <button
                                className="btn"
                                type="submit"
                                disabled={model.lines.length === 0 || createMutation.isPending}
                            >
                                Save sale
                            </button>
                            <span className="mut">{model.lines.length} line(s)</span>
                        </div>
                    </div>
                )}
            </form>
        </>
    );
}
