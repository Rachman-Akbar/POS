import { useEffect, useMemo, useRef, useState } from 'react';
import { ImageOff, Plus, Star, Package, ChevronLeft, ChevronRight, ChevronDown } from 'lucide-react';
import { formatIDR } from '../api/client';
import ProductDetailModal from './ProductDetailModal';
import ViewModeSwitch from './ViewModeSwitch';

function ProductImage({ product, className = '' }) {
    const [err, setErr] = useState(false);
    if (!product.image || err) {
        return (
            <div className={`bg-gray-100 flex items-center justify-center ${className}`}>
                <ImageOff size={20} className="text-gray-300" />
            </div>
        );
    }
    return <img src={product.image} alt={product.name} loading="lazy" onError={() => setErr(true)} className={className} />;
}

const CardItem = ({ product, onOpen, onAdd, showStock }) => {
    const out = product.stock <= 0;
    return (
        <div className={`relative bg-white border border-gray-100 rounded-xl p-3 ${out ? '' : 'cursor-pointer hover:border-orange-300'} transition-colors`} onClick={() => !out && onOpen(product)}>
            <div className="aspect-square w-full overflow-hidden rounded-lg bg-gray-50 mb-2 relative">
                <ProductImage product={product} className="w-full h-full object-cover" />
                <button
                    onClick={(e) => {
                        e.stopPropagation();
                        if (!out) onAdd(product);
                    }}
                    disabled={out}
                    title={out ? 'Stok habis' : 'Tambah ke transaksi'}
                    className="absolute right-2 bottom-2 w-8 h-8 rounded-full bg-white text-orange-600 border border-gray-200 hover:bg-orange-600 hover:text-white hover:border-orange-600 flex items-center justify-center transition-colors disabled:opacity-50"
                >
                    <Plus size={16} />
                </button>
            </div>
            <div className="font-semibold text-sm leading-snug line-clamp-1">{product.name}</div>
            <div className="mt-1 text-orange-600 font-bold text-sm">{formatIDR(product.price)}</div>
            {showStock && <div className={`text-[11px] mt-1 ${out ? 'text-red-500 font-semibold' : 'text-muted'}`}>Stok {product.stock}</div>}
            {product.is_favorite && <Star size={13} className="absolute top-5 right-5 text-amber-500 fill-amber-500" />}
        </div>
    );
};

