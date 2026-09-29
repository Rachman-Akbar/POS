import { useEffect, useMemo, useState } from 'react';
import { ImageOff, Plus, Package, ChevronLeft, ChevronRight, ChevronDown, Eye, EyeOff } from 'lucide-react';
import { formatNumber } from '../api/client';
import Price from './Price';
import ProductDetailModal from './ProductDetailModal';
import ViewModeSwitch from './ViewModeSwitch';
import CategoryFilter from './CategoryFilter';

export const ALL_CATEGORIES = '__all__';
export const FAVORITES_CATEGORY = '__favorites__';

export const catalogSectionKeys = ({ products = [], query = '', category = ALL_CATEGORIES, showFavorites = true }) => {
    const needle = query.trim().toLowerCase();
    const keys = showFavorites && category !== FAVORITES_CATEGORY ? ['favorites'] : [];
    const seen = new Set();

    products.forEach((product) => {
        const name = product.category ?? 'Lainnya';
        if (category !== ALL_CATEGORIES && category !== FAVORITES_CATEGORY && name !== category) return;
        if (needle && !product.name.toLowerCase().includes(needle) && !name.toLowerCase().includes(needle)) return;
        if (seen.has(name)) return;
        seen.add(name);
        keys.push(`cat:${name}`);
    });

    return keys;
};

function ProductImage({ product, className = '' }) {
    const [err, setErr] = useState(false);
    if (!product.image || err) {
        return (
            <div className={`bg-surface-3 flex items-center justify-center ${className}`}>
                <ImageOff size={20} className="text-faint" />
            </div>
        );
    }
    return <img src={product.image} alt={product.name} loading="lazy" onError={() => setErr(true)} className={className} />;
}

