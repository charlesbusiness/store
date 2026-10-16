import { useFieldArray, useFormContext } from 'react-hook-form';

interface InnerUnitsProps {
    lineIndex: number;
    family: string;
    units: any[];
}

export function InnerUnits({ lineIndex, family, units }: InnerUnitsProps) {
    const { control, register } = useFormContext();
    const familyUnits = units.filter((u: any) => u.family === family && u.is_active);

    const { fields: innerUnits, append: addInnerUnit, remove: removeInnerUnit } = useFieldArray({
        control,
        name: `lines.${lineIndex}.inner_units`,
    });

    const handleAddInnerUnit = () => {
        addInnerUnit({ id: crypto.randomUUID(), unit: '', per: '', cost: '', sellingPrice: '' });
    };

    return (
        <div className="space-y-2 mt-2 pt-2 border-t">
            <div className="flex justify-between items-center">
                <h5 className="font-medium">Inner Units</h5>
                <button
                    type="button"
                    onClick={handleAddInnerUnit}
                    className="btn alt xs"
                >
                    + Add Inner Unit
                </button>
            </div>

            {innerUnits.map((inner: any, innerIndex: number) => (
                <div key={inner.id} className="grid grid-cols-5 gap-2">
                    <select
                        {...register(`lines.${lineIndex}.inner_units.${innerIndex}.unit`)}
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
                        {...register(`lines.${lineIndex}.inner_units.${innerIndex}.per`)}
                        type="number"
                        min="1"
                        step="1"
                        className="input"
                        placeholder="Per"
                        required
                    />
                    <input
                        {...register(`lines.${lineIndex}.inner_units.${innerIndex}.cost`)}
                        type="number"
                        step="0.01"
                        min="0"
                        className="input"
                        placeholder="Cost"
                    />
                    <input
                        {...register(`lines.${lineIndex}.inner_units.${innerIndex}.sellingPrice`)}
                        type="number"
                        step="0.01"
                        min="0"
                        className="input"
                        placeholder="Sell Price"
                    />
                    <button
                        type="button"
                        onClick={() => removeInnerUnit(innerIndex)}
                        className="text-red-500 hover:text-red-700"
                    >
                        ✕
                    </button>
                </div>
            ))}
        </div>
    );
}