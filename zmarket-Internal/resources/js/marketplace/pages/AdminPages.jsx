import { useEffect, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Boxes, Eye, LayoutDashboard, LogOut, Menu, Package, Pencil, Plus, ReceiptText, RefreshCw, Trash2, Users, X } from "lucide-react";
import { Link, Navigate, NavLink, Outlet, useNavigate } from "react-router-dom";
import { Empty, fieldClass, Loading, StatusBadge } from "@/components/Common";
import { ProductForm } from "@/components/admin/ProductForm";
import { OrderCreateForm } from "@/components/admin/OrderCreateForm";
import { api, collectionData, errorMessage, paginatedData, resourceData } from "@/lib/api";
import { currency, dateTime } from "@/lib/format";
import { useAuthStore } from "@/stores/authStore";

export function ProtectedRoute() {
    const { user, checked, loading, load } = useAuthStore();
    useEffect(() => { if (!checked && !loading) void load(); }, [checked, loading, load]);
    if (!checked || loading) return <Loading label="Memeriksa sesi..." />;
    return user ? <Outlet /> : <Navigate to="/admin/login" replace />;
}

export function AdminOnlyRoute() {
    const user = useAuthStore((state) => state.user);
    const role = String(user?.role ?? "").trim().toLowerCase();
    return role === "admin" ? <Outlet /> : <Navigate to="/admin" replace />;
}

export function AdminLoginPage() {
    const navigate = useNavigate();
    const { login, loading, user } = useAuthStore();
    const [email, setEmail] = useState("admin@company.local");
    const [password, setPassword] = useState("12345678");
    const [remember, setRemember] = useState(false);
    const [error, setError] = useState("");
    useEffect(() => { if (user) navigate("/admin", { replace: true }); }, [user, navigate]);
    const submit = async (event) => { event.preventDefault(); setError(""); try { await login(email, password, remember); navigate("/admin"); } catch (e) { setError(errorMessage(e, "Login gagal.")); } };

    return (
        <main className="flex min-h-screen items-center justify-center bg-gradient-to-br from-gray-900 to-gray-800 p-4">
            <form onSubmit={submit} className="w-full max-w-md rounded-2xl border border-gray-100 bg-white p-8 shadow-2xl">
                <div className="grid h-12 w-12 place-items-center rounded-xl bg-gradient-to-br from-green-600 to-green-700 text-lg font-black text-white shadow-sm shadow-green-600/20">K</div>
                <h1 className="mt-5 text-2xl font-black text-gray-900">Login Dashboard</h1>
                <p className="mt-1 text-sm text-gray-500">Akses admin dan seller marketplace.</p>
                {error ? <p className="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-bold text-red-600">{error}</p> : null}
                <div className="mt-6 grid gap-4">
                    <label className="grid gap-1.5 text-sm font-semibold text-gray-700">Email<input type="email" required value={email} onChange={(event) => setEmail(event.target.value)} className={fieldClass} /></label>
                    <label className="grid gap-1.5 text-sm font-semibold text-gray-700">Password<input type="password" required value={password} onChange={(event) => setPassword(event.target.value)} className={fieldClass} /></label>
                    <label className="flex items-center gap-2 text-sm text-gray-600"><input type="checkbox" checked={remember} onChange={(event) => setRemember(event.target.checked)} className="rounded" />Ingat sesi login</label>
                    <button disabled={loading} className="rounded-xl bg-green-600 py-3 text-sm font-bold text-white shadow-sm shadow-green-600/25 transition hover:bg-green-700 disabled:bg-gray-200">{loading ? "Masuk..." : "Login"}</button>
                    <Link to="/" className="text-center text-sm font-bold text-green-600 transition hover:text-green-700">Kembali ke marketplace</Link>
                </div>
            </form>
        </main>
    );
}

function AdminNavLink({ to, icon, children, onClick }) {
    return (
        <NavLink to={to} end={to === "/admin"} onClick={onClick} className={({ isActive }) => `flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-semibold transition ${isActive ? "bg-green-600 text-white shadow-sm shadow-green-600/20" : "text-gray-400 hover:bg-white/5 hover:text-white"}`}>
            {icon}{children}
        </NavLink>
    );
}

