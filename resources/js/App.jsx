import { useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import WaiterDashboard from './pages/WaiterDashboard';
import KitchenDashboard from './pages/KitchenDashboard';
import CashierDashboard from './pages/CashierDashboard';
import AdminDashboard from './pages/AdminDashboard';
import { api } from './api/client';
import { syncServerAppearance } from './theme';

export default function App() {
    const { data: settings } = useQuery({
        queryKey: ['settings'],
        queryFn: async () => (await api.get('/settings')).data.data,
    });

    useEffect(() => {
        syncServerAppearance(settings?.appearance);
    }, [settings]);

    return (
        <BrowserRouter>
            <Routes>
                <Route path="/kasir" element={<CashierDashboard />} />
                <Route path="/admin" element={<AdminDashboard />} />
                <Route path="/koki" element={<KitchenDashboard />} />
                <Route path="/waiters" element={<WaiterDashboard />} />
                <Route path="/" element={<Navigate to="/kasir" replace />} />
                <Route path="*" element={<Navigate to="/kasir" replace />} />
            </Routes>
        </BrowserRouter>
    );
}
