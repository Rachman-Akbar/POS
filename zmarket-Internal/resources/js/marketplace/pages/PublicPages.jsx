import { useEffect, useMemo, useRef, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
    ArrowLeft,
    CheckCircle2,
    History,
    Menu,
    Minus,
    Pencil,
    Plus,
    Search,
    ShoppingCart,
    Trash2,
    X,
} from "lucide-react";
import { Link, Outlet, useLocation, useNavigate, useParams, useSearchParams } from "react-router-dom";
import company from "@/data/companyProfile.json";
import { Empty, fieldClass, Loading, productPrice, QueryError, StatusBadge } from "@/components/Common";
import { api, collectionData, errorMessage, resourceData } from "@/lib/api";
import { currency, dateTime } from "@/lib/format";
import { cartSummary, useCartStore } from "@/stores/cartStore";

function normalizeImageValue(value) {
    if (typeof value === "string") {
        return value.trim();
    }
    if (value && typeof value === "object") {
        return String(value.url ?? value.path ?? "").trim();
    }
    return "";
}

function productImageUrls(product) {
    const values = [
        product?.thumbnail,
        ...(Array.isArray(product?.image_urls) ? product.image_urls : []),
        ...(Array.isArray(product?.images) ? product.images : []),
    ];
    return [...new Set(values.map(normalizeImageValue).filter(Boolean))];
}

function SafeImage({ src, alt, className, fallbackClassName = "" }) {
    const [failed, setFailed] = useState(false);
    useEffect(() => { setFailed(false); }, [src]);
    if (!src || failed) {
        return (
            <div className={`grid place-items-center bg-gradient-to-br from-green-50 to-green-100 ${fallbackClassName}`}>
                <svg className="h-12 w-12 text-green-300" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                </svg>
            </div>
        );
    }
    return <img src={src} alt={alt} className={className} onError={() => setFailed(true)} loading="lazy" />;
}

