import { NavLink } from 'react-router-dom';
import { ChefHat, Receipt, UtensilsCrossed } from 'lucide-react';

const NAV = [
    { to: '/waiters', label: 'Waiter', icon: UtensilsCrossed },
    { to: '/koki', label: 'Koki', icon: ChefHat },
    { to: '/kasir', label: 'Kasir', icon: Receipt },
];

export default function Layout({ title, subtitle, children, right }) {
    return (
        <div className="min-h-screen bg-gray-50 flex">
            <aside className="w-60 bg-white border-r border-gray-200 p-4 flex flex-col gap-1 shrink-0">
                <div className="flex items-center gap-2 px-4 py-3 mb-2">
                    <div className="btn-icon bg-orange-600 text-white w-9 h-9">
                        <ChefHat />
                    </div>
                    <div>
                        <div className="font-bold leading-tight">POS Resto</div>
                        <div className="text-[11px] text-muted">Kitchen Display & Kasir</div>
                    </div>
                </div>

                {NAV.map((item) => (
                    <NavLink
                        key={item.to}
                        to={item.to}
                        className={({ isActive }) => `navitem ${isActive ? 'navitem-active' : 'navitem-idle'}`}
                    >
                        <item.icon size={18} />
                        {item.label}
                    </NavLink>
                ))}
            </aside>

            <main className="flex-1 p-6 overflow-auto">
                <div className="flex items-start justify-between gap-4 mb-6">
                    <div>
                        <h1 className="text-2xl font-bold">{title}</h1>
                        {subtitle && <p className="text-muted text-sm mt-0.5">{subtitle}</p>}
                    </div>
                    {right}
                </div>
                {children}
            </main>
        </div>
    );
}