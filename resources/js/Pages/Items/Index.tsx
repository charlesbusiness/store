import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { toast } from 'sonner';

const itemSchema = z.object({
    name: z.string().min(1, 'Name required'),
    family: z.string().min(1, 'Family required'),
    default_unit_code: z.string().min(1, 'Unit required'),
    pack_size: z.string().optional(),
    notes: z.string().optional(),
    is_active: z.boolean(),
});

type ItemFormData = z.infer<typeof itemSchema>;


export default function Items() {
    const queryClient = useQueryClient();
    const [showForm, setShowForm] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [search, setSearch] = useState('');

    const { data: itemsResponse } = useQuery({
        queryKey: ['items', search],
        queryFn: () => api.get('/items', { params: { search } }),
    });

    const { data: unitsResponse } = useQuery({
        queryKey: ['units'],
        queryFn: () => api.get('/units'),
    });

    const items = itemsResponse?.data?.data || [];
    const units = unitsResponse?.data?.data || [];

    const form = useForm<ItemFormData>({
        resolver: zodResolver(itemSchema),
        defaultValues: {
            name: '',
            family: 'count',
            default_unit_code: 'pcs',
            pack_size: '',
            notes: '',
            is_active: true,
        },
    });

    const createMutation = useMutation({
        mutationFn: (data: any) => api.post('/items', data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['items'] });
            form.reset({ name: '', family: 'count', default_unit_code: 'pcs', pack_size: '', notes: '', is_active: true });
            setShowForm(false);
            toast.success('Item created');
        },
        onError: (error: any) => toast.error(error.response?.data?.message || 'Failed to create item'),
    });

    const updateMutation = useMutation({
        mutationFn: ({ id, data }: { id: number; data: any }) => api.put(`/items/${id}`, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['items'] });
            setEditingId(null);
            setShowForm(false);
            toast.success('Item updated');
        },
    });

    const deleteMutation = useMutation({
        mutationFn: (id: number) => api.delete(`/items/${id}`),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['items'] }),
    });

    return (
        <div className="p-6">
            <div className="flex justify-between items-center mb-6">
                <h1 className="text-2xl font-bold">Items</h1>
                <div className="flex gap-2">
                    <input
                        type="text"
                        placeholder="Search items..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="input w-64"
                    />
                    <button
                        onClick={() => { setEditingId(null); form.reset({ name: '', family: 'count', default_unit_code: 'pcs', pack_size: '', notes: '', is_active: true }); setShowForm(true); }}
                        className="btn"
                    >
                        + New Item
                    </button>
                </div>
            </div>

            {showForm && (
                <div className="card mb-6">
                    <form onSubmit={form.handleSubmit((data: any) => {
                        if (editingId) {
                            updateMutation.mutate({ id: editingId, data });
                        } else {
                            createMutation.mutate(data);
                        }
                    })} className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium mb-1">Name</label>
                            <input {...form.register('name')} type="text" className="input" required placeholder="Item name" />
                            {form.formState.errors.name && <p className="mt-1 text-sm text-red-600">{form.formState.errors.name.message}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium mb-1">Family</label>
                            <select {...form.register('family')} className="input" required>
                                <option value="count">Count</option>
                                <option value="weight">Weight</option>
                                <option value="volume">Volume</option>
                                <option value="length">Length</option>
                                <option value="area">Area</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium mb-1">Unit</label>
                            <select {...form.register('default_unit_code')} className="input" required>
                                <option value="">Select unit</option>
                                {units?.map((u: any) => (
                                    <option key={u.code} value={u.code}>{u.label}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium mb-1">Pack Size (optional)</label>
                            <input {...form.register('pack_size')} type="text" className="input" placeholder="e.g. 50kg, 12pcs" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium mb-1">Notes</label>
                            <textarea {...form.register('notes')} className="input" rows={2} placeholder="Optional notes" />
                        </div>
                        <div className="flex items-center">
                            <input {...form.register('is_active')} type="checkbox" className="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded" />
                            <label className="ml-2 block text-sm font-medium text-gray-700">Active</label>
                        </div>
                    </div>
                    <div className="flex justify-end pt-4 border-t">
                        <button type="button" onClick={() => { setShowForm(false); setEditingId(null); }} className="btn alt mr-2">Cancel</button>
                        <button type="submit" className="btn">Save</button>
                    </div>
                </form>
            </div>
            )}

            <div className="overflow-x-auto">
                <div className="flex items-center justify-between mb-4">
                    <input
                        type="text"
                        placeholder="Search items..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="input w-64"
                    />
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b">
                                <th className="text-left p-3">Name</th>
                                <th className="text-left p-3">Family</th>
                                <th className="text-left p-3">Unit</th>
                                <th className="text-right p-3">Stock</th>
                                <th className="text-right p-3">Value</th>
                                <th className="text-right p-3">Last Price</th>
                                <th className="p-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {items?.map((item: any) => (
                                <tr key={item.id} className="border-b hover:bg-gray-50">
                                    <td className="p-3 font-medium">{item.name}</td>
                                    <td className="p-3">{item.family}</td>
                                    <td className="p-3">{item.unit}</td>
                                    <td className="text-right p-3">{item.stockLabel}</td>
                                    <td className="text-right p-3">{item.costPerUnit ? new Intl.NumberFormat('en-US', { style: 'currency', currency: 'NGN' }).format(item.costPerUnit) : '—'}</td>
                                    <td className="text-right p-3">{item.lastSellingPrice ? new Intl.NumberFormat('en-US', { style: 'currency', currency: 'NGN' }).format(item.lastSellingPrice) : '—'}</td>
                                    <td className="p-3">
                                        <button onClick={() => { setEditingId(item.id); setShowForm(true); }} className="btn alt xs mr-1">Edit</button>
                                        <button onClick={() => deleteMutation.mutate(item.id)} className="btn alt xs text-red-500">Delete</button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}