export function AdminLayout() {
    const [open, setOpen] = useState(false);
    const { user, logout } = useAuthStore();
    const navigate = useNavigate();
    const role = String(user?.role ?? "").trim().toLowerCase();
    const isAdmin = role === "admin";
    const close = () => setOpen(false);
    const signOut = async () => { await logout(); navigate("/admin/login", { replace: true }); };

    const sidebar = (
        <aside className="flex h-full w-64 flex-col bg-gray-900">
            <div className="border-b border-white/5 p-5">
                <div className="flex items-center gap-2.5">
                    <span className="grid h-8 w-8 place-items-center rounded-lg bg-gradient-to-br from-green-500 to-green-600 text-xs font-black text-white">K</span>
                    <div>
                        <p className="text-sm font-black text-white">KishaMarket</p>
                        <p className="text-[10px] text-gray-500">{user?.name ?? "User"} · {role}</p>
                    </div>
                </div>
            </div>
            <nav className="flex-1 space-y-1 p-3">
                <AdminNavLink to="/admin" icon={<LayoutDashboard size={18} />} onClick={close}>Dashboard</AdminNavLink>
                <AdminNavLink to="/admin/categories" icon={<Boxes size={18} />} onClick={close}>Kategori</AdminNavLink>
                <AdminNavLink to="/admin/products" icon={<Package size={18} />} onClick={close}>Produk</AdminNavLink>
                <AdminNavLink to="/admin/orders" icon={<ReceiptText size={18} />} onClick={close}>Order</AdminNavLink>
                {isAdmin ? <AdminNavLink to="/admin/users" icon={<Users size={18} />} onClick={close}>User</AdminNavLink> : null}
            </nav>
            <div className="border-t border-white/5 p-3">
                <button type="button" onClick={() => void signOut()} className="flex w-full items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-semibold text-gray-500 transition hover:bg-white/5 hover:text-white">
                    <LogOut size={18} /> Logout
                </button>
            </div>
        </aside>
    );

    return (
        <div className="min-h-screen bg-gray-50 lg:flex">
            <div className="hidden lg:block">{sidebar}</div>
            {open ? (
                <div className="fixed inset-0 z-50 flex bg-black/50 lg:hidden">
                    <div>{sidebar}</div>
                    <button type="button" aria-label="Tutup" onClick={close} className="flex-1" />
                </div>
            ) : null}
            <main className="min-w-0 flex-1">
                <header className="flex items-center justify-between border-b border-gray-100 bg-white px-4 py-3 lg:px-6">
                    <button type="button" onClick={() => setOpen(true)} className="grid h-9 w-9 place-items-center rounded-lg border border-gray-200 text-gray-500 lg:hidden"><Menu size={18} /></button>
                    <div className="ml-auto flex items-center gap-3">
                        <span className="hidden text-sm text-gray-500 sm:block">{user?.email}</span>
                        <div className="grid h-8 w-8 place-items-center rounded-full bg-green-100 text-xs font-bold text-green-700">{(user?.name ?? "U").charAt(0).toUpperCase()}</div>
                    </div>
                </header>
                <div className="p-4 lg:p-6"><Outlet /></div>
            </main>
        </div>
    );
}

export function AdminDashboardPage() {
    const query = useQuery({ queryKey: ["admin-dashboard"], queryFn: async () => resourceData(await api.get("/admin/dashboard")), retry: false });
    if (query.isLoading) return <Loading />;
    if (query.isError) return (
        <div>
            <h1 className="text-2xl font-black text-gray-900">Dashboard</h1>
            <div className="mt-6 rounded-2xl border border-red-200 bg-red-50/50 p-5">
                <p className="font-bold text-red-700">Dashboard gagal memuat data</p>
                <p className="mt-1 text-sm text-red-600">{errorMessage(query.error, "Data dashboard belum dapat dimuat.")}</p>
                <button type="button" onClick={() => void query.refetch()} className="mt-4 rounded-xl bg-red-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-red-700">Coba Lagi</button>
            </div>
        </div>
    );

    const stats = [
        { label: "Total Produk", value: query.data?.products ?? 0, icon: <Package size={20} />, color: "bg-blue-50 text-blue-600" },
        { label: "Kategori", value: query.data?.categories ?? 0, icon: <Boxes size={20} />, color: "bg-violet-50 text-violet-600" },
        { label: "User", value: query.data?.users ?? 0, icon: <Users size={20} />, color: "bg-amber-50 text-amber-600" },
        { label: "Order Pending", value: query.data?.pending_orders ?? 0, icon: <ReceiptText size={20} />, color: "bg-rose-50 text-rose-600" },
    ];

    return (
        <div>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div><h1 className="text-2xl font-black text-gray-900">Dashboard</h1><p className="mt-0.5 text-sm text-gray-500">Ringkasan aktivitas marketplace</p></div>
                <button type="button" onClick={() => void query.refetch()} className="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50"><RefreshCw size={16} /> Refresh</button>
            </div>

            <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {stats.map((stat) => (
                    <div key={stat.label} className="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-semibold text-gray-500">{stat.label}</span>
                            <div className={`grid h-10 w-10 place-items-center rounded-xl ${stat.color}`}>{stat.icon}</div>
                        </div>
                        <p className="mt-3 text-2xl font-black text-gray-900">{Number(stat.value ?? 0).toLocaleString("id-ID")}</p>
                    </div>
                ))}
            </div>

            <div className="mt-4 rounded-xl border border-gray-100 bg-white p-5 shadow-sm sm:col-span-2 lg:col-span-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-base font-black text-gray-900">Pendapatan</h2>
                    <span className="text-xl font-black text-green-600">{currency.format(Number(query.data?.revenue) || 0)}</span>
                </div>
            </div>

            <div className="mt-5 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 className="text-base font-black text-gray-900">Order Terbaru</h2>
                {query.data?.latest_orders?.length ? (
                    <div className="mt-4 overflow-x-auto">
                        <table className="w-full min-w-[700px]">
                            <thead><tr className="border-b border-gray-100 text-left text-xs font-bold uppercase tracking-wider text-gray-400"><th className="pb-3">Nomor</th><th className="pb-3">Buyer</th><th className="pb-3">Status</th><th className="pb-3">Total</th><th className="pb-3">Tanggal</th></tr></thead>
                            <tbody>{query.data.latest_orders.map((order) => <tr key={order.id} className="border-b border-gray-50 last:border-0"><td className="py-3 font-bold text-gray-900">{order.order_number}</td><td className="py-3 text-gray-600">{order.guest_name ?? "-"}</td><td className="py-3"><StatusBadge status={order.status} /></td><td className="py-3 font-bold text-green-600">{currency.format(Number(order.total_amount) || 0)}</td><td className="py-3 text-gray-500">{dateTime(order.created_at)}</td></tr>)}</tbody>
                        </table>
                    </div>
                ) : <p className="mt-4 text-sm text-gray-400">Belum ada order.</p>}
            </div>
        </div>
    );
}

