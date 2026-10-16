import { useFormContext, useWatch } from 'react-hook-form';

interface LineDraftProps {
    isSale: boolean;
    onAdd: () => void;
}

export function LineDraft({ isSale, onAdd }: LineDraftProps) {
    const { watch } = useFormContext();

    const draft = useWatch({ name: 'draft' });

    if (!draft || !draft.label) return null;

    const unitCode = watch('draft.unit_code');
    const qty = watch('draft.qty');
    const unitPrice = watch('draft.unit_price');
    const unitCost = watch('draft.unit_cost');
    const sellingPrice = watch('draft.selling_price');

    const baseQty = qty && unitCode
        ? parseFloat(qty) * 1
        : 0;

    const total = baseQty * (parseFloat(unitPrice) || 0);

    const profit = isSale && unitCost
        ? total - (baseQty * parseFloat(unitCost))
        : 0;

    const profitClass = isSale && profit !== undefined
        ? profit > 0 ? 'text-green-600' : profit < 0 ? 'text-red-600' : 'text-gray-600'
        : '';

    return (
        <div className="invoice-preview p-4 bg-gray-50 rounded mb-4 border">
            <div className="grid grid-cols-2 gap-4">
                <div>
                    <span className="text-sm text-gray-600">Item</span>
                    <strong className="block">{draft.label}</strong>
                </div>
                <div>
                    <span className="text-sm text-gray-600">Qty</span>
                    <strong className="block">
                        {draft.qtyLabel || ''} {draft.unitLabel || ''}
                    </strong>
                    {draft.equivalents && <small className="text-gray-500 block">{draft.equivalents}</small>}
                </div>

                {isSale ? (
                    <>
                        <div>
                            <span className="text-sm text-gray-600">Cost / unit</span>
                            <strong className="block">
                                {new Intl.NumberFormat('en-US', { style: 'currency', currency: 'NGN' }).format(unitCost || 0)}
                            </strong>
                        </div>
                        <div>
                            <span className="text-sm text-gray-600">Sell Price / unit</span>
                            <strong className="block">
                                {new Intl.NumberFormat('en-US', { style: 'currency', currency: 'NGN' }).format(sellingPrice || unitPrice || 0)}
                            </strong>
                        </div>
                        <div>
                            <span className="text-sm text-gray-600">Total</span>
                            <strong className="block">
                                {new Intl.NumberFormat('en-US', { style: 'currency', currency: 'NGN' }).format(total)}
                            </strong>
                        </div>
                        <div>
                            <span className="text-sm text-gray-600">Profit</span>
                            <strong className={`block ${profitClass}`}>
                                {new Intl.NumberFormat('en-US', { style: 'currency', currency: 'NGN' }).format(profit)}
                            </strong>
                        </div>
                    </>
                ) : (
                    <>
                        <div>
                            <span className="text-sm text-gray-600">Cost / unit</span>
                            <strong className="block">
                                {new Intl.NumberFormat('en-US', { style: 'currency', currency: 'NGN' }).format(unitPrice || 0)}
                            </strong>
                        </div>
                        <div>
                            <span className="text-sm text-gray-600">Total</span>
                            <strong className="block">
                                {new Intl.NumberFormat('en-US', { style: 'currency', currency: 'NGN' }).format(total)}
                            </strong>
                        </div>
                    </>
                )}

                <div className="col-span-2">
                    <button type="button" onClick={onAdd} className="btn sm mt-2">
                        Add
                    </button>
                </div>
            </div>
        </div>
    );
}