import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate, Outlet } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Toaster } from 'sonner';
import { lazy, Suspense, useEffect, useState } from 'react';

import { AuthProvider, useAuthStore } from '@/stores/authStore';
import { MainLayout } from '@/components/Layout';

const Login = lazy(() => import('@/Pages/Auth/Login'));
const PurchaseList = lazy(() => import('@/Pages/Purchases/List'));
const SaleList = lazy(() => import('@/Pages/Sales/List'));
const Reports = lazy(() => import('@/Pages/Reports/Index'));
const Stock = lazy(() => import('@/Pages/Stock/Index'));
const Items = lazy(() => import('@/Pages/Items/Index'));
const Units = lazy(() => import('@/Pages/Units/Index'));

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            staleTime: 5 * 60 * 1000,
            retry: 1,
        },
    },
});

function ProtectedRoute() {
    const { user, fetchUser } = useAuthStore();
    const [isLoading, setIsLoading] = useState(!user);

    useEffect(() => {
        if (!user) {
            fetchUser().finally(() => setIsLoading(false));
        } else {
            setIsLoading(false);
        }
    }, [user, fetchUser]);

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
                            </Route>
                            <Route element={<ProtectedRoute />}>
                                <Route element={<MainLayout />}>
                                    <Route path="/" element={<Reports />} />
                                    <Route path="/reports" element={<Reports />} />
                                    <Route path="/stock" element={<Stock />} />
                                    <Route path="/items" element={<Items />} />
                                    <Route path="/units" element={<Units />} />
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
