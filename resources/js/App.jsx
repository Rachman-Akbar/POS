import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import WaiterDashboard from './pages/WaiterDashboard';
import KitchenDashboard from './pages/KitchenDashboard';
import CashierDashboard from './pages/CashierDashboard';
import AdminDashboard from './pages/AdminDashboard';

export default function App() {
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