export function PublicHeader() {
    const [mobileOpen, setMobileOpen] = useState(false);
    const [categoryOpen, setCategoryOpen] = useState(false);
    const [categoryPanelStyle, setCategoryPanelStyle] = useState(null);
    const categoryAreaRef = useRef(null);
    const items = useCartStore((state) => state.items);
    const navigate = useNavigate();
    const [params] = useSearchParams();
    const paramsKey = params.toString();
    const [search, setSearch] = useState(params.get("search") ?? "");
    const summary = cartSummary(items);
    const categories = useQuery({
        queryKey: ["categories"],
        queryFn: async () => collectionData(await api.get("/categories")),
        staleTime: 5 * 60 * 1000,
    });
    const sortedCategories = useMemo(
        () => [...(categories.data ?? [])].sort((a, b) => Number(a.sort_order) - Number(b.sort_order) || a.name.localeCompare(b.name)),
        [categories.data],
    );
    const selectedCategory = sortedCategories.find((category) => category.slug === params.get("category"));
    const categoryLabel = selectedCategory ? selectedCategory.name : "Semua Kategori";

    useEffect(() => { setSearch(params.get("search") ?? ""); }, [paramsKey]);

    useEffect(() => {
        const close = (event) => { if (!categoryAreaRef.current?.contains(event.target)) setCategoryOpen(false); };
        document.addEventListener("mousedown", close);
        return () => document.removeEventListener("mousedown", close);
    }, []);

    useEffect(() => {
        if (!categoryOpen) { setCategoryPanelStyle(null); return undefined; }
        const updatePosition = () => {
            const area = categoryAreaRef.current;
            if (!area) return;
            const rect = area.getBoundingClientRect();
            const vpPad = 16;
            const gap = 8;
            const rightSpace = window.innerWidth - rect.right - gap - vpPad;
            const placeOnRight = rightSpace >= 320;
            const width = placeOnRight ? Math.min(500, rightSpace) : Math.min(500, window.innerWidth - vpPad * 2);
            const left = placeOnRight ? rect.right + gap : Math.min(Math.max(vpPad, rect.left), window.innerWidth - width - vpPad);
            const top = placeOnRight ? Math.max(vpPad, rect.top) : rect.bottom + 6;
            const maxHeight = Math.max(160, window.innerHeight - top - vpPad);
            setCategoryPanelStyle({ left, top, width, maxHeight, showArrow: placeOnRight, arrowLeft: rect.right + 4, arrowTop: rect.top + 16 });
        };
        updatePosition();
        window.addEventListener("resize", updatePosition);
        window.addEventListener("scroll", updatePosition, true);
        return () => { window.removeEventListener("resize", updatePosition); window.removeEventListener("scroll", updatePosition, true); };
    }, [categoryOpen]);

    const submit = (event) => {
        event.preventDefault();
        const next = new URLSearchParams(params);
        if (search.trim()) next.set("search", search.trim()); else next.delete("search");
        navigate(`/products${next.toString() ? `?${next.toString()}` : ""}`);
        setMobileOpen(false);
    };

    const categoryUrl = (category) => {
        const next = new URLSearchParams(params);
        if (next.get("category") === category.slug) next.delete("category"); else next.set("category", category.slug);
        return `/products${next.toString() ? `?${next.toString()}` : ""}`;
    };

    return (
        <header className="sticky top-0 z-50 border-b border-gray-100 bg-white/80 backdrop-blur-xl">
            <div className="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3 lg:px-6">
                <Link to="/" className="flex shrink-0 items-center gap-2.5">
                    <span className="grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-green-600 to-green-700 text-sm font-black text-white shadow-sm shadow-green-600/20">K</span>
                    <span className="hidden sm:block">
                        <strong className="block text-sm font-black leading-none text-gray-900">{company.shortName}</strong>
                        <small className="mt-0.5 block text-[9px] font-bold uppercase tracking-[0.2em] text-gray-400">{company.tagline}</small>
                    </span>
                </Link>

                <div className="hidden min-w-0 flex-1 grid-cols-2 gap-2.5 md:grid">
                    <div ref={categoryAreaRef} className="relative min-w-0">
                        <button
                            type="button"
                            aria-expanded={categoryOpen}
                            onClick={() => setCategoryOpen((v) => !v)}
                            className="flex h-10 w-full min-w-0 items-center gap-2.5 rounded-xl border border-gray-200 bg-gray-50 px-3.5 text-left text-sm font-semibold text-gray-600 transition hover:border-green-300 hover:bg-white"
                        >
                            <svg className="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                            <span className="truncate">{categoryLabel}</span>
                        </button>
                        {categoryOpen && categoryPanelStyle ? (
                            <>
                                {categoryPanelStyle.showArrow ? <span className="fixed z-[60] h-2.5 w-2.5 rotate-45 border-b border-l border-gray-200 bg-white" style={{ left: categoryPanelStyle.arrowLeft, top: categoryPanelStyle.arrowTop }} /> : null}
                                <div className="fixed z-[60] overflow-x-hidden overflow-y-auto rounded-2xl border border-gray-100 bg-white p-2.5 shadow-xl shadow-black/5" style={{ left: categoryPanelStyle.left, top: categoryPanelStyle.top, width: categoryPanelStyle.width, maxHeight: categoryPanelStyle.maxHeight }}>
                                    <div className="flex min-w-0 flex-wrap gap-1.5">
                                        <Link to="/products" onClick={() => setCategoryOpen(false)} className={`w-fit rounded-lg px-3.5 py-2 text-sm font-semibold transition ${!selectedCategory ? "bg-green-600 text-white" : "bg-gray-50 text-gray-600 hover:bg-green-50 hover:text-green-700"}`}>
                                            Semua Kategori
                                        </Link>
                                        {categories.isLoading ? (
                                            <span className="w-fit rounded-lg bg-gray-50 px-3.5 py-2 text-sm text-gray-400">Memuat...</span>
                                        ) : sortedCategories.length ? sortedCategories.map((category) => (
                                            <Link key={category.id} to={categoryUrl(category)} onClick={() => setCategoryOpen(false)} className={`w-fit max-w-full break-words rounded-lg px-3.5 py-2 text-sm font-semibold transition ${selectedCategory?.id === category.id ? "bg-green-600 text-white" : "bg-gray-50 text-gray-600 hover:bg-green-50 hover:text-green-700"}`}>
                                                {category.name}
                                            </Link>
                                        )) : null}
                                    </div>
                                </div>
                            </>
                        ) : null}
                    </div>
                    <form onSubmit={submit} className="relative flex h-10 min-w-0 items-center">
                        <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Cari produk atau layanan..." className="h-full min-w-0 flex-1 rounded-xl border border-gray-200 bg-gray-50 px-3.5 pr-12 text-sm outline-none transition placeholder:text-gray-400 focus:border-green-500 focus:bg-white focus:ring-2 focus:ring-green-100" />
                        <button type="submit" aria-label="Cari" className="absolute right-2 grid h-7 w-7 cursor-pointer place-items-center rounded-lg text-gray-400 transition hover:bg-green-50 hover:text-green-600"><Search size={16} /></button>
                    </form>
                </div>

                <Link to="/track-order" title="Riwayat order" className="grid h-9 w-9 shrink-0 place-items-center rounded-xl text-gray-500 transition hover:bg-green-50 hover:text-green-600">
                    <History size={19} />
                </Link>

                <Link to="/cart" title="Keranjang" className="relative grid h-9 w-9 shrink-0 place-items-center rounded-xl text-gray-600 transition hover:bg-green-50 hover:text-green-600">
                    <ShoppingCart size={19} />
                    {summary.quantity > 0 ? <span className="absolute -right-0.5 -top-0.5 min-w-[18px] rounded-full bg-green-600 px-1 text-center text-[10px] font-bold leading-[18px] text-white ring-2 ring-white">{summary.quantity}</span> : null}
                </Link>

                <button type="button" onClick={() => setMobileOpen((v) => !v)} className="grid h-9 w-9 place-items-center rounded-xl border border-gray-200 text-gray-500 md:hidden">
                    {mobileOpen ? <X size={18} /> : <Menu size={18} />}
                </button>
            </div>

            {mobileOpen ? (
                <div className="border-t border-gray-100 bg-white px-4 py-4 md:hidden">
                    <form onSubmit={submit} className="relative flex h-10 items-center">
                        <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Cari produk atau layanan..." className="h-full min-w-0 flex-1 rounded-xl border border-gray-200 bg-gray-50 px-3.5 pr-12 text-sm outline-none focus:border-green-500 focus:ring-2 focus:ring-green-100" />
                        <button type="submit" aria-label="Cari" className="absolute right-2 grid h-7 w-7 cursor-pointer place-items-center rounded-lg text-gray-400 hover:bg-green-50 hover:text-green-600"><Search size={16} /></button>
                    </form>
                    <div className="mt-3 flex flex-wrap gap-1.5">
                        <Link to="/products" onClick={() => setMobileOpen(false)} className={`w-fit rounded-lg px-3 py-1.5 text-sm font-semibold ${!selectedCategory ? "bg-green-600 text-white" : "border border-gray-200 bg-white text-gray-600"}`}>Semua</Link>
                        {sortedCategories.map((category) => <Link key={category.id} to={categoryUrl(category)} onClick={() => setMobileOpen(false)} className={`w-fit rounded-lg px-3 py-1.5 text-sm font-semibold ${selectedCategory?.id === category.id ? "bg-green-600 text-white" : "border border-gray-200 bg-white text-gray-600"}`}>{category.name}</Link>)}
                    </div>
                </div>
            ) : null}
        </header>
    );
}

export function PublicLayout() {
    return <div className="min-h-screen bg-gray-50 text-gray-900"><PublicHeader /><Outlet /></div>;
}

