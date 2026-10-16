import { useFormContext, useWatch, useFieldArray } from 'react-hook-form';

interface RecordTotalsProps {
    isSale: boolean;
}

export function RecordTotals({ isSale }: RecordTotalsProps) {
    const { control } = useFormContext();
    const { remove: removeLine } = useFieldArray({ control, name: 'lines' });
    const lines = useWatch({ control, name: 'lines' });

    const total = lines.reduce((sum: number, line: any) => {
        const qty = parseFloat(line.qty) || 0;
        const price = parseFloat(line.unit_price) || 0;
        const multiplier = line.unit?.base_multiplier || 1;
        return sum + (qty * multiplier * price);
    }, 0);

    const totalProfit = isSale ? lines.reduce((sum: number, line: any) => {
        const qty = parseFloat(line.qty) || 0;
        const price = parseFloat(line.unit_price) || 0;
        const cost = parseFloat(line.unit_cost) || 0;
        const multiplier = line.unit?.base_multiplier || 1;
        return sum + (qty * multiplier * (price - cost));
    }, 0) : 0;

    const margin = isSale && total > 0 ? (totalProfit / total) * 100 : 0;

    const totalProfitClass = isSale && totalProfit !== undefined
        ? totalProfit > 0 ? 'text-green-600' : totalProfit < 0 ? 'text-red-600' : 'text-gray-600'
        : '';

    return (
        <div className="row mt-4 flex items-center justify-between">
            <div className="flex items-center gap-4">
                <button
                    type="button"
                    className="btn alt sm"
                    onClick={() => lines.forEach((_: any, i: number) => removeLine(i))}
                >
                    Clear
                </button>
                <span className="text-sm text-gray-600">
                    {lines.length} line(s)
                </span>
            </div>
            <div className="flex items-center gap-4">
                {isSale && (
                    <>
                        <div className="text-right">
                            <div className="text-xs text-gray-500">Total Profit</div>
                            <div className={`font-semibold ${totalProfitClass}`}>
                                {new Intl.NumberFormat('en-US', { style: 'currency', currency: 'NGN' }).format(totalProfit)}
                            </div>
                        </div>
                        <div className="text-right">
                            <div className="text-xs text-gray-500">Margin</div>
                            <div className="font-semibold text-blue-600">
                                {margin.toFixed(1)}%
                            </div>
                        </div>
                    </>
                )}
                <button
                    type="submit"
                    className="btn"
                    disabled={lines.length === 0}
                >
                    Save {isSale ? 'sale' : 'purchase'}
                </button>
            </div>
        </div>
    );
}