function Pagination({ meta, onPage }) {
    if (!meta || meta.last_page <= 1) return null;
    return (
        <div className="mt-5 flex items-center justify-between gap-3 rounded-xl border border-gray-100 bg-white p-3 shadow-sm">
            <button disabled={meta.current_page <= 1} onClick={() => onPage(meta.current_page - 1)} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 disabled:opacity-40">Sebelumnya</button>
            <span className="text-sm text-gray-500">Halaman {meta.current_page} dari {meta.last_page} · {meta.total} data</span>
            <button disabled={meta.current_page >= meta.last_page} onClick={() => onPage(meta.current_page + 1)} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 disabled:opacity-40">Berikutnya</button>
        </div>
    );
}

const blankCategory = () => ({ name: "", slug: "", description: "", sort_order: "0", is_active: true });

export function AdminCategoriesPage() {
    const client = useQueryClient();
    const [editing, setEditing] = useState(null);
    const [open, setOpen] = useState(false);
    const [form, setForm] = useState(blankCategory());
    const [image, setImage] = useState(null);
    const [error, setError] = useState("");
    const query = useQuery({ queryKey: ["admin-categories"], queryFn: async () => collectionData(await api.get("/admin/categories")) });

    const start = (category) => { setEditing(category ?? null); setForm(category ? { name: category.name, slug: category.slug, description: category.description ?? "", sort_order: String(category.sort_order), is_active: category.is_active } : blankCategory()); setImage(null); setError(""); setOpen(true); };
    const save = async (event) => { event.preventDefault(); setError(""); const body = new FormData(); body.append("name", form.name); body.append("slug", form.slug); body.append("description", form.description); body.append("sort_order", form.sort_order); body.append("is_active", form.is_active ? "1" : "0"); if (image) body.append("image", image); try { if (editing) await api.post(`/admin/categories/${editing.id}`, body); else await api.post("/admin/categories", body); await client.invalidateQueries({ queryKey: ["admin-categories"] }); await client.invalidateQueries({ queryKey: ["categories"] }); await client.invalidateQueries({ queryKey: ["products"] }); setOpen(false); } catch (e) { setError(errorMessage(e)); } };
    const remove = async (category) => { if (!confirm(`Hapus kategori ${category.name}?`)) return; try { await api.delete(`/admin/categories/${category.id}`); await client.invalidateQueries({ queryKey: ["admin-categories"] }); await client.invalidateQueries({ queryKey: ["categories"] }); await client.invalidateQueries({ queryKey: ["products"] }); } catch (e) { alert(errorMessage(e, "Kategori gagal dihapus.")); } };

    return (
        <div>
            <div className="flex items-center justify-between"><h1 className="text-2xl font-black text-gray-900">Kategori</h1><button onClick={() => start()} className="inline-flex items-center gap-2 rounded-xl bg-green-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm shadow-green-600/20 transition hover:bg-green-700"><Plus size={16} /> Tambah</button></div>
            {query.isLoading ? <Loading /> : (
                <div className="mt-5 overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
                    <table className="w-full min-w-[700px]">
                        <thead><tr className="border-b border-gray-100 text-left text-xs font-bold uppercase tracking-wider text-gray-400"><th className="p-4">Nama</th><th>Slug</th><th>Urutan</th><th>Produk</th><th>Status</th><th /></tr></thead>
                        <tbody>{query.data?.map((category) => <tr key={category.id} className="border-b border-gray-50 last:border-0"><td className="p-4 font-bold text-gray-900">{category.name}</td><td className="text-gray-500">{category.slug}</td><td className="text-gray-500">{category.sort_order ?? 0}</td><td className="text-gray-500">{category.products_count ?? 0}</td><td><span className={`inline-flex rounded-lg px-2 py-1 text-xs font-bold ${category.is_active ? "bg-green-50 text-green-700" : "bg-gray-100 text-gray-500"}`}>{category.is_active ? "Aktif" : "Nonaktif"}</span></td><td className="text-right"><button onClick={() => start(category)} className="p-2 text-gray-400 transition hover:text-green-600"><Pencil size={16} /></button><button onClick={() => void remove(category)} className="p-2 text-gray-400 transition hover:text-red-500"><Trash2 size={16} /></button></td></tr>)}</tbody>
                    </table>
                </div>
            )}
            {open ? (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <form onSubmit={save} className="w-full max-w-xl rounded-2xl border border-gray-100 bg-white p-6 shadow-2xl">
                        <div className="flex items-center justify-between"><h2 className="text-lg font-black text-gray-900">{editing ? "Edit" : "Tambah"} Kategori</h2><button type="button" onClick={() => setOpen(false)} className="grid h-8 w-8 place-items-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-600"><X size={18} /></button></div>
                        {error ? <p className="mt-3 rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-bold text-red-600">{error}</p> : null}
                        <div className="mt-5 grid gap-4">
                            <label className="grid gap-1.5 text-sm font-semibold text-gray-700">Nama Kategori<input required placeholder="Nama kategori" value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} className={fieldClass} /></label>
                            <label className="grid gap-1.5 text-sm font-semibold text-gray-700">Slug<input placeholder="Otomatis dari nama jika kosong" value={form.slug} onChange={(event) => setForm({ ...form, slug: event.target.value })} className={fieldClass} /></label>
                            <label className="grid gap-1.5 text-sm font-semibold text-gray-700">Deskripsi<textarea placeholder="Deskripsi singkat" value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} className={fieldClass} /></label>
                            <label className="grid gap-1.5 text-sm font-semibold text-gray-700">Urutan<input type="number" min="0" value={form.sort_order} onChange={(event) => setForm({ ...form, sort_order: event.target.value })} className={fieldClass} /></label>
                            <label className="grid gap-1.5 text-sm font-semibold text-gray-700">Gambar<input type="file" accept="image/png,image/jpeg,image/webp" onChange={(event) => setImage(event.target.files?.[0] ?? null)} className={fieldClass} /></label>
                            <label className="flex items-center gap-2 text-sm font-semibold text-gray-700"><input type="checkbox" checked={form.is_active} onChange={(event) => setForm({ ...form, is_active: event.target.checked })} className="rounded" /> Aktif</label>
                            <button className="rounded-xl bg-green-600 py-3 text-sm font-bold text-white shadow-sm shadow-green-600/20 transition hover:bg-green-700">Simpan Kategori</button>
                        </div>
                    </form>
                </div>
            ) : null}
        </div>
    );
}

