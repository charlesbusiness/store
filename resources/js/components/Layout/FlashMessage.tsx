import { useEffect, useState } from 'react';
import { toast } from 'sonner';

interface FlashMessage {
    type: 'success' | 'error';
    message: string;
}

export function FlashMessage() {
    const [shown, setShown] = useState<Set<string>>(new Set());

    useEffect(() => {
        const status = sessionStorage.getItem('flash_status');
        const errors = sessionStorage.getItem('flash_errors');

        if (status && !shown.has(status)) {
            toast.success(status);
            setShown(prev => new Set(prev).add(status));
            sessionStorage.removeItem('flash_status');
        }

        if (errors) {
            try {
                const errorArray = JSON.parse(errors);
                errorArray.forEach((error: string) => {
                    if (!shown.has(error)) {
                        toast.error(error);
                        setShown(prev => new Set(prev).add(error));
                    }
                });
            } catch {
                if (!shown.has(errors)) {
                    toast.error(errors);
                    setShown(prev => new Set(prev).add(errors));
                }
            }
            sessionStorage.removeItem('flash_errors');
        }
    }, [shown]);

    return null;
}