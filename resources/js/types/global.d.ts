/// <reference types="vite/client" />

interface ImportMeta {
    glob: (pattern: string, options?: { eager?: boolean; import?: 'default' }) => Record<string, any>;
}