export function AdminProductsPage() {
    const client = useQueryClient();
    const [search, setSearch] = useState("");
    const [page, setPage] = useState(1);
    const [editing, setEditing] = useState(null);
    const [formOpen, setFormOpen] = useState(false);
    const categories = useQuery({ queryKey: ["admin-categories"], queryFn: async () => collectionData(await api.get("/admin/categories")) });
    const products = useQuery({ queryKey: ["admin-products", search, page], queryFn: async () => paginatedData(await api.get("/admin/products", { params: { search, page, per_page: 20 } })) });

    const openCreate = () => { setEditing(null); setFormOpen(true); };
    const openEdit = (product) => { setEditing(product); setFormOpen(true); };
    const remove = async (product) => { if (!confirm(`Hapus produk ${product.name}?`)) return; try { await api.delete(`/admin/products/${product.id}`); await client.invalidateQueries({ queryKey: ["admin-products"] }); await client.invalidateQueries({ queryKey: ["products"] }); await client.invalidateQueries({ queryKey: ["featured"] }); await client.invalidateQueries({ queryKey: ["product"] }); } catch (e) { alert(errorMessage(e, "Produk gagal dihapus.")); } };

    return (
        <div>
            <div className="flex flex-wrap items-center justify-between gap-3"><h1 className="text-2xl font-black text-gray-900">Produk</h1><button onClick={openCreate} className="inline-flex items-center gap-2 rounded-xl bg-green-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm shadow-green-600/20 transition hover:bg-green-700"><Plus size={16} /> Tambah Produk</button></div>
            <div className="mt-5 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                <input value={search} onChange={(event) => { setSearch(event.target.value); setPage(1); }} placeholder="Cari nama, kategori, brand, atau SKU" className={`${fieldClass} w-full`} />
            </div>
            {products.isLoading ? <Loading /> : products.data?.data.length ? (
                <div className="mt-5 overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
                    <table className="w-full min-w-[900px]">
                        <thead><tr className="border-b border-gray-100 text-left text-xs font-bold uppercase tracking-wider text-gray-400"><th className="p-4">Produk</th><th>Kategori</th><th>SKU</th><th>Harga</th><th>Status</th><th /></tr></thead>
                        <tbody>{products.data.data.map((product) => <tr key={product.id} className="border-b border-gray-50 last:border-0"><td className="p-4"><div className="flex items-center gap-3"><div className="h-11 w-11 overflow-hidden rounded-lg bg-gray-50">{product.thumbnail ? <img src={product.thumbnail} alt={product.name} className="h-full w-full object-cover" /> : null}</div><div><b className="text-sm text-gray-900">{product.name}</b><small className="block text-xs text-gray-400">{product.slug}</small></div></div></td><td className="text-sm text-gray-600">{product.category?.name ?? "-"}</td><td><span className="text-sm font-semibold text-gray-700">{product.sku ?? "-"}</span><small className="block text-xs text-gray-400">{product.track_stock ? `Stok ${product.stock ?? 0}` : "Stok tidak dipantau"}</small></td><td className="text-sm font-bold text-green-600">{currency.format(product.price ?? 0)}</td><td><span className={`inline-flex rounded-lg px-2 py-1 text-xs font-bold ${product.status === 'published' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500'}`}>{product.status}</span><span className={`ml-1 inline-flex rounded-lg px-2 py-1 text-xs font-bold ${product.is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500'}`}>{product.is_active ? 'aktif' : 'nonaktif'}</span></td><td className="text-right"><button onClick={() => openEdit(product)} className="p-2 text-gray-400 transition hover:text-green-600"><Pencil size={16} /></button><button onClick={() => void remove(product)} className="p-2 text-gray-400 transition hover:text-red-500"><Trash2 size={16} /></button></td></tr>)}</tbody>
                    </table>
                </div>
            ) : <div className="mt-5"><Empty title="Produk tidak ditemukan" /></div>}
            <Pagination meta={products.data?.meta} onPage={setPage} />
            {formOpen ? <ProductForm product={editing} categories={categories.data ?? []} onClose={() => setFormOpen(false)} onSaved={async () => { await client.invalidateQueries({ queryKey: ["admin-products"] }); await client.invalidateQueries({ queryKey: ["products"] }); await client.invalidateQueries({ queryKey: ["featured"] }); await client.invalidateQueries({ queryKey: ["product"] }); }} /> : null}
        </div>
    );
}