function ProductCard({ product }) {
    const image = productImageUrls(product)[0];
    return (
        <Link to={`/products/${product.slug}`} className="group block overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition-all duration-300 hover:shadow-lg hover:shadow-green-900/5 hover:-translate-y-0.5">
            <div className="relative aspect-[4/3] overflow-hidden bg-gray-50">
                <SafeImage src={image} alt={product.name} className="h-full w-full object-cover transition duration-500 group-hover:scale-105" fallbackClassName="h-full w-full" />
                {product.is_featured ? <span className="absolute left-3 top-3 rounded-lg bg-green-600 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-white shadow-sm">Populer</span> : null}
            </div>
            <div className="p-4">
                <p className="text-xs font-semibold text-green-600">{product.category?.name ?? product.type}</p>
                <h3 className="mt-1 line-clamp-2 text-sm font-bold text-gray-900 group-hover:text-green-700 transition-colors">{product.name}</h3>
                <div className="mt-3 flex items-baseline justify-between">
                    <span className="text-lg font-black text-green-700">{productPrice(product)}</span>
                    {product.track_stock ? <span className="text-xs text-gray-400">Stok {product.stock}</span> : null}
                </div>
            </div>
        </Link>
    );
}

function ProductGrid({ products }) {
    if (!products.length) {
        return <Empty title="Produk tidak ditemukan" />;
    }
    return <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">{products.map((product) => <ProductCard key={product.id} product={product} />)}</div>;
}

export function HomePage() {
    const featured = useQuery({
        queryKey: ["featured"],
        queryFn: async () => collectionData(await api.get("/products", { params: { featured: 1, sort: "newest" } })),
    });

    return (
        <main>
            <section className="relative overflow-hidden bg-gradient-to-br from-green-700 via-green-600 to-emerald-600">
                <div className="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiNmZmYiIGZpbGwtb3BhY2l0eT0iMC4wNSI+PHBhdGggZD0iTTM2IDM0djZoNnYtNmgtNnptMC0zMHY2aDZ2LTZoLTZ6Ii8+PC9nPjwvZz48L3N2Zz4=')] opacity-40" />
                <div className="relative mx-auto max-w-7xl px-4 py-16 sm:py-20 lg:px-6 lg:py-24">
                    <p className="max-w-2xl text-3xl font-black leading-tight text-white sm:text-4xl lg:text-5xl">{company.heroTitle}</p>
                    <p className="mt-4 max-w-xl text-base text-green-100/80 sm:text-lg">Solusi terpercaya untuk kebutuhan operasional dan pengadaan internal perusahaan Anda.</p>
                    <div className="mt-8 flex flex-wrap gap-3">
                        <Link to="/products" className="inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3 text-sm font-bold text-green-700 shadow-lg shadow-black/10 transition hover:shadow-xl">
                            Lihat Catalog
                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth="2.5" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                        </Link>
                        <Link to="/track-order" className="inline-flex items-center gap-2 rounded-xl border border-white/30 px-6 py-3 text-sm font-bold text-white backdrop-blur-sm transition hover:bg-white/10">
                            Lacak Order
                        </Link>
                    </div>
                </div>
            </section>

            <section className="mx-auto max-w-7xl px-4 py-12 lg:px-6">
                <div className="mb-8 flex items-end justify-between">
                    <div>
                        <p className="text-sm font-bold uppercase tracking-wider text-green-600">Catalog</p>
                        <h2 className="mt-1 text-2xl font-black text-gray-900">Produk Populer</h2>
                    </div>
                    <Link to="/products" className="inline-flex items-center gap-1.5 text-sm font-bold text-green-600 transition hover:text-green-700">
                        Lihat semua
                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth="2.5" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                    </Link>
                </div>
                {featured.isLoading ? <Loading /> : featured.isError ? <QueryError message={errorMessage(featured.error)} /> : <ProductGrid products={featured.data ?? []} />}
            </section>
        </main>
    );
}

export function ProductsPage() {
    const [params, setParams] = useSearchParams();
    const search = params.get("search") ?? "";
    const category = params.get("category") ?? "";
    const sort = params.get("sort") ?? "newest";
    const query = useQuery({
        queryKey: ["products", search, category, sort],
        queryFn: async () => collectionData(await api.get("/products", { params: { search: search || undefined, category: category || undefined, sort } })),
    });
    const setParam = (key, value) => {
        const next = new URLSearchParams(params);
        if (value) next.set(key, value); else next.delete(key);
        setParams(next);
    };

    return (
        <main className="mx-auto max-w-7xl px-4 py-8 lg:px-6">
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-black text-gray-900">Semua Produk</h1>
                    <p className="mt-1 text-sm text-gray-500">{query.data?.length ?? 0} produk tersedia</p>
                </div>
                <select value={sort} onChange={(event) => setParam("sort", event.target.value)} className="rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm font-semibold text-gray-600 outline-none transition focus:border-green-500 focus:ring-2 focus:ring-green-100">
                    <option value="newest">Terbaru</option>
                    <option value="name_asc">Nama A-Z</option>
                    <option value="name_desc">Nama Z-A</option>
                    <option value="price_asc">Harga terendah</option>
                    <option value="price_desc">Harga tertinggi</option>
                </select>
            </div>
            {query.isLoading ? <Loading /> : query.isError ? <QueryError message={errorMessage(query.error)} /> : <ProductGrid products={query.data ?? []} />}
        </main>
    );
}

