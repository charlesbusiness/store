import { useEffect, useState, useCallback } from 'react';

type Theme = 'light' | 'dark';

export function useTheme() {
    const [theme, setTheme] = useState<Theme>(() => {
        if (typeof window === 'undefined') return 'light';
        const saved = localStorage.getItem('stockly_theme') as Theme | null;
        if (saved) return saved;
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    });

    useEffect(() => {
        const root = document.documentElement;
        const saved = localStorage.getItem('stockly_theme') as Theme | null;
        if (saved) {
            root.setAttribute('data-theme', saved);
            setTheme(saved);
        } else if (!root.hasAttribute('data-theme')) {
            const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const initial = systemDark ? 'dark' : 'light';
            root.setAttribute('data-theme', initial);
            setTheme(initial);
        }
    }, []);

    const toggleTheme = useCallback(() => {
        const root = document.documentElement;
        const current = root.getAttribute('data-theme') as Theme | null;
        const isDark = current === 'dark' || (!current && window.matchMedia('(prefers-color-scheme: dark)').matches);
        const next = isDark ? 'light' : 'dark';
        
        root.setAttribute('data-theme', next);
        localStorage.setItem('stockly_theme', next);
        setTheme(next);
    }, []);

    return { theme, toggleTheme };
}