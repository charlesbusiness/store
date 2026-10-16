import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import React from 'react';
import { api } from '@/lib/api';

interface User {
    id: number;
    name: string;
    email: string;
}

interface AuthState {
    user: User | null;
    token: string | null;
    login: (email: string, password: string) => Promise<void>;
    logout: () => Promise<void>;
    fetchUser: () => Promise<User | null>;
}

export const useAuthStore = create<AuthState>()(
    persist(
        (set) => ({
            user: null,
            token: null,
            login: async (email: string, password: string) => {
                const response = await api.post('/auth/login', { email, password });

                if (!response.user || !response.token) {
                    throw new Error(response.message || 'Login failed');
                }

                set({ user: response.user, token: response.token });
            },
            logout: async () => {
                try {
                    await api.post('/auth/logout');
                } finally {
                    set({ user: null, token: null });
                }
            },
            fetchUser: async () => {
                try {
                    const response = await api.get('/auth/user');

                    const user = response.user;

                    if (!user) {
                        throw new Error('Unauthorized');
                    }

                    set({ user });
                    return user;
                } catch {
                    set({ user: null, token: null });
                    return null;
                }
            },
        }),
        {
            name: 'auth-storage',
            partialize: (state) => ({ user: state.user, token: state.token }),
        }
    )
);

export const AuthProvider = ({ children }: { children: React.ReactNode }) => {
    return <>{children}</>;
};
