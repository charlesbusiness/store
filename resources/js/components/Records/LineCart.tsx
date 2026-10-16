import { useFormContext, useFieldArray } from 'react-hook-form';

interface LineCartProps {
    isSale: boolean;
}

export function LineCart({ isSale }: LineCartProps) {
    const { control, getValues } = useFormContext();
    const { remove } = useFieldArray({ control, name: 'lines' });
    const lines = getValues('lines');

    if (!lines.length) return null;

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

    const totalProfitClass = isSale && totalProfit !== undefined
        ? totalProfit > 0 ? 'text-green-600' : totalProfit < 0 ? 'text-red-600' : 'text-gray-600'
        : '';

    return (
        <div className="sc mt-4">
            <table className="w-full">
                <thead>
                    <tr className="border-b">
                        <th className="text-left p-2">Item</th>
                        <th className="text-right p-2">Qty</th>
                        {isSale ? (
                            <>
                                <th className="text-right p-2">Cost / unit</th>
                                <th className="text-right p-2">Sell Price / unit</th>
                                <th className="text-right p-2">Total</th>
                                <th className="text-right p-2">Profit</th>
                            </>
                        ) : (
                            <>
                                <th className="text-right p-2">Cost / unit</th>
                                <th className="text-right p-2">Total</th>
                            </>
                        )}
                        <th className="text-right p-2"></th>
                    </tr>
                </thead>
                <tbody>
                    {lines.map((line: any, index: number) => (
                        <tr key={line.id} className="border-b hover:bg-gray-50">
                            <td className="p-2 text-left">{line.label || line.name}</td>
                            <td className="p-2 text-right">
                                {line.qtyLabel || line.qty} {line.unitLabel || line.unit_code}
                            </td>
                            {isSale ? (
                                <>
                                    <td className="p-2 text-right">
                                        {new Intl.NumberFormat('en-US', { style: 'currency', currency: 'NGN' }).format(line.unit_cost || 0)}
                                    </td>
                                    <td className="p-2 text-right">
                                        {new Intl.NumberFormat('en-US', { style: 'currency', currency: 'NGN' }).format(line.selling_price || line.unit_price || 0)}
                                    </td>
                                    <td className="p-2 text-right">
                                        {new Intl.NumberFormat('en-US', { style: 'currency', currency: 'NGN' }).format(line.total || 0)}
                                    </td>
                                    <td className="p-2 text-right">
                                        <span className={line.profitClass || ''}>
                                            {new Intl.NumberFormat('en-US', { style: 'currency', currency: 'NGN' }).format(line.profit || 0)}
                                        </span>
                                    </td>
                                </>
                            ) : (
                                <>
                                    <td className="p-2 text-right">
                                        {new Intl.NumberFormat('en-US', { style: 'currency', currency: 'NGN' }).format(line.price || 0)}
                                    </td>
                                    <td className="p-2 text-right">
                                        {new Intl.NumberFormat('en-US', { style: 'currency', currency: 'NGN' }).format(line.total || 0)}
                                    </td>
                                </>
                            )}
                            <td className="p-2 text-right">
                                <button
                                    type="button"
                                    onClick={() => remove(index)}
                                    className="text-red-500 hover:text-red-700"
                                    title="Remove this line"
                                >
                                    ×
                                </button>
                            </td>
                        </tr>
                    ))}
                </tbody>
                <tfoot>
                    <tr className="font-bold border-t-2">
                        {isSale ? (
                            <>
                                <td colSpan={2} className="p-2">Sale total</td>
                                <td className="p-2 text-right">
                                    {new Intl.NumberFormat('en-US', { style: 'currency', currency: 'NGN' }).format(total)}
                                </td>
                                <td className={`p-2 text-right ${totalProfitClass}`}>
                                    {new Intl.NumberFormat('en-US', { style: 'currency', currency: 'NGN' }).format(totalProfit)}
                                </td>
                                <td className="p-2"></td>
                            </>
                        ) : (
                            <>
                                <td colSpan={2} className="p-2">Purchase total</td>
                                <td className="p-2 text-right">
                                    {new Intl.NumberFormat('en-US', { style: 'currency', currency: 'NGN' }).format(total)}
                                </td>
                                <td className="p-2"></td>
                            </>
                        )}
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}