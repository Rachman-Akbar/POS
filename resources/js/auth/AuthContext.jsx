import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { api, setUnauthenticatedHandler, tokenStore } from '../api/client';

const AuthContext = createContext(null);

/**
 * Sumber kebenaran soal user yang sedang login.
 *
 * `can()` hanya untuk menyembunyikan UI. Penegakan yang sebenarnya terjadi di
 * backend lewat middleware `permission`, jadi memanipulasi frontend tidak
 * memberi akses apa pun.
 */
export function AuthProvider({ children }) {
    const queryClient = useQueryClient();
    const [user, setUser] = useState(null);
    const [checking, setChecking] = useState(true);

    const clearSession = useCallback(() => {
        tokenStore.clear();
        setUser(null);
        queryClient.clear();
    }, [queryClient]);

    useEffect(() => {
        setUnauthenticatedHandler(clearSession);
        return () => setUnauthenticatedHandler(null);
    }, [clearSession]);

    // Sesi dipulihkan dari token yang tersimpan saat aplikasi dimuat.
    useEffect(() => {
        let active = true;

        if (!tokenStore.get()) {
            setChecking(false);

            return () => {
                active = false;
            };
        }

        api
            .get('/me')
            .then(({ data }) => active && setUser(data.user))
            .catch(() => active && tokenStore.clear())
            .finally(() => active && setChecking(false));

        return () => {
            active = false;
        };
    }, []);

    const login = useCallback(async (identity, password) => {
        // `identity` diterima backend sebagai nama pengguna maupun email, jadi
        // field `email` yang dulu dikirim tidak lagi dibedakan di sini.
        const { data } = await api.post('/login', { identity, password });

        tokenStore.set(data.token);
        setUser(data.user);

        return data.user;
    }, []);

    const logout = useCallback(async () => {
        try {
            await api.post('/logout');
        } catch {
            // Token sudah tidak berlaku di server juga tetap keluar di client.
        }

        clearSession();
    }, [clearSession]);

    const can = useCallback(
        (...permissions) => {
            if (!user) {
                return false;
            }

            if (user.is_super_admin) {
                return true;
            }

            const owned = user.permissions ?? [];

            return permissions.every((permission) => owned.includes(permission));
        },
        [user],
    );

    const canAny = useCallback(
        (...permissions) => {
            if (!user) {
                return false;
            }

            if (user.is_super_admin) {
                return true;
            }

            const owned = user.permissions ?? [];

            return permissions.some((permission) => owned.includes(permission));
        },
        [user],
    );

    const value = useMemo(
        () => ({ user, checking, login, logout, can, canAny, isAuthenticated: Boolean(user) }),
        [user, checking, login, logout, can, canAny],
    );

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
    const context = useContext(AuthContext);

    if (!context) {
        throw new Error('useAuth harus dipakai di dalam AuthProvider.');
    }

    return context;
}
