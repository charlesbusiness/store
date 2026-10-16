import type { PageProps } from '@inertiajs/react';

declare module '@inertiajs/react' {
    interface PageProps {
        ziggy?: {
            url: string;
            port: number | null;
            defaults: Record<string, any>;
            routes: Record<string, any>;
        };
    }
}
