import { useState, useEffect, useCallback } from 'react';
import { useForm } from 'react-hook-form';
import { useQuery } from '@tanstack/react-query';
import { useFieldArray } from 'react-hook-form';
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

interface Item {
    id: number;
    name: string;
    family: string;
    unit: string;
    stock: number;
    costPerUnit: number | null;
    lastSellingPrice: number | null;
}

interface LineData {
    id: string;
    item_id?: number;
    name: string;
    family: string;
    unit_code: string;
    qty: string;
    unit_price: string;
    selling_price?: string;
    unit_cost?: string;
    inner_units: any[];
}

interface RecordFormOptions {
    isSale: boolean;
    defaultTransactedOn?: string;
    initialLines?: LineData[];
}

const DRAFT_STORAGE_KEY = 'record_form_draft';

export function useRecordForm({
    isSale,
    defaultTransactedOn,
    initialLines = [],
}: RecordFormOptions) {
    const [draft, setDraftState] = useState<any>(null);
    const [loadedDraft, setLoadedDraft] = useState(false);

    const { data: unitsResponse } = useQuery<{ data: Unit[] }>({
        queryKey: ['units'],
        queryFn: () => api.get('/units'),
    });

    const { data: itemsResponse } = useQuery<{ data: Item[] }>({
        queryKey: ['items'],
        queryFn: () => api.get('/items'),
    });

    const units = unitsResponse?.data || [];
    const items = itemsResponse?.data || [];

    const form = useForm({
        mode: 'onChange',
        defaultValues: {
            transacted_on: defaultTransactedOn || new Date().toISOString().split('T')[0],
            lines: initialLines.length > 0
                ? initialLines
                : [
                    {
                        id: crypto.randomUUID(),
                        name: '',
                        family: '',
                        unit_code: '',
                        qty: '',
                        unit_price: '',
                        selling_price: '',
                        unit_cost: '',
                        inner_units: [],
                    },
                ],
        },
    });

    const { fields: lines, append, remove } = useFieldArray({
        control: form.control,
        name: 'lines',
    });

    const familyUnits = useCallback((family: string) => {
        return units.filter((u) => u.family === family && u.is_active);
    }, [units]);

    const familyItems = useCallback((family: string) => {
        return items.filter((i) => i.family === family);
    }, [items]);

    const watchLine = (path: string) => form.watch(path as any);

    const baseQty = useCallback((lineIndex: number) => {
        const qty = parseFloat(watchLine(`lines.${lineIndex}.qty`)) || 0;
        const unitCode = watchLine(`lines.${lineIndex}.unit_code`);
        const unit = units.find((u) => u.code === unitCode);
        return qty * (unit?.base_multiplier || 1);
    }, [watchLine, units]);

    const lineTotal = useCallback((lineIndex: number) => {
        const bq = baseQty(lineIndex);
        const price = parseFloat(watchLine(`lines.${lineIndex}.unit_price`)) || 0;
        return bq * price;
    }, [baseQty, watchLine]);

    const lineProfit = useCallback((lineIndex: number) => {
        if (!isSale) return 0;
        const bq = baseQty(lineIndex);
        const price = parseFloat(watchLine(`lines.${lineIndex}.unit_price`)) || 0;
        const cost = parseFloat(watchLine(`lines.${lineIndex}.unit_cost`)) || 0;
        return bq * (price - cost);
    }, [isSale, baseQty, watchLine]);

    const total = lines.reduce((sum: number, _line: any, index: number) => {
        return sum + lineTotal(index);
    }, 0);

    const totalProfit = isSale ? lines.reduce((sum: number, _line: any, index: number) => {
        return sum + lineProfit(index);
    }, 0) : 0;

    const margin = isSale && total > 0 ? (totalProfit / total) * 100 : 0;

    const addLine = useCallback(() => {
        append({
            id: crypto.randomUUID(),
            name: '',
            family: '',
            unit_code: '',
            qty: '',
            unit_price: '',
            selling_price: '',
            unit_cost: '',
            inner_units: [],
        });
    }, [append]);

    const removeLine = useCallback((index: number) => {
        remove(index);
    }, [remove]);

    const addInnerUnit = useCallback((lineIndex: number) => {
        form.setValue(`lines.${lineIndex}.inner_units`, [
            ...(form.getValues(`lines.${lineIndex}.inner_units`) || []),
            { id: crypto.randomUUID(), unit: '', per: '', cost: '', sellingPrice: '' },
        ]);
    }, [form]);

    const removeInnerUnit = useCallback((lineIndex: number, innerIndex: number) => {
        const innerUnits = form.getValues(`lines.${lineIndex}.inner_units`) || [];
        form.setValue(`lines.${lineIndex}.inner_units`, innerUnits.filter((_: any, i: number) => i !== innerIndex));
    }, [form]);

    const innerUnits = useCallback((lineIndex: number) => {
        return form.getValues(`lines.${lineIndex}.inner_units`) || [];
    }, [form]);

    const setDraft = useCallback((newDraft: any) => {
        setDraftState(newDraft);
    }, []);

    const clearDraft = useCallback(() => {
        setDraftState(null);
    }, []);

    const saveDraft = useCallback(() => {
        const linesData = form.getValues('lines');
        const draftData = {
            transacted_on: form.getValues('transacted_on'),
            lines: linesData,
            timestamp: Date.now(),
        };
        localStorage.setItem(DRAFT_STORAGE_KEY, JSON.stringify(draftData));
    }, [form]);

    const loadDraft = useCallback(() => {
        if (loadedDraft) return;
        try {
            const stored = localStorage.getItem(DRAFT_STORAGE_KEY);
            if (stored) {
                const data = JSON.parse(stored);
                if (data.timestamp && Date.now() - data.timestamp < 24 * 60 * 60 * 1000) {
                    form.setValue('transacted_on', data.transacted_on);
                    data.lines.forEach((line: any) => {
                        append(line);
                    });
                }
            }
        } catch (error) {
            console.error('Failed to load draft:', error);
        }
        setLoadedDraft(true);
    }, [form, loadedDraft, append]);

    useEffect(() => {
        loadDraft();
    }, [loadDraft]);

    useEffect(() => {
        const handleBeforeUnload = () => {
            saveDraft();
        };
        window.addEventListener('beforeunload', handleBeforeUnload);
        return () => window.removeEventListener('beforeunload', handleBeforeUnload);
    }, [saveDraft]);

    return {
        ...form,
        units,
        items,
        familyUnits,
        familyItems,
        baseQty,
        lineTotal,
        lineProfit,
        total,
        totalProfit,
        margin,
        draft,
        setDraft,
        clearDraft,
        addLine,
        removeLine,
        addInnerUnit,
        removeInnerUnit,
        saveDraft,
        loadDraft,
        lines: lines as LineData[],
        innerUnits,
    };
}