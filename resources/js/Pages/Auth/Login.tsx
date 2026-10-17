import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useNavigate } from 'react-router-dom';
import { toast } from 'sonner';
import { useAuthStore } from '@/stores/authStore';
import { Eye, EyeOff, LoaderCircle } from 'lucide-react';
import { useState } from 'react'

const loginSchema = z.object({
    email: z.string().email('Invalid email address'),
    password: z.string().min(1, 'Password is required'),
});

type LoginForm = z.infer<typeof loginSchema>;

export default function Login() {
    const [showPassword, setShowPassword] = useState(false);
    const navigate = useNavigate();
    const login = useAuthStore((state) => state.login);
    const {
        register,
        handleSubmit,
        formState: { errors, isSubmitting },
    } = useForm<LoginForm>({
        resolver: zodResolver(loginSchema),
    });

    const onSubmit = async (data: LoginForm) => {
        try {
            await login(data.email, data.password);
            toast.success('Welcome back!');
            navigate('/');
        } catch (error: any) {
            toast.error(error.message);
        }
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
            <div className="max-w-md w-full space-y-8">
                <div>
                    <div className="mx-auto h-12 w-12 flex items-center justify-center rounded-full bg-primary-600">
                        <span className="text-white font-bold text-xl">Stl</span>
                    </div>
                    <h2 className="mt-6 text-center text-3xl font-extrabold text-gray-900">
                        Sign in to your account
                    </h2>
                    <p className="mt-2 text-center text-sm text-gray-600">
                        Or{' '}
                        <a href="#" className="font-medium text-primary-600 hover:text-primary-500">
                            create a new account
                        </a>
                    </p>
                </div>
                <form
                    className="mt-8 space-y-6"
                    onSubmit={handleSubmit(onSubmit)}
                    noValidate
                >
                    <div className="space-y-5">
                        {/* Email */}
                        <div>
                            <label
                                htmlFor="email"
                                className="mb-2 block text-sm font-medium text-gray-700"
                            >
                                Email address
                            </label>

                            <input
                                {...register('email')}
                                id="email"
                                type="email"
                                autoComplete="email"
                                aria-invalid={!!errors.email}
                                aria-describedby={errors.email ? 'email-error' : undefined}
                                placeholder="you@example.com"
                                className={`block w-full rounded-xl border bg-white px-4 py-3 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:ring-4 ${errors.email
                                    ? 'border-red-400 focus:border-red-500 focus:ring-red-100'
                                    : 'border-gray-300 focus:border-primary-500 focus:ring-primary-100'
                                    }`}
                            />

                            {errors.email && (
                                <p
                                    id="email-error"
                                    role="alert"
                                    className="mt-2 text-sm text-red-600"
                                >
                                    {errors.email.message}
                                </p>
                            )}
                        </div>

                        {/* Password */}
                        <div>
                            <div className="mb-2 flex items-center justify-between">
                                <label
                                    htmlFor="password"
                                    className="block text-sm font-medium text-gray-700"
                                >
                                    Password
                                </label>

                                <a
                                    href="/forgot-password"
                                    className="text-sm font-medium text-primary-600 hover:text-primary-700 focus:outline-none focus:underline"
                                >
                                    Forgot password?
                                </a>
                            </div>

                            <div className="relative">
                                <input
                                    {...register('password')}
                                    id="password"
                                    type={showPassword ? 'text' : 'password'}
                                    autoComplete="current-password"
                                    aria-invalid={!!errors.password}
                                    aria-describedby={
                                        errors.password ? 'password-error' : undefined
                                    }
                                    placeholder="Enter your password"
                                    className={`block w-full rounded-xl border bg-white px-4 py-3 pr-12 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:ring-4 ${errors.password
                                            ? 'border-red-400 focus:border-red-500 focus:ring-red-100'
                                            : 'border-gray-300 focus:border-primary-500 focus:ring-primary-100'
                                        }`}
                                />

                                <button
                                    type="button"
                                    onClick={() => setShowPassword((prev: any) => !prev)}
                                    aria-label={showPassword ? 'Hide password' : 'Show password'}
                                    aria-pressed={showPassword}
                                    className="absolute inset-y-0 right-0 flex items-center px-4 text-gray-500 hover:text-gray-700 focus:outline-none  rounded-r-xl"
                                >
                                    {showPassword ? (
                                        <EyeOff size={18} />
                                    ) : (
                                        <Eye size={18} />
                                    )}
                                </button>
                            </div>

                            {errors.password && (
                                <p
                                    id="password-error"
                                    role="alert"
                                    className="mt-2 text-sm text-red-600"
                                >
                                    {errors.password.message}
                                </p>
                            )}
                        </div>
                    </div>

                    {/* Remember me */}
                    {/* <div className="flex items-center">
        <input
            id="remember_me"
            type="checkbox"
            {...register('remember')}
            className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
        />

        <label
            htmlFor="remember_me"
            className="ml-2 block text-sm text-gray-600"
        >
            Remember me
        </label>
    </div> */}

                    {/* Submit */}
                    <button
                        type="submit"
                        disabled={isSubmitting}
                        className="flex w-full items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-4 focus:ring-primary-200 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {isSubmitting && (
                            <LoaderCircle size={18} className="animate-spin" />
                        )}

                        {isSubmitting ? 'Signing in...' : 'Sign in'}
                    </button>
                </form>
            </div>
        </div>
    );
}