export function ProductDetailPage() {
    const { slug } = useParams();
    const [quantity, setQuantity] = useState(1);
    const [activeImage, setActiveImage] = useState(0);
    const add = useCartStore((state) => state.add);
    const navigate = useNavigate();
    const query = useQuery({
        queryKey: ["product", slug],
        queryFn: async () => resourceData(await api.get(`/products/${slug}`)),
    });

    useEffect(() => { setQuantity(1); setActiveImage(0); }, [slug]);

    if (query.isLoading) return <Loading />;
    if (query.isError || !query.data) return <main className="mx-auto max-w-7xl px-4 py-10 lg:px-6"><QueryError message={errorMessage(query.error, "Produk tidak ditemukan.")} /></main>;

    const product = query.data;
    const images = productImageUrls(product);
    const max = product.track_stock ? Math.max(1, Number(product.stock) || 1) : 999;
    const unitPrice = Number(product.price) || 0;
    const subtotal = unitPrice * quantity;

    return (
        <main className="mx-auto max-w-7xl px-4 py-8 lg:px-6">
            <Link to="/products" className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-gray-500 transition hover:text-green-600">
                <ArrowLeft size={16} /> Kembali ke catalog
            </Link>

            <div className="grid gap-8 lg:grid-cols-[1fr_1fr]">
                <section>
                    <div className="aspect-square overflow-hidden rounded-2xl border border-gray-100 bg-gray-50">
                        <SafeImage src={images[activeImage]} alt={product.name} className="h-full w-full object-cover" fallbackClassName="h-full w-full" />
                    </div>
                    {images.length > 1 ? (
                        <div className="mt-3 flex gap-2 overflow-x-auto pb-1">
                            {images.map((image, index) => (
                                <button key={image} type="button" onClick={() => setActiveImage(index)} className={`h-16 w-16 shrink-0 overflow-hidden rounded-xl border-2 transition ${activeImage === index ? "border-green-500" : "border-gray-100 hover:border-gray-200"}`}>
                                    <SafeImage src={image} alt={`${product.name} ${index + 1}`} className="h-full w-full object-cover" fallbackClassName="h-full w-full" />
                                </button>
                            ))}
                        </div>
                    ) : null}
                </section>

                <section className="lg:pt-2">
                    <span className="inline-flex rounded-lg bg-green-50 px-2.5 py-1 text-xs font-bold text-green-700">{product.category?.name ?? product.type}</span>
                    <h1 className="mt-3 text-2xl font-black text-gray-900 sm:text-3xl">{product.name}</h1>
                    <p className="mt-4 text-3xl font-black text-green-700">{productPrice(product)}</p>

                    <div className="mt-4 flex flex-wrap gap-3 text-sm text-gray-500">
                        {product.brand ? <span className="flex items-center gap-1"><svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" /><path strokeLinecap="round" strokeLinejoin="round" d="M6 6h.008v.008H6V6z" /></svg>{product.brand}</span> : null}
                        <span className="flex items-center gap-1">
                            {product.available ? (
                                product.track_stock ? <><span className="h-2 w-2 rounded-full bg-green-500" />Stok {product.stock ?? 0} tersedia</> : <><span className="h-2 w-2 rounded-full bg-green-500" />Tersedia</>
                            ) : <><span className="h-2 w-2 rounded-full bg-red-400" />Tidak tersedia</>}
                        </span>
                    </div>

                    {product.description ? <p className="mt-6 whitespace-pre-line leading-relaxed text-gray-600">{product.description}</p> : null}

                    <div className="mt-8 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50 p-4">
                        <div className="flex items-center gap-2">
                            <button type="button" disabled={quantity <= 1} onClick={() => setQuantity((c) => Math.max(1, c - 1))} className="grid h-10 w-10 place-items-center rounded-xl border border-gray-200 bg-white text-gray-600 transition hover:border-green-300 hover:text-green-600 disabled:cursor-not-allowed disabled:opacity-30">
                                <Minus size={16} />
                            </button>
                            <span className="min-w-10 text-center text-lg font-black text-gray-900">{quantity}</span>
                            <button type="button" disabled={quantity >= max} onClick={() => setQuantity((c) => Math.min(max, c + 1))} className="grid h-10 w-10 place-items-center rounded-xl border border-gray-200 bg-white text-gray-600 transition hover:border-green-300 hover:text-green-600 disabled:cursor-not-allowed disabled:opacity-30">
                                <Plus size={16} />
                            </button>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="text-sm text-gray-500">Subtotal</span>
                            <span className="text-xl font-black text-green-700">{currency.format(subtotal)}</span>
                        </div>
                    </div>

                    <div className="mt-5 grid gap-3 sm:grid-cols-2">
                        <button type="button" disabled={!product.available} onClick={() => add(product, quantity)} className="rounded-xl border-2 border-green-600 px-5 py-3.5 text-sm font-bold text-green-700 transition hover:bg-green-50 disabled:border-gray-200 disabled:text-gray-300 disabled:hover:bg-transparent">
                            Tambah ke Keranjang
                        </button>
                        <button type="button" disabled={!product.available} onClick={() => { add(product, quantity); navigate("/checkout"); }} className="rounded-xl bg-green-600 px-5 py-3.5 text-sm font-bold text-white shadow-sm shadow-green-600/25 transition hover:bg-green-700 disabled:bg-gray-200 disabled:shadow-none">
                            Checkout Langsung
                        </button>
                    </div>
                </section>
            </div>
        </main>
    );
}

export function CartPage() {
    const items = useCartStore((state) => state.items);
    const update = useCartStore((state) => state.update);
    const remove = useCartStore((state) => state.remove);
    const summary = cartSummary(items);

    if (!items.length) {
        return <main className="mx-auto max-w-7xl px-4 py-10 lg:px-6"><Empty title="Keranjang masih kosong" action={<Link to="/products" className="rounded-xl bg-green-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-green-700">Lihat catalog</Link>} /></main>;
    }

    return (
        <main className="mx-auto grid max-w-7xl gap-6 px-4 py-8 lg:grid-cols-[1fr_340px] lg:px-6">
            <section className="grid gap-3">
                {items.map((item) => (
                    <article key={item.product.id} className="flex gap-4 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                        <div className="h-24 w-24 shrink-0 overflow-hidden rounded-xl bg-gray-50">
                            <SafeImage src={productImageUrls(item.product)[0]} alt={item.product.name} className="h-full w-full object-cover" fallbackClassName="h-full w-full" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <Link to={`/products/${item.product.slug}`} className="text-sm font-bold text-gray-900 hover:text-green-600 transition">{item.product.name}</Link>
                            <p className="mt-1 text-sm font-bold text-green-600">{currency.format(Number(item.product.price) || 0)}</p>
                            <div className="mt-2.5 flex items-center gap-2">
                                <button type="button" onClick={() => update(item.product.id, item.quantity - 1)} className="grid h-8 w-8 place-items-center rounded-lg border border-gray-200 text-gray-500 transition hover:border-green-300 hover:text-green-600"><Minus size={14} /></button>
                                <span className="min-w-8 text-center text-sm font-bold">{item.quantity}</span>
                                <button type="button" onClick={() => update(item.product.id, item.quantity + 1)} className="grid h-8 w-8 place-items-center rounded-lg border border-gray-200 text-gray-500 transition hover:border-green-300 hover:text-green-600"><Plus size={14} /></button>
                                <button type="button" onClick={() => remove(item.product.id)} className="ml-auto grid h-8 w-8 place-items-center rounded-lg text-gray-400 transition hover:bg-red-50 hover:text-red-500"><Trash2 size={16} /></button>
                            </div>
                        </div>
                    </article>
                ))}
            </section>

            <aside className="h-fit rounded-2xl border border-gray-100 bg-white p-5 shadow-sm lg:sticky lg:top-24">
                <h3 className="text-sm font-bold text-gray-900">Ringkasan</h3>
                <div className="mt-4 flex justify-between text-sm text-gray-500"><span>Total item</span><span className="font-bold text-gray-900">{summary.quantity}</span></div>
                <div className="mt-2 flex justify-between border-t border-gray-100 pt-3"><span className="text-base font-bold">Total</span><span className="text-xl font-black text-green-700">{currency.format(summary.total)}</span></div>
                <Link to="/checkout" className="mt-5 block rounded-xl bg-green-600 px-5 py-3.5 text-center text-sm font-bold text-white shadow-sm shadow-green-600/25 transition hover:bg-green-700">Lanjut Checkout</Link>
            </aside>
        </main>
    );
}

const emptyBuyerForm = () => ({ customer_type: "individual", name: "", email: "", phone: "", address: "", nik: "", npwp: "", province: "", city: "", company_name: "", postal_code: "", country: "Indonesia", notes: "", payment_method: "internal_billing" });

function buyerFormFromOrder(order) {
    return { customer_type: order.customer_type === "business" ? "business" : "individual", name: order.guest_name ?? "", email: order.guest_email ?? "", phone: order.guest_phone ?? "", address: order.guest_address ?? "", nik: order.guest_nik ?? "", npwp: order.guest_npwp ?? "", province: order.guest_province ?? "", city: order.guest_city ?? "", company_name: order.guest_company_name ?? "", postal_code: order.guest_postal_code ?? "", country: order.guest_country ?? "Indonesia", notes: order.guest_notes ?? "", payment_method: order.payment_method ?? "internal_billing" };
}

function checkoutPayload(form) {
    if (form.customer_type === "business") return form;
    return { ...form, nik: null, npwp: null, province: null, city: null, company_name: null, postal_code: null, country: null };
}

function BuyerTypeSelector({ value, onChange, disabled = false }) {
    return (
        <div className="grid grid-cols-2 gap-1.5 rounded-xl border border-gray-200 bg-gray-50 p-1">
            <button type="button" disabled={disabled} onClick={() => onChange("individual")} className={`rounded-lg px-4 py-2.5 text-sm font-bold transition ${value === "individual" ? "bg-white text-green-700 shadow-sm" : "text-gray-500"}`}>Perorangan</button>
            <button type="button" disabled={disabled} onClick={() => onChange("business")} className={`rounded-lg px-4 py-2.5 text-sm font-bold transition ${value === "business" ? "bg-white text-green-700 shadow-sm" : "text-gray-500"}`}>Badan Usaha</button>
        </div>
    );
}

function BuyerIdentityFields({ form, onChange, disabled = false, showPayment = false }) {
    const business = form.customer_type === "business";
    const update = (key) => (event) => onChange({ [key]: event.target.value });
    return (
        <div className="grid gap-4">
            <BuyerTypeSelector value={form.customer_type} onChange={(customer_type) => onChange({ customer_type })} disabled={disabled} />
            <div className="grid gap-4 sm:grid-cols-2">
                {business ? <>
                    <label className="grid gap-1.5 text-sm font-semibold sm:col-span-2">Perusahaan<input required disabled={disabled} value={form.company_name} onChange={update("company_name")} className={fieldClass} /></label>
                    <label className="grid gap-1.5 text-sm font-semibold">Nama penanggung jawab<input required disabled={disabled} value={form.name} onChange={update("name")} className={fieldClass} /></label>
                    <label className="grid gap-1.5 text-sm font-semibold">NIK<input required disabled={disabled} inputMode="numeric" maxLength={16} pattern="[0-9]{16}" value={form.nik} onChange={update("nik")} className={fieldClass} /></label>
                    <label className="grid gap-1.5 text-sm font-semibold">NPWP<input required disabled={disabled} value={form.npwp} onChange={update("npwp")} className={fieldClass} /></label>
                    <label className="grid gap-1.5 text-sm font-semibold">Negara<input required disabled={disabled} value={form.country} onChange={update("country")} className={fieldClass} /></label>
                    <label className="grid gap-1.5 text-sm font-semibold">Provinsi<input required disabled={disabled} value={form.province} onChange={update("province")} className={fieldClass} /></label>
                    <label className="grid gap-1.5 text-sm font-semibold">Kota<input required disabled={disabled} value={form.city} onChange={update("city")} className={fieldClass} /></label>
                    <label className="grid gap-1.5 text-sm font-semibold">Kode Pos<input required disabled={disabled} value={form.postal_code} onChange={update("postal_code")} className={fieldClass} /></label>
                </> : <label className="grid gap-1.5 text-sm font-semibold sm:col-span-2">Nama lengkap<input required disabled={disabled} value={form.name} onChange={update("name")} className={fieldClass} /></label>}
                <label className="grid gap-1.5 text-sm font-semibold">Email<input required disabled={disabled} type="email" value={form.email} onChange={update("email")} className={fieldClass} /></label>
                <label className="grid gap-1.5 text-sm font-semibold">Telepon<input required disabled={disabled} value={form.phone} onChange={update("phone")} className={fieldClass} /></label>
                <label className="grid gap-1.5 text-sm font-semibold sm:col-span-2">Alamat<textarea required disabled={disabled} rows={3} value={form.address} onChange={update("address")} className={fieldClass} /></label>
                <label className="grid gap-1.5 text-sm font-semibold sm:col-span-2">Catatan<textarea disabled={disabled} rows={2} value={form.notes} onChange={update("notes")} className={fieldClass} /></label>
                {showPayment ? <label className="grid gap-1.5 text-sm font-semibold sm:col-span-2">Metode pembayaran<select disabled={disabled} value={form.payment_method} onChange={update("payment_method")} className={fieldClass}><option value="internal_billing">Internal Billing</option><option value="bank_transfer">Transfer Manual</option><option value="cod">COD</option></select></label> : null}
            </div>
        </div>
    );
}

function BuyerIdentitySummary({ data }) {
    const business = data.customer_type === "business";
    return (
        <dl className="grid gap-3 text-sm sm:grid-cols-2">
            <div><dt className="text-gray-400">Tipe checkout</dt><dd className="mt-1 font-bold">{business ? "Badan Usaha" : "Perorangan"}</dd></div>
            {business ? <div><dt className="text-gray-400">Perusahaan</dt><dd className="mt-1 font-bold">{data.company_name}</dd></div> : null}
            <div><dt className="text-gray-400">{business ? "Penanggung jawab" : "Nama"}</dt><dd className="mt-1 font-bold">{data.name}</dd></div>
            <div><dt className="text-gray-400">Email</dt><dd className="mt-1 break-all font-bold">{data.email}</dd></div>
            <div><dt className="text-gray-400">Telepon</dt><dd className="mt-1 font-bold">{data.phone}</dd></div>
            {business ? <><div><dt className="text-gray-400">NIK</dt><dd className="mt-1 font-bold">{data.nik}</dd></div><div><dt className="text-gray-400">NPWP</dt><dd className="mt-1 font-bold">{data.npwp}</dd></div><div><dt className="text-gray-400">Provinsi</dt><dd className="mt-1 font-bold">{data.province}</dd></div><div><dt className="text-gray-400">Kota</dt><dd className="mt-1 font-bold">{data.city}</dd></div><div><dt className="text-gray-400">Kode Pos</dt><dd className="mt-1 font-bold">{data.postal_code}</dd></div><div><dt className="text-gray-400">Negara</dt><dd className="mt-1 font-bold">{data.country}</dd></div></> : null}
            <div className="sm:col-span-2"><dt className="text-gray-400">Alamat</dt><dd className="mt-1 whitespace-pre-line font-bold">{data.address}</dd></div>
            {data.notes ? <div className="sm:col-span-2"><dt className="text-gray-400">Catatan</dt><dd className="mt-1 whitespace-pre-line font-bold">{data.notes}</dd></div> : null}
        </dl>
    );
}

function CheckoutItemsSummary({ items }) {
    const summary = cartSummary(items);
    return (
        <section className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            {items.map((item) => <div key={item.product.id} className="flex justify-between gap-3 border-b border-gray-50 py-3 text-sm last:border-0"><span className="font-semibold text-gray-700">{item.product.name} x {item.quantity}</span><span className="font-bold">{currency.format((Number(item.product.price) || 0) * item.quantity)}</span></div>)}
            <div className="mt-4 flex justify-between border-t border-gray-100 pt-3"><span className="font-bold">Total</span><span className="text-lg font-black text-green-700">{currency.format(summary.total)}</span></div>
        </section>
    );
}

export function CheckoutPage() {
    const items = useCartStore((state) => state.items);
    const clear = useCartStore((state) => state.clear);
    const navigate = useNavigate();
    const [form, setForm] = useState(emptyBuyerForm());
    const [preview, setPreview] = useState(false);
    const mutation = useMutation({
        mutationFn: async () => resourceData(await api.post("/checkout", { ...checkoutPayload(form), items: items.map((item) => ({ product_id: Number(item.product.id), quantity: item.quantity })) })),
        onSuccess: (order) => { localStorage.setItem("kishamarket-last-order-email", form.email.trim().toLowerCase()); clear(); navigate(`/order-success/${order.order_number}`, { state: { order, email: form.email.trim().toLowerCase() } }); },
    });

    if (!items.length) return <main className="mx-auto max-w-7xl px-4 py-10 lg:px-6"><Empty title="Keranjang masih kosong" action={<Link to="/products" className="rounded-xl bg-green-600 px-5 py-3 text-sm font-bold text-white">Lihat catalog</Link>} /></main>;

    const updateForm = (patch) => setForm((c) => ({ ...c, ...patch }));

    return (
        <main className="mx-auto max-w-6xl px-4 py-8 lg:px-6">
            {!preview ? (
                <form onSubmit={(e) => { e.preventDefault(); setPreview(true); }} className="grid gap-6 lg:grid-cols-[1fr_360px]">
                    <section className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm"><h2 className="mb-5 text-lg font-black text-gray-900">Data Pembeli</h2><BuyerIdentityFields form={form} onChange={updateForm} showPayment /></section>
                    <aside className="h-fit lg:sticky lg:top-24"><CheckoutItemsSummary items={items} /><button className="mt-4 w-full rounded-xl bg-green-600 px-5 py-3.5 text-sm font-bold text-white shadow-sm shadow-green-600/25 transition hover:bg-green-700">Preview Order</button></aside>
                </form>
            ) : (
                <div className="grid gap-6 lg:grid-cols-[1fr_360px]">
                    <section className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm"><h2 className="mb-5 text-lg font-black text-gray-900">Konfirmasi Data</h2><BuyerIdentitySummary data={form} /></section>
                    <aside className="h-fit lg:sticky lg:top-24"><CheckoutItemsSummary items={items} />{mutation.isError ? <p className="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-bold text-red-600">{errorMessage(mutation.error, "Checkout gagal.")}</p> : null}<div className="mt-4 grid gap-2.5"><button type="button" onClick={() => setPreview(false)} className="rounded-xl border border-gray-200 px-5 py-3 text-sm font-bold text-gray-600 transition hover:bg-gray-50">Kembali ke Form</button><button type="button" disabled={mutation.isPending} onClick={() => mutation.mutate()} className="rounded-xl bg-green-600 px-5 py-3.5 text-sm font-bold text-white shadow-sm shadow-green-600/25 transition hover:bg-green-700 disabled:bg-gray-200 disabled:shadow-none">{mutation.isPending ? "Memproses..." : "Buat Order"}</button></div></aside>
                </div>
            )}
        </main>
    );
}

function OrderDetail({ order, email, onCancel, cancelling = false, showEdit = true }) {
    if (!order) return <div className="grid min-h-64 place-items-center rounded-2xl border border-dashed border-gray-200 text-sm text-gray-400">Pilih order untuk melihat detail.</div>;
    const business = order.customer_type === "business";
    return (
        <article className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <div><p className="text-sm text-gray-400">{order.order_number}</p><div className="mt-2"><StatusBadge status={order.status} /></div></div>
                {showEdit && order.can_edit && email ? <Link to={`/orders/${order.order_number}/edit?email=${encodeURIComponent(email)}`} title="Edit data buyer" className="grid h-9 w-9 place-items-center rounded-xl border border-gray-200 text-gray-500 transition hover:border-green-300 hover:text-green-600"><Pencil size={16} /></Link> : null}
            </div>
            <dl className="mt-5 grid gap-3 text-sm sm:grid-cols-2">
                <div><dt className="text-gray-400">Tipe</dt><dd className="mt-0.5 font-bold">{business ? "Badan Usaha" : "Perorangan"}</dd></div>
                {business ? <div><dt className="text-gray-400">Perusahaan</dt><dd className="mt-0.5 font-bold">{order.guest_company_name}</dd></div> : null}
                <div><dt className="text-gray-400">Nama</dt><dd className="mt-0.5 font-bold">{order.guest_name}</dd></div>
                <div><dt className="text-gray-400">Email</dt><dd className="mt-0.5 break-all font-bold">{order.guest_email}</dd></div>
                <div><dt className="text-gray-400">Telepon</dt><dd className="mt-0.5 font-bold">{order.guest_phone}</dd></div>
                <div><dt className="text-gray-400">Tanggal</dt><dd className="mt-0.5 font-bold">{dateTime(order.created_at)}</dd></div>
                <div className="sm:col-span-2"><dt className="text-gray-400">Alamat</dt><dd className="mt-0.5 whitespace-pre-line font-bold">{order.guest_address}</dd></div>
            </dl>
            <div className="mt-5 border-t border-gray-100 pt-4">
                {(order.items ?? []).map((item) => <div key={item.id} className="flex justify-between gap-3 border-b border-gray-50 py-2.5 text-sm last:border-0"><div><span className="font-bold">{item.product_name}</span> x {item.quantity}{item.product_sku ? <p className="mt-0.5 text-xs text-gray-400">{item.product_sku}</p> : null}</div><span className="font-bold">{currency.format(item.subtotal)}</span></div>)}
                <div className="mt-3 flex justify-between border-t border-gray-100 pt-3"><span className="font-bold">Total</span><span className="text-lg font-black text-green-700">{currency.format(order.total_amount)}</span></div>
            </div>
            {order.can_cancel && onCancel ? <button type="button" disabled={cancelling} onClick={() => onCancel(order)} className="mt-4 rounded-xl border border-red-200 px-4 py-2.5 text-sm font-bold text-red-600 transition hover:bg-red-50 disabled:opacity-50">{cancelling ? "Membatalkan..." : "Batalkan Order"}</button> : null}
        </article>
    );
}

export function TrackOrderPage() {
    const client = useQueryClient();
    const [urlParams, setUrlParams] = useSearchParams();
    const initialEmail = (urlParams.get("email") ?? "").trim().toLowerCase();
    const [emailInput, setEmailInput] = useState(initialEmail);
    const [email, setEmail] = useState(initialEmail);
    const [selectedNumber, setSelectedNumber] = useState("");
    const query = useQuery({ queryKey: ["track-orders", email], enabled: Boolean(email), queryFn: async () => collectionData(await api.get("/orders/track", { params: { email } })) });
    const selected = (query.data ?? []).find((order) => order.order_number === selectedNumber) ?? query.data?.[0] ?? null;
    const cancel = useMutation({ mutationFn: async (order) => resourceData(await api.post(`/orders/${order.order_number}/cancel`, { email, cancel_reason: "Dibatalkan oleh buyer." })), onSuccess: async (order) => { setSelectedNumber(order.order_number); await client.invalidateQueries({ queryKey: ["track-orders", email] }); } });

    useEffect(() => { if (query.data?.length && !query.data.some((order) => order.order_number === selectedNumber)) setSelectedNumber(query.data[0].order_number); }, [query.data, selectedNumber]);

    const submit = (event) => { event.preventDefault(); const n = emailInput.trim().toLowerCase(); const next = new URLSearchParams(urlParams); if (n) next.set("email", n); else next.delete("email"); setUrlParams(next, { replace: true }); setEmail(n); setSelectedNumber(""); };

    return (
        <main className="mx-auto max-w-7xl px-4 py-8 lg:px-6">
            <form onSubmit={submit} className="relative mb-6 flex max-w-xl items-center">
                <input required type="email" value={emailInput} onChange={(event) => setEmailInput(event.target.value)} placeholder="Masukkan email untuk melihat riwayat order" className={`${fieldClass} w-full pr-12`} />
                <button type="submit" className="absolute right-2 grid h-9 w-9 place-items-center rounded-lg text-gray-400 transition hover:bg-green-50 hover:text-green-600"><Search size={18} /></button>
            </form>
            {!email ? <Empty title="Masukkan email untuk melihat riwayat order" /> : query.isLoading ? <Loading /> : query.isError ? <QueryError message={errorMessage(query.error, "Riwayat order gagal dimuat.")} /> : !(query.data ?? []).length ? <Empty title="Riwayat order tidak ditemukan" /> : (
                <div className="grid gap-5 lg:grid-cols-[320px_1fr]">
                    <aside className="grid h-fit gap-2 lg:sticky lg:top-24">
                        {(query.data ?? []).map((order) => (
                            <button key={order.id} type="button" onClick={() => setSelectedNumber(order.order_number)} className={`rounded-xl border p-4 text-left transition ${selected?.id === order.id ? "border-green-300 bg-green-50/50 shadow-sm" : "border-gray-100 bg-white hover:border-gray-200"}`}>
                                <div className="flex items-center justify-between gap-2"><span className="text-sm font-bold">{order.order_number}</span><StatusBadge status={order.status} /></div>
                                <p className="mt-2 font-bold text-green-600">{currency.format(order.total_amount)}</p>
                                <p className="mt-1 text-xs text-gray-400">{dateTime(order.created_at)}</p>
                            </button>
                        ))}
                    </aside>
                    <OrderDetail order={selected} email={email} onCancel={(order) => cancel.mutate(order)} cancelling={cancel.isPending} />
                </div>
            )}
        </main>
    );
}

export function OrderEditPage() {
    const { orderNumber } = useParams();
    const [params] = useSearchParams();
    const navigate = useNavigate();
    const verificationEmail = (params.get("email") ?? "").trim().toLowerCase();
    const query = useQuery({ queryKey: ["track-orders", verificationEmail], enabled: Boolean(verificationEmail), queryFn: async () => collectionData(await api.get("/orders/track", { params: { email: verificationEmail } })) });
    const order = query.data?.find((item) => item.order_number === orderNumber);
    const [form, setForm] = useState(null);
    useEffect(() => { if (order) setForm(buyerFormFromOrder(order)); }, [order]);
    const mutation = useMutation({ mutationFn: async () => resourceData(await api.put(`/orders/track/${orderNumber}`, { ...checkoutPayload(form), email_verification: verificationEmail })), onSuccess: () => navigate(`/track-order?email=${encodeURIComponent(verificationEmail)}`) });

    if (!verificationEmail) return <main className="mx-auto max-w-4xl px-4 py-10"><QueryError message="Email verifikasi tidak tersedia." /></main>;
    if (query.isLoading || !form) return <Loading />;
    if (query.isError || !order) return <main className="mx-auto max-w-4xl px-4 py-10"><QueryError message="Order tidak ditemukan." /></main>;
    if (!order.can_edit) return <main className="mx-auto max-w-4xl px-4 py-10"><QueryError message="Order ini tidak dapat diedit karena statusnya bukan pending." /></main>;

    return (
        <main className="mx-auto max-w-4xl px-4 py-8 lg:px-6">
            <Link to={`/track-order?email=${encodeURIComponent(verificationEmail)}`} className="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-gray-500 hover:text-green-600"><ArrowLeft size={16} /> Kembali ke detail</Link>
            <form onSubmit={(e) => { e.preventDefault(); mutation.mutate(); }} className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 className="mb-5 text-lg font-black text-gray-900">Edit Data Pembeli</h2>
                <BuyerIdentityFields form={form} onChange={(patch) => setForm((c) => ({ ...c, ...patch }))} />
                {mutation.isError ? <p className="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-bold text-red-600">{errorMessage(mutation.error, "Data order gagal diperbarui.")}</p> : null}
                <div className="mt-5 flex justify-end gap-3"><button type="button" onClick={() => navigate(-1)} className="rounded-xl border border-gray-200 px-5 py-3 text-sm font-bold text-gray-600 transition hover:bg-gray-50">Batal</button><button disabled={mutation.isPending} className="rounded-xl bg-green-600 px-6 py-3 text-sm font-bold text-white shadow-sm shadow-green-600/25 transition hover:bg-green-700 disabled:bg-gray-200">{mutation.isPending ? "Menyimpan..." : "Simpan"}</button></div>
            </form>
        </main>
    );
}

export function OrderSuccessPage() {
    const { orderNumber } = useParams();
    const location = useLocation();
    const stateOrder = location.state?.order;
    const stateEmail = location.state?.email;
    const email = stateEmail || localStorage.getItem("kishamarket-last-order-email") || "";
    const query = useQuery({ queryKey: ["track-orders", email], enabled: !stateOrder && Boolean(email), queryFn: async () => collectionData(await api.get("/orders/track", { params: { email } })) });
    const order = stateOrder ?? query.data?.find((item) => item.order_number === orderNumber);

    if (!order && query.isLoading) return <Loading />;

    return (
        <main className="mx-auto max-w-4xl px-4 py-10 lg:px-6">
            <div className="mb-6 flex items-center gap-4 rounded-2xl border border-green-200 bg-green-50/50 p-5">
                <div className="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-green-100"><CheckCircle2 size={24} className="text-green-600" /></div>
                <div><b className="block text-lg text-green-900">Order berhasil dibuat</b><span className="text-sm text-green-700">Simpan nomor order dan email untuk melihat riwayat.</span></div>
            </div>
            {order ? <OrderDetail order={order} email={email} showEdit={false} /> : <QueryError message="Detail order belum dapat dimuat." />}
            <div className="mt-5 flex flex-wrap gap-3"><Link to="/products" className="rounded-xl border border-gray-200 px-5 py-3 text-sm font-bold text-gray-600 transition hover:bg-gray-50">Kembali ke Catalog</Link><Link to="/track-order" className="rounded-xl bg-green-600 px-5 py-3 text-sm font-bold text-white shadow-sm shadow-green-600/25 transition hover:bg-green-700">Lihat Riwayat Order</Link></div>
        </main>
    );
}
