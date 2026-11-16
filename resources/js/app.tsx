import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate, Outlet } from 'react-router-dom';
import { QueryClientProvider } from '@tanstack/react-query';
import { Toaster } from 'sonner';
import { lazy, Suspense, useEffect, useRef, useState } from 'react';

import { AuthProvider, useAuthStore } from '@/stores/authStore';
import { MainLayout } from '@/components/Layout';
import { queryClient } from '@/lib/queryClient';

const Login = lazy(() => import('@/Pages/Auth/Login'));
const ForgotPassword = lazy(() => import('@/Pages/Auth/ForgotPassword'));
const ResetPassword = lazy(() => import('@/Pages/Auth/ResetPassword'));
const AcceptInvite = lazy(() => import('@/Pages/Auth/AcceptInvite'));
const PurchaseList = lazy(() => import('@/Pages/Purchases/List'));
const SaleList = lazy(() => import('@/Pages/Sales/List'));
const Reports = lazy(() => import('@/Pages/Reports/Index'));
const Stock = lazy(() => import('@/Pages/Stock/Index'));
const Items = lazy(() => import('@/Pages/Items/Index'));
const Units = lazy(() => import('@/Pages/Units/Index'));
const Settings = lazy(() => import('@/Pages/Settings/Index'));
const Transfers = lazy(() => import('@/Pages/Transfers/Index'));

function ProtectedRoute() {
    const { user, fetchUser, refreshMemberships } = useAuthStore();
    const [isLoading, setIsLoading] = useState(!user);
    const hydrated = useRef(false);

    useEffect(() => {
        if (hydrated.current) return;
        hydrated.current = true;

        const refresh = user
            ? refreshMemberships().catch(() => undefined)
            : fetchUser();

        refresh.finally(() => setIsLoading(false));
    }, [user, fetchUser, refreshMemberships]);

    if (isLoading) {
        return (
            <div className="min-h-screen flex items-center justify-center">
                <div className="animate-spin rounded-full h-12 w-12 border-4 border-primary-600 border-t-transparent"></div>
            </div>
        );
    }

    if (!user) {
        return <Navigate to="/login" replace />;
    }

    return <Outlet />;
}

function PublicRoute() {
    const { user } = useAuthStore();
    if (user) {
        return <Navigate to="/purchases" replace />;
    }
    return <Outlet />;
}

function App() {
    return (
        <QueryClientProvider client={queryClient}>
            <AuthProvider>
                <BrowserRouter>
                    <Toaster position="top-right" />
                    <Suspense
                        fallback={
                            <div className="min-h-screen flex items-center justify-center">
                                <div className="animate-spin rounded-full h-12 w-12 border-4 border-primary-600 border-t-transparent"></div>
                            </div>
                        }
                    >
                        <Routes>
                            <Route element={<PublicRoute />}>
                             <Route path="/login" element={<Login />} />
                             <Route path="/forgot-password" element={<ForgotPassword />} />
                              <Route path="/reset-password" element={<ResetPassword />} />
                              <Route path="/accept-invite" element={<AcceptInvite />} />
                            </Route>
                            <Route element={<ProtectedRoute />}>
                                <Route element={<MainLayout />}>
                                    <Route path="/" element={<Reports />} />
                                    <Route path="/reports" element={<Reports />} />
                                    <Route path="/stock" element={<Stock />} />
                                    <Route path="/items" element={<Items />} />
                                     <Route path="/units" element={<Units />} />
                                     <Route path="/settings" element={<Settings />} />
                                     <Route path="/transfers" element={<Transfers />} />
                                    <Route path="/purchases" element={<PurchaseList />} />
                                    <Route path="/sales" element={<SaleList />} />
                                    {/* The entry forms live in modals now; these keep old links working. */}
                                    <Route path="/buy" element={<Navigate to="/purchases" replace />} />
                                    <Route path="/sell" element={<Navigate to="/sales" replace />} />
                                    <Route path="*" element={<Navigate to="/" replace />} />
                                </Route>
                            </Route>
                        </Routes>
                    </Suspense>
                </BrowserRouter>
            </AuthProvider>
        </QueryClientProvider>
    );
}

createRoot(document.getElementById('app')!).render(<App />);
