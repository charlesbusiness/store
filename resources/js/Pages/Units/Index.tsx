import { useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api';

interface Unit {
    id: number;
    code: string;
    label: string;
    family: string;
    base_multiplier: number;
    is_decimal: boolean;
    is_active: boolean;
}

export default function Units() {
    const { data: units } = useQuery<{ data: Unit[] }>({
        queryKey: ['units'],
        queryFn: () => api.get('/units'),
    });

    return (
        <div className="p-6">
            <h1 className="text-2xl font-bold mb-4">Units</h1>
            {units?.data && units.data.length > 0 ? (
                <div className="overflow-x-auto">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b">
                                <th className="text-left p-3">Code</th>
                                <th className="text-left p-3">Label</th>
                                <th className="text-left p-3">Family</th>
                                <th className="text-right p-3">Base Multiplier</th>
                                <th className="text-left p-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {units.data.map((unit) => (
                                <tr key={unit.id} className="border-b hover:bg-gray-50">
                                    <td className="p-3 font-medium">{unit.code}</td>
                                    <td className="p-3">{unit.label}</td>
                                    <td className="p-3">{unit.family}</td>
                                    <td className="text-right p-3">{unit.base_multiplier}</td>
                                    <td className="p-3">
                                        {unit.is_active ? <span className="badge badge-green">Active</span> : <span className="badge badge-red">Inactive</span>}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : (
                <p className="text-gray-600">No units found.</p>
            )}
        </div>
    );
}