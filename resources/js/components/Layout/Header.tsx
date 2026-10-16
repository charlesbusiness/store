import { NavLink } from 'react-router-dom';
import { useAuthStore } from '@/stores/authStore';
import { useTheme } from '@/hooks/useTheme';

const navTabs = [
    // { path: '/sell', label: 'Sell' },
    { path: '/sales', label: 'Sales' },
    // { path: '/buy', label: 'Buy' },
    { path: '/purchases', label: 'Purchases' },
    { path: '/stock', label: 'Stock' },
];

export function Header() {
    const { user } = useAuthStore();
    const { theme, toggleTheme } = useTheme();

    if (!user) return null;

    return (
        <header className="hd">
            <div className="logo">
                <NavLink to={'/'}
                    className={({ isActive }) => `tab ${isActive ? 'on' : ''}`}
                >
                    Stock<b>ly</b>
                </NavLink>
            </div>
            <nav className="nav-justified">
                {navTabs.map((tab) => (
                    <NavLink
                        key={tab.path}
                        to={tab.path}
                        className={({ isActive }) => `tab ${isActive ? 'on' : ''}`}
                    >
                        {tab.label}
                    </NavLink>
                ))}
            </nav>
            <button
                type="button"
                className="icon-btn"
                onClick={toggleTheme}
                aria-label={theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'}
            >
                {theme === 'dark' ? (
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2} aria-hidden="true">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                ) : (
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2} aria-hidden="true">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                )}
            </button>
        </header>
    );
}