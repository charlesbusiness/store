import { useEffect, useRef, useState } from 'react';
import { Link, NavLink, useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { useAuthStore } from '@/stores/authStore';
import { useTheme } from '@/hooks/useTheme';
import { api } from '@/lib/api';
import { queryClient } from '@/lib/queryClient';

const navTabs = [
    { path: '/sales', label: 'Sales' },
    { path: '/purchases', label: 'Purchases' },
    { path: '/stock', label: 'Stock' },
    { path: '/transfers', label: 'Transfers' },
];

export function Header() {
    const { user, branches: storedBranches, activeBranchId, activeTenantId, logout } = useAuthStore();
    const { theme, toggleTheme } = useTheme();
    const navigate = useNavigate();
    const [profileOpen, setProfileOpen] = useState(false);
    const profileRef = useRef<HTMLDivElement>(null);

    const branchesQuery = useQuery({
        queryKey: ['branches', activeTenantId],
        queryFn: () => api.get('/branches'),
        enabled: Boolean(user && activeTenantId),
    });

    const branches = (branchesQuery.data?.data ?? storedBranches.filter((branch) => !activeTenantId || branch.tenant_id === activeTenantId)) as Array<{ id: number; name: string }>;

    useEffect(() => {
        if (!profileOpen) return;
        const close = (event: MouseEvent) => {
            if (!profileRef.current?.contains(event.target as Node)) setProfileOpen(false);
        };
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, [profileOpen]);

    if (!user) return null;

    const handleLogout = async () => {
        await logout();
        navigate('/login');
    };

    return (
        <header className="hd">
            <div className="logo">
                <NavLink to="/" className={({ isActive }) => `tab ${isActive ? 'on' : ''}`}>Stock<b>ly</b></NavLink>
            </div>
            <nav className="nav-justified">
                {navTabs.map((tab) => <NavLink key={tab.path} to={tab.path} className={({ isActive }) => `tab ${isActive ? 'on' : ''}`}>{tab.label}</NavLink>)}
            </nav>
            <div className="relative" ref={profileRef}>
                <button type="button" className="icon-btn flex items-center gap-2" onClick={() => setProfileOpen((open) => !open)} aria-expanded={profileOpen} aria-haspopup="menu">
                    <span className="hidden sm:inline">{user.name}</span>
                    <span className="inline-flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white">{user.name.charAt(0).toUpperCase()}</span>
                </button>
                {profileOpen && (
                    <div className="absolute right-0 z-20 mt-2 w-72 rounded-xl border border-[var(--line)] bg-white p-3 shadow-lg dark:bg-slate-900" role="menu">
                        <div className="border-b border-[var(--line)] px-2 pb-3">
                            <div className="font-semibold">{user.name}</div>
                            <div className="mut truncate text-sm">{user.email}</div>
                        </div>
                        <div className="space-y-2 py-3">
                            {branches.length > 0 && <label className="block px-2 text-sm"><span className="mut mb-1 block">Current branch</span><select className="input w-full" value={activeBranchId ?? ''} onChange={(event) => { useAuthStore.setState({ activeBranchId: Number(event.target.value) }); queryClient.invalidateQueries(); }}>{branches.map((branch) => <option key={branch.id} value={branch.id}>{branch.name}</option>)}</select></label>}
                            <Link to="/settings" className="block rounded-lg px-2 py-2 text-sm hover:bg-[var(--line)]" onClick={() => setProfileOpen(false)}>Settings</Link>
                            <button type="button" className="flex w-full items-center justify-between rounded-lg px-2 py-2 text-left text-sm hover:bg-[var(--line)]" onClick={toggleTheme} aria-label={theme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme'}><span>{theme === 'dark' ? 'Light theme' : 'Dark theme'}</span>{theme === 'dark' ? <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2} aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg> : <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2} aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" d="M20.354 15.354A9 9 0 008.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>}</button>
                            <button type="button" className="block w-full rounded-lg px-2 py-2 text-left text-sm text-red-600 hover:bg-red-50" onClick={handleLogout}>Sign out</button>
                        </div>
                    </div>
                )}
            </div>
        </header>
    );
}
