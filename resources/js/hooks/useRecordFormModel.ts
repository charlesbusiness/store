import { useEffect, useMemo, useReducer } from 'react';
import { RecordFormModel, type RecordConfig } from '@/lib/recordForm';

/**
 * A stable RecordFormModel that re-renders the component whenever the model is
 * mutated. The instance is rebuilt only when the config identity changes.
 */
export function useRecordFormModel(config: RecordConfig | null | undefined): RecordFormModel | null {
    const [, force] = useReducer((n: number) => n + 1, 0);

    const model = useMemo(() => (config ? new RecordFormModel(config) : null), [config]);

    useEffect(() => {
        if (!model) return;
        return model.subscribe(force);
    }, [model]);

    return model;
}
