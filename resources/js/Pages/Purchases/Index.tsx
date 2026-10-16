import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { useForm, useFieldArray } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { toast } from 'sonner';

interface LineItem {
    id: string;
    name: string;
    family: string;
    unit: string;
    qty: string;
    unitCost: string;
    sellingPrice: string;
    innerUnits: InnerUnit[];
}

interface InnerUnit {
    id: string;
    unit: string;
    per: string;
    cost: string;
    sellingPrice: string;
}

interface Purchase {
    id: number;
    transacted_on: string;
    total: number;
    lines: LineItem[];
}

const lineSchema = z.object({
    id: z.string(),
    name: z.string().min(1, 'Item name required'),
    family: z.string().min(1, 'Family required'),
    unit: z.string().min(1, 'Unit required'),
    qty: z.string().min(1, 'Quantity required'),
    unitCost: z.string().min(1, 'Unit cost required'),
    sellingPrice: z.string().optional(),
    innerUnits: z.array(z.object({
        id: z.string(),
        unit: z.string().min(1, 'Unit required'),
        per: z.string().min(1, 'Per required'),
        cost: z.string().optional(),
        sellingPrice: z.string().optional(),
    })),
});

const purchaseSchema = z.object({
    transacted_on: z.string().min(1, 'Date required'),
    lines: z.array(lineSchema).min(1, 'At least one line required'),
});

