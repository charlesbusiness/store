import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import React from 'react';
import { api } from '@/lib/api';

interface User {
    id: number;
    name: string;
    email: string;
}

interface Branch { id: number; tenant_id: number; name: string; code: string; is_default: boolean; }
interface Tenant { id: number; name: string; branches?: Branch[]; }

interface LoginResponse {
    user?: User;
    token?: string;
    message?: string;
    tenants?: Tenant[];
    branches?: Branch[];
}

interface UserResponse {
    user?: User;
    tenants?: Tenant[];
    branches?: Branch[];
}

interface AuthState {
    user: User | null;
    token: string | null;
    tenants: Tenant[];
    branches: Branch[];
    activeTenantId: number | null;
    activeBranchId: number | null;
    login: (email: string, password: string) => Promise<void>;
    logout: () => Promise<void>;
    fetchUser: () => Promise<User | null>;
    refreshMemberships: () => Promise<void>;
}

export const useAuthStore = create<AuthState>()(
    persist(
        (set) => ({
            user: null,
            token: null,
            tenants: [],
            branches: [],
            activeTenantId: null,
            activeBranchId: null,
            login: async (email: string, password: string) => {
                const response = await api.post<LoginResponse, LoginResponse>('/auth/login', { email, password });

                if (!response.user || !response.token) {
                    throw new Error(response.message || 'Login failed');
                }

                const tenants = response.tenants ?? [];
                const branches = response.branches ?? tenants.flatMap((tenant) => tenant.branches ?? []);
                const branch = branches.find((entry) => entry.is_default) ?? branches[0];
                set({ user: response.user, token: response.token, tenants, branches, activeTenantId: tenants[0]?.id ?? null, activeBranchId: branch?.id ?? null });
            },
            logout: async () => {
                try {
                    await api.post('/auth/logout');
                } finally {
                    set({ user: null, token: null, tenants: [], branches: [], activeTenantId: null, activeBranchId: null });
                }
            },
            fetchUser: async () => {
                try {
                    const response = await api.get<UserResponse, UserResponse>('/auth/user');

                    const user = response.user;

                    if (!user) {
                        throw new Error('Unauthorized');
                    }

                    const tenants = response.tenants ?? [];
                    const branches = response.branches ?? tenants.flatMap((tenant) => tenant.branches ?? []);
                    const state = useAuthStore.getState();
                    const activeTenantId = state.activeTenantId ?? tenants[0]?.id ?? null;
                    const activeBranchId = branches.some((entry) => entry.id === state.activeBranchId)
                        ? state.activeBranchId
                        : branches.find((entry) => entry.is_default)?.id ?? branches[0]?.id ?? null;
                    set({ user, tenants, branches, activeTenantId, activeBranchId });
                    return user;
                } catch {
                    set({ user: null, token: null });
                    return null;
                }
            },
            refreshMemberships: async () => {
                const response = await api.get<UserResponse, UserResponse>('/auth/user');
                const tenants = response.tenants ?? [];
                const branches = response.branches ?? tenants.flatMap((tenant) => tenant.branches ?? []);
                const state = useAuthStore.getState();
                set({ tenants, branches, activeTenantId: state.activeTenantId ?? tenants[0]?.id ?? null, activeBranchId: state.activeBranchId && branches.some((entry) => entry.id === state.activeBranchId) ? state.activeBranchId : branches.find((entry) => entry.is_default)?.id ?? branches[0]?.id ?? null });
            },
        }),
        {
            name: 'auth-storage',
            version: 2,
            partialize: (state) => ({ user: state.user, token: state.token, tenants: state.tenants, branches: state.branches, activeTenantId: state.activeTenantId, activeBranchId: state.activeBranchId }),
            migrate: (persisted) => {
                const state = (persisted ?? {}) as Partial<AuthState>;

                return {
                    ...state,
                    tenants: [],
                    branches: [],
                    activeBranchId: null,
                } as AuthState;
            },
        }
    )
);

export const AuthProvider = ({ children }: { children: React.ReactNode }) => {
    return <>{children}</>;
};
