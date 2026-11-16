import { FormEvent, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { toast } from 'sonner';
import { api } from '@/lib/api';

export default function ForgotPassword() {
    const [email, setEmail] = useState('');
    const [loading, setLoading] = useState(false);
    const navigate = useNavigate();

    async function submit(event: FormEvent) {
        event.preventDefault();
        setLoading(true);
        try {
            await api.post('/auth/forgot-password', { email });
            toast.success('If the email exists, a reset code has been sent.');
            navigate(`/reset-password?email=${encodeURIComponent(email)}`);
        } catch (error: any) {
            toast.error(error.response?.data?.message ?? 'Unable to request a reset code.');
        } finally {
            setLoading(false);
        }
    }

    return (
        <div className="min-h-screen flex items-center justify-center bg-gray-50 px-4">
            <form onSubmit={submit} className="w-full max-w-md space-y-6 rounded-2xl bg-white p-8 shadow-sm">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Reset your password</h1>
                    <p className="mt-2 text-sm text-gray-600">Enter your email and we will send a one-time reset code.</p>
                </div>
                <input value={email} onChange={(event) => setEmail(event.target.value)} type="email" required autoComplete="email" placeholder="you@example.com" className="block w-full rounded-xl border px-4 py-3" />
                <button disabled={loading} className="w-full rounded-xl bg-primary-600 px-4 py-3 font-semibold text-white disabled:opacity-60">{loading ? 'Sending...' : 'Send reset code'}</button>
                <Link to="/login" className="block text-center text-sm text-primary-600">Back to sign in</Link>
            </form>
        </div>
    );
}
