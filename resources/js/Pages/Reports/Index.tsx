import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { api } from '@/lib/api';

interface TrendBucket {
    key: string;
    label: string;
    start: string;
    sales: number;
    purchases: number;
}

interface ItemRow {
    item: { id: number; name: string; family: string };
    unit: { code: string; label: string } | null;
    base_qty: number;
    display_qty: number;
    total: number;
}

interface ReportSummary {
    mode: 'd' | 'w' | 'm';
    anchor: string;
    periodLabel: string;
    sales: number;
    purchases: number;
    stockValue: number;
    trend: TrendBucket[];
    trendMax: number;
    itemsSold: ItemRow[];
    itemsBought: ItemRow[];
}

export default function Reports() {
    const navigate = useNavigate();
    const [mode, setMode] = useState<'d' | 'w' | 'm'>('d');
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');

    const hasCustomRange = startDate || endDate;
    const anchor = hasCustomRange ? undefined : new Date().toISOString().split('T')[0];

    const params = new URLSearchParams();
    params.set('mode', mode);
    if (startDate) params.set('start_date', startDate);
    if (endDate) params.set('end_date', endDate);
    if (anchor) params.set('anchor', anchor);

    const { data: report, isLoading } = useQuery<{ data: ReportSummary }>({
        queryKey: ['report', mode, startDate, endDate],
        queryFn: () => api.get('/reports', { params }),
    });

    const summary = report?.data;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const params = new URLSearchParams();
        params.set('mode', mode);
        if (startDate) params.set('start_date', startDate);
        if (endDate) params.set('end_date', endDate);
        if (!startDate && !endDate) {
            params.set('anchor', new Date().toISOString().split('T')[0]);
        }
        navigate(`/reports?${params.toString()}`);
    };

    const formatMoney = (value: number) => {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'NGN' }).format(value);
    };

    const formatQty = (qty: number, unit: { code: string; label: string } | null) => {
        const formatted = qty % 1 === 0 ? qty.toString() : qty.toFixed(2).replace(/\.?0+$/, '');
        return `${formatted} ${unit?.label || unit?.code || 'units'}`;
    };

    const difference = summary ? summary.sales - summary.purchases : 0;
    const isDeficit = difference < 0;

    const trendBuckets = summary?.trend || [];
    const trendMax = summary?.trendMax || 1;

    const barHeight = (value: number) => {
        const max = Math.max(1, trendMax);
        return `${Math.max(0, (value / max) * 100).toFixed(4)}%`;
    };

    return (
        <div className="p-6">
            <form className="seg mb-6" onSubmit={handleSubmit}>
                <div className="flex flex-wrap items-center gap-4">
                    <div className="sg flex gap-2">
                        {(['d', 'w', 'm'] as const).map((m) => (
                            <label key={m} className="inline-flex items-center gap-1 cursor-pointer">
                                <input
                                    type="radio"
                                    name="mode"
                                    value={m}
                                    checked={mode === m}
                                    onChange={() => setMode(m)}
                                    className="sr-only"
                                />
                                <span className={`px-3 py-2 rounded text-sm font-semibold transition-colors border-2 ${
                                    mode === m
                                        ? 'bg-blue-600 text-white border-blue-600'
                                        : 'bg-white text-gray-900 border-gray-300 hover:bg-gray-50'
                                }`}>
                                    {m === 'd' ? 'Daily' : m === 'w' ? 'Weekly' : 'Monthly'}
                                </span>
                            </label>
                        ))}
                    </div>

                    <label className="flex items-center gap-1">
                        <input
                            type="date"
                            value={startDate}
                            onChange={(e) => setStartDate(e.target.value)}
                            className="input w-40"
                            placeholder="Start"
                            aria-label="Period start date"
                        />
                    </label>
                    <label className="flex items-center gap-1">
                        <input
                            type="date"
                            value={endDate}
                            onChange={(e) => setEndDate(e.target.value)}
                            className="input w-40"
                            placeholder="End"
                            aria-label="Period end date"
                        />
                    </label>
                    <button type="submit" className="btn sm">Go</button>
                    <span className="mut text-sm text-gray-500 whitespace-nowrap">
                        {summary?.periodLabel}
                    </span>
                </div>
            </form>

            {isLoading ? (
                <div className="text-center py-12">Loading...</div>
            ) : summary && (
                <>
                    <div className="kp grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                        <div className="card p-4">
                            <small className="block text-gray-600 mb-1">Sales</small>
                            <strong className="text-2xl">{formatMoney(summary.sales)}</strong>
                        </div>
                        <div className="card p-4">
                            <small className="block text-gray-600 mb-1">Purchases</small>
                            <strong className="text-2xl">{formatMoney(summary.purchases)}</strong>
                        </div>
                        <div className="card p-4">
                            <small className="block text-gray-600 mb-1">Sales − Purchases</small>
                            <strong className={`text-2xl ${isDeficit ? 'text-red-600' : 'text-green-600'}`}>
                                {formatMoney(difference)}
                            </strong>
                        </div>
                        <div className="card p-4">
                            <small className="block text-gray-600 mb-1">Stock value</small>
                            <strong className="text-2xl">{formatMoney(summary.stockValue)}</strong>
                        </div>
                    </div>

                    <div className="card mb-6">
                        <h2 className="text-lg font-semibold p-4 border-b">Trend</h2>
                        {(summary.sales > 0 || summary.purchases > 0) ? (
                            <div className="ch p-4">
                                <div className="flex gap-1 overflow-x-auto pb-4 h-64 items-end">
                                    {trendBuckets.map((bucket, i) => (
                                        <div
                                            key={i}
                                            className="flex flex-col items-center gap-1 flex-shrink-0"
                                            style={{ minWidth: '60px' }}
                                            title={`Sales ${formatMoney(bucket.sales)} · Purchases ${formatMoney(bucket.purchases)}`}
                                        >
                                            <div className="bars flex gap-1 w-full h-full relative" style={{ height: '100%' }}>
                                                <i
                                                    className="bg-blue-500 w-full"
                                                    style={{ height: barHeight(bucket.sales) }}
                                                ></i>
                                                <i
                                                    className="bg-orange-500 w-full"
                                                    style={{ height: barHeight(bucket.purchases) }}
                                                ></i>
                                            </div>
                                            <span className="text-xs text-center whitespace-nowrap">{bucket.label}</span>
                                        </div>
                                    ))}
                                </div>
                                <div className="lg flex gap-4 justify-center text-sm">
                                    <span className="flex items-center gap-1">
                                        <span className="w-3 h-3 bg-blue-500 inline-block"></span>Sales
                                    </span>
                                    <span className="flex items-center gap-1">
                                        <span className="w-3 h-3 bg-orange-500 inline-block"></span>Purchases
                                    </span>
                                </div>
                            </div>
                        ) : (
                            <div className="e p-4 text-center text-gray-500">No activity in this period.</div>
                        )}
                    </div>

                    <div className="grid gap-6 md:grid-cols-2">
                        <div className="card">
                            <h2 className="text-lg font-semibold p-4 border-b">Items sold</h2>
                            {summary.itemsSold.length > 0 ? (
                                <div className="sc overflow-x-auto">
                                    <table className="w-full">
                                        <thead>
                                            <tr className="border-b">
                                                <th className="text-left p-3">Item</th>
                                                <th className="text-right p-3">Quantity</th>
                                                <th className="text-right p-3">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {summary.itemsSold.map((row, i) => (
                                                <tr key={i} className="border-b hover:bg-gray-50">
                                                    <td className="p-3">{row.item.name}</td>
                                                    <td className="text-right p-3">{formatQty(row.display_qty, row.unit)}</td>
                                                    <td className="text-right p-3">{formatMoney(row.total)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <div className="e p-4 text-center text-gray-500">Nothing recorded for this period.</div>
                            )}
                        </div>

                        <div className="card">
                            <h2 className="text-lg font-semibold p-4 border-b">Items purchased</h2>
                            {summary.itemsBought.length > 0 ? (
                                <div className="sc overflow-x-auto">
                                    <table className="w-full">
                                        <thead>
                                            <tr className="border-b">
                                                <th className="text-left p-3">Item</th>
                                                <th className="text-right p-3">Quantity</th>
                                                <th className="text-right p-3">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {summary.itemsBought.map((row, i) => (
                                                <tr key={i} className="border-b hover:bg-gray-50">
                                                    <td className="p-3">{row.item.name}</td>
                                                    <td className="text-right p-3">{formatQty(row.display_qty, row.unit)}</td>
                                                    <td className="text-right p-3">{formatMoney(row.total)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <div className="e p-4 text-center text-gray-500">Nothing recorded for this period.</div>
                            )}
                        </div>
                    </div>
                </>
            )}
        </div>
    );
}