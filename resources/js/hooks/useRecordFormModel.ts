import { useEffect, useReducer, useRef } from 'react';
import { RecordFormModel, type RecordConfig } from '@/lib/recordForm';

/**
 * A stable RecordFormModel that re-renders the component whenever the model is
 * mutated. The instance is created once and the config is swapped in place, so
 * a config refetch never discards staged lines.
 */
export function useRecordFormModel(config: RecordConfig | null | undefined): RecordFormModel | null {
    const [, force] = useReducer((n: number) => n + 1, 0);
    const modelRef = useRef<RecordFormModel | null>(null);

    if (!modelRef.current && config) {
        modelRef.current = new RecordFormModel(config);
    }

    const model = modelRef.current;

    useEffect(() => {
        if (!model) return;
        // Subscribe first so swapping the config in place re-renders the view.
        const unsubscribe = model.subscribe(force);
        if (config) model.updateConfig(config);
        return unsubscribe;
    }, [model, config]);

    return model;
}
