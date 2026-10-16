import { NavLink, useNavigate } from 'react-router-dom';
import { useAuthStore } from '@/stores/authStore';

export function Footer() {
    const { user, logout } = useAuthStore();
    const navigate = useNavigate();

    const handleLogout = async (e: React.FormEvent) => {
        e.preventDefault();
        await logout();
        navigate('/login');
    };

    if (!user) return null;

    return (
        <footer className="foot">
            <span>
                <NavLink to="/items">Items</NavLink> &middot;
                <NavLink to="/units">Units</NavLink>
            </span>
            <span>
                {user.name} &middot;
                <form onSubmit={handleLogout} style={{ display: 'inline' }}>
                    <button type="submit" className="x" style={{ color: 'var(--mut)', fontSize: '12px' }}>
                        Sign out
                    </button>
                </form>
            </span>
        </footer>
    );
}