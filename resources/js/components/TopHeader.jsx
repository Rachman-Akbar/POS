import { useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import {
    Search, CheckCircle2, ChevronDown, ChefHat, LogIn, LogOut, Receipt, ShieldCheck, UtensilsCrossed, Eye, EyeOff,
} from 'lucide-react';
import ViewModeSwitch from './ViewModeSwitch';
import CategoryFilter from './CategoryFilter';
import ThemePicker from './ThemePicker';
import { useClickOutside } from '../hooks/useClickOutside';
import { useAuth } from '../auth/AuthContext';
import {
    ADMIN_PERMISSIONS,
    CASHIER_PERMISSIONS,
    KITCHEN_PERMISSIONS,
    WAITER_PERMISSIONS,
} from '../auth/permissions';

export { VIEW_MODES } from './ViewModeSwitch';

// Halaman kerja tetap (fungsi harian): bisa dikunjungi oleh semua peran yang
// relevan, jadi ditempatkan di dropdown halaman di sebelah icon profil.
export const MAIN_PAGES = [
    { path: '/kasir', label: 'Kasir', icon: Receipt, permissions: CASHIER_PERMISSIONS },
    { path: '/koki', label: 'Dapur', icon: ChefHat, permissions: KITCHEN_PERMISSIONS },
    { path: '/waiters', label: 'Pelayan', icon: UtensilsCrossed, permissions: WAITER_PERMISSIONS },
];

// Halaman khusus yang hanya bisa dikunjungi ketika user memang berhak (admin,
// super admin, dan sejenisnya). Muncul di dropdown profil, bukan di dropdown
// halaman, supaya menu harian tetap berisi fungsi tetap saja.
export const SPECIAL_PAGES = [
    { path: '/admin', label: 'Admin', icon: ShieldCheck, permissions: ADMIN_PERMISSIONS },
];

export const APP_PAGES = [...MAIN_PAGES, ...SPECIAL_PAGES];

function currentPage(pathname) {
    return APP_PAGES.find((item) => pathname.startsWith(item.path)) ?? APP_PAGES[0];
}

export default function TopHeader({
    navLabel = 'Menu',
    showCatalog = false,
    mode,
    onModeChange,
    viewModes,
    query,
    onQueryChange,
    searchPlaceholder = 'Cari produk...',
    categories = [],
    category = '__all__',
    onCategoryChange,
    filterAllLabel = 'Semua Kategori',
    filterPlaceholder = 'Cari kategori...',
    filterEmptyLabel = 'Kategori tidak ditemukan.',
    favoritesCount = 0,
    allOpen,
    onToggleAll,
    navItems = [],
    activeNav,
    onNavChange,
}) {
    const navigate = useNavigate();
    const { pathname } = useLocation();
    const { user, isAuthenticated, logout, canAny } = useAuth();
    const [navOpen, setNavOpen] = useState(false);
    const [profileOpen, setProfileOpen] = useState(false);
    const navRef = useClickOutside(() => setNavOpen(false));
    const profileRef = useClickOutside(() => setProfileOpen(false));
    const page = currentPage(pathname);
    const dropdownLabel = navItems.find((item) => item.key === activeNav)?.label ?? navLabel;

    // Semua pindah halaman/role dikumpulkan di satu tempat: dropdown profil.
    const pages = APP_PAGES.filter((item) => !item.permissions || canAny(...item.permissions));
    const displayName = user?.name ?? 'Tamu';
    const roleLabel = user
        ? user.roles?.map((role) => role.name).join(', ') || 'Tanpa role'
        : 'Belum masuk';
    const initials = (user?.name ?? '?').slice(0, 1);

    const goPage = (path) => {
        setProfileOpen(false);
        navigate(path);
    };

    return (
        <header className="sticky top-0 z-30 bg-surface border-b border-line">
            <div className="mx-auto max-w-[1600px] px-4 md:px-6 flex items-center gap-2 md:gap-3 h-16">
                {onToggleAll && (
                    <button
                        type="button"
                        onClick={onToggleAll}
                        title={allOpen ? 'Tutup semua kategori' : 'Buka semua kategori'}
                        className={`btn !px-2.5 hidden sm:inline-flex shrink-0 border border-line ${allOpen ? 'bg-accent-soft text-accent-ink' : 'bg-surface-2 text-muted hover:bg-surface-3'}`}
                    >
                        {allOpen ? <EyeOff size={16} /> : <Eye size={16} />}
                    </button>
                )}

                {onModeChange && (
                    <ViewModeSwitch value={mode} onChange={onModeChange} modes={viewModes} className="hidden sm:inline-flex shrink-0" />
                )}

                {showCatalog && onQueryChange && (
                    <div className="flex-1 min-w-0 max-w-2xl flex items-center gap-2 md:gap-3">
                        <div className="relative flex-1 min-w-0">
                            <Search size={15} className="text-faint absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                            <input
                                value={query}
                                onChange={(e) => onQueryChange(e.target.value)}
                                placeholder={searchPlaceholder}
                                className="input !pl-9"
                            />
                        </div>

                        {onCategoryChange && (
                            <CategoryFilter
                                categories={categories}
                                value={category}
                                onChange={onCategoryChange}
                                favoritesCount={favoritesCount}
                                allLabel={filterAllLabel}
                                searchPlaceholder={filterPlaceholder}
                                emptyLabel={filterEmptyLabel}
                            />
                        )}
                    </div>
                )}

                <div className="flex-1" />

                {navItems.length > 0 && (
                    <div className="relative shrink-0" ref={navRef}>
                        <button onClick={() => setNavOpen((v) => !v)} className="btn btn-ghost !px-3">
                            <span>{dropdownLabel}</span>
                            <ChevronDown size={14} className={`transition-transform ${navOpen ? 'rotate-180' : ''}`} />
                        </button>
                        {navOpen && (
                            <div className="absolute right-0 top-12 w-56 bg-surface rounded-lg py-1.5 shadow-xl z-40">
                                {navItems.map((item) => (
                                    <button
                                        key={item.key}
                                        onClick={() => {
                                            onNavChange(item.key);
                                            setNavOpen(false);
                                        }}
                                        className={`w-full flex items-center gap-2 px-3 py-2 text-sm text-left transition-colors cursor-pointer ${
                                            activeNav === item.key ? 'bg-accent-soft text-accent-ink font-semibold' : 'hover:bg-surface-2'
                                        }`}
                                    >
                                        {item.label}
                                        {item.count !== undefined && (
                                            <span className="ml-auto text-[11px] font-bold text-muted">{item.count}</span>
                                        )}
                                        {activeNav === item.key && item.count === undefined && (
                                            <CheckCircle2 size={14} className="ml-auto text-accent" />
                                        )}
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                <ThemePicker />

                <div className="relative shrink-0" ref={profileRef}>
                    <button
                        onClick={() => setProfileOpen((v) => !v)}
                        className="flex items-center gap-2 py-1.5 pl-1.5 pr-2 rounded-lg hover:bg-surface-2 transition-colors"
                        title={isAuthenticated ? user.email : 'Menu halaman & masuk'}
                    >
                        <span className="hidden md:block text-left leading-tight">
                            <span className="block text-sm font-semibold">{displayName}</span>
                            <span className="block text-[10px] text-muted truncate max-w-[140px]">{roleLabel}</span>
                        </span>
                        <span className="w-8 h-8 rounded-full bg-content text-surface text-xs font-bold flex items-center justify-center uppercase">
                            {initials}
                        </span>
                        <ChevronDown size={14} className={`text-muted transition-transform ${profileOpen ? 'rotate-180' : ''}`} />
                    </button>

                    {profileOpen && (
                        <div className="absolute right-0 top-12 w-60 bg-surface rounded-lg py-1.5 shadow-xl">
                            {isAuthenticated ? (
                                <div className="flex items-center gap-3 px-3 py-2 mb-1.5">
                                    <span className="w-9 h-9 rounded-full bg-accent-soft text-accent-ink text-xs font-bold flex items-center justify-center uppercase shrink-0">
                                        {initials}
                                    </span>
                                    <div className="min-w-0">
                                        <div className="text-sm font-bold truncate">{user.name}</div>
                                        <div className="text-[11px] text-muted truncate">{user.email}</div>
                                    </div>
                                </div>
                            ) : (
                                <div className="px-3 py-2 mb-1.5">
                                    <div className="text-sm font-bold">Belum masuk</div>
                                    <div className="text-[11px] text-muted">
                                        Halaman kasir, dapur, dan pelayan tetap bisa dipakai.
                                    </div>
                                </div>
                            )}

                            <div className="px-3 py-1 text-[11px] font-semibold text-muted uppercase tracking-wide">
                                Pindah Halaman
                            </div>
                            {pages.map((item) => (
                                <button
                                    key={item.path}
                                    onClick={() => goPage(item.path)}
                                    className={`w-full flex items-center gap-2 px-3 py-2 text-sm text-left transition-colors cursor-pointer ${
                                        page.path === item.path ? 'bg-accent-soft text-accent-ink font-semibold' : 'hover:bg-surface-2'
                                    }`}
                                >
                                    <item.icon size={15} />
                                    {item.label}
                                    {page.path === item.path && <CheckCircle2 size={14} className="ml-auto text-accent" />}
                                </button>
                            ))}

                            <div className="my-1.5 border-t border-line" />

                            {isAuthenticated ? (
                                <button
                                    onClick={() => {
                                        setProfileOpen(false);
                                        logout();
                                    }}
                                    className="w-full flex items-center gap-2 px-3 py-2 text-sm text-left transition-colors cursor-pointer hover:bg-surface-2"
                                >
                                    <LogOut size={15} />
                                    Keluar
                                </button>
                            ) : (
                                <button
                                    onClick={() => goPage('/login')}
                                    className="w-full flex items-center gap-2 px-3 py-2 text-sm text-left transition-colors cursor-pointer hover:bg-surface-2"
                                >
                                    <LogIn size={15} />
                                    Masuk
                                </button>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </header>
    );
}