const TableView = ({ items, onOpen, onAdd, showStock }) => (
    <div className="border border-gray-100 rounded-xl overflow-hidden">
        <table className="w-full">
            <thead className="bg-gray-50/70">
                <tr>
                    <th className="table-head">Produk</th>
                    <th className="table-head text-right">Harga</th>
                    {showStock && <th className="table-head text-center">Stok</th>}
                    <th className="table-head text-center w-16"></th>
                </tr>
            </thead>
            <tbody className="divide-y divide-gray-50">
                {items.map((product) => {
                    const out = product.stock <= 0;
                    return (
                        <tr key={product.id} className={`${out ? '' : 'cursor-pointer hover:bg-orange-50/40'}`} onClick={() => !out && onOpen(product)}>
                            <td className="table-cell">
                                <div className="flex items-center gap-3">
                                    <div className="w-10 h-10 rounded-lg overflow-hidden shrink-0 bg-gray-50">
                                        <ProductImage product={product} className="w-full h-full object-cover" />
                                    </div>
                                    <div className="min-w-0">
                                        <span className="font-semibold text-sm block truncate">{product.name}</span>
                                        <span className="text-[11px] text-muted">{product.category ?? 'Lainnya'}</span>
                                    </div>
                                </div>
                            </td>
                            <td className="table-cell text-right text-orange-600 font-semibold whitespace-nowrap">{formatIDR(product.price)}</td>
                            {showStock && <td className={`table-cell text-center ${out ? 'text-red-500 font-semibold' : ''}`}>{product.stock}</td>}
                            <td className="table-cell text-center">
                                <button
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        if (!out) onAdd(product);
                                    }}
                                    disabled={out}
                                    title={out ? 'Stok habis' : 'Tambah ke transaksi'}
                                    className="w-8 h-8 rounded-full inline-flex items-center justify-center text-orange-600 border border-gray-200 hover:bg-orange-600 hover:text-white hover:border-orange-600 transition-colors disabled:opacity-50"
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

const HeroView = ({ items, onOpen, onAdd, showStock }) => {
    const ref = useRef(null);
    const [active, setActive] = useState(0);

    useEffect(() => {
        const el = ref.current;
        if (!el) return;
        const onScroll = () => {
            const per = el.clientWidth || 1;
            setActive(Math.max(0, Math.min(items.length - 1, Math.round(el.scrollLeft / per))));
        };
        el.addEventListener('scroll', onScroll, { passive: true });
        return () => el.removeEventListener('scroll', onScroll);
    }, [items.length]);

    if (items.length === 0) return null;

    const scrollTo = (index) => {
        const el = ref.current;
        el?.scrollTo({ left: index * el.clientWidth, behavior: 'smooth' });
    };

    return (
        <div className="flex flex-col gap-2" style={{ height: 'calc(100dvh - 9.5rem)' }}>
            <div className="relative flex-1 min-h-0">
                <div ref={ref} className="absolute inset-0 flex overflow-x-auto snap-x snap-mandatory scrollbar-none rounded-xl">
                    {items.map((product) => {
                        const out = product.stock <= 0;
                        return (
                            <div key={product.id} className="min-w-full snap-start relative cursor-pointer" onClick={() => !out && onOpen(product)}>
                                <ProductImage product={product} className="w-full h-full object-cover" />
                                <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-transparent" />
                                <span className="absolute top-4 left-4 badge bg-white/90 text-gray-700">{product.category ?? 'Lainnya'} {product.is_favorite ? '· Favorit' : ''}</span>
                                <button
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        if (!out) onAdd(product);
                                    }}
                                    disabled={out}
                                    title={out ? 'Stok habis' : 'Tambah ke transaksi'}
                                    className="absolute top-4 right-4 w-10 h-10 rounded-full bg-white text-orange-600 border border-gray-200 hover:bg-orange-600 hover:text-white hover:border-orange-600 flex items-center justify-center transition-colors disabled:opacity-50"
                                >
                                    <Plus size={20} />
                                </button>
                                <div className="absolute left-4 right-4 bottom-4 text-white">
                                    <div className="text-lg font-bold leading-snug">{product.name}</div>
                                    <div className="flex items-center gap-2 mt-1">
                                        <span className="text-orange-400 font-bold">{formatIDR(product.price)}</span>
                                        {showStock && (
                                            <span className={`text-xs font-medium ${out ? 'text-red-300' : 'text-gray-300'}`}>Stok {product.stock}</span>
                                        )}
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>

                {items.length > 1 && (
                    <>
                        <button
                            onClick={() => scrollTo(Math.max(0, active - 1))}
                            disabled={active === 0}
                            className="absolute left-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-white/90 border border-gray-200 flex items-center justify-center hover:bg-white disabled:opacity-40"
                        >
                            <ChevronLeft size={18} />
                        </button>
                        <button
                            onClick={() => scrollTo(Math.min(items.length - 1, active + 1))}
                            disabled={active === items.length - 1}
                            className="absolute right-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-white/90 border border-gray-200 flex items-center justify-center hover:bg-white disabled:opacity-40"
                        >
                            <ChevronRight size={18} />
                        </button>
                        <div className="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1.5">
                            {items.map((_, i) => (
                                <button
                                    key={i}
                                    onClick={() => scrollTo(i)}
                                    className={`h-1.5 rounded-full transition-all ${i === active ? 'w-5 bg-white' : 'w-1.5 bg-white/60'}`}
                                    aria-label={`Ke produk ${i + 1}`}
                                />
                            ))}
                        </div>
                    </>
                )}
            </div>

            <div className="flex gap-2 overflow-x-auto shrink-0 px-0.5">
                {items.map((product, i) => (
                    <button
                        key={product.id}
                        onClick={() => scrollTo(i)}
                        className={`w-16 h-16 rounded-lg overflow-hidden shrink-0 border transition-colors ${i === active ? 'border-orange-500' : 'border-transparent opacity-70'}`}
                    >
                        <ProductImage product={product} className="w-full h-full object-cover" />
                    </button>
                ))}
            </div>
        </div>
    );
};

export default function ProductCatalog({ products = [], onAdd, mode, onModeChange, query, onQueryChange, showStock = true, showFavorites = true, hideToolbar = false }) {
    const [internalMode, setInternalMode] = useState('grid');
    const [internalQuery, setInternalQuery] = useState('');
    const [detail, setDetail] = useState(null);
    const [collapsed, setCollapsed] = useState({});

    const toggleSection = (key) => setCollapsed((prev) => ({ ...prev, [key]: !prev[key] }));

    const view = mode ?? internalMode;
    const q = query ?? internalQuery;
    const setView = onModeChange ?? setInternalMode;
    const setQ = onQueryChange ?? setInternalQuery;

    const { filtered, favorites, grouped, filteredCount } = useMemo(() => {
        const needle = q.trim().toLowerCase();
        const filtered = needle
            ? products.filter(
                  (p) => p.name.toLowerCase().includes(needle) || (p.category ?? 'Lainnya').toLowerCase().includes(needle),
              )
            : products;
        const favorites = filtered.filter((p) => p.is_favorite);
        const grouped = filtered.reduce((acc, p) => {
            (acc[p.category ?? 'Lainnya'] ??= []).push(p);
            return acc;
        }, {});
        return { filtered, favorites, grouped, filteredCount: filtered.length };
    }, [products, q]);

    if (view === 'hero') {
        return (
            <div className="space-y-4">
                {!hideToolbar && <Toolbar view={view} setView={setView} query={q} setQuery={setQ} count={filteredCount} />}
                <HeroView items={filtered} onOpen={setDetail} onAdd={onAdd} showStock={showStock} />
                {filteredCount === 0 && (
                    <div className="card text-muted text-center py-10">
                        <Package size={28} className="mx-auto mb-2 text-gray-300" /> Tidak ada produk yang cocok.
                    </div>
                )}
                {detail && <ProductDetailModal product={detail} onClose={() => setDetail(null)} onAdd={onAdd} />}
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {!hideToolbar && <Toolbar view={view} setView={setView} query={q} setQuery={setQ} count={filteredCount} />}

            {showFavorites && (
                <section>
                    <div className="border border-gray-100 rounded-xl overflow-hidden">
                        <SectionHeader title="Favorit" open={!collapsed.favorites} onToggle={() => toggleSection('favorites')} />
                        {!collapsed.favorites && (
                            <div className="p-3">
                                {favorites.length > 0 ? (
                                    <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-3">
                                        {favorites.map((product) => (
                                            <CardItem key={product.id} product={product} onOpen={setDetail} onAdd={onAdd} showStock={showStock} />
                                        ))}
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted py-6 text-center">Belum ada produk favorit. Kelola melalui menu Admin.</p>
                                )}
                            </div>
                        )}
                    </div>
                </section>
            )}

            {filteredCount === 0 && (
                <div className="card text-muted text-center py-10">
                    <Package size={28} className="mx-auto mb-2 text-gray-300" /> Tidak ada produk yang cocok dengan pencarian.
                </div>
            )}

            {Object.entries(grouped).map(([category, items]) => (
                <div key={category} className="border border-gray-100 rounded-xl overflow-hidden">
                    <SectionHeader title={category} open={!collapsed[`cat:${category}`]} onToggle={() => toggleSection(`cat:${category}`)} />
                    {!collapsed[`cat:${category}`] && (
                        <div className="p-3">
                            {view === 'grid' ? (
                                <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-3">
                                    {items.map((product) => (
                                        <CardItem key={product.id} product={product} onOpen={setDetail} onAdd={onAdd} showStock={showStock} />
                                    ))}
                                </div>
                            ) : (
                                <TableView items={items} onOpen={setDetail} onAdd={onAdd} showStock={showStock} />
                            )}
                        </div>
                    )}
                </div>
            ))}

            {detail && <ProductDetailModal product={detail} onClose={() => setDetail(null)} onAdd={onAdd} />}
        </div>
    );
}

function SectionHeader({ title, open, onToggle }) {
    return (
        <button
            type="button"
            onClick={onToggle}
            className="w-full flex items-center gap-2 px-4 py-3 border-b border-gray-50 text-left cursor-pointer hover:bg-gray-50/60 transition-colors"
        >
            <ChevronDown size={15} className={`text-muted shrink-0 transition-transform ${open ? '' : '-rotate-90'}`} />
            <span className="font-bold text-sm">{title}</span>
        </button>
    );
}

function Toolbar({ view, setView, query, setQuery, count }) {
    return (
        <div className="flex items-center gap-2">
            <div className="relative flex-1">
                <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Cari produk atau kategori..." className="input !pl-3" />
            </div>
            <ViewModeSwitch value={view} onChange={setView} />
            <span className="hidden sm:inline text-xs text-muted">{count}</span>
        </div>
    );
}