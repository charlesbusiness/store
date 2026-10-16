import { useMemo, useCallback } from 'react';

interface Unit {
    id: number;
    code: string;
    label: string;
    family: string;
    base_multiplier: number;
    is_decimal: boolean;
    is_active: boolean;
}

interface PackDefinition {
    unit: string;
    per: number;
    baseMultiplier: number;
    label: string;
}

interface PackEquivalent {
    unit: string;
    label: string;
    qty: number;
    per: number;
}

export function usePacks(units: Unit[], family: string) {
    const familyUnits = useMemo(() => {
        return units.filter((u) => u.family === family && u.is_active);
    }, [units, family]);

    const packDefinitions = useMemo(() => {
        const packs: PackDefinition[] = [];

        familyUnits.forEach((u) => {
            if (u.base_multiplier > 1) {
                packs.push({
                    unit: u.code,
                    per: u.base_multiplier,
                    baseMultiplier: u.base_multiplier,
                    label: `${u.label} (${u.base_multiplier} base)`,
                });
            }
        });

        return packs;
    }, [familyUnits]);

    const calculateEquivalents = useCallback((
        baseQty: number,
        selectedUnitCode?: string
    ): PackEquivalent[] => {
        if (!selectedUnitCode) return [];

        const selectedUnit = familyUnits.find((u) => u.code === selectedUnitCode);
        if (!selectedUnit) return [];

        const equivalents: PackEquivalent[] = [];
        const baseMultiplier = selectedUnit.base_multiplier;

        familyUnits.forEach((u) => {
            if (u.code === selectedUnitCode) return;
            const qty = baseQty * (baseMultiplier / u.base_multiplier);
            if (qty >= 0.001) {
                equivalents.push({
                    unit: u.code,
                    label: u.label,
                    qty,
                    per: u.base_multiplier,
                });
            }
        });

        return equivalents.sort((a, b) => a.per - b.per);
    }, [familyUnits]);

    const formatEquivalents = useCallback((equivalents: PackEquivalent[]): string => {
        if (!equivalents.length) return '';
        return equivalents
            .map((eq) => `${eq.qty.toFixed(eq.qty % 1 === 0 ? 0 : 3)} ${eq.label}`)
            .join(', ');
    }, []);

    const getPackForUnit = useCallback((unitCode: string) => {
        return packDefinitions.find((p) => p.unit === unitCode);
    }, [packDefinitions]);

    return {
        familyUnits,
        packDefinitions,
        calculateEquivalents,
        formatEquivalents,
        getPackForUnit,
    };
}