const blankUser = () => ({ name: "", email: "", password: "", role: "seller", phone: "", department: "", is_active: true });

export function AdminUsersPage() {
    const client = useQueryClient();
    const [search, setSearch] = useState("");
    const [role, setRole] = useState("");
    const [page, setPage] = useState(1);
    const [editing, setEditing] = useState(null);
    const [open, setOpen] = useState(false);
    const [form, setForm] = useState(blankUser());
    const [error, setError] = useState("");
    const query = useQuery({ queryKey: ["admin-users", search, role, page], queryFn: async () => paginatedData(await api.get("/admin/users", { params: { search, role, page, per_page: 20 } })), retry: false });

    const start = (user) => { setEditing(user ?? null); setForm(user ? { name: user.name, email: user.email, password: "", role: user.role, phone: user.phone ?? "", department: user.department ?? "", is_active: user.is_active } : blankUser()); setError(""); setOpen(true); };
    const save = async (event) => { event.preventDefault(); setError(""); try { const payload = { ...form, password: form.password || null, phone: form.phone || null, department: form.department || null }; if (editing) await api.put(`/admin/users/${editing.id}`, payload); else await api.post("/admin/users", payload); await client.invalidateQueries({ queryKey: ["admin-users"] }); setOpen(false); } catch (e) { setError(errorMessage(e)); } };
    const remove = async (user) => { if (!confirm(`Hapus user ${user.name}?`)) return; try { await api.delete(`/admin/users/${user.id}`); await client.invalidateQueries({ queryKey: ["admin-users"] }); } catch (e) { alert(errorMessage(e, "User gagal dihapus.")); } };

    return (
        <div>
            <div className="flex flex-wrap items-center justify-between gap-3"><h1 className="text-2xl font-black text-gray-900">Manajemen User</h1><button onClick={() => start()} className="inline-flex items-center gap-2 rounded-xl bg-green-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm shadow-green-600/20 transition hover:bg-green-700"><Plus size={16} /> Tambah User</button></div>
            <div className="mt-5 grid gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm md:grid-cols-2">
                <input value={search} onChange={(event) => { setSearch(event.target.value); setPage(1); }} placeholder="Cari nama, email, HP, atau departemen" className={fieldClass} />
                <select value={role} onChange={(event) => { setRole(event.target.value); setPage(1); }} className={fieldClass}><option value="">Semua role</option><option value="admin">Admin</option><option value="seller">Seller</option></select>
            </div>
            {query.isLoading ? <Loading /> : query.isError ? (
                <div className="mt-5 rounded-xl border border-red-200 bg-red-50/50 p-5"><p className="font-bold text-red-700">Data user gagal dimuat</p><p className="mt-1 text-sm text-red-600">{errorMessage(query.error, "Data user belum dapat dimuat.")}</p><button type="button" onClick={() => void query.refetch()} className="mt-4 rounded-xl bg-red-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-red-700">Coba Lagi</button></div>
            ) : (
                <div className="mt-5 overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
                    <table className="w-full min-w-[800px]">
                        <thead><tr className="border-b border-gray-100 text-left text-xs font-bold uppercase tracking-wider text-gray-400"><th className="p-4">Nama</th><th>Email</th><th>Role</th><th>Departemen</th><th>Status</th><th /></tr></thead>
                        <tbody>{query.data?.data.map((user) => <tr key={user.id} className="border-b border-gray-50 last:border-0"><td className="p-4"><span className="font-bold text-gray-900">{user.name}</span><small className="block text-xs text-gray-400">{user.phone ?? "-"}</small></td><td className="text-sm text-gray-600">{user.email}</td><td><span className={`inline-flex rounded-lg px-2 py-1 text-xs font-bold ${user.role === 'admin' ? 'bg-violet-50 text-violet-700' : 'bg-blue-50 text-blue-700'}`}>{user.role}</span></td><td className="text-sm text-gray-500">{user.department ?? "-"}</td><td><span className={`inline-flex rounded-lg px-2 py-1 text-xs font-bold ${user.is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500'}`}>{user.is_active ? "Aktif" : "Nonaktif"}</span></td><td className="text-right"><button onClick={() => start(user)} className="p-2 text-gray-400 transition hover:text-green-600"><Pencil size={16} /></button><button onClick={() => void remove(user)} className="p-2 text-gray-400 transition hover:text-red-500"><Trash2 size={16} /></button></td></tr>)}</tbody>
                    </table>
                </div>
            )}
            <Pagination meta={query.data?.meta} onPage={setPage} />
            {open ? (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <form onSubmit={save} className="w-full max-w-xl rounded-2xl border border-gray-100 bg-white p-6 shadow-2xl">
                        <div className="flex items-center justify-between"><h2 className="text-lg font-black text-gray-900">{editing ? "Edit" : "Tambah"} User</h2><button type="button" onClick={() => setOpen(false)} className="grid h-8 w-8 place-items-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-600"><X size={18} /></button></div>
                        {error ? <p className="mt-3 rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-bold text-red-600">{error}</p> : null}
                        <div className="mt-5 grid gap-4">
                            <label className="grid gap-1.5 text-sm font-semibold text-gray-700">Nama<input required placeholder="Nama lengkap" value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} className={fieldClass} /></label>
                            <label className="grid gap-1.5 text-sm font-semibold text-gray-700">Email<input required type="email" placeholder="Email" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} className={fieldClass} /></label>
                            <label className="grid gap-1.5 text-sm font-semibold text-gray-700">Password<input required={!editing} type="password" minLength={8} placeholder={editing ? "Kosongkan jika tidak diubah" : "Minimal 8 karakter"} value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} className={fieldClass} /></label>
                            <label className="grid gap-1.5 text-sm font-semibold text-gray-700">Role<select value={form.role} onChange={(event) => setForm({ ...form, role: event.target.value })} className={fieldClass}><option value="admin">Admin</option><option value="seller">Seller</option></select></label>
                            <label className="grid gap-1.5 text-sm font-semibold text-gray-700">No. HP<input placeholder="No. HP" value={form.phone} onChange={(event) => setForm({ ...form, phone: event.target.value })} className={fieldClass} /></label>
                            <label className="grid gap-1.5 text-sm font-semibold text-gray-700">Department<input placeholder="Department" value={form.department} onChange={(event) => setForm({ ...form, department: event.target.value })} className={fieldClass} /></label>
                            <label className="flex items-center gap-2 text-sm font-semibold text-gray-700"><input type="checkbox" checked={form.is_active} onChange={(event) => setForm({ ...form, is_active: event.target.checked })} className="rounded" /> Aktif</label>
                            <button className="rounded-xl bg-green-600 py-3 text-sm font-bold text-white shadow-sm shadow-green-600/20 transition hover:bg-green-700">Simpan User</button>
                        </div>
                    </form>
                </div>
            ) : null}
        </div>
    );
}

