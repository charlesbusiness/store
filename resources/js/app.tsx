import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate, Outlet } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Toaster } from 'sonner';
import { useEffect, useState } from 'react';

import Login from '@/Pages/Auth/Login';
import { AuthProvider, useAuthStore } from '@/stores/authStore';
import { MainLayout } from '@/components/Layout';
import PurchaseList from '@/Pages/Purchases/List';
import PurchaseCreate from '@/Pages/Purchases/Create';
import SaleList from '@/Pages/Sales/List';
import SaleCreate from '@/Pages/Sales/Create';
import Reports from '@/Pages/Reports/Index';
import Stock from '@/Pages/Stock/Index';
import Items from '@/Pages/Items/Index';
import Units from '@/Pages/Units/Index';

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
        return <Navigate to="/buy" replace />;
    }
    return <Outlet />;
}

function App() {
    return (
        <QueryClientProvider client={queryClient}>
            <AuthProvider>
                <BrowserRouter>
                    <Toaster position="top-right" />
                    <Routes>
                        <Route element={<PublicRoute />}>
                            <Route path="/login" element={<Login />} />
                        </Route>
                        <Route element={<ProtectedRoute />}>
                            <Route element={<MainLayout />}>
                                <Route path="/buy" element={<PurchaseCreate />} />
                                <Route path="/sell" element={<SaleCreate />} />
                                <Route path="/" element={<Reports />} />
                                <Route path="/stock" element={<Stock />} />
                                <Route path="/items" element={<Items />} />
                                <Route path="/units" element={<Units />} />
                                <Route path="/purchases" element={<PurchaseList />} />
                                <Route path="/sales" element={<SaleList />} />
                                {/* <Route path="/" element={<Navigate to="/buy" replace />} /> */}
                                <Route path="*" element={<Navigate to="/" replace />} />
                            </Route>
                        </Route>
                    </Routes>
                </BrowserRouter>
            </AuthProvider>
        </QueryClientProvider>
    );
}

createRoot(document.getElementById('app')!).render(<App />);