import axios from 'axios';
import { useAuthStore } from '@/stores/authStore';

export const api = axios.create({
    baseURL: '/api',
    withCredentials: false,
});

api.interceptors.request.use((config) => {
    const token = useAuthStore.getState().token;
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    const state = useAuthStore.getState();
    if (state.activeTenantId) config.headers['X-Tenant-ID'] = String(state.activeTenantId);
    if (state.activeBranchId) config.headers['X-Branch-ID'] = String(state.activeBranchId);

    return config;
});

api.interceptors.response.use(
    (response) => response.data,
    async (error) => {
        if (error.response?.status === 401) {
            useAuthStore.getState().logout();
            window.location.href = '/login';
        }
        return Promise.reject(error);
    }
);