export default function Purchases() {
    const queryClient = useQueryClient();
    const [showForm, setShowForm] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);

    const { data: purchases } = useQuery({
        queryKey: ['purchases'],
        queryFn: () => api.get('/purchases'),
    });

    const { data: unitsResponse } = useQuery({
        queryKey: ['units'],
        queryFn: () => api.get('/units'),
    });

    const units = unitsResponse?.data || [];

    const form = useForm<z.infer<typeof purchaseSchema>>({
        resolver: zodResolver(purchaseSchema),
        defaultValues: {
            transacted_on: new Date().toISOString().split('T')[0],
            lines: [{ id: crypto.randomUUID(), name: '', family: '', unit: '', qty: '', unitCost: '', sellingPrice: '', innerUnits: [] }]
        },
    });

    const { fields: lines, append, remove: removeLine } = useFieldArray({
        control: form.control,
        name: 'lines',
    });

    const createMutation = useMutation({
        mutationFn: (data: any) => api.post('/purchases', data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['purchases'] });
            form.reset({ transacted_on: new Date().toISOString().split('T')[0], lines: [{ id: crypto.randomUUID(), name: '', family: '', unit: '', qty: '', unitCost: '', sellingPrice: '', innerUnits: [] }] });
            setShowForm(false);
            toast.success('Purchase created');
        },
        onError: (error: any) => toast.error(error.response?.data?.message || 'Failed to create purchase'),
    });

    const updateMutation = useMutation({
        mutationFn: ({ id, data }: { id: number; data: any }) => api.put(`/purchases/${id}`, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['purchases'] });
            setEditingId(null);
            toast.success('Purchase updated');
        },
    });

    const deleteMutation = useMutation({
        mutationFn: (id: number) => api.delete(`/purchases/${id}`),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['purchases'] }),
    });

    const handleSubmit = (data: any) => {
        if (editingId) {
            updateMutation.mutate({ id: editingId, data });
        } else {
            createMutation.mutate(data);
        }
    };

    const addInnerUnit = (lineIndex: number) => {
        form.setValue(`lines.${lineIndex}.innerUnits`, [
            ...(form.getValues(`lines.${lineIndex}.innerUnits`) || []),
            { id: crypto.randomUUID(), unit: '', per: '', cost: '', sellingPrice: '' },
        ]);
    };

    const removeInnerUnit = (lineIndex: number, innerIndex: number) => {
        const innerUnits = form.getValues(`lines.${lineIndex}.innerUnits`) || [];
        form.setValue(`lines.${lineIndex}.innerUnits`, innerUnits.filter((_: any, i: number) => i !== innerIndex));
    };

    const getUnitsForFamily = (family: string) => {
        return units.filter((u: any) => u.family === family) || [];
    };

    return (
        <div className="p-6">
            <div className="flex justify-between items-center mb-6">
                <h1 className="text-2xl font-bold">Purchases</h1>
                <button
                    onClick={() => { setEditingId(null); form.reset({ transacted_on: new Date().toISOString().split('T')[0], lines: [{ id: crypto.randomUUID(), name: '', family: '', unit: '', qty: '', unitCost: '', sellingPrice: '', innerUnits: [] }] }); setShowForm(true); }}
                    className="btn"
                >
                    + New Purchase
                </button>
            </div>

            {showForm && (
                <div className="card mb-6">
                    <form onSubmit={form.handleSubmit(handleSubmit)} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium mb-1">Date</label>
                                <input {...form.register('transacted_on')} type="date" className="input" required />
                            </div>
                        </div>

                        <div className="space-y-4">
                            <div className="flex justify-between items-center">
                                <h3 className="font-semibold">Line Items</h3>
                                <button type="button" onClick={() => append({ id: crypto.randomUUID(), name: '', family: '', unit: '', qty: '', unitCost: '', sellingPrice: '', innerUnits: [] })} className="btn alt sm">
                                    + Add Line
                                </button>
                            </div>
                            {lines.map((line: any, index: number) => (
                                <div key={line.id} className="border p-4 rounded space-y-4 bg-gray-50">
                                    <div className="flex justify-between">
                                        <h4 className="font-medium">Line {index + 1}</h4>
                                        <button type="button" onClick={() => removeLine(index)} className="text-red-500 hover:text-red-700">Remove</button>
                                    </div>
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium mb-1">Item</label>
                                            <input {...form.register(`lines.${index}.name`)} type="text" placeholder="Item name" className="input" required />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium mb-1">Family</label>
                                            <select {...form.register(`lines.${index}.family`)} className="input" required>
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
                                            <select {...form.register(`lines.${index}.unit`)} className="input" required>
                                                <option value="">Select unit</option>
                                                {getUnitsForFamily(form.watch(`lines.${index}.family`)).map((u: any) => (
                                                    <option key={u.code} value={u.code}>{u.label}</option>
                                                ))}
                                            </select>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium mb-1">Qty</label>
                                            <input {...form.register(`lines.${index}.qty`)} type="number" step="any" min="0.001" className="input" required />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium mb-1">Unit Cost</label>
                                            <input {...form.register(`lines.${index}.unitCost`)} type="number" step="0.01" min="0" className="input" required />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium mb-1">Selling Price (optional)</label>
                                            <input {...form.register(`lines.${index}.sellingPrice`)} type="number" step="0.01" min="0" className="input" />
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <div className="flex justify-between items-center">
                                            <h5 className="font-medium">Inner Units</h5>
                                            <button type="button" onClick={() => addInnerUnit(lines.indexOf(line))} className="btn alt xs">+ Add Inner Unit</button>
                                        </div>
                                        {form.getValues(`lines.${index}.innerUnits`).map((inner: any, innerIndex: number) => (
                                            <div key={inner.id} className="grid grid-cols-5 gap-2">
                                                <select {...form.register(`lines.${index}.innerUnits.${innerIndex}.unit`)} className="input" required>
                                                    <option value="">Unit</option>
                                                    {getUnitsForFamily(form.watch(`lines.${index}.family`)).map((u: any) => (
                                                        <option key={u.code} value={u.code}>{u.label}</option>
                                                    ))}
                                                </select>
                                                <input {...form.register(`lines.${index}.innerUnits.${innerIndex}.per`)} type="number" min="1" step="1" className="input" placeholder="Per" required />
                                                <input {...form.register(`lines.${index}.innerUnits.${innerIndex}.cost`)} type="number" step="0.01" min="0" className="input" placeholder="Cost" />
                                                <input {...form.register(`lines.${index}.innerUnits.${innerIndex}.sellingPrice`)} type="number" step="0.01" min="0" className="input" placeholder="Sell Price" />
                                                <button type="button" onClick={() => removeInnerUnit(lines.indexOf(line), innerIndex)} className="text-red-500 hover:text-red-700">✕</button>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>

                            <div className="flex justify-end pt-4 border-t">
                                <button type="button" onClick={() => setShowForm(false)} className="btn alt mr-2">Cancel</button>
                                <button type="submit" className="btn">Save</button>
                            </div>
                        </form>
                </div>
            )}

            <div className="overflow-x-auto">
                <table className="w-full">
                    <thead>
                        <tr className="border-b">
                            <th className="text-left p-3">Date</th>
                            <th className="text-left p-3">Total</th>
                            <th className="text-left p-3">Lines</th>
                            <th className="text-right p-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {purchases?.data?.map((purchase: Purchase) => (
                            <tr key={purchase.id} className="border-b hover:bg-gray-50">
                                <td className="p-3">{purchase.transacted_on}</td>
                                <td className="p-3">{purchase.total}</td>
                                <td className="p-3">{purchase.lines?.length} lines</td>
                                <td className="p-3 text-right">
                                    <button onClick={() => { setEditingId(purchase.id); setShowForm(true); }} className="btn alt xs mr-1">Edit</button>
                                    <button onClick={() => deleteMutation.mutate(purchase.id)} className="btn alt xs text-red-500">Delete</button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}