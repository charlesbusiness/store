import { FormEvent, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { toast } from 'sonner';
import { api } from '@/lib/api';

export default function ResetPassword() {
    const location = useLocation();
    const navigate = useNavigate();
    const params = new URLSearchParams(location.search);
    const [email, setEmail] = useState(params.get('email') ?? '');
    const [otp, setOtp] = useState('');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [loading, setLoading] = useState(false);

    async function submit(event: FormEvent) {
        event.preventDefault();
        if (password !== passwordConfirmation) {
            toast.error('Passwords do not match.');
            return;
        }
        setLoading(true);
        try {
            await api.post('/auth/verify-reset-otp', { email, otp });
            await api.post('/auth/reset-password', { email, otp, password, password_confirmation: passwordConfirmation });
            toast.success('Password reset successfully.');
            navigate('/login');
        } catch (error: any) {
            toast.error(error.response?.data?.message ?? 'Unable to reset password.');
        } finally {
            setLoading(false);
        }
    }

    return (
        <div className="min-h-screen flex items-center justify-center bg-gray-50 px-4">
            <form onSubmit={submit} className="w-full max-w-md space-y-4 rounded-2xl bg-white p-8 shadow-sm">
                <div className="mb-5">
                    <h1 className="text-2xl font-bold text-gray-900">Enter reset code</h1>
                    <p className="mt-2 text-sm text-gray-600">Use the six-digit code sent to your email.</p>
                </div>
                <input value={email} onChange={(event) => setEmail(event.target.value)} type="email" required placeholder="Email" className="block w-full rounded-xl border px-4 py-3" />
                <input value={otp} onChange={(event) => setOtp(event.target.value.replace(/\D/g, '').slice(0, 6))} inputMode="numeric" pattern="[0-9]{6}" required placeholder="6-digit code" className="block w-full rounded-xl border px-4 py-3 tracking-widest" />
                <input value={password} onChange={(event) => setPassword(event.target.value)} type="password" required minLength={8} placeholder="New password" className="block w-full rounded-xl border px-4 py-3" />
                <input value={passwordConfirmation} onChange={(event) => setPasswordConfirmation(event.target.value)} type="password" required minLength={8} placeholder="Confirm new password" className="block w-full rounded-xl border px-4 py-3" />
                <button disabled={loading} className="w-full rounded-xl bg-primary-600 px-4 py-3 font-semibold text-white disabled:opacity-60">{loading ? 'Resetting...' : 'Reset password'}</button>
                <Link to="/login" className="block text-center text-sm text-primary-600">Back to sign in</Link>
            </form>
        </div>
    );
}
