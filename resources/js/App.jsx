import { useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import WaiterDashboard from './pages/WaiterDashboard';
import KitchenDashboard from './pages/KitchenDashboard';
import CashierDashboard from './pages/CashierDashboard';
import AdminDashboard from './pages/AdminDashboard';
import Login from './pages/Login';
import ProtectedRoute from './components/ProtectedRoute';
import { api } from './api/client';
import { syncServerAppearance } from './theme';
import {
    ADMIN_PERMISSIONS,
    CASHIER_PERMISSIONS,
    KITCHEN_PERMISSIONS,
    WAITER_PERMISSIONS,
    landingFor,
} from './auth/permissions';
import { useAuth } from './auth/AuthContext';

export default function App() {
    const { isAuthenticated, checking, user } = useAuth();

    const { data: settings } = useQuery({
        queryKey: ['settings'],
        queryFn: async () => (await api.get('/settings')).data.data,
        // `/settings` kini butuh sesi. Menjalankannya sebelum login hanya
        // menghasilkan 401 yang tidak ada artinya.
        enabled: isAuthenticated,
        retry: false,
    });

    useEffect(() => {
        syncServerAppearance(settings?.appearance);
    }, [settings]);

    // User tanpa akses ke halaman mana pun perlu diberi tahu, bukan dilempar
    // ke login berulang kali.
    if (isAuthenticated && landingFor(user?.permissions) === null) {
        return (
            <div className="min-h-screen flex items-center justify-center px-4 text-center">
                <div className="card max-w-md">
                    <h2 className="font-bold text-lg">Belum ada hak akses</h2>
                    <p className="text-sm text-muted mt-1">
                        Akun Anda belum diberi halaman apa pun. Hubungi admin untuk menambah
                        Group hak akses.
                    </p>
                </div>
            </div>
        );
    }

    return (
        <BrowserRouter>
            <Routes>
                <Route path="/login" element={<Login />} />
                <Route
                    path="/kasir"
                    element={
                        <ProtectedRoute anyPermission={CASHIER_PERMISSIONS}>
                            <CashierDashboard />
                        </ProtectedRoute>
                    }
                />
                <Route
                    path="/koki"
                    element={
                        <ProtectedRoute anyPermission={KITCHEN_PERMISSIONS}>
                            <KitchenDashboard />
                        </ProtectedRoute>
                    }
                />
                <Route
                    path="/waiters"
                    element={
                        <ProtectedRoute anyPermission={WAITER_PERMISSIONS}>
                            <WaiterDashboard />
                        </ProtectedRoute>
                    }
                />
                <Route
                    path="/admin"
                    element={
                        <ProtectedRoute anyPermission={ADMIN_PERMISSIONS}>
                            <AdminDashboard />
                        </ProtectedRoute>
                    }
                />
                <Route path="/" element={<Home />} />
                <Route path="*" element={<Home />} />
            </Routes>
        </BrowserRouter>
    );
}

/**
 * Akar dan URL tak dikenal tidak lagi diarahkan ke kasir, karena kasir kini
 * butuh login. User anonim diarahkan ke login, user yang sudah masuk ke
 * halaman pertama yang dia punya aksesnya.
 */
function Home() {
    const { isAuthenticated, checking, user } = useAuth();

    if (checking) {
        return <div className="min-h-screen flex items-center justify-center text-muted">Memuat sesi...</div>;
    }

    if (!isAuthenticated) {
        return <Navigate to="/login" replace />;
    }

    return <Navigate to={landingFor(user?.permissions) ?? '/login'} replace />;
}
