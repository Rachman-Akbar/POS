import { useEffect, useMemo, useState } from 'react';
import { Check, Search, Star, X } from 'lucide-react';
import { useClickOutside } from '../hooks/useClickOutside';

const ALL = '__all__';
const FAVORITES = '__favorites__';

export default function CategoryFilter({
    categories = [],
    value = ALL,
    onChange,
    allLabel = 'Semua Kategori',
    favoritesLabel = 'Favorit',
    favoritesCount = 0,
    searchPlaceholder = 'Cari kategori...',
    emptyLabel = 'Kategori tidak ditemukan.',
}) {
    const [open, setOpen] = useState(false);
    const [term, setTerm] = useState('');
    const ref = useClickOutside(() => setOpen(false));

    const active = categories.find((c) => c.name === value) ?? null;
    const favoritesActive = value === FAVORITES;

    // `name` adalah nilai yang dikembalikan ke onChange, sedangkan `label`
    // opsional untuk ditampilkan. Tanpa `label`, `name` dipakai langsung.
    const labelOf = (name) => {
        if (name === ALL) return allLabel;
        if (name === FAVORITES) return favoritesLabel;
        const found = categories.find((c) => c.name === name);
        return found?.label ?? found?.name ?? name;
    };

    const options = useMemo(() => {
        const needle = term.trim().toLowerCase();
        if (!needle) return categories;
        return categories.filter((c) => (c.label ?? c.name).toLowerCase().includes(needle));
    }, [categories, term]);

    const showFavorites = favoritesCount > 0 && !term.trim();
    const filtering = term.trim().length > 0 || active !== null || favoritesActive;

    useEffect(() => {
        setTerm(value === ALL ? '' : labelOf(value));
    }, [value, categories]);

    const pick = (name) => {
        onChange?.(name);
        setTerm(name === ALL ? '' : labelOf(name));
        setOpen(false);
    };

    const optionClass = (isActive) =>
        `w-full flex items-center gap-2 px-3 py-2 text-sm text-left cursor-pointer transition-colors ${
            isActive ? 'bg-accent-soft text-accent-ink font-semibold' : 'hover:bg-surface-2'
        }`;

    return (
        <div className="relative flex-1 min-w-0" ref={ref}>
            <Search size={15} className="text-faint absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none z-10" />
            <input
                value={term}
                onFocus={() => setOpen(true)}
                onChange={(e) => {
                    setTerm(e.target.value);
                    setOpen(true);
                    if (!e.target.value && value !== ALL) onChange?.(ALL);
                }}
                onKeyDown={(e) => {
                    if (e.key === 'Escape') setOpen(false);
                    if (e.key === 'Enter' && options.length) {
                        e.preventDefault();
                        pick(options[0].name);
                    }
                }}
                placeholder={searchPlaceholder}
                title={searchPlaceholder}
                className="input !pl-9 !pr-8"
            />
            {filtering && (
                <button
                    type="button"
                    onClick={() => pick(ALL)}
                    title={`Hapus filter: ${allLabel}`}
                    className="absolute right-2.5 top-1/2 -translate-y-1/2 text-faint hover:text-content cursor-pointer"
                >
                    <X size={14} />
                </button>
            )}

            {open && (
                <div className="absolute right-0 top-12 w-full min-w-56 bg-surface rounded-xl shadow-xl z-40 overflow-hidden">
                    <div className="max-h-72 overflow-y-auto scrollbar-thin py-1">
                        <button type="button" onClick={() => pick(ALL)} className={optionClass(!active && !favoritesActive)}>
                            {allLabel}
                            {(!active && !favoritesActive) && <Check size={14} className="ml-auto" />}
                        </button>

                        {showFavorites && (
                            <button type="button" onClick={() => pick(FAVORITES)} className={optionClass(favoritesActive)}>
                                <Star size={14} className="text-amber-500 fill-amber-500 shrink-0" />
                                <span>{favoritesLabel}</span>
                                <span className="ml-auto text-[11px] text-muted">{favoritesCount}</span>
                                {favoritesActive && <Check size={14} />}
                            </button>
                        )}

                        {options.map((category) => (
                            <button
                                key={category.name}
                                type="button"
                                onClick={() => pick(category.name)}
                                className={optionClass(active?.name === category.name)}
                            >
                                <span className="truncate">{category.label ?? category.name}</span>
                                <span className="ml-auto text-[11px] text-muted">{category.count}</span>
                                {active?.name === category.name && <Check size={14} />}
                            </button>
                        ))}

                        {options.length === 0 && !showFavorites && (
                            <p className="text-xs text-muted text-center py-4">{emptyLabel}</p>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
