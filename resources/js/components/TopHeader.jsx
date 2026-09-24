import { useEffect, useRef, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import {
    Search, Menu, CheckCircle2, ChevronDown, ChefHat, Receipt, ShieldCheck, UtensilsCrossed,
} from 'lucide-react';
import ViewModeSwitch from './ViewModeSwitch';

export { VIEW_MODES } from './ViewModeSwitch';

export const ROLES = [
    { path: '/kasir', label: 'Kasir', icon: Receipt, user: { name: 'Fajar', jabatan: 'Kasir' } },
    { path: '/admin', label: 'Admin', icon: ShieldCheck, user: { name: 'Raka', jabatan: 'Administrator' } },
    { path: '/koki', label: 'Koki', icon: ChefHat, user: { name: 'Dimas', jabatan: 'Koki Dapur' } },
    { path: '/waiters', label: 'Waiters', icon: UtensilsCrossed, user: { name: 'Sari', jabatan: 'Pelayan' } },
];

function useClickOutside(onClose) {
    const ref = useRef(null);

    useEffect(() => {
        const handler = (e) => {
            if (ref.current && !ref.current.contains(e.target)) onClose();
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, [onClose]);

    return ref;
}

function currentRole(pathname) {
    return ROLES.find((r) => pathname.startsWith(r.path)) ?? ROLES[0];
}

export default function TopHeader({
    navLabel = 'Menu',
    showCatalog = false,
    mode,
    onModeChange,
    query,
    onQueryChange,
    navItems = [],
    activeNav,
    onNavChange,
    onCheckOrders,
    orderCount = 0,
}) {
    const navigate = useNavigate();
    const { pathname } = useLocation();
    const [navOpen, setNavOpen] = useState(false);
    const [profileOpen, setProfileOpen] = useState(false);
    const navRef = useClickOutside(() => setNavOpen(false));
    const profileRef = useClickOutside(() => setProfileOpen(false));
    const role = currentRole(pathname);

    const goRole = (path) => {
        setProfileOpen(false);
        navigate(path);
    };

    return (
        <header className="sticky top-0 z-30 bg-white border-b border-gray-100">
            <div className="mx-auto max-w-[1600px] px-4 md:px-6 flex items-center gap-2 md:gap-3 h-16">
                {showCatalog && onModeChange && (
                    <ViewModeSwitch value={mode} onChange={onModeChange} className="hidden sm:inline-flex shrink-0" />
                )}

                {showCatalog && onQueryChange && (
                    <div className="relative flex-1 min-w-0 max-w-md">
                        <Search size={15} className="text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                        <input
                            value={query}
                            onChange={(e) => onQueryChange(e.target.value)}
                            placeholder="Cari produk..."
                            className="input !pl-9"
                        />
                    </div>
                )}

                {navItems.length > 0 && (
                    <div className="relative shrink-0" ref={navRef}>
                        <button onClick={() => setNavOpen((v) => !v)} className="btn btn-ghost !px-3">
                            <Menu size={16} /> <span className="hidden sm:inline">{navLabel}</span>
                            <ChevronDown size={14} className={`transition-transform ${navOpen ? 'rotate-180' : ''}`} />
                        </button>
                        {navOpen && (
                            <div className="absolute left-0 top-12 w-56 bg-white border border-gray-100 rounded-lg py-1.5 z-40">
                                {navItems.map((item) => (
                                    <button
                                        key={item.key}
                                        onClick={() => {
                                            onNavChange(item.key);
                                            setNavOpen(false);
                                        }}
                                        className={`w-full flex items-center gap-2 px-3 py-2 text-sm text-left transition-colors cursor-pointer ${
                                            activeNav === item.key ? 'bg-orange-50 text-orange-700 font-semibold' : 'hover:bg-gray-50'
                                        }`}
                                    >
                                        {item.icon && <item.icon size={15} />}
                                        {item.label}
                                        {item.count !== undefined && (
                                            <span className="ml-auto text-[11px] font-bold text-muted">{item.count}</span>
                                        )}
                                        {activeNav === item.key && item.count === undefined && (
                                            <CheckCircle2 size={14} className="ml-auto text-orange-600" />
                                        )}
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                <div className="flex-1" />

                {onCheckOrders && (
                    <button onClick={onCheckOrders} className="btn btn-ghost shrink-0 relative">
                        <Receipt size={16} /> <span className="hidden md:inline">Cek Pesanan</span>
                        {orderCount > 0 && (
                            <span className="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-orange-600 text-white text-[10px] font-bold flex items-center justify-center">
                                {orderCount}
                            </span>
                        )}
                    </button>
                )}

                <div className="relative shrink-0" ref={profileRef}>
                    <button
                        onClick={() => setProfileOpen((v) => !v)}
                        className="flex items-center gap-2 py-1.5 pl-1.5 pr-2 rounded-lg hover:bg-gray-50 transition-colors"
                        title="Akun & ganti peran (simulasi)"
                    >
                        <span className="w-8 h-8 rounded-full bg-gray-900 text-white text-xs font-bold flex items-center justify-center uppercase">
                            {role.user.name[0]}
                        </span>
                        <span className="hidden md:block text-left leading-tight">
                            <span className="block text-sm font-semibold">{role.user.name}</span>
                            <span className="block text-[10px] text-muted">{role.user.jabatan}</span>
                        </span>
                        <ChevronDown size={14} className={`text-muted transition-transform ${profileOpen ? 'rotate-180' : ''}`} />
                    </button>

                    {profileOpen && (
                        <div className="absolute right-0 top-12 w-56 bg-white border border-gray-100 rounded-lg py-1.5">
                            <div className="flex items-center gap-3 px-3 py-2 border-b border-gray-50 mb-1">
                                <span className="w-9 h-9 rounded-full bg-orange-100 text-orange-700 text-xs font-bold flex items-center justify-center uppercase shrink-0">
                                    {role.user.name[0]}
                                </span>
                                <div className="min-w-0">
                                    <div className="text-sm font-bold truncate">{role.user.name}</div>
                                    <div className="text-[11px] text-muted truncate">{role.user.jabatan}</div>
                                </div>
                            </div>

                            <div className="px-3 py-1 text-[11px] font-semibold text-muted uppercase tracking-wide">
                                Switch Role (Simulasi)
                            </div>
                            {ROLES.map((r) => (
                                <button
                                    key={r.path}
                                    onClick={() => goRole(r.path)}
                                    className={`w-full flex items-center gap-2 px-3 py-2 text-sm text-left transition-colors cursor-pointer ${
                                        role.path === r.path ? 'bg-orange-50 text-orange-700 font-semibold' : 'hover:bg-gray-50'
                                    }`}
                                >
                                    <r.icon size={15} />
                                    {r.label}
                                    {role.path === r.path && <CheckCircle2 size={14} className="ml-auto text-orange-600" />}
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </header>
    );
}
