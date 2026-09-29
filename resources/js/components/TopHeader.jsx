import { useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import {
    Search, CheckCircle2, ChevronDown, ChefHat, Receipt, ShieldCheck, UtensilsCrossed, Eye, EyeOff,
} from 'lucide-react';
import ViewModeSwitch from './ViewModeSwitch';
import CategoryFilter from './CategoryFilter';
import ThemePicker from './ThemePicker';
import { useClickOutside } from '../hooks/useClickOutside';

export { VIEW_MODES } from './ViewModeSwitch';

export const ROLES = [
    { path: '/kasir', label: 'Kasir', icon: Receipt, user: { name: 'Fajar', jabatan: 'Kasir' } },
    { path: '/admin', label: 'Admin', icon: ShieldCheck, user: { name: 'Raka', jabatan: 'Administrator' } },
    { path: '/koki', label: 'Koki', icon: ChefHat, user: { name: 'Dimas', jabatan: 'Koki Dapur' } },
    { path: '/waiters', label: 'Waiters', icon: UtensilsCrossed, user: { name: 'Sari', jabatan: 'Pelayan' } },
];

function currentRole(pathname) {
    return ROLES.find((r) => pathname.startsWith(r.path)) ?? ROLES[0];
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
    const [navOpen, setNavOpen] = useState(false);
    const [profileOpen, setProfileOpen] = useState(false);
    const navRef = useClickOutside(() => setNavOpen(false));
    const profileRef = useClickOutside(() => setProfileOpen(false));
    const role = currentRole(pathname);
    const dropdownLabel = navItems.find((item) => item.key === activeNav)?.label ?? navLabel;

    const goRole = (path) => {
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
                        title="Akun & ganti peran (simulasi)"
                    >
                        <span className="hidden md:block text-left leading-tight">
                            <span className="block text-sm font-semibold">{role.user.name}</span>
                            <span className="block text-[10px] text-muted">{role.user.jabatan}</span>
                        </span>
                        <span className="w-8 h-8 rounded-full bg-content text-surface text-xs font-bold flex items-center justify-center uppercase">
                            {role.user.name[0]}
                        </span>
                        <ChevronDown size={14} className={`text-muted transition-transform ${profileOpen ? 'rotate-180' : ''}`} />
                    </button>

                    {profileOpen && (
                        <div className="absolute right-0 top-12 w-56 bg-surface rounded-lg py-1.5 shadow-xl">
                            <div className="flex items-center gap-3 px-3 py-2 mb-1.5">
                                <span className="w-9 h-9 rounded-full bg-accent-soft text-accent-ink text-xs font-bold flex items-center justify-center uppercase shrink-0">
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
                                        role.path === r.path ? 'bg-accent-soft text-accent-ink font-semibold' : 'hover:bg-surface-2'
                                    }`}
                                >
                                    <r.icon size={15} />
                                    {r.label}
                                    {role.path === r.path && <CheckCircle2 size={14} className="ml-auto text-accent" />}
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </header>
    );
}
