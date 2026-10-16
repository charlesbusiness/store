import { FormEvent, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { useRecordFormModel } from '@/hooks';
import type { PurchasePayload, RecordConfig } from '@/lib/recordForm';

export default function PurchaseCreate() {
    const queryClient = useQueryClient();

    const { data, isLoading } = useQuery<{ data: RecordConfig }>({
        queryKey: ['record-form', 'purchase'],
        queryFn: () => api.get('/purchases/form'),
    });

    const config = data?.data ?? null;
    const model = useRecordFormModel(config);

    const [status, setStatus] = useState<string | null>(null);
    const [errors, setErrors] = useState<string[]>([]);

    const createMutation = useMutation({
        mutationFn: (payload: PurchasePayload) => api.post('/purchases', payload),
        onSuccess: (response: any) => {
            setStatus(response?.message ?? 'Purchase recorded.');
            setErrors([]);
            model?.reset();
            queryClient.invalidateQueries({ queryKey: ['purchases'] });
        },
        onError: (error: any) => {
            const payload = error?.response?.data;
            setStatus(null);
            if (payload?.errors) {
                setErrors(Object.values(payload.errors).flat() as string[]);
            } else {
                setErrors([payload?.message ?? 'Failed to record purchase.']);
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
                <h2>Record a purchase</h2>
            </div>
        );
    }

    const draft = model.draft;

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
                    <h2>Record a purchase</h2>

                    <div className="form-rows">
                        <div className="form-row">
                            <label className="form-field">
                                Item
                                <textarea
                                    rows={2}
                                    {...({ list: 'item-names' } as Record<string, string>)}
                                    value={model.name}
                                    onChange={(event) => model.setName(event.target.value)}
                                    placeholder="e.g. Rice"
                                    autoComplete="off"
                                />
                                <datalist id="item-names">
                                    {model.items.map((item) => (
                                        <option key={item.id} value={item.name} />
                                    ))}
                                </datalist>
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
                            <div className="form-row row-2-buy">
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
                                        onChange={(event) => model.setUnitCost(event.target.value)}
                                        placeholder="0"
                                        required
                                    />
                                </label>

                                <label className="form-field">
                                    <span>{model.sellingPriceLabel}</span>
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

                        <div style={{ display: model.measurement || model.currentItem ? undefined : 'none' }}>
                            {model.packs.map((pack, packIndex) => (
                                <div
                                    key={packIndex}
                                    className={
                                        'form-row inner-unit-row' +
                                        (model.draggingPackIndex === packIndex ? ' dragging' : '') +
                                        (model.dragOverPackIndex === packIndex ? ' drag-over' : '')
                                    }
                                    draggable
                                    onDragStart={() => model.dragStartPack(packIndex)}
                                    onDragOver={(event) => {
                                        event.preventDefault();
                                        model.dragOverPack(packIndex);
                                    }}
                                    onDrop={() => model.dropPack(packIndex)}
                                    onDragEnd={() => model.dragEndPack()}
                                >
                                    <label
                                        className="form-field"
                                        style={{ display: 'flex', alignItems: 'center', gap: 8 }}
                                    >
                                        <span
                                            className="drag-handle mut"
                                            style={{ cursor: 'grab' }}
                                            title="Drag to reorder"
                                        >
                                            ⋮⋮
                                        </span>
                                        <span className="mut">{`Inner unit ${packIndex + 1}`}</span>
                                        <select
                                            value={pack.unit}
                                            onChange={(event) => model.setPackField(packIndex, 'unit', event.target.value)}
                                            disabled={!model.measurement && !model.currentItem}
                                        >
                                            <option value="">None</option>
                                            {model.innerUnitOptions.map((option) => (
                                                <option key={option.code} value={option.code}>
                                                    {option.label}
                                                </option>
                                            ))}
                                        </select>
                                    </label>

                                    <label className="form-field">
                                        <span>{`How many per ${model.tradingUnitLabel()}`}</span>
                                        <input
                                            type="number"
                                            min={1}
                                            step={1}
                                            inputMode="numeric"
                                            value={pack.per}
                                            onChange={(event) => model.setPackField(packIndex, 'per', event.target.value)}
                                            placeholder="e.g. 12"
                                        />
                                    </label>

                                    <label className="form-field">
                                        <span>{model.innerUnitCostLabel(packIndex)}</span>
                                        <input
                                            type="number"
                                            min={0}
                                            step="any"
                                            inputMode="decimal"
                                            value={pack.cost}
                                            disabled={!pack.unit}
                                            readOnly={Boolean(pack.unit && pack.per && model.unitCost)}
                                            onChange={(event) => model.setPackField(packIndex, 'cost', event.target.value)}
                                            placeholder="0"
                                        />
                                        {pack.unit && pack.per && model.unitCost ? (
                                            <span className="pv mut" style={{ fontSize: 11 }}>
                                                Auto-calculated from base cost
                                            </span>
                                        ) : null}
                                    </label>

                                    <label className="form-field">
                                        <span>{`Selling price per ${model.innerUnitOptions.find((option) => option.code === pack.unit)?.label || 'unit'}`}</span>
                                        <input
                                            type="number"
                                            min={0}
                                            step="any"
                                            inputMode="decimal"
                                            value={pack.sellingPrice}
                                            disabled={!pack.unit}
                                            onChange={(event) => model.setPackField(packIndex, 'sellingPrice', event.target.value)}
                                            placeholder="Optional"
                                        />
                                    </label>

                                    <button
                                        type="button"
                                        className="x"
                                        onClick={() => model.removePack(packIndex)}
                                        title="Remove"
                                        style={{ alignSelf: 'end', justifySelf: 'end' }}
                                    >
                                        &times;
                                    </button>
                                </div>
                            ))}

                            <button
                                type="button"
                                className="btn alt sm"
                                onClick={() => model.addPack()}
                                disabled={model.packs.length >= 10}
                                style={{ display: model.packs.length < 10 ? undefined : 'none' }}
                            >
                                + Add inner unit
                            </button>
                            {model.packs.length >= 10 && (
                                <span className="pv mut" style={{ fontSize: 12 }}>
                                    Maximum 10 inner units
                                </span>
                            )}
                        </div>
                        <div className="pv">{model.packHint()}</div>

                        <div className="pv">{model.preview}</div>
                        {model.shortfall && <div className="pv bad">{model.shortfall}</div>}

                        <div className="row">
                            <button type="button" className="btn" onClick={() => model.add()} disabled={!model.draftComplete}>
                                Add line
                            </button>
                        </div>
                    </div>
                </div>

                {(model.lines.length > 0 || model.hasDraft) && (
                    <div className="card">
                        <div className="card-head">
                            <h2>Current purchase</h2>
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
                                    <strong>{model.money(draft.price)}</strong>
                                </div>
                                <div>
                                    <span>Total</span>
                                    <strong>{model.money(draft.total)}</strong>
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
                                            <th className="r">Total</th>
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
                                                <td className="r">{model.money(line.price)}</td>
                                                <td className="r">{model.money(line.total)}</td>
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
                                            <td colSpan={3}>Purchase total</td>
                                            <td className="r">{model.money(model.total)}</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        )}

                        <div className="row">
                            <button className="btn" type="submit" disabled={model.lines.length === 0 || createMutation.isPending}>
                                Save purchase
                            </button>
                            <span className="mut">{model.lines.length} line(s)</span>
                        </div>
                    </div>
                )}
            </form>
        </>
    );
}
