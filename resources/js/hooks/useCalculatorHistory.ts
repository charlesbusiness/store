import { useCallback, useEffect, useState } from 'react';

export interface CalculationHistoryEntry {
    id: string;
    expression: string;
    result: string;
    createdAt: number;
}

interface NewCalculationEntry {
    expression: string;
    result: string;
}

const STORAGE_KEY = 'stockly_calculator_history';
const MAX_ENTRIES = 50;

function isValidEntry(value: unknown): value is CalculationHistoryEntry {
    if (typeof value !== 'object' || value === null) return false;
    const entry = value as Record<string, unknown>;
    return (
        typeof entry.id === 'string' &&
        typeof entry.expression === 'string' &&
        typeof entry.result === 'string' &&
        typeof entry.createdAt === 'number'
    );
}

function loadHistory(): CalculationHistoryEntry[] {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (!stored) return [];

        const parsed = JSON.parse(stored);
        if (!Array.isArray(parsed)) return [];

        return parsed.filter(isValidEntry).slice(0, MAX_ENTRIES);
    } catch {
        return [];
    }
}

export function useCalculatorHistory() {
    const [history, setHistory] = useState<CalculationHistoryEntry[]>(loadHistory);

    useEffect(() => {
        try {
            if (history.length === 0) {
                localStorage.removeItem(STORAGE_KEY);
            } else {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(history));
            }
        } catch {}
    }, [history]);

    const addEntry = useCallback((entry: NewCalculationEntry) => {
        setHistory((current) =>
            [
                {
                    id: crypto.randomUUID(),
                    expression: entry.expression,
                    result: entry.result,
                    createdAt: Date.now(),
                },
                ...current,
            ].slice(0, MAX_ENTRIES)
        );
    }, []);

    const clearHistory = useCallback(() => {
        setHistory([]);
    }, []);

    return { history, addEntry, clearHistory };
}
