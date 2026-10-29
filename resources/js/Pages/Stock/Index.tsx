import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api';

interface StockPosition {
    id: number;
    name: string;
    family: string;
    unit: string;
    stock: number;
    stockLabel: string;
    costPerUnit: number | null;
    totalValue: number;
    lastSellingPrice: number | null;
}

interface StockApiResponse {
    data: StockPosition[];
    breakdowns: any;
    traded: StockPosition[];
    stockValue: number;
    totalPurchased: number;
}

export default function Stock() {
    const [search, setSearch] = useState('');
    const [sortBy, setSortBy] = useState<'name' | 'stock' | 'totalValue'>('name');
    const [sortOrder, setSortOrder] = useState<'asc' | 'desc'>('asc');

    const { data: response } = useQuery<StockApiResponse>({
        queryKey: ['stock'],
        queryFn: () => api.get('/stock'),
    });

    const positions = response?.data || [];

    const sortedPositions = [...positions].sort((a, b) => {
        const aVal = a[sortBy];
        const bVal = b[sortBy];
        
        if (typeof aVal === 'string' && typeof bVal === 'string') {
            const aStr = aVal.toLowerCase();
            const bStr = bVal.toLowerCase();
            return sortOrder === 'asc' ? (aStr > bStr ? 1 : -1) : (aStr < bStr ? 1 : -1);
        }
        
        if (typeof aVal === 'number' && typeof bVal === 'number') {
            return sortOrder === 'asc' ? aVal - bVal : bVal - aVal;
        }
        
        return 0;
    });

    const totalValue = positions.reduce((sum, p) => sum + (p.totalValue || 0), 0) || 0;
    const lowStock = positions.filter((p) => p.stock > 0 && p.stock < 10).length || 0;
    const outOfStock = positions.filter((p) => p.stock === 0).length || 0;

    return (
        <div className="p-6">
            <div className="flex justify-between items-center mb-6">
                <h1 className="text-2xl font-bold">Stock</h1>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div className="card p-4">
                    <p className="text-sm text-gray-600">Total Items</p>
                    <p className="text-2xl font-bold">{positions?.length || 0}</p>
                </div>
                <div className="card p-4">
                    <p className="text-sm text-gray-600">Total Value</p>
                    <p className="text-2xl font-bold">{new Intl.NumberFormat('en-US', { style: 'currency', currency: 'NGN' }).format(totalValue)}</p>
                </div>
                <div className="card p-4">
                    <p className="text-sm text-gray-600">Low Stock</p>
                    <p className="text-2xl font-bold text-amber-600">{lowStock}</p>
                </div>
                <div className="card p-4">
                    <p className="text-sm text-gray-600">Out of Stock</p>
                    <p className="text-2xl font-bold text-red-600">{outOfStock}</p>
                </div>
            </div>

            <div className="overflow-x-auto">
                <div className="flex items-center justify-between mb-4">
                    <input
                        type="text"
                        placeholder="Search..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="input w-64"
                    />
                    <div className="flex gap-2">
                        <select
                            value={sortBy}
                            onChange={(e) => setSortBy(e.target.value as any)}
                            className="input w-32"
                        >
                            <option value="name">Name</option>
                            <option value="stock">Stock</option>
                            <option value="totalValue">Value</option>
                        </select>
                        <button
                            onClick={() => setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc')}
                            className="btn alt sm"
                        >
                            {sortOrder === 'asc' ? '↑' : '↓'}
                        </button>
                    </div>
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b">
                                <th className="text-left p-3 cursor-pointer" onClick={() => { setSortBy('name'); setSortOrder(sortBy === 'name' && sortOrder === 'asc' ? 'desc' : 'asc'); }}>
                                    Name {sortBy === 'name' ? (sortOrder === 'asc' ? '↑' : '↓') : ''}
                                </th>
                                <th className="text-left p-3">Family</th>
                                <th className="text-left p-3">Unit</th>
                                <th className="text-right p-3 cursor-pointer" onClick={() => { setSortBy('stock'); setSortOrder(sortBy === 'stock' && sortOrder === 'asc' ? 'desc' : 'asc'); }}>
                                    Stock {sortBy === 'stock' ? (sortOrder === 'asc' ? '↑' : '↓') : ''}
                                </th>
                                <th className="text-right p-3 cursor-pointer" onClick={() => { setSortBy('totalValue'); setSortOrder(sortBy === 'totalValue' && sortOrder === 'asc' ? 'desc' : 'asc'); }}>
                                    Value {sortBy === 'totalValue' ? (sortOrder === 'asc' ? '↑' : '↓') : ''}
                                </th>
                                <th className="text-right p-3">Cost/Unit</th>
                                <th className="text-right p-3">Last Price</th>
                                <th className="text-left p-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {sortedPositions.filter(p => p.name.toLowerCase().includes(search.toLowerCase())).map((pos) => (
                                <tr key={pos.id} className="border-b hover:bg-gray-50">
                                    <td className="p-3 font-medium">{pos.name}</td>
                                    <td className="p-3">{pos.family}</td>
                                    <td className="p-3">{pos.unit}</td>
                                    <td className="text-right p-3">{pos.stockLabel}</td>
                                    <td className="text-right p-3">{new Intl.NumberFormat('en-US', { style: 'currency', currency: 'NGN' }).format(pos.totalValue || 0)}</td>
                                    <td className="p-3">{pos.costPerUnit ? new Intl.NumberFormat('en-US', { style: 'currency', currency: 'NGN' }).format(pos.costPerUnit) : '—'}</td>
                                    <td className="p-3">{pos.lastSellingPrice ? new Intl.NumberFormat('en-US', { style: 'currency', currency: 'NGN' }).format(pos.lastSellingPrice) : '—'}</td>
                                    <td className="p-3">
                                        {pos.stock === 0 ? <span className="badge badge-red">Out</span> : pos.stock < 10 ? <span className="badge badge-amber">Low</span> : <span className="badge badge-green">OK</span>}
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