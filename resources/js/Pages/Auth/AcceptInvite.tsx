import { FormEvent, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { toast } from 'sonner';
import { api } from '@/lib/api';

export default function AcceptInvite() {
    const location = useLocation();
    const navigate = useNavigate();
    const token = new URLSearchParams(location.search).get('token') ?? '';
    const [password, setPassword] = useState('');
    const [confirmation, setConfirmation] = useState('');
    const [loading, setLoading] = useState(false);

    async function submit(event: FormEvent) {
        event.preventDefault();
        if (password !== confirmation) {
            toast.error('Passwords do not match.');
            return;
        }

        setLoading(true);
        try {
            await api.post('/auth/accept-invite', {
                token,
                password,
                password_confirmation: confirmation,
            });
            toast.success('Password created. You can now sign in.');
            navigate('/login');
        } catch (error: any) {
            toast.error(error.response?.data?.message ?? 'Unable to accept invitation.');
        } finally {
            setLoading(false);
        }
    }

    return (
        <div className="min-h-screen flex items-center justify-center bg-gray-50 px-4">
            <form onSubmit={submit} className="w-full max-w-md space-y-4 rounded-2xl bg-white p-8 shadow-sm">
                <div className="mb-5">
                    <h1 className="text-2xl font-bold text-gray-900">Create your password</h1>
                    <p className="mt-2 text-sm text-gray-600">Choose a password to activate your Stockly access.</p>
                </div>
                <input value={password} onChange={(event) => setPassword(event.target.value)} type="password" required minLength={8} autoComplete="new-password" placeholder="Password" className="block w-full rounded-xl border px-4 py-3" />
                <input value={confirmation} onChange={(event) => setConfirmation(event.target.value)} type="password" required minLength={8} autoComplete="new-password" placeholder="Confirm password" className="block w-full rounded-xl border px-4 py-3" />
                <button disabled={loading || token.length !== 64} className="w-full rounded-xl bg-primary-600 px-4 py-3 font-semibold text-white disabled:opacity-60">{loading ? 'Creating...' : 'Create password'}</button>
                <Link to="/login" className="block text-center text-sm text-primary-600">Back to sign in</Link>
            </form>
        </div>
    );
}