export const CardItem = ({ product, onOpen, onAdd, onSelect, showStock, compact = false, active = false }) => {
    const out = product.stock <= 0;
    const open = () => (onSelect ? onSelect(product) : !out && onOpen(product));

    return (
        <div
            role="button"
            tabIndex={0}
            onClick={open}
            onKeyDown={(e) => e.key === 'Enter' && open()}
            className={`relative flex flex-col rounded-xl overflow-hidden bg-surface-2 text-left transition-colors ${
                out ? '' : 'cursor-pointer hover:bg-surface-3'
            } ${compact ? 'w-full h-full' : ''} ${active ? 'ring-2 ring-accent' : ''}`}
        >
            <div className="relative w-full aspect-square overflow-hidden bg-surface-3">
                <ProductImage product={product} className="absolute inset-0 w-full h-full object-cover" />
            </div>

            <div className={`flex-1 flex flex-col gap-2 ${compact ? 'p-2' : 'p-3'}`}>
                <div className={`font-semibold leading-snug line-clamp-2 ${compact ? 'text-[11px]' : 'text-sm'}`}>{product.name}</div>

                <div className="mt-auto flex items-end justify-between gap-2">
                    <button
                        onClick={(e) => {
                            e.stopPropagation();
                            if (!out) onAdd(product);
                        }}
                        disabled={out}
                        title={out ? 'Stok habis' : 'Tambah ke transaksi'}
                        className={`shrink-0 rounded-full bg-accent text-on-accent flex items-center justify-center transition-colors disabled:opacity-40 ${
                            compact ? 'w-6 h-6' : 'w-8 h-8'
                        }`}
                    >
                        <Plus size={compact ? 13 : 16} />
                    </button>

                    <div className="text-right min-w-0">
                        <Price value={product.price} className={`text-accent font-bold ${compact ? 'text-[11px]' : 'text-sm'}`} />
                        {showStock && (
                            <div
                                className={`tabular-nums whitespace-nowrap ${compact ? 'text-[10px]' : 'text-[11px]'} ${
                                    out ? 'text-negative font-semibold' : 'text-muted'
                                }`}
                            >
                                Stok: {product.stock}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
};

const fitText = (text = '') => {
    if (text.length > 34) return 'text-[11px] leading-tight';
    if (text.length > 18) return 'text-xs leading-snug';
    return 'text-sm leading-snug';
};

const TableView = ({ items, title, open, onToggle, onOpen, onAdd, showStock }) => (
    <div className="rounded-xl overflow-hidden bg-surface">
        <table className="w-full table-fixed">
            <thead>
                <tr>
                    {open ? (
                        <>
                            <th className="table-head w-[38%] !p-0">
                                <button
                                    type="button"
                                    onClick={onToggle}
                                    className="w-full flex items-center gap-2 px-4 py-3 text-left cursor-pointer hover:bg-surface-2 transition-colors"
                                >
                                    <ChevronDown size={15} className="text-faint shrink-0" />
                                    <span className="font-bold text-sm text-content">{title}</span>
                                </button>
                            </th>
                            <th className="table-head w-[24%]"></th>
                            <th className="table-head w-[6%]"></th>
                            <th className="table-head w-[14%] text-right">Harga</th>
                            {showStock && <th className="table-head w-[10%] text-right">Stok</th>}
                            <th className={`table-head ${showStock ? 'w-[8%]' : 'w-[18%]'}`}></th>
                        </>
                    ) : (
                        <th colSpan={showStock ? 6 : 5} className="table-head !p-0">
                            <button
                                type="button"
                                onClick={onToggle}
                                title="Buka daftar produk"
                                className="w-full flex items-center gap-2 px-4 py-3 text-left cursor-pointer hover:bg-surface-2 transition-colors"
                            >
                                <ChevronDown size={15} className="text-faint shrink-0 -rotate-90" />
                                <span className="font-bold text-sm text-content">{title}</span>
                            </button>
                        </th>
                    )}
                </tr>
            </thead>
            <tbody className="divide-y divide-line">
                {open &&
                    items.map((product) => {
                        const out = product.stock <= 0;
                        return (
                            <tr
                                key={product.id}
                                className={`align-top h-16 ${out ? '' : 'cursor-pointer hover:bg-accent-soft/40'}`}
                                onClick={() => !out && onOpen(product)}
                            >
                                <td className="p-0 align-middle">
                                    <div className="flex items-stretch h-16">
                                        <div className="relative w-14 sm:w-16 shrink-0 self-stretch overflow-hidden bg-surface-3">
                                            <ProductImage product={product} className="absolute inset-0 w-full h-full object-cover" />
                                        </div>
                                        <div className="flex-1 min-w-0 self-center px-4 py-2">
                                            <span className={`block break-words font-semibold ${fitText(product.name)}`}>{product.name}</span>
                                        </div>
                                    </div>
                                </td>
                                <td className="table-cell align-middle">
                                    <div className={`break-words text-muted ${fitText(product.description)}`}>{product.description || '-'}</div>
                                </td>
                                <td className="table-cell text-right align-middle text-accent font-semibold pr-0">Rp.</td>
                                <td className="table-cell text-right align-middle text-accent font-semibold tabular-nums pl-1">
                                    {formatNumber(product.price)}
                                </td>
                                {showStock && (
                                    <td
                                        className={`table-cell text-right align-middle tabular-nums ${out ? 'text-negative font-semibold' : ''}`}
                                    >
                                        {product.stock}
                                    </td>
                                )}
                                <td className="table-cell text-right align-middle">
                                    <button
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            if (!out) onAdd(product);
                                        }}
                                        disabled={out}
                                        title={out ? 'Stok habis' : 'Tambah ke transaksi'}
                                        className="w-8 h-8 rounded-full inline-flex items-center justify-center bg-accent text-on-accent hover:bg-accent-hover transition-colors disabled:opacity-40"
                                    >
                                        <Plus size={16} />
                                    </button>
                                </td>
                            </tr>
                        );
                    })}
            </tbody>
        </table>
    </div>
);

const STRIP_PAGE = 6;

const HeroView = ({ items, onOpen, onAdd, showStock, height = 'calc(100dvh - 9.5rem)' }) => {
    const [active, setActive] = useState(0);
    const [page, setPage] = useState(0);

    const pages = Math.max(1, Math.ceil(items.length / STRIP_PAGE));
    const pageItems = items.slice(page * STRIP_PAGE, page * STRIP_PAGE + STRIP_PAGE);

    useEffect(() => {
        setActive(0);
        setPage(0);
    }, [items.length]);

    useEffect(() => {
        setPage(Math.floor(active / STRIP_PAGE));
    }, [active]);

    if (items.length === 0) return null;

    const product = items[active];
    const out = product.stock <= 0;
    const go = (delta) => setActive((a) => Math.min(items.length - 1, Math.max(0, a + delta)));

    const goPage = (delta) => {
        const next = (page + delta + pages) % pages;
        const first = next * STRIP_PAGE;
        if (active < first || active >= first + STRIP_PAGE) setActive(Math.min(first, items.length - 1));
        setPage(next);
    };
    const arrowClass =
        'shrink-0 self-center w-10 h-10 rounded-full bg-surface-2 text-content flex items-center justify-center hover:bg-surface-3 disabled:opacity-30 disabled:cursor-not-allowed transition-colors';

    return (
        <div className="flex flex-col gap-3" style={{ height }}>
            <div className="flex items-stretch gap-2 flex-1 min-h-0">
                <button onClick={() => go(-1)} disabled={active === 0} title="Produk sebelumnya" className={arrowClass}>
                    <ChevronLeft size={20} />
                </button>

                <div
                    className="relative flex-1 min-h-0 rounded-xl overflow-hidden bg-gray-900 cursor-pointer"
                    onClick={() => !out && onOpen(product)}
                >
                    <ProductImage key={product.id} product={product} className="absolute inset-0 w-full h-full object-cover" />
                    <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-transparent" />

                    <span className="absolute top-4 left-4 badge bg-white/90 text-gray-700">{product.category ?? 'Lainnya'} {product.is_favorite ? '· Favorit' : ''}</span>
                    <button
                        onClick={(e) => {
                            e.stopPropagation();
                            if (!out) onAdd(product);
                        }}
                        disabled={out}
                        title={out ? 'Stok habis' : 'Tambah ke transaksi'}
                        className="absolute top-4 right-4 w-10 h-10 rounded-full bg-white text-accent hover:bg-accent hover:text-on-accent flex items-center justify-center transition-colors disabled:opacity-50"
                    >
                        <Plus size={20} />
                    </button>

                    <div className="absolute left-4 right-4 bottom-4 text-white">
                        <div className="text-lg font-bold leading-snug">{product.name}</div>
                        <div className="flex items-center gap-2 mt-1">
                            <Price value={product.price} className="text-accent font-bold" />
                            {showStock && (
                                <span className={`text-xs font-medium tabular-nums ${out ? 'text-red-300' : 'text-gray-300'}`}>Stok {product.stock}</span>
                            )}
                        </div>
                    </div>
                </div>

                <button
                    onClick={() => go(1)}
                    disabled={active === items.length - 1}
                    title="Produk berikutnya"
                    className={arrowClass}
                >
                    <ChevronRight size={20} />
                </button>
            </div>

            <div className="shrink-0 flex items-center gap-2">
                <button
                    onClick={() => goPage(-1)}
                    title="6 card sebelumnya"
                    disabled={pages < 2}
                    className={arrowClass}
                >
                    <ChevronLeft size={16} />
                </button>

                <div className="flex-1 min-w-0 flex justify-center gap-2">
                    {pageItems.map((item, index) => (
                        <div
                            key={item.id}
                            data-thumb={item.id}
                            className="shrink-0"
                            style={{ width: `calc((100% - ${(STRIP_PAGE - 1) * 0.5}rem) / ${STRIP_PAGE})` }}
                        >
                            <CardItem
                                product={item}
                                onOpen={onOpen}
                                onAdd={onAdd}
                                onSelect={(selected) => setActive(items.findIndex((p) => p.id === selected.id))}
                                showStock={showStock}
                                compact
                                active={page * STRIP_PAGE + index === active}
                            />
                        </div>
                    ))}
                </div>

                <button
                    onClick={() => goPage(1)}
                    title="6 card berikutnya"
                    disabled={pages < 2}
                    className={arrowClass}
                >
                    <ChevronRight size={16} />
                </button>
            </div>

            {pages > 1 && (
                <p className="shrink-0 -mt-2 text-center text-[11px] text-muted tabular-nums">
                    {page + 1} / {pages}
                </p>
            )}
        </div>
    );
};

export default function ProductCatalog({
    products = [],
    onAdd,
    mode,
    onModeChange,
    query,
    onQueryChange,
    categories = [],
    category = ALL_CATEGORIES,
    onCategoryChange,
    showStock = true,
    showFavorites = true,
    hideToolbar = false,
    collapsed: collapsedProp,
    onCollapsedChange,
}) {
    const [internalMode, setInternalMode] = useState('grid');
    const [internalQuery, setInternalQuery] = useState('');
    const [detail, setDetail] = useState(null);
    const [internalCollapsed, setInternalCollapsed] = useState({});

    const collapsed = collapsedProp ?? internalCollapsed;
    const setCollapsed = onCollapsedChange ?? setInternalCollapsed;

    const toggleSection = (key) => setCollapsed((prev) => ({ ...prev, [key]: !prev[key] }));

    const view = mode ?? internalMode;
    const q = query ?? internalQuery;
    const setView = onModeChange ?? setInternalMode;
    const setQ = onQueryChange ?? setInternalQuery;

    const { filtered, favorites, grouped, filteredCount } = useMemo(() => {
        const needle = q.trim().toLowerCase();
        const matches = products.filter((p) => {
            const name = p.category ?? 'Lainnya';
            const inCategory =
                category === ALL_CATEGORIES ? true : category === FAVORITES_CATEGORY ? !!p.is_favorite : name === category;
            if (!inCategory) return false;
            if (!needle) return true;
            return p.name.toLowerCase().includes(needle) || name.toLowerCase().includes(needle);
        });
        const favorites = matches.filter((p) => p.is_favorite);
        const grouped = matches.reduce((acc, p) => {
            (acc[p.category ?? 'Lainnya'] ??= []).push(p);
            return acc;
        }, {});
        return { filtered: matches, favorites, grouped, filteredCount: matches.length };
    }, [products, q, category]);

    const searching = q.trim().length > 0;

    const isOpen = (key) => searching || !collapsed[key];

    const sectionKeys = useMemo(
        () => catalogSectionKeys({ products, query: q, category, showFavorites }),
        [products, q, category, showFavorites]
    );

    const allOpen = sectionKeys.length > 0 && sectionKeys.every((key) => isOpen(key));

    const toggleAllSections = () => setCollapsed(Object.fromEntries(sectionKeys.map((key) => [key, allOpen])));

    /**
     * Category groups in the order configured by the admin, unknown names last.
     */
    const groupNames = useMemo(() => {
        const position = (name) => {
            const index = categories.findIndex((c) => c.name === name);
            return index === -1 ? Number.MAX_SAFE_INTEGER : index;
        };

        return Object.keys(grouped)
            .sort((a, b) => position(a) - position(b) || a.localeCompare(b, 'id-ID'))
            .filter((name) => category === ALL_CATEGORIES || category === FAVORITES_CATEGORY || name === category);
    }, [grouped, categories, category]);

    /**
     * Render a product list with the layout that matches the active view mode.
     *
     * @param {object[]} items
     * @param {{title?: string, open?: boolean, onToggle?: () => void, heroHeight?: string}} section
     */
    const renderList = (items, section = {}) => {
        if (view === 'hero') {
            return <HeroView items={items} onOpen={setDetail} onAdd={onAdd} showStock={showStock} height={section.heroHeight} />;
        }
        if (view === 'table') {
            return (
                <TableView
                    items={items}
                    title={section.title}
                    open={section.open}
                    onToggle={section.onToggle}
                    onOpen={setDetail}
                    onAdd={onAdd}
                    showStock={showStock}
                />
            );
        }
        return (
            <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-3">
                {items.map((product) => (
                    <CardItem key={product.id} product={product} onOpen={setDetail} onAdd={onAdd} showStock={showStock} />
                ))}
            </div>
        );
    };

    const toolbar = !hideToolbar && (
        <Toolbar
            view={view}
            setView={setView}
            query={q}
            setQuery={setQ}
            count={filteredCount}
            categories={categories}
            category={category}
            onCategoryChange={onCategoryChange}
            favoritesCount={products.filter((p) => p.is_favorite).length}
            allOpen={allOpen}
            onToggleAll={toggleAllSections}
        />
    );

    const emptyState = (
        <div className="card text-muted text-center py-10">
            <Package size={28} className="mx-auto mb-2 text-faint" /> Tidak ada produk yang cocok.
        </div>
    );

    /**
     * In table mode the collapsible category title lives inside the table head,
     * so the table must stay mounted to keep the toggle reachable.
     */
    const renderSection = (items, section) => (view === 'table' || section.open ? renderList(items, section) : null);

    const favoritesSection =
        showFavorites &&
        view !== 'hero' &&
        category !== FAVORITES_CATEGORY && (
            <section>
                <div>
                    {view !== 'table' && (
                        <SectionHeader title="Favorit" open={isOpen('favorites')} onToggle={() => toggleSection('favorites')} />
                    )}
                    {favorites.length > 0 ? (
                        renderSection(favorites, {
                            title: 'Favorit',
                            open: isOpen('favorites'),
                            onToggle: () => toggleSection('favorites'),
                        })
                    ) : (
                        isOpen('favorites') && (
                            <p className="text-sm text-muted py-6 text-center">Belum ada produk favorit. Kelola melalui menu Admin.</p>
                        )
                    )}
                </div>
            </section>
        );

    if (view === 'hero') {
        const heroHeight = hideToolbar ? 'calc(100dvh - 6.5rem)' : 'calc(100dvh - 9.5rem)';

        return (
            <div>
                {toolbar}
                {filteredCount === 0 ? emptyState : renderList(filtered, { heroHeight })}
                {detail && <ProductDetailModal product={detail} onClose={() => setDetail(null)} onAdd={onAdd} />}
            </div>
        );
    }

    return (
        <div>
            {toolbar}
            {favoritesSection}

            {filteredCount === 0 && (
                <div className="card text-muted text-center py-10">
                    <Package size={28} className="mx-auto mb-2 text-faint" /> Tidak ada produk yang cocok dengan filter.
                </div>
            )}

            {groupNames.map((categoryName) => {
                const sectionKey = `cat:${categoryName}`;
                const open = isOpen(sectionKey);

                return (
                    <div key={categoryName}>
                        {view !== 'table' && <SectionHeader title={categoryName} open={open} onToggle={() => toggleSection(sectionKey)} />}
                        {renderSection(grouped[categoryName], {
                            title: categoryName,
                            open,
                            onToggle: () => toggleSection(sectionKey),
                        })}
                    </div>
                );
            })}

            {detail && <ProductDetailModal product={detail} onClose={() => setDetail(null)} onAdd={onAdd} />}
        </div>
    );
}

function SectionHeader({ title, open, onToggle }) {
    return (
        <button
            type="button"
            onClick={onToggle}
            className="w-full flex items-center gap-2 px-4 py-3 text-left cursor-pointer hover:bg-surface-2/60 transition-colors"
        >
            <ChevronDown size={15} className={`text-muted shrink-0 transition-transform ${open ? '' : '-rotate-90'}`} />
            <span className="font-bold text-sm">{title}</span>
        </button>
    );
}

function Toolbar({
    view, setView, query, setQuery, count, categories = [], category, onCategoryChange, favoritesCount, allOpen, onToggleAll,
}) {
    return (
        <div className="flex items-center gap-2">
            <div className="relative flex-1 min-w-0">
                <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Cari produk atau kategori..." className="input !pl-3" />
            </div>
            {onCategoryChange && (
                <CategoryFilter
                    categories={categories}
                    value={category}
                    onChange={onCategoryChange}
                    favoritesCount={favoritesCount}
                />
            )}
            <button
                type="button"
                onClick={onToggleAll}
                title={allOpen ? 'Tutup semua kategori' : 'Buka semua kategori'}
                className={`btn !px-2.5 shrink-0 border border-line ${allOpen ? 'bg-accent-soft text-accent-ink' : 'bg-surface-2 text-muted hover:bg-surface-3'}`}
            >
                {allOpen ? <EyeOff size={16} /> : <Eye size={16} />}
            </button>
            <ViewModeSwitch value={view} onChange={setView} />
            <span className="hidden sm:inline text-xs text-muted">{count}</span>
        </div>
    );
}
