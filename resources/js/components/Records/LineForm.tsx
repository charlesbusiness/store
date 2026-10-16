import { useFormContext, useWatch } from 'react-hook-form';
import { SearchableSelect } from './SearchableSelect';

interface LineFormProps {
    index: number;
    isSale: boolean;
    units: any[];
    items: any[];
    onRemove: () => void;
    onAddInnerUnit: () => void;
    onRemoveInnerUnit: (innerIndex: number) => void;
}

export function LineForm({
    index,
    isSale,
    units,
    items,
    onRemove,
    onAddInnerUnit,
    onRemoveInnerUnit,
}: LineFormProps) {
    const { register, watch, setValue } = useFormContext();
    const family = watch(`lines.${index}.family`);
    const unitCode = watch(`lines.${index}.unit_code`);
    const qty = watch(`lines.${index}.qty`);
    const unitPrice = watch(`lines.${index}.unit_price`);
    const unitCost = watch(`lines.${index}.unit_cost`);
    const innerUnits = useWatch({ name: `lines.${index}.inner_units` });

    const familyUnits = units.filter((u: any) => u.family === family && u.is_active);
    const familyItems = items.filter((i: any) => i.family === family);

    const baseQty = qty && unitCode
        ? parseFloat(qty) * (familyUnits.find((u: any) => u.code === unitCode)?.base_multiplier || 1)
        : 0;

    const total = baseQty * (parseFloat(unitPrice) || 0);

    const profit = isSale && unitCost
        ? total - (baseQty * parseFloat(unitCost))
        : 0;

    const profitClass = isSale && profit !== undefined
        ? profit > 0 ? 'text-green-600' : profit < 0 ? 'text-red-600' : 'text-gray-600'
        : '';

    return (
        <div className="border p-4 rounded space-y-4 bg-gray-50">
            <div className="flex justify-between">
                <h4 className="font-medium">Line {index + 1}</h4>
                <button
                    type="button"
                    onClick={onRemove}
                    className="text-red-500 hover:text-red-700"
                >
                    Remove
                </button>
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-medium mb-1">Item</label>
                    <SearchableSelect
                        options={familyItems.map((i: any) => ({
                            value: i.id.toString(),
                            label: i.name,
                            item: i,
                        }))}
                        value={watch(`lines.${index}.item_id`) || ''}
                        onChange={(val) => {
                            const item = familyItems.find((i: any) => i.id.toString() === val);
                            if (item) {
                                setValue(`lines.${index}.item_id`, item.id);
                                setValue(`lines.${index}.name`, item.name);
                            }
                        }}
                        placeholder="Select or type item name"
                    />
                    <input
                        {...register(`lines.${index}.name`)}
                        type="hidden"
                    />
                </div>

                <div>
                    <label className="block text-sm font-medium mb-1">Family</label>
                    <select
                        {...register(`lines.${index}.family`)}
                        className="input"
                        required
                    >
                        <option value="">Select family</option>
                        <option value="count">Count</option>
                        <option value="weight">Weight</option>
                        <option value="volume">Volume</option>
                        <option value="length">Length</option>
                        <option value="area">Area</option>
                    </select>
                </div>

                <div>
                    <label className="block text-sm font-medium mb-1">Unit</label>
                    <select
                        {...register(`lines.${index}.unit_code`)}
                        className="input"
                        required
                    >
                        <option value="">Select unit</option>
                        {familyUnits.map((u: any) => (
                            <option key={u.code} value={u.code}>
                                {u.label}
                            </option>
                        ))}
                    </select>
                </div>

                <div>
                    <label className="block text-sm font-medium mb-1">Qty</label>
                    <input
                        {...register(`lines.${index}.qty`)}
                        type="number"
                        step="any"
                        min="0.001"
                        className="input"
                        required
                    />
                </div>

                <div>
                    <label className="block text-sm font-medium mb-1">
                        {isSale ? 'Selling Price' : 'Unit Cost'}
                    </label>
                    <input
                        {...register(`lines.${index}.unit_price`)}
                        type="number"
                        step="0.01"
                        min="0"
                        className="input"
                        required
                    />
                </div>

                {isSale && (
                    <div>
                        <label className="block text-sm font-medium mb-1">Unit Cost (optional)</label>
                        <input
                            {...register(`lines.${index}.unit_cost`)}
                            type="number"
                            step="0.01"
                            min="0"
                            className="input"
                        />
                    </div>
                )}

                {!isSale && (
                    <div>
                        <label className="block text-sm font-medium mb-1">Selling Price (optional)</label>
                        <input
                            {...register(`lines.${index}.selling_price`)}
                            type="number"
                            step="0.01"
                            min="0"
                            className="input"
                        />
                    </div>
                )}

                <div className="col-span-2 text-sm text-gray-600">
                    <p>Base Qty: {baseQty.toFixed(3)}</p>
                    <p>Line Total: {new Intl.NumberFormat('en-US', { style: 'currency', currency: 'NGN' }).format(total)}</p>
                    {isSale && profit !== undefined && (
                        <p className={profitClass}>Profit: {new Intl.NumberFormat('en-US', { style: 'currency', currency: 'NGN' }).format(profit)}</p>
                    )}
                </div>
            </div>

            <div className="space-y-2">
                <div className="flex justify-between items-center">
                    <h5 className="font-medium">Inner Units</h5>
                    <button
                        type="button"
                        onClick={onAddInnerUnit}
                        className="btn alt xs"
                    >
                        + Add Inner Unit
                    </button>
                </div>

                {innerUnits.map((inner: any, innerIndex: number) => (
                    <div key={inner.id} className="grid grid-cols-5 gap-2">
                        <select
                            {...register(`lines.${index}.inner_units.${innerIndex}.unit`)}
                            className="input"
                            required
                        >
                            <option value="">Unit</option>
                            {familyUnits.map((u: any) => (
                                <option key={u.code} value={u.code}>
                                    {u.label}
                                </option>
                            ))}
                        </select>
                        <input
                            {...register(`lines.${index}.inner_units.${innerIndex}.per`)}
                            type="number"
                            min="1"
                            step="1"
                            className="input"
                            placeholder="Per"
                            required
                        />
                        <input
                            {...register(`lines.${index}.inner_units.${innerIndex}.cost`)}
                            type="number"
                            step="0.01"
                            min="0"
                            className="input"
                            placeholder="Cost"
                        />
                        <input
                            {...register(`lines.${index}.inner_units.${innerIndex}.sellingPrice`)}
                            type="number"
                            step="0.01"
                            min="0"
                            className="input"
                            placeholder="Sell Price"
                        />
                        <button
                            type="button"
                            onClick={() => onRemoveInnerUnit(innerIndex)}
                            className="text-red-500 hover:text-red-700"
                        >
                            ✕
                        </button>
                    </div>
                ))}
            </div>
        </div>
    );
}