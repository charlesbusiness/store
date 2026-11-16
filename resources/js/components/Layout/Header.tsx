import { NavLink } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { useAuthStore } from '@/stores/authStore';
import { useTheme } from '@/hooks/useTheme';
import { Link } from 'react-router-dom';
import { api } from '@/lib/api';
import { queryClient } from '@/lib/queryClient';

const navTabs = [
    { path: '/sales', label: 'Sales' },
    { path: '/purchases', label: 'Purchases' },
    { path: '/stock', label: 'Stock' },
    { path: '/transfers', label: 'Transfers' },
];

export function Header() {
    const { user, branches: storedBranches, activeBranchId, activeTenantId } = useAuthStore();
    const { theme, toggleTheme } = useTheme();

    const branchesQuery = useQuery({
        queryKey: ['branches', activeTenantId],
        queryFn: () => api.get('/branches'),
        enabled: Boolean(user && activeTenantId),
    });

    const branches = (branchesQuery.data?.data ?? storedBranches.filter((branch) => !activeTenantId || branch.tenant_id === activeTenantId)) as Array<{ id: number; name: string }>;

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
            <div className="header-actions">
                {branches.length > 0 && <select className="branch-select" value={activeBranchId ?? ''} onChange={(event) => { useAuthStore.setState({ activeBranchId: Number(event.target.value) }); queryClient.invalidateQueries(); }} aria-label="Branch">
                    {branches.map((branch) => <option key={branch.id} value={branch.id}>{branch.name}</option>)}
                </select>}
                <Link to="/settings" className="icon-btn" aria-label="Account settings">Settings</Link>
            </div>
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