export function AdminOrdersPage() {
    const client = useQueryClient();
    const [filters, setFilters] = useState({ search: "", status: "", date_from: "", date_to: "" });
    const [page, setPage] = useState(1);
    const [selected, setSelected] = useState(null);
    const [detailError, setDetailError] = useState("");
    const [createOpen, setCreateOpen] = useState(false);
    const query = useQuery({ queryKey: ["admin-orders", filters, page], queryFn: async () => paginatedData(await api.get("/admin/orders", { params: { ...filters, page, per_page: 20 } })) });

    const openDetail = async (order) => { setDetailError(""); try { setSelected(resourceData(await api.get(`/admin/orders/${order.id}`))); } catch (e) { alert(errorMessage(e, "Detail order gagal dimuat.")); } };
    const updateSelected = (patch) => setSelected((c) => c ? { ...c, ...patch } : c);
    const saveSelected = async () => { if (!selected) return; setDetailError(""); try { const result = resourceData(await api.put(`/admin/orders/${selected.id}`, { customer_type: selected.customer_type, guest_email: selected.guest_email, guest_name: selected.guest_name, guest_phone: selected.guest_phone, guest_address: selected.guest_address, guest_nik: selected.customer_type === "business" ? selected.guest_nik : null, guest_npwp: selected.customer_type === "business" ? selected.guest_npwp : null, guest_province: selected.customer_type === "business" ? selected.guest_province : null, guest_city: selected.customer_type === "business" ? selected.guest_city : null, guest_company_name: selected.customer_type === "business" ? selected.guest_company_name : null, guest_postal_code: selected.customer_type === "business" ? selected.guest_postal_code : null, guest_country: selected.customer_type === "business" ? selected.guest_country : null, guest_notes: selected.guest_notes, status: selected.status, payment_status: selected.payment_status, payment_method: selected.payment_method, cancel_reason: selected.cancel_reason, admin_notes: selected.admin_notes })); setSelected(result); await client.invalidateQueries({ queryKey: ["admin-orders"] }); await client.invalidateQueries({ queryKey: ["admin-dashboard"] }); } catch (e) { setDetailError(errorMessage(e)); } };
    const remove = async (order) => { if (!confirm(`Hapus order ${order.order_number}?`)) return; try { await api.delete(`/admin/orders/${order.id}`); await client.invalidateQueries({ queryKey: ["admin-orders"] }); await client.invalidateQueries({ queryKey: ["admin-dashboard"] }); if (selected?.id === order.id) setSelected(null); } catch (e) { alert(errorMessage(e, "Order gagal dihapus.")); } };

    return (
        <div>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-black text-gray-900">Order</h1>
                <div className="flex gap-2">
                    <button type="button" onClick={() => void query.refetch()} className="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50"><RefreshCw size={16} /> Refresh</button>
                    <button type="button" onClick={() => setCreateOpen(true)} className="inline-flex items-center gap-2 rounded-xl bg-green-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm shadow-green-600/20 transition hover:bg-green-700"><Plus size={16} /> Tambah Order</button>
                </div>
            </div>
            <div className="mt-5 grid gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm md:grid-cols-4">
                <input value={filters.search} onChange={(event) => { setFilters((c) => ({ ...c, search: event.target.value })); setPage(1); }} placeholder="Nomor order, email, nama" className={fieldClass} />
                <select value={filters.status} onChange={(event) => { setFilters((c) => ({ ...c, status: event.target.value })); setPage(1); }} className={fieldClass}><option value="">Semua status</option>{['pending', 'confirmed', 'processing', 'completed', 'cancelled'].map((s) => <option key={s} value={s}>{s}</option>)}</select>
                <input type="date" value={filters.date_from} onChange={(event) => { setFilters((c) => ({ ...c, date_from: event.target.value })); setPage(1); }} className={fieldClass} />
                <input type="date" value={filters.date_to} onChange={(event) => { setFilters((c) => ({ ...c, date_to: event.target.value })); setPage(1); }} className={fieldClass} />
            </div>
            {query.isLoading ? <Loading /> : query.data?.data.length ? (
                <div className="mt-5 overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
                    <table className="w-full min-w-[960px]">
                        <thead><tr className="border-b border-gray-100 text-left text-xs font-bold uppercase tracking-wider text-gray-400"><th className="p-4">Order</th><th>Buyer</th><th>Total</th><th>Status</th><th>Pembayaran</th><th>Tanggal</th><th /></tr></thead>
                        <tbody>{query.data.data.map((order) => <tr key={order.id} className="border-b border-gray-50 last:border-0"><td className="p-4 font-bold text-gray-900">{order.order_number}</td><td><span className="font-semibold text-gray-900">{order.guest_name}</span><small className="block text-xs text-gray-400">{order.guest_email}</small>{order.guest_company_name ? <small className="block text-xs text-gray-400">{order.guest_company_name}</small> : null}</td><td className="font-bold text-green-600">{currency.format(order.total_amount)}</td><td><StatusBadge status={order.status} /></td><td className="text-sm text-gray-500">{order.payment_status} · {order.payment_method}</td><td className="text-sm text-gray-500">{dateTime(order.created_at)}</td><td className="text-right"><button type="button" onClick={() => void openDetail(order)} className="p-2 text-gray-400 transition hover:text-green-600"><Eye size={16} /></button><button type="button" onClick={() => void remove(order)} className="p-2 text-gray-400 transition hover:text-red-500"><Trash2 size={16} /></button></td></tr>)}</tbody>
                    </table>
                </div>
            ) : <div className="mt-5"><Empty title="Order tidak ditemukan" /></div>}
            <Pagination meta={query.data?.meta} onPage={setPage} />
            {createOpen ? <OrderCreateForm onClose={() => setCreateOpen(false)} onSaved={async () => { await client.invalidateQueries({ queryKey: ["admin-orders"] }); await client.invalidateQueries({ queryKey: ["admin-dashboard"] }); }} /> : null}
            {selected ? (
                <div className="fixed inset-0 z-50 overflow-y-auto bg-black/50 p-4">
                    <div className="mx-auto my-4 w-full max-w-4xl rounded-2xl border border-gray-100 bg-white p-6 shadow-2xl">
                        <div className="flex items-start justify-between gap-3">
                            <div><p className="text-sm text-gray-400">{selected.order_number}</p><h2 className="mt-1 text-xl font-black text-gray-900">Detail Order</h2></div>
                            <button type="button" onClick={() => setSelected(null)} className="grid h-8 w-8 place-items-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-600"><X size={18} /></button>
                        </div>
                        {detailError ? <p className="mt-3 rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-bold text-red-600">{detailError}</p> : null}
                        <div className="mt-5 grid gap-5 lg:grid-cols-2">
                            <section className="grid gap-3">
                                <select value={selected.customer_type ?? "individual"} onChange={(event) => updateSelected({ customer_type: event.target.value })} className={fieldClass}><option value="individual">Perorangan</option><option value="business">Badan Usaha</option></select>
                                {selected.customer_type === "business" ? <><input required placeholder="Perusahaan" value={selected.guest_company_name ?? ""} onChange={(event) => updateSelected({ guest_company_name: event.target.value })} className={fieldClass} /><input required inputMode="numeric" maxLength={16} pattern="[0-9]{16}" placeholder="NIK" value={selected.guest_nik ?? ""} onChange={(event) => updateSelected({ guest_nik: event.target.value })} className={fieldClass} /><input required placeholder="NPWP" value={selected.guest_npwp ?? ""} onChange={(event) => updateSelected({ guest_npwp: event.target.value })} className={fieldClass} /><input required placeholder="Negara" value={selected.guest_country ?? ""} onChange={(event) => updateSelected({ guest_country: event.target.value })} className={fieldClass} /><input required placeholder="Provinsi" value={selected.guest_province ?? ""} onChange={(event) => updateSelected({ guest_province: event.target.value })} className={fieldClass} /><input required placeholder="Kota" value={selected.guest_city ?? ""} onChange={(event) => updateSelected({ guest_city: event.target.value })} className={fieldClass} /><input required placeholder="Kode Pos" value={selected.guest_postal_code ?? ""} onChange={(event) => updateSelected({ guest_postal_code: event.target.value })} className={fieldClass} /></> : null}
                                <input type="email" value={selected.guest_email} onChange={(event) => updateSelected({ guest_email: event.target.value })} className={fieldClass} />
                                <input value={selected.guest_name} onChange={(event) => updateSelected({ guest_name: event.target.value })} className={fieldClass} />
                                <input value={selected.guest_phone} onChange={(event) => updateSelected({ guest_phone: event.target.value })} className={fieldClass} />
                                <textarea rows={3} value={selected.guest_address} onChange={(event) => updateSelected({ guest_address: event.target.value })} className={fieldClass} />
                                <textarea rows={2} value={selected.guest_notes ?? ""} onChange={(event) => updateSelected({ guest_notes: event.target.value })} placeholder="Catatan buyer" className={fieldClass} />
                                <textarea rows={2} value={selected.admin_notes ?? ""} onChange={(event) => updateSelected({ admin_notes: event.target.value })} placeholder="Catatan internal admin" className={fieldClass} />
                                <select value={selected.status} onChange={(event) => updateSelected({ status: event.target.value })} className={fieldClass}>{['pending', 'confirmed', 'processing', 'completed', 'cancelled'].map((s) => <option key={s} value={s}>{s}</option>)}</select>
                                <select value={selected.payment_status} onChange={(event) => updateSelected({ payment_status: event.target.value })} className={fieldClass}><option value="unpaid">Unpaid</option><option value="paid">Paid</option></select>
                                <select value={selected.payment_method} onChange={(event) => updateSelected({ payment_method: event.target.value })} className={fieldClass}><option value="internal_billing">Internal Billing</option><option value="bank_transfer">Transfer Manual</option><option value="cod">COD</option></select>
                                {selected.status === "cancelled" ? <textarea rows={2} value={selected.cancel_reason ?? ""} onChange={(event) => updateSelected({ cancel_reason: event.target.value })} placeholder="Alasan pembatalan" className={fieldClass} /> : null}
                                <button type="button" onClick={() => void saveSelected()} className="rounded-xl bg-green-600 py-3 text-sm font-bold text-white shadow-sm shadow-green-600/20 transition hover:bg-green-700">Simpan Perubahan</button>
                            </section>
                            <section>
                                <h3 className="text-sm font-bold text-gray-900">Item Order</h3>
                                <div className="mt-3 space-y-2">{(selected.items ?? []).map((item) => <div key={item.id} className="rounded-lg border border-gray-100 p-3"><div className="flex justify-between gap-3"><span className="text-sm font-bold">{item.product_name} x {item.quantity}</span><span className="text-sm font-bold">{currency.format(item.subtotal)}</span></div>{item.product_sku ? <p className="mt-1 text-xs text-gray-400">{item.product_sku}</p> : null}</div>)}</div>
                                <div className="mt-4 flex justify-between border-t border-gray-100 pt-3"><span className="font-bold">Total</span><span className="text-lg font-black text-green-600">{currency.format(selected.total_amount)}</span></div>
                                <h3 className="mt-5 text-sm font-bold text-gray-900">Riwayat Status</h3>
                                <div className="mt-3 space-y-2">{selected.status_histories?.map((history) => <div key={history.id} className="rounded-lg border-l-2 border-green-500 bg-green-50/30 pl-3 py-2 text-sm"><b className="text-gray-900">{history.from_status ?? "baru"} → {history.to_status}</b><p className="text-xs text-gray-500">{history.notes ?? "-"} · {history.changed_by?.name ?? "Guest/System"} · {dateTime(history.created_at)}</p></div>)}</div>
                            </section>
                        </div>
                    </div>
                </div>
            ) : null}
        </div>
    );
}
