import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { toast } from 'sonner';

const formatDate = (value: string) => {
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

export default function Transfers() {
    const queryClient = useQueryClient();
    const [source, setSource] = useState('');
    const [destination, setDestination] = useState('');
    const [item, setItem] = useState('');
    const [qty, setQty] = useState('');
    const [date, setDate] = useState(new Date().toISOString().slice(0, 10));

    const branchesQuery = useQuery({ queryKey: ['branches'], queryFn: () => api.get('/branches') });
    const itemsQuery = useQuery({
        queryKey: ['items', 'transfer', source],
        queryFn: () => api.get('/items', { params: { per_page: 100, with_stock: true, ...(source ? { branch_id: Number(source) } : {}) } }),
    });
    const transfersQuery = useQuery({ queryKey: ['transfers'], queryFn: () => api.get('/transfers') });
    const branches = branchesQuery.data?.data ?? [];
    const items = itemsQuery.data?.data ?? [];
    const selectedItem = items.find((entry: any) => entry.id === Number(item));

    const create = useMutation({
        mutationFn: () => api.post('/transfers', { source_branch_id: Number(source), destination_branch_id: Number(destination), transferred_on: date, lines: [{ item_id: Number(item), qty: Number(qty), unit_code: selectedItem.unit }] }),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['transfers'] }); queryClient.invalidateQueries({ queryKey: ['stock'] }); toast.success('Stock transferred.'); setQty(''); },
        onError: (error: any) => {
            const payload = error.response?.data;
            const validationMessage = payload?.errors
                ? Object.values(payload.errors).flat()[0]
                : null;

            toast.error(validationMessage ?? payload?.message ?? 'Unable to transfer stock.');
        },
    });

    return <div className="p-6">
        <div className="card-head"><h1>Stock transfers</h1></div>
        <section className="card">
            <h2>Transfer stock</h2>
            <div className="g">
                <label>From<select value={source} onChange={(event) => setSource(event.target.value)}><option value="">Select branch</option>{branches.map((branch: any) => <option key={branch.id} value={branch.id}>{branch.name}</option>)}</select></label>
                <label>To<select value={destination} onChange={(event) => setDestination(event.target.value)}><option value="">Select branch</option>{branches.map((branch: any) => <option key={branch.id} value={branch.id}>{branch.name}</option>)}</select></label>
                <label>Date<input type="date" value={date} onChange={(event) => setDate(event.target.value)} /></label>
                <label>Item<select value={item} onChange={(event) => setItem(event.target.value)}><option value="">Select item</option>{items.map((entry: any) => <option key={entry.id} value={entry.id}>{entry.name} ({entry.stockLabel ?? entry.stock ?? 0} available)</option>)}</select></label>
                <label>Quantity<input type="number" min="0.0001" step="any" value={qty} onChange={(event) => setQty(event.target.value)} /></label>
            </div>
            <div className="row"><button className="btn" disabled={!source || !destination || source === destination || !item || !selectedItem?.unit || !qty || create.isPending} onClick={() => create.mutate()}>Transfer stock</button></div>
        </section>
        <section className="card"><h2>Transfer history</h2><div className="sc"><table><thead><tr><th>Date</th><th>From</th><th>To</th><th>Items</th><th>Status</th></tr></thead><tbody>{(transfersQuery.data?.data ?? []).map((transfer: any) => <tr key={transfer.id}><td>{formatDate(transfer.transferred_on)}</td><td>{transfer.source_branch?.name}</td><td>{transfer.destination_branch?.name}</td><td>{transfer.lines?.map((line: any) => `${line.item?.name} (${line.qty})`).join(', ')}</td><td>{transfer.status}</td></tr>)}</tbody></table></div></section>
    </div